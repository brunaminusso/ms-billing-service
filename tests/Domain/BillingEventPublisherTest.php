<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Service\BillingEventPublisher;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BillingEventPublisherTest extends TestCase
{
    public function testPublishesPaymentApprovedEvent(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $connection = $this->createMock(AMQPStreamConnection::class);

        $connection->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $channel->expects($this->once())
            ->method('exchange_declare')
            ->with('billing.events', 'direct', false, true, false);

        $channel->expects($this->once())
            ->method('basic_publish')
            ->with($this->isInstanceOf(\PhpAmqpLib\Message\AMQPMessage::class), 'billing.events', 'billing.payment.approved');

        $channel->expects($this->once())
            ->method('close');

        $publisher = new BillingEventPublisher($connection);
        $publisher->publishPaymentApproved(['id' => 10]);
    }

    public function testPublishesPaymentFailedEvent(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $connection = $this->createMock(AMQPStreamConnection::class);

        $connection->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $channel->expects($this->once())
            ->method('exchange_declare')
            ->with('billing.events', 'direct', false, true, false);

        $channel->expects($this->once())
            ->method('basic_publish')
            ->with($this->isInstanceOf(\PhpAmqpLib\Message\AMQPMessage::class), 'billing.events', 'billing.payment.failed');

        $channel->expects($this->once())
            ->method('close');

        $publisher = new BillingEventPublisher($connection);
        $publisher->publishPaymentFailed(['id' => 10]);
    }
}
