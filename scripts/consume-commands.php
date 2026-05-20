<?php

declare(strict_types=1);

use App\Domain\Entity\PaymentEvent;
use App\Domain\Entity\Quotation;
use App\Domain\Repository\PaymentEventRepositoryInterface;
use App\Domain\Repository\PaymentRepositoryInterface;
use App\Domain\Repository\QuotationRepositoryInterface;
use App\Domain\Service\BillingEventPublisher;
use DI\ContainerBuilder;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$containerBuilder = new ContainerBuilder();
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);
$container = $containerBuilder->build();

$host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
$port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
$user = getenv('RABBITMQ_USER') ?: 'guest';
$password = getenv('RABBITMQ_PASSWORD') ?: 'guest';
$vhost = getenv('RABBITMQ_VHOST') ?: '/';

$connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
$channel = $connection->channel();

$commandExchange = 'billing.commands';
$eventExchange = 'billing.events';

$channel->exchange_declare($commandExchange, 'direct', false, true, false);
$channel->exchange_declare($eventExchange, 'direct', false, true, false);

$queue = 'billing-service-commands';
$channel->queue_declare($queue, false, true, false, false);
$channel->queue_bind($queue, $commandExchange, 'billing.quotation.create');
$channel->queue_bind($queue, $commandExchange, 'billing.payment.refund.requested');

$quotationRepository = $container->get(QuotationRepositoryInterface::class);
$paymentRepository = $container->get(PaymentRepositoryInterface::class);
$paymentEventRepository = $container->get(PaymentEventRepositoryInterface::class);
$eventPublisher = $container->get(BillingEventPublisher::class);

$callback = function ($msg) use ($quotationRepository, $paymentRepository, $paymentEventRepository, $eventPublisher) {
    $payload = json_decode((string)$msg->body, true);
    $routingKey = $msg->getRoutingKey();

    if (!is_array($payload)) {
        $msg->ack();
        return;
    }

    if ($routingKey === 'billing.quotation.create') {
        try {
            $serviceOrderId = (int)($payload['service_order_id'] ?? 0);
            $totalInCents = (int)($payload['total_in_cents'] ?? 0);
            $currency = (string)($payload['currency'] ?? 'BRL');

            $quotation = new Quotation($serviceOrderId, $totalInCents, $currency);
            $quotationRepository->save($quotation);

            $eventPublisher->publishQuotationCreated([
                'quotation_id' => $quotation->getId(),
                'service_order_id' => $serviceOrderId,
                'total_in_cents' => $totalInCents,
                'currency' => $currency,
            ]);
        } catch (Throwable $exception) {
            $eventPublisher->publishQuotationFailed([
                'service_order_id' => $payload['service_order_id'] ?? null,
                'reason' => $exception->getMessage(),
            ]);
        }

        $msg->ack();
        return;
    }

    if ($routingKey === 'billing.payment.refund.requested') {
        $providerPaymentId = (string)($payload['provider_payment_id'] ?? '');

        if ($providerPaymentId === '') {
            $eventPublisher->publishRefundFailed([
                'service_order_id' => $payload['service_order_id'] ?? null,
                'reason' => 'provider_payment_id ausente',
            ]);
            $msg->ack();
            return;
        }

        $payment = $paymentRepository->findByProviderPaymentId($providerPaymentId);
        if (!$payment) {
            $eventPublisher->publishRefundFailed([
                'service_order_id' => $payload['service_order_id'] ?? null,
                'provider_payment_id' => $providerPaymentId,
                'reason' => 'Pagamento nao encontrado',
            ]);
            $msg->ack();
            return;
        }

        $payment->setStatus('refund_requested');
        $paymentRepository->save($payment);

        $event = new PaymentEvent($payment->getId() ?? 0, 'payment.refund.requested', $payload);
        $paymentEventRepository->save($event);

        $eventPublisher->publishRefundRequested([
            'service_order_id' => $payload['service_order_id'] ?? null,
            'payment_id' => $payment->getId(),
            'provider_payment_id' => $providerPaymentId,
            'status' => $payment->getStatus(),
        ]);

        $msg->ack();
        return;
    }

    $msg->ack();
};

$channel->basic_consume($queue, '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
