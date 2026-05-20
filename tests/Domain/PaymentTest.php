<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Entity\Payment;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    public function testUpdatesProviderPaymentIdAndStatus(): void
    {
        $payment = new Payment(10, 1500, 'mercadopago');

        $payment->setProviderPaymentId('mp-123');
        $payment->setStatus('approved');

        $this->assertSame('mp-123', $payment->getProviderPaymentId());
        $this->assertSame('approved', $payment->getStatus());
    }

    public function testDefaultsAreSet(): void
    {
        $payment = new Payment(10, 2500, 'mercadopago', 'BRL');

        $this->assertSame(10, $payment->getQuotationId());
        $this->assertSame(2500, $payment->getAmountInCents());
        $this->assertSame('mercadopago', $payment->getProvider());
        $this->assertSame('BRL', $payment->getCurrency());
        $this->assertSame('pending', $payment->getStatus());
        $this->assertNull($payment->getProviderPaymentId());
    }
}
