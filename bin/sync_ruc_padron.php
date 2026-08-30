<?php

/**
 * Sincroniza ruc_cache con el Padrón Reducido de RUC de SUNAT.
 *
 * Flujo:
 *  1. Descarga y descomprime el zip.
 *  2. Carga el txt completo (streaming) a `ruc_padron_staging` (TRUNCATE + INSERT por lotes).
 *     Esto no toca `ruc_cache`, así que no afecta las lecturas en vivo del API.
 *  3. Recorre `ruc_padron_staging` en una sola pasada por keyset pagination y, por cada lote:
 *     a) hace upsert en `ruc_cache` SOLO de las filas cuyo row_hash cambió respecto a lo que
 *        ya está guardado (filas nuevas o modificadas). Las columnas es_agente_retencion,
 *        es_buen_contribuyente y locales_anexos nunca se tocan aquí: esas se completan aparte
 *        (ver SunatController y, a futuro, los padrones de Buenos Contribuyentes / Agentes de
 *        Retención).
 *     b) para las filas de persona natural (RUC con prefijo "10"), precalienta `dni_cache` con
 *        el DNI (embebido en el propio RUC) y el nombre (la razón social), marcado con
 *        source='sunat_ruc' (para distinguirlo de un dato verificado por RENIEC vía el API), y
 *        sin sobrescribir registros que ya existan ahí.
 *
 * Uso: php bin/sync_ruc_padron.php
 */

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;
use GuzzleHttp\Client;

require __DIR__ . '/bootstrap.php';

const PADRON_URL = 'http://www2.sunat.gob.pe/padron_reducido_ruc.zip';
const STAGING_CHUNK = 2000;
const DIFF_CHUNK = 2000;

function log_line(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . "] {$msg}\n";
}

function to_utf8(string $str): string
{
    return mb_check_encoding($str, 'UTF-8')
        ? $str
        : mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
}

function normalize(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    return ($value === '' || $value === '-') ? null : $value;
}

function build_direccion(array $f): ?string
{
    $parts = [];

    if ($f['via_tipo'] || $f['via_nombre']) {
        $parts[] = trim(($f['via_tipo'] ?? '') . ' ' . ($f['via_nombre'] ?? ''));
    }
    if ($f['numero']) {
        $parts[] = 'NRO. ' . $f['numero'];
    }
    if ($f['interior']) {
        $parts[] = 'INT. ' . $f['interior'];
    }
    if ($f['dpto']) {
        $parts[] = 'DPTO. ' . $f['dpto'];
    }
    if ($f['manzana']) {
        $parts[] = 'MZ. ' . $f['manzana'];
    }
    if ($f['lote']) {
        $parts[] = 'LOTE ' . $f['lote'];
    }
    if ($f['kilometro']) {
        $parts[] = 'KM. ' . $f['kilometro'];
    }

    return empty($parts) ? null : implode(' ', $parts);
}

/**
 * Heurística para separar "APELLIDO_PATERNO APELLIDO_MATERNO NOMBRES..." tal como
 * viene la razón social de persona natural en el padrón (sin separador explícito
 * entre apellidos y nombres). No es exacta: apellidos compuestos (p. ej. "DE LA
 * CRUZ") se parten mal. Trade-off aceptable para precalentar cache, no para
 * datos que se muestren como verificados.
 */
function split_persona_natural_name(string $fullName): array
{
    $tokens = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    $count = count($tokens);

    if ($count >= 3) {
        return [$tokens[0], $tokens[1], implode(' ', array_slice($tokens, 2))];
    }
    if ($count === 2) {
        return [$tokens[0], '', $tokens[1]];
    }
    return ['', '', $tokens[0] ?? ''];
}

function row_hash(array $f): string
{
    $sentinel = "\x01"; // separador que no aparece en los datos
    $ordered = [
        $f['razon_social'], $f['estado'], $f['condicion'], $f['ubigeo'],
        $f['via_tipo'], $f['via_nombre'], $f['zona_codigo'], $f['zona_tipo'],
        $f['numero'], $f['interior'], $f['lote'], $f['dpto'],
        $f['manzana'], $f['kilometro'],
    ];
    return md5(implode($sentinel, array_map(fn ($v) => $v ?? '', $ordered)));
}

