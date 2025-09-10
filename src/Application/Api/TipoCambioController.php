<?php

namespace App\Application\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\ApiToken;
use App\Models\TipoCambioCache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TipoCambioController
{

    public function getTipoCambio(Request $request, Response $response, array $args): Response
    {
        $date = $args['date'];

        // 1. Validar token del header
        $authHeader = $request->getHeaderLine('Authorization');
        $token = trim(str_replace('Bearer', '', $authHeader));

        if (empty($token) || $token !== $_ENV['AUTH_TOKEN']) {
            return $this->json($response, [
                'success' => false,
                'message' => 'No se encuentra autenticado'
            ], 401);
        }

        // 2. Validar formato de fecha (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Formato de fecha no válido, debe ser YYYY-MM-DD'
            ], 400);
        }

        // 3. Revisar cache
        $record = TipoCambioCache::where('date', $date)->first();


        if ($record) {
            // Construir respuesta desde cache
            return $this->json($response, $this->buildSuccessResponse($record->toArray()));
        }

        // 4. Obtener token disponible
        $apiTokenRecord = ApiToken::getAvailableToken();

        if (!$apiTokenRecord) {
            return $this->json($response, [
                'success' => false,
                'message' => 'No hay tokens disponibles este mes'
            ], 503);
        }

        // 5. Consultar API externa de tipo de cambio
        $remoteResponse = $this->remoteQuery($date, $apiTokenRecord->token);
        
        // 7. Incrementar contador del token usado
        $apiTokenRecord->incrementCounter();

        // 6. Verificar si hay error en la respuesta del API
        if (isset($remoteResponse['error'])) {
            return $this->json($response, [
                'success' => false,
                'message' => $remoteResponse['message'] ?? $remoteResponse['error'] ?? 'Error desconocido'
            ], isset($remoteResponse['status_code']) ? $remoteResponse['status_code'] : 400);
        }

        // 8. Guardar/actualizar cache
        if ($record) {
            // Actualizar registro existente
            $record->update([
                'buy_price' => $remoteResponse['buy_price'],
                'sell_price' => $remoteResponse['sell_price'],
                'base_currency' => $remoteResponse['base_currency'],
                'quote_currency' => $remoteResponse['quote_currency'],
                'date' => $remoteResponse['date'],
                'fecha_registro' => date("Y-m-d H:i:s")
            ]);
        } else {
            // Crear nuevo registro
            TipoCambioCache::create([
                'buy_price' => $remoteResponse['buy_price'],
                'sell_price' => $remoteResponse['sell_price'],
                'base_currency' => $remoteResponse['base_currency'],
                'quote_currency' => $remoteResponse['quote_currency'],
                'date' => $remoteResponse['date'],
                'fecha_registro' => date("Y-m-d H:i:s")
            ]);
        }

        // 9. Devolver respuesta formateada
        return $this->json($response, $this->buildSuccessResponse($remoteResponse));
    }

    private function buildSuccessResponse(array $data): array
    {
        return [
            'buy_price' => $data['buy_price'],
            'sell_price' => $data['sell_price'],
            'base_currency' => $data['base_currency'],
            'quote_currency' => $data['quote_currency'],
            'date' => $data['date']
        ];
    }

    private function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function remoteQuery(string $date, string $apiToken): array
    {
        $url = rtrim($_ENV['EXTERNAL_API_URL'], '?') . '/v1/tipo-cambio/sunat?date=' . urlencode($date);

        $client = new Client([
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        try {
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            // Si el API devuelve 400, significa fecha no válida o error
            if ($statusCode === 400) {
                return [
                    'error' => 'Fecha no válida o sin datos',
                    'message' => $data['error'] ?? 'Invalid request',
                    'status_code' => 400
                ];
            }

            // Verificar que la respuesta tenga los datos esperados
            if (!isset($data['buy_price']) || !isset($data['sell_price'])) {
                return [
                    'error' => 'Respuesta inválida del API externo de tipo de cambio',
                    'status_code' => 500
                ];
            }

            return $data;
        } catch (RequestException $e) {
            // Verificar si es un error 400 específicamente
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 400) {
                $body = (string) $e->getResponse()->getBody();
                $errorData = json_decode($body, true);

                return [
                    'error' => 'Fecha no válida o sin datos',
                    'message' => $errorData['error'] ?? 'Invalid request',
                    'status_code' => 400
                ];
            }

            return [
                'error' => 'Error al consultar la API externa de tipo de cambio: ' . $e->getMessage(),
                'status_code' => 500
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'Error inesperado en consulta de tipo de cambio: ' . $e->getMessage(),
                'status_code' => 500
            ];
        }
    }
}
