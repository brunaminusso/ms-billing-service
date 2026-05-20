<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;

require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$config = new PhpFile(__DIR__ . '/migrations.php');

$connection = DriverManager::getConnection([
    'driver' => getenv('DB_DRIVER') ?: 'pdo_mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'dbname' => getenv('DB_NAME') ?: 'billing_service',
    'user' => getenv('DB_USER') ?: 'billing_user',
    'password' => getenv('DB_PASSWORD') ?: 'billing_pass',
]);

$dependencyFactory = DependencyFactory::fromConnection($config, new ExistingConnection($connection));

return $dependencyFactory;
