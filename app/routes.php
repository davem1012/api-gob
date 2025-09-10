<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Application\Api\ReniecController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Application\Api\SunatController;
use App\Application\Api\TipoCambioController;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    $app->get('/api/ruc/{ruc}', [SunatController::class, 'lookup']);
    $app->get('/api/dni/{dni}', [ReniecController::class, 'lookup']);

    // Ruta para obtener tipo de cambio por fecha
    $app->get('/tipo-cambio/{date}', [TipoCambioController::class, 'getTipoCambio']);

    // Opcional: ruta sin fecha que use la fecha actual
    $app->get('/tipo-cambio', function ($request, $response, $args) {
        $args['date'] = date('Y-m-d');
        $controller = new TipoCambioController();
        return $controller->getTipoCambio($request, $response, $args);
    });
};
