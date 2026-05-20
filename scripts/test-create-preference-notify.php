<?php
require __DIR__ . '/../vendor/autoload.php';
$env = __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env);
$dotenv->safeLoad();
putenv('MP_ACCESS_TOKEN=' . trim(shell_exec("grep '^MP_ACCESS_TOKEN=' $env/.env | cut -d'=' -f2-")));

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

MercadoPagoConfig::$BASE_URL = getenv('MP_BASE_URI') ?: MercadoPagoConfig::$BASE_URL;
MercadoPagoConfig::setAccessToken(getenv('MP_ACCESS_TOKEN'));
MercadoPagoConfig::setRuntimeEnviroment(getenv('MP_RUNTIME_ENVIRONMENT') ?: MercadoPagoConfig::SERVER);

$client = new PreferenceClient();

$payload = [
    'items' => [[
        'id' => '1',
        'title' => 'Service order 1',
        'description' => 'Quotation 1',
        'currency_id' => 'BRL',
        'quantity' => 1,
        'unit_price' => 215.00,
    ]],
    'payer' => [
        'email' => 'customer@example.com',
    ],
    'external_reference' => '1',
    'notification_url' => getenv('MP_NOTIFICATION_URL') ?: null,
];

try {
    $preference = $client->create($payload);
    echo "SUCCESS\n";
    echo json_encode(json_decode(json_encode($preference)), JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    if (method_exists($e, 'getApiResponse')) {
        $resp = $e->getApiResponse();
        echo "Status: " . $resp->getStatusCode() . "\n";
        echo json_encode($resp->getContent(), JSON_PRETTY_PRINT) . "\n";
    }
}
