<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            return new Settings([
                'displayErrorDetails' => true,
                'logError'            => false,
                'logErrorDetails'     => false,
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../../logs/app.log',
                    'level' => Logger::DEBUG,
                ],
                'db' => [
                    'driver'    => 'mysql',
                    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
                    'port'      => $_ENV['DB_PORT'] ?? '3306',
                    'database'  => $_ENV['DB_DATABASE'] ?? 'fenbot',
                    'username'  => $_ENV['DB_USERNAME'] ?? 'roosssssst',
                    'password'  => $_ENV['DB_PASSWORD'] ?? 'root',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => '',
                ],
            ]);
        }
    ]);
};
