<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Domain\Service\PaymentGatewayInterface;
use App\Domain\Service\MercadoPagoGateway;
use App\Domain\Service\MercadoPagoPaymentClientInterface;
use App\Domain\Service\MercadoPagoPreferenceClientInterface;
use App\Domain\Service\BillingEventPublisher;
use App\Infrastructure\Observability\MetricsRegistryInterface;
use App\Infrastructure\Observability\PrometheusMetricsRegistry;
use App\Infrastructure\Service\MercadoPagoPaymentClientAdapter;
use App\Presentation\Controller\BillingController;
use App\Presentation\Controller\HealthController;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        EntityManager::class => function () {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths: [__DIR__ . '/../src/Domain/Entity'],
                isDevMode: true
            );
            $config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy());

            $connection = DriverManager::getConnection([
                'driver' => getenv('DB_DRIVER') ?: 'pdo_mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'dbname' => getenv('DB_NAME') ?: 'billing_service',
                'user' => getenv('DB_USER') ?: 'billing_user',
                'password' => getenv('DB_PASSWORD') ?: 'billing_pass',
            ], $config);

            return new EntityManager($connection, $config);
        },
        EntityManagerInterface::class => \DI\get(EntityManager::class),
        AMQPStreamConnection::class => function () {
            $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
            $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
            $user = getenv('RABBITMQ_USER') ?: 'guest';
            $password = getenv('RABBITMQ_PASSWORD') ?: 'guest';
            $vhost = getenv('RABBITMQ_VHOST') ?: '/';

            try {
                return new AMQPStreamConnection($host, $port, $user, $password, $vhost);
            } catch (\Throwable $e) {
                // RabbitMQ not available locally — return null. The publisher accepts
                // a nullable connection and will skip publishes when null.
                return null;
            }
        },
        MercadoPagoPaymentClientInterface::class => \DI\autowire(MercadoPagoPaymentClientAdapter::class),
        MercadoPagoPreferenceClientInterface::class => \DI\autowire(MercadoPagoPaymentClientAdapter::class),
        PaymentGatewayInterface::class => \DI\autowire(MercadoPagoGateway::class),
        BillingEventPublisher::class => \DI\autowire(),
        BillingController::class => \DI\autowire(),
        HealthController::class => \DI\autowire(),
        MetricsRegistryInterface::class => function () {
            return new PrometheusMetricsRegistry();
        },
    ]);
};
