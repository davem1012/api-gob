<?php

namespace App\Application\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\ApiToken;
use App\Models\DniCache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ReniecController
{

    public function lookup(Request $request, Response $response, array $args): Response
    {
        $dni = $args['dni'];

        // 1. Validar token del header
        $authHeader = $request->getHeaderLine('Authorization');
        $token = trim(str_replace('Bearer', '', $authHeader));

        if (empty($token) || $token !== $_ENV['AUTH_TOKEN']) {
            return $this->json($response, [
                'success' => false,
                'message' => 'No se encuentra autenticado'
            ], 401);
        }

        // 2. Revisar cache
        $record = DniCache::where('document_number', $dni)->first();
        $ttl = intval($_ENV['CACHE_TTL_DAYS'] ?? 7) * 86400;

        if ($record && $record->fecha_registro && (time() - strtotime($record->fecha_registro)) < $ttl) {
            // Construir respuesta desde cache
            return $this->json($response, $this->buildSuccessResponse($record->toArray()));
        }

        // 3. Obtener token disponible
        $apiTokenRecord = ApiToken::getAvailableToken();

        if (!$apiTokenRecord) {
            return $this->json($response, [
                'success' => false,
                'message' => 'No hay tokens disponibles este mes'
            ], 503);
        }

        // 4. Consultar API intermedia
        $remoteResponse = $this->remoteQuery($dni, $apiTokenRecord->token);

        // 5. Verificar si hay error en la respuesta del API
        if (isset($remoteResponse['error'])) {
            return $this->json($response, [
                'success' => false,
                'message' => $remoteResponse['message'] ?? $remoteResponse['error'] ?? 'Error desconocido'
            ]);
        }

        // 6. Incrementar contador del token usado
        $apiTokenRecord->incrementCounter();

        // 7. Guardar/actualizar cache
        if ($record) {
            // Actualizar registro existente
            $record->update([
                'first_name' => $remoteResponse['first_name'],
                'first_last_name' => $remoteResponse['first_last_name'],
                'second_last_name' => $remoteResponse['second_last_name'],
                'full_name' => $remoteResponse['full_name'],
                'document_number' => $remoteResponse['document_number'],
                'fecha_registro' => date("Y-m-d H:i:s")
            ]);
        } else {
            // Crear nuevo registro
            DniCache::create([
                'first_name' => $remoteResponse['first_name'],
                'first_last_name' => $remoteResponse['first_last_name'],
                'second_last_name' => $remoteResponse['second_last_name'],
                'full_name' => $remoteResponse['full_name'],
                'document_number' => $remoteResponse['document_number'],
                'fecha_registro' => date("Y-m-d H:i:s")
            ]);
        }

        // 8. Devolver respuesta formateada
        return $this->json($response, $this->buildSuccessResponse($remoteResponse));
    }

    private function buildSuccessResponse(array $data): array
    {

        $ubigeoArray = [null, null, null];

        return [
            'success' => true,
            'data' => [
                "numero" => $data['document_number'],
                "nombre_completo" => $data['full_name'],
                "nombres" => $data['first_name'],
                "apellido_paterno" => $data['first_last_name'],
                "apellido_materno" => $data['second_last_name'],
                "direccion" => '',
                "direccion_completa" => '',
                "departamento" => '',
                "provincia" => '',
                "distrito" => '',
                "codigo_verificacion" => '',
                "ubigeo_inei" => '',
                "location_id" => $ubigeoArray,
                "ubigeo" => $ubigeoArray
            ]
        ];
    }

    private function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function remoteQuery(string $dni, string $apiToken): array
    {

        $url = rtrim($_ENV['EXTERNAL_API_URL'], '?') . '/v1/reniec/dni?numero=' . urlencode($dni);

        $client = new Client([
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ]
        ]);

        try {
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            // Si el API devuelve 422, significa DNI no válido
            if ($statusCode === 422) {
                return [
                    'error' => 'DNI no valido',
                    'message' => $data['message'] ?? 'dni no valido'
                ];
            }

            // Verificar que la respuesta tenga los datos esperados
            if (!isset($data['document_number']) || empty($data['document_number'])) {
                return [
                    'error' => 'Respuesta inválida del API externo'
                ];
            }

            return $data;
        } catch (RequestException $e) {
            // Verificar si es un error 422 específicamente
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 422) {
                return [
                    'error' => 'DNI no valido',
                    'message' => 'dni no valido'
                ];
            }

            return [
                'error' => 'Error al consultar la API externa: ' . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'Error inesperado: ' . $e->getMessage()
            ];
        }
    }
}
