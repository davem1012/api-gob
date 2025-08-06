<?php

namespace App\Application\Api;

use App\Domain\SunatCache;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\RucCache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;

class SunatController
{

    public function lookup(Request $request, Response $response, array $args): Response
    {
        $ruc = $args['ruc'];

        // 1. Validar token del header
        $authHeader = $request->getHeaderLine('Authorization');
        $token = trim(str_replace('Bearer', '', $authHeader));

        /* if (empty($token) || $token !== $_ENV['AUTH_TOKEN']) {
            return $this->json($response, [
                'success' => false,
                'message' => 'No se encuentra autenticado'
            ], 401);
        } */

        // 2. Revisar cache
        $record = RucCache::where('numero_documento', $ruc)->first();
        $ttl = intval($_ENV['CACHE_TTL_SECONDS'] ?? 86400); // 1 día por defecto

        if ($record && $record->fecha_registro && (time() - strtotime($record->fecha_registro)) < $ttl) {
            // Construir respuesta desde cache
            return $this->json($response, $this->buildSuccessResponse($record->toArray()));
        }

        // 3. Consultar API intermedia
        $remoteResponse = $this->remoteQuery($ruc);

        // 4. Verificar si hay error en la respuesta del API
        if (isset($remoteResponse['error']) || isset($remoteResponse['message'])) {
            return $this->json($response, [
                'success' => false,
                'message' => 'RUC no valido'
            ]);
        }

        // 5. Guardar/actualizar cache
        if ($record) {
            // Actualizar registro existente
            $record->update([
                'razon_social' => $remoteResponse['razon_social'],
                'estado' => $remoteResponse['estado'],
                'condicion' => $remoteResponse['condicion'],
                'direccion' => $remoteResponse['direccion'],
                'ubigeo' => $remoteResponse['ubigeo'],
                'via_tipo' => $remoteResponse['via_tipo'] ?? null,
                'via_nombre' => $remoteResponse['via_nombre'] ?? null,
                'zona_codigo' => $remoteResponse['zona_codigo'] ?? null,
                'zona_tipo' => $remoteResponse['zona_tipo'] ?? null,
                'numero' => $remoteResponse['numero'] ?? null,
                'interior' => $remoteResponse['interior'] ?? null,
                'lote' => $remoteResponse['lote'] ?? null,
                'dpto' => $remoteResponse['dpto'] ?? null,
                'manzana' => $remoteResponse['manzana'] ?? null,
                'kilometro' => $remoteResponse['kilometro'] ?? null,
                'distrito' => $remoteResponse['distrito'],
                'provincia' => $remoteResponse['provincia'],
                'departamento' => $remoteResponse['departamento'],
                'es_agente_retencion' => $remoteResponse['es_agente_retencion'] ?? false,
                'es_buen_contribuyente' => $remoteResponse['es_buen_contribuyente'] ?? false,
                'locales_anexos' => json_encode($remoteResponse['locales_anexos'] ?? []),
                'fecha_registro' => date("Y-m-d H:i:s")
            ]);
        } else {
            // Crear nuevo registro
            RucCache::create([
                'numero_documento' => $remoteResponse['numero_documento'],
                'razon_social' => $remoteResponse['razon_social'],
                'estado' => $remoteResponse['estado'],
                'condicion' => $remoteResponse['condicion'],
                'direccion' => $remoteResponse['direccion'],
                'ubigeo' => $remoteResponse['ubigeo'],
                'via_tipo' => $remoteResponse['via_tipo'] ?? null,
                'via_nombre' => $remoteResponse['via_nombre'] ?? null,
                'zona_codigo' => $remoteResponse['zona_codigo'] ?? null,
                'zona_tipo' => $remoteResponse['zona_tipo'] ?? null,
                'numero' => $remoteResponse['numero'] ?? null,
                'interior' => $remoteResponse['interior'] ?? null,
                'lote' => $remoteResponse['lote'] ?? null,
                'dpto' => $remoteResponse['dpto'] ?? null,
                'manzana' => $remoteResponse['manzana'] ?? null,
                'kilometro' => $remoteResponse['kilometro'] ?? null,
                'distrito' => $remoteResponse['distrito'],
                'provincia' => $remoteResponse['provincia'],
                'departamento' => $remoteResponse['departamento'],
                'es_agente_retencion' => $remoteResponse['es_agente_retencion'] ?? false,
                'es_buen_contribuyente' => $remoteResponse['es_buen_contribuyente'] ?? false,
                'locales_anexos' => json_encode($remoteResponse['locales_anexos'] ?? []),
                'fecha_registro' => date("Y-m-d H:i:s")
            ]);
        }

        // 6. Devolver respuesta formateada
        return $this->json($response, $this->buildSuccessResponse($remoteResponse));
    }

    private function buildSuccessResponse(array $data): array
    {
        // Construir ubigeo array
        $ubigeo = $data['ubigeo'] ?? '';
        $ubigeoArray = [];

        if (strlen($ubigeo) === 6) {
            $ubigeoArray = [
                substr($ubigeo, 0, 2), // Departamento
                substr($ubigeo, 0, 4), // Provincia  
                $ubigeo               // Distrito
            ];
        }

        return [
            'success' => true,
            'agente_retencion' => ($data['es_agente_retencion'] ?? false) ? 'SI' : 'NO',
            'data' => [
                'ruc' => $data['numero_documento'] ?? '',
                'nombre_o_razon_social' => $data['razon_social'] ?? '',
                'direccion' => $data['direccion'] ?? '',
                'estado' => $data['estado'] ?? '',
                'condicion' => $data['condicion'] ?? '',
                'departamento' => strtoupper($data['departamento'] ?? ''),
                'provincia' => strtoupper($data['provincia'] ?? ''),
                'distrito' => strtoupper($data['distrito'] ?? ''),
                'ubigeo' => $ubigeoArray,
                'location_id' => $ubigeoArray
            ]
        ];
    }

    private function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function remoteQuery(string $ruc): array
    {
        $url = rtrim($_ENV['EXTERNAL_API_URL'], '?') . '/v1/sunat/ruc/full?numero=' . urlencode($ruc);
        $apiKey = $_ENV['EXTERNAL_API_KEY'];

        $client = new Client([
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ]
        ]);

        try {
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            // Si el API devuelve 422, significa RUC no válido
            if ($statusCode === 422) {
                return [
                    'error' => 'RUC no valido',
                    'message' => $data['message'] ?? 'ruc no valido'
                ];
            }

            // Verificar que la respuesta tenga los datos esperados
            if (!isset($data['numero_documento']) || empty($data['numero_documento'])) {
                return [
                    'error' => 'Respuesta inválida del API externo'
                ];
            }

            return $data;
        } catch (RequestException $e) {
            // Verificar si es un error 422 específicamente
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 422) {
                return [
                    'error' => 'RUC no valido',
                    'message' => 'ruc no valido'
                ];
            }

            return [
                'error' => 'Error al consultar la API intermedia: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'Error inesperado: ' . $e->getMessage()
            ];
        }
    }
}