// --- 1. Descargar y descomprimir ---------------------------------------

$tmpDir = sys_get_temp_dir() . '/ruc_padron_' . date('Ymd_His');
mkdir($tmpDir, 0755, true);
$zipPath = $tmpDir . '/padron.zip';

$lockHandle = fopen(__DIR__ . '/../var/sync_ruc_padron.lock', 'c');
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    log_line('Ya hay una sincronización en curso, se aborta esta corrida.');
    exit(0);
}

try {
    log_line('Descargando padrón desde ' . PADRON_URL);
    $client = new Client(['timeout' => 300]);
    $client->get(PADRON_URL, ['sink' => $zipPath]);

    log_line('Descomprimiendo...');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('No se pudo abrir el zip descargado.');
    }
    $zip->extractTo($tmpDir);

    $txtName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_ends_with(strtolower($name), '.txt')) {
            $txtName = $name;
            break;
        }
    }
    $zip->close();

    if (!$txtName) {
        throw new RuntimeException('El zip no contiene un .txt.');
    }
    $txtPath = $tmpDir . '/' . $txtName;

    // --- 2. Cargar ubigeo en memoria (catálogo estático, ~1834 filas) ---

    $ubigeoMap = DB::table('ubigeo')->get()->keyBy('codigo');

    // --- 3. Bulk load a staging -----------------------------------------

    log_line('Cargando padrón a ruc_padron_staging...');
    DB::table('ruc_padron_staging')->truncate();

    $handle = fopen($txtPath, 'r');
    $header = fgets($handle); // descarta encabezado

    $buffer = [];
    $totalLoaded = 0;

    while (($line = fgets($handle)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            continue;
        }

        $cols = explode('|', to_utf8($line));

        $f = [
            'numero_documento' => normalize($cols[0] ?? null),
            'razon_social'     => normalize($cols[1] ?? null),
            'estado'           => normalize($cols[2] ?? null),
            'condicion'        => normalize($cols[3] ?? null),
            'ubigeo'           => normalize($cols[4] ?? null),
            'via_tipo'         => normalize($cols[5] ?? null),
            'via_nombre'       => normalize($cols[6] ?? null),
            'zona_codigo'      => normalize($cols[7] ?? null),
            'zona_tipo'        => normalize($cols[8] ?? null),
            'numero'           => normalize($cols[9] ?? null),
            'interior'         => normalize($cols[10] ?? null),
            'lote'             => normalize($cols[11] ?? null),
            'dpto'             => normalize($cols[12] ?? null),
            'manzana'          => normalize($cols[13] ?? null),
            'kilometro'        => normalize($cols[14] ?? null),
        ];

        if (!$f['numero_documento']) {
            continue;
        }

        $geo = $f['ubigeo'] ? $ubigeoMap->get($f['ubigeo']) : null;

        $buffer[] = $f + [
            'direccion'    => build_direccion($f),
            'distrito'     => $geo->distrito ?? null,
            'provincia'    => $geo->provincia ?? null,
            'departamento' => $geo->departamento ?? null,
            'row_hash'     => row_hash($f),
        ];

        if (count($buffer) >= STAGING_CHUNK) {
            DB::table('ruc_padron_staging')->insert($buffer);
            $totalLoaded += count($buffer);
            $buffer = [];
        }
    }

    if (!empty($buffer)) {
        DB::table('ruc_padron_staging')->insert($buffer);
        $totalLoaded += count($buffer);
    }
    fclose($handle);

    log_line("Staging cargado: {$totalLoaded} filas.");

    // --- 4. Una sola pasada: diff hacia ruc_cache + derivar DNI hacia dni_cache ---

    log_line('Sincronizando cambios hacia ruc_cache y derivando DNI hacia dni_cache...');

    $lastKey = '';
    $inserted = 0;
    $updated = 0;
    $dniInserted = 0;

    $updateColumns = [
        'razon_social', 'estado', 'condicion', 'direccion', 'ubigeo',
        'via_tipo', 'via_nombre', 'zona_codigo', 'zona_tipo', 'numero',
        'interior', 'lote', 'dpto', 'manzana', 'kilometro',
        'distrito', 'provincia', 'departamento', 'row_hash', 'fecha_registro',
    ];

    while (true) {
        $batch = DB::table('ruc_padron_staging')
            ->where('numero_documento', '>', $lastKey)
            ->orderBy('numero_documento')
            ->limit(DIFF_CHUNK)
            ->get();

        if ($batch->isEmpty()) {
            break;
        }

        $keys = $batch->pluck('numero_documento')->all();

        $existingHashes = DB::table('ruc_cache')
            ->whereIn('numero_documento', $keys)
            ->pluck('row_hash', 'numero_documento');

        $toUpsert = [];
        $dniToInsert = [];

        foreach ($batch as $row) {
            $current = $existingHashes[$row->numero_documento] ?? null;
            if ($current !== $row->row_hash) {
                if ($current === null) {
                    $inserted++;
                } else {
                    $updated++;
                }

                $toUpsert[] = [
                    'numero_documento' => $row->numero_documento,
                    'razon_social' => $row->razon_social,
                    'estado' => $row->estado,
                    'condicion' => $row->condicion,
                    'direccion' => $row->direccion,
                    'ubigeo' => $row->ubigeo,
                    'via_tipo' => $row->via_tipo,
                    'via_nombre' => $row->via_nombre,
                    'zona_codigo' => $row->zona_codigo,
                    'zona_tipo' => $row->zona_tipo,
                    'numero' => $row->numero,
                    'interior' => $row->interior,
                    'lote' => $row->lote,
                    'dpto' => $row->dpto,
                    'manzana' => $row->manzana,
                    'kilometro' => $row->kilometro,
                    'distrito' => $row->distrito,
                    'provincia' => $row->provincia,
                    'departamento' => $row->departamento,
                    'row_hash' => $row->row_hash,
                    'fecha_registro' => date('Y-m-d H:i:s'),
                ];
            }

            // RUC de persona natural: 10 + DNI (8 dígitos) + dígito verificador.
            // La razón social en el padrón ES el nombre completo de la persona.
            // Precalentamos dni_cache marcado como 'sunat_ruc' (no verificado por
            // RENIEC); ReniecController lo sana a 'reniec' en cuanto alguien lo
            // consulta en vivo. Nunca se sobrescribe un dni_cache ya existente.
            if (str_starts_with($row->numero_documento, '10')) {
                $razonSocial = trim((string) $row->razon_social);
                if ($razonSocial !== '') {
                    $dni = substr($row->numero_documento, 2, 8);
                    [$apellidoPaterno, $apellidoMaterno, $nombres] = split_persona_natural_name($razonSocial);

                    $dniToInsert[] = [
                        'document_number' => $dni,
                        'first_name' => $nombres,
                        'first_last_name' => $apellidoPaterno,
                        'second_last_name' => $apellidoMaterno,
                        'full_name' => $razonSocial,
                        'source' => 'sunat_ruc',
                        'fecha_registro' => date('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        if (!empty($toUpsert)) {
            DB::table('ruc_cache')->upsert($toUpsert, ['numero_documento'], $updateColumns);
        }
        if (!empty($dniToInsert)) {
            $dniInserted += DB::table('dni_cache')->insertOrIgnore($dniToInsert);
        }

        $lastKey = end($keys);
    }

    log_line("Listo. ruc_cache -> nuevos: {$inserted}, actualizados: {$updated}, sin cambios: " . ($totalLoaded - $inserted - $updated) . '.');
    log_line("dni_cache -> {$dniInserted} nuevos derivados del padrón (los ya existentes no se tocan).");
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    // limpieza de temporales
    array_map('unlink', glob("$tmpDir/*"));
    @rmdir($tmpDir);
}
