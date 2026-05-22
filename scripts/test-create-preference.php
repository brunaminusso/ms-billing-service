<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

$accessToken = getenv('MP_ACCESS_TOKEN');
if (! $accessToken) {
    echo "MP_ACCESS_TOKEN missing\n";
    exit(1);
}

MercadoPagoConfig::$BASE_URL = getenv('MP_BASE_URI') ?: MercadoPagoConfig::$BASE_URL;
MercadoPagoConfig::setAccessToken($accessToken);
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
];

try {
    $preference = $client->create($payload);
    echo "SUCCESS\n";
    echo json_encode(json_decode(json_encode($preference)), JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . get_class($e) . "\n";
    if (method_exists($e, 'getApiResponse')) {
        $resp = $e->getApiResponse();
        echo "Status: " . $resp->getStatusCode() . "\n";
        echo json_encode($resp->getContent(), JSON_PRETTY_PRINT) . "\n";
    } else {
        echo $e->getMessage() . "\n";
    }
}
