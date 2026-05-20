<?php

declare(strict_types=1);

namespace App\Domain\Service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class BillingEventPublisher
{
    public function __construct(private ?AMQPStreamConnection $connection) {}

    public function publishPaymentApproved(array $payload): void
    {
        $this->publish('billing.payment.approved', $payload);
    }

    public function publishQuotationCreated(array $payload): void
    {
        $this->publish('billing.quotation.created', $payload);
    }

    public function publishQuotationFailed(array $payload): void
    {
        $this->publish('billing.quotation.failed', $payload);
    }

    public function publishPaymentFailed(array $payload): void
    {
        $this->publish('billing.payment.failed', $payload);
    }

    public function publishRefundRequested(array $payload): void
    {
        $this->publish('billing.payment.refund.requested', $payload);
    }

    public function publishRefundFailed(array $payload): void
    {
        $this->publish('billing.payment.refund.failed', $payload);
    }

    private function publish(string $routingKey, array $payload): void
    {
        if (! $this->connection) {
            // No RabbitMQ available in local dev: silently ignore publishing.
            return;
        }

        $channel = $this->connection->channel();
        $exchange = 'billing.events';

        $channel->exchange_declare($exchange, 'direct', false, true, false);

        $message = new AMQPMessage(json_encode($payload), [
            'content_type' => 'application/json',
        ]);

        $channel->basic_publish($message, $exchange, $routingKey);
        $channel->close();
    }
}
