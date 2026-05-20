<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Domain\Entity\Payment;
use App\Domain\Repository\PaymentEventRepositoryInterface;
use App\Domain\Repository\PaymentRepositoryInterface;
use App\Domain\Repository\QuotationRepositoryInterface;
use App\Domain\Service\BillingEventPublisher;
use App\Domain\Service\PaymentGatewayInterface;
use App\Infrastructure\Observability\MetricsRegistryInterface;
use App\Presentation\Controller\BillingController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class BillingControllerBddTest extends TestCase
{
    private QuotationRepositoryInterface|MockObject $quotationRepository;
    private PaymentRepositoryInterface|MockObject $paymentRepository;
    private PaymentEventRepositoryInterface|MockObject $eventRepository;
    private PaymentGatewayInterface|MockObject $gateway;
    private BillingEventPublisher|MockObject $publisher;
    private MetricsRegistryInterface|MockObject $metrics;

    protected function setUp(): void
    {
        $this->quotationRepository = $this->createMock(QuotationRepositoryInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->eventRepository = $this->createMock(PaymentEventRepositoryInterface::class);
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->publisher = $this->createMock(BillingEventPublisher::class);
        $this->metrics = $this->createMock(MetricsRegistryInterface::class);
    }

    public function testGivenApprovedPaymentWhenWebhookReceivedThenPublishEvent(): void
    {
        $payment = new Payment(10, 1500, 'mercadopago');
        $payment->setProviderPaymentId('mp-123');

        $this->paymentRepository
            ->method('findByProviderPaymentId')
            ->with('mp-123')
            ->willReturn($payment);

        $this->gateway
            ->method('getPayment')
            ->with('mp-123')
            ->willReturn(['status' => 'approved', 'external_reference' => '99']);

        $this->paymentRepository->expects($this->once())->method('save');
        $this->eventRepository->expects($this->once())->method('save');
        $this->publisher->expects($this->once())->method('publishPaymentApproved');

        $controller = new BillingController(
            $this->quotationRepository,
            $this->paymentRepository,
            $this->eventRepository,
            $this->gateway,
            $this->publisher,
            $this->metrics
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody([
                'type' => 'payment',
                'data' => ['id' => 'mp-123'],
            ]);

        $response = $controller->mercadopagoWebhook($request, new \Slim\Psr7\Response());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGivenWebhookWithUnknownPaymentThenThrowsNotFound(): void
    {
        $this->paymentRepository
            ->method('findByProviderPaymentId')
            ->with('mp-404')
            ->willReturn(null);

        $controller = new BillingController(
            $this->quotationRepository,
            $this->paymentRepository,
            $this->eventRepository,
            $this->gateway,
            $this->publisher,
            $this->metrics
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody([
                'type' => 'payment',
                'data' => ['id' => 'mp-404'],
            ]);

        $this->expectException(\App\Infrastructure\Exception\NotFoundException::class);
        $controller->mercadopagoWebhook($request, new \Slim\Psr7\Response());
    }
}
