<?php

require __DIR__ . '/../vendor/autoload.php';
$env = __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env);
$dotenv->safeLoad();

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/../src/Domain/Entity'],
    isDevMode: true
);
// Match the naming strategy used by the application so column names map correctly
$config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy());
$conn = [
    'driver' => getenv('DB_DRIVER') ?: 'pdo_mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: 3306,
    'dbname' => getenv('DB_NAME') ?: 'billing_service',
    'user' => getenv('DB_USER') ?: 'billing_user',
    'password' => getenv('DB_PASSWORD') ?: 'billing_pass',
];

$em = EntityManager::create($conn, $config);

$paymentId = $argv[1] ?? null;
$providerId = $argv[2] ?? null;
if (! $paymentId || ! $providerId) {
    echo "Usage: php set-payment-provider-id.php <payment_id> <provider_payment_id>\n";
    exit(1);
}

$payment = $em->find(\App\Domain\Entity\Payment::class, (int)$paymentId);
if (! $payment) {
    echo "Payment not found\n";
    exit(1);
}

$payment->setProviderPaymentId($providerId);
$em->persist($payment);
$em->flush();

echo "Updated payment {$paymentId} -> provider id {$providerId}\n";
