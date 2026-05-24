<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Entity\PaymentEvent;
use PHPUnit\Framework\TestCase;

class PaymentEventTest extends TestCase
{
    public function testIdIsNullOnNewEntity(): void
    {
        $event = new PaymentEvent(10, 'payment.created', ['id' => 'mp-1']);

        $this->assertNull($event->getId());
    }

    public function testExposesPayloadAndMetadata(): void
    {
        $event = new PaymentEvent(10, 'payment.created', ['id' => 'mp-1']);

        $this->assertSame(10, $event->getPaymentId());
        $this->assertSame('payment.created', $event->getEventType());
        $this->assertSame(['id' => 'mp-1'], $event->getPayload());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getCreatedAt());
    }
}
