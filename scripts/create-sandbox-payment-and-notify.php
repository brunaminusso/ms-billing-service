<?php

require __DIR__ . '/../vendor/autoload.php';
$env = __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env);
$dotenv->safeLoad();
putenv('MP_ACCESS_TOKEN=' . trim(shell_exec("grep '^MP_ACCESS_TOKEN=' $env/.env | cut -d'=' -f2-")));

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

MercadoPagoConfig::$BASE_URL = getenv('MP_BASE_URI') ?: MercadoPagoConfig::$BASE_URL;
MercadoPagoConfig::setAccessToken(getenv('MP_ACCESS_TOKEN'));
MercadoPagoConfig::setRuntimeEnviroment(getenv('MP_RUNTIME_ENVIRONMENT') ?: MercadoPagoConfig::SERVER);

$client = new PaymentClient();
$amount = 215.00;
$payerEmail = 'customer@example.com';
$externalRef = '1';
$paymentMethods = ['account_money', 'pix', 'visa', 'master', 'amex'];

$created = null;
foreach ($paymentMethods as $method) {
    $payload = [
        'transaction_amount' => $amount,
        'description' => 'Sandbox test payment',
        'payment_method_id' => $method,
        'payer' => ['email' => $payerEmail],
        'external_reference' => $externalRef,
    ];

    try {
        $resp = $client->create($payload);
        $created = $resp;
        echo "Created with method: $method\n";
        break;
    } catch (Exception $e) {
        echo "Tried $method: " . $e->getMessage() . PHP_EOL;
        if (method_exists($e, 'getApiResponse')) {
            $r = $e->getApiResponse();
            echo "Status: " . $r->getStatusCode() . " Response: " . json_encode($r->getContent()) . PHP_EOL;
        }
    }
}

if (! $created) {
    echo "Failed to create payment with available methods\n";
    exit(1);
}

$paymentId = $created->id ?? null;
if (! $paymentId) {
    echo "No payment id returned\n";
    exit(1);
}

echo "Payment created id: $paymentId\n";

$notifyUrl = getenv('MP_NOTIFICATION_URL') ?: null;
if (! $notifyUrl) {
    echo "No MP_NOTIFICATION_URL set\n";
    exit(0);
}

$payload = [
    'type' => 'payment',
    'data' => ['id' => (string)$paymentId]
];

$ch = curl_init($notifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false) {
    echo "Notify failed: " . curl_error($ch) . PHP_EOL;
} else {
    echo "Notify status: $code\n";
    echo $res . PHP_EOL;
}
curl_close($ch);
