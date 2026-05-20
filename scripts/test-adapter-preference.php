<?php
require __DIR__ . '/../vendor/autoload.php';
$env = __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env);
$dotenv->safeLoad();

// Ensure MP_ACCESS_TOKEN is set in environment for this run
if (!getenv('MP_ACCESS_TOKEN')) {
    putenv('MP_ACCESS_TOKEN=' . trim(shell_exec("grep '^MP_ACCESS_TOKEN=' $env/.env | cut -d'=' -f2-")));
}

use App\Infrastructure\Service\MercadoPagoPaymentClientAdapter;

$adapter = new MercadoPagoPaymentClientAdapter();
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
    $pref = $adapter->createPreference($payload);
    echo json_encode(json_decode(json_encode($pref)), JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Exception: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    if (method_exists($e, 'getApiResponse')) {
        $resp = $e->getApiResponse();
        echo "Status: " . $resp->getStatusCode() . "\n";
        echo json_encode($resp->getContent(), JSON_PRETTY_PRINT) . "\n";
    }
}
