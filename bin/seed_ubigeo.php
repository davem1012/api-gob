<?php

/**
 * Carga el catálogo de ubigeo (código SUNAT -> distrito/provincia/departamento).
 * Es un dato prácticamente estático (~1834 filas), por eso se ejecuta una sola vez
 * (o cada vez que se quiera refrescar), no como parte del sync diario del padrón.
 *
 * Fuente: concordancia INEI/RENIEC/SUNAT de CONCYTEC
 * https://github.com/CONCYTEC/ubigeo-peru
 *
 * Uso: php bin/seed_ubigeo.php
 */

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as DB;

require __DIR__ . '/bootstrap.php';

$csvPath = __DIR__ . '/../resources/ubigeo_sunat.csv';

if (!is_readable($csvPath)) {
    fwrite(STDERR, "No se encontró {$csvPath}\n");
    exit(1);
}

$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle);

$rows = [];
$total = 0;

while (($data = fgetcsv($handle)) !== false) {
    $row = array_combine($header, $data);

    $rows[] = [
        'codigo' => $row['codigo'],
        'distrito' => $row['distrito'],
        'provincia' => $row['provincia'],
        'departamento' => $row['departamento'],
    ];
    $total++;

    if (count($rows) >= 500) {
        upsertBatch($rows);
        $rows = [];
    }
}

if (!empty($rows)) {
    upsertBatch($rows);
}

fclose($handle);

echo "Ubigeo cargado: {$total} filas.\n";

function upsertBatch(array $rows): void
{
    DB::table('ubigeo')->upsert(
        $rows,
        ['codigo'],
        ['distrito', 'provincia', 'departamento']
    );
}
