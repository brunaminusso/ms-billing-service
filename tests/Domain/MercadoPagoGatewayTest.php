<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Service\MercadoPagoPaymentClientInterface;
use App\Domain\Service\MercadoPagoPreferenceClientInterface;
use App\Domain\Service\MercadoPagoGateway;
use MercadoPago\Resources\Preference;
use MercadoPago\Resources\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MercadoPagoGatewayTest extends TestCase
{
    private MercadoPagoPaymentClientInterface|MockObject $paymentClient;
    private MercadoPagoPreferenceClientInterface|MockObject $preferenceClient;

    protected function setUp(): void
    {
        $this->paymentClient = $this->createMock(MercadoPagoPaymentClientInterface::class);
        $this->preferenceClient = $this->createMock(MercadoPagoPreferenceClientInterface::class);
    }

    public function testCreatePreferenceReturnsArray(): void
    {
        $preference = new Preference();
        $preference->id = 'pref-123';
        $preference->init_point = 'https://checkout.mercadopago.com/init';

        $this->preferenceClient
            ->expects($this->once())
            ->method('createPreference')
            ->with(['amount' => 10])
            ->willReturn($preference);

        $gateway = new MercadoPagoGateway($this->paymentClient, $this->preferenceClient);
        $result = $gateway->createPreference(['amount' => 10]);

        $this->assertSame([
            'id' => 'pref-123',
            'init_point' => 'https://checkout.mercadopago.com/init',
        ], $result);
    }

    public function testGetPaymentReturnsArray(): void
    {
        $payment = new Payment();
        $payment->id = 321;
        $payment->status = 'approved';

        $this->paymentClient
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($payment);

        $gateway = new MercadoPagoGateway($this->paymentClient, $this->preferenceClient);
        $result = $gateway->getPayment('123');

        $this->assertSame([
            'id' => 321,
            'status' => 'approved',
        ], $result);
    }
}
