<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Lima');

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$capsule = new Capsule();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'      => $_ENV['DB_PORT'] ?? '3306',
    'database'  => $_ENV['DB_DATABASE'] ?? 'fenbot',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? 'root',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

return $capsule;
