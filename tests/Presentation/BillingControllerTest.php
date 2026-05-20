<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Domain\Entity\Payment;
use App\Domain\Entity\Quotation;
use App\Domain\Repository\PaymentEventRepositoryInterface;
use App\Domain\Repository\PaymentRepositoryInterface;
use App\Domain\Repository\QuotationRepositoryInterface;
use App\Domain\Service\BillingEventPublisher;
use App\Domain\Service\PaymentGatewayInterface;
use App\Infrastructure\Exception\NotFoundException;
use App\Infrastructure\Exception\ValidationException;
use App\Infrastructure\Observability\MetricsRegistryInterface;
use App\Presentation\Controller\BillingController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class BillingControllerTest extends TestCase
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

    private function controller(): BillingController
    {
        return new BillingController(
            $this->quotationRepository,
            $this->paymentRepository,
            $this->eventRepository,
            $this->gateway,
            $this->publisher,
            $this->metrics
        );
    }

    public function testCreateQuotationRequiresFields(): void
    {
        $this->expectException(ValidationException::class);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/quotations')
            ->withParsedBody([]);

        $this->controller()->createQuotation($request, new Response());
    }

    public function testGetQuotationNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->quotationRepository
            ->method('findById')
            ->willReturn(null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/quotations/10');

        $this->controller()->getQuotation($request, new Response(), ['id' => 10]);
    }

    public function testCreatePaymentRequiresFields(): void
    {
        $this->expectException(ValidationException::class);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments')
            ->withParsedBody([]);

        $this->controller()->createPayment($request, new Response());
    }

    public function testCreatePaymentWhenQuotationMissing(): void
    {
        $this->expectException(NotFoundException::class);

        $this->quotationRepository
            ->method('findById')
            ->willReturn(null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments')
            ->withParsedBody([
                'quotation_id' => 10,
                'amount_in_cents' => 1000,
            ]);

        $this->controller()->createPayment($request, new Response());
    }

    public function testGetPaymentNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->paymentRepository
            ->method('findById')
            ->willReturn(null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/payments/10');

        $this->controller()->getPayment($request, new Response(), ['id' => 10]);
    }

    public function testWebhookRequiresProviderPaymentId(): void
    {
        $this->expectException(ValidationException::class);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody(['type' => 'payment']);

        $this->controller()->mercadopagoWebhook($request, new Response());
    }

    public function testWebhookPaymentNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->paymentRepository
            ->method('findByProviderPaymentId')
            ->with('mp-404')
            ->willReturn(null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody(['type' => 'payment', 'data' => ['id' => 'mp-404']]);

        $this->controller()->mercadopagoWebhook($request, new Response());
    }

    public function testWebhookDoesNotPublishWhenNotApproved(): void
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
            ->willReturn(['status' => 'pending', 'external_reference' => '99']);

        $this->publisher->expects($this->never())->method('publishPaymentApproved');
        $this->publisher->expects($this->never())->method('publishPaymentFailed');
        $this->paymentRepository->expects($this->once())->method('save');
        $this->eventRepository->expects($this->once())->method('save');

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody(['type' => 'payment', 'data' => ['id' => 'mp-123']]);

        $response = $this->controller()->mercadopagoWebhook($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWebhookFallsBackToQuotationLookup(): void
    {
        $payment = new Payment(10, 1500, 'mercadopago');

        $this->paymentRepository
            ->method('findByProviderPaymentId')
            ->with('mp-123')
            ->willReturn(null);

        $this->gateway
            ->method('getPayment')
            ->with('mp-123')
            ->willReturn(['status' => 'approved', 'external_reference' => '10']);

        $this->paymentRepository
            ->method('findByQuotationId')
            ->with(10)
            ->willReturn($payment);

        $this->publisher->expects($this->once())->method('publishPaymentApproved');
        $this->paymentRepository->expects($this->once())->method('save');
        $this->eventRepository->expects($this->once())->method('save');

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody(['type' => 'payment', 'data' => ['id' => 'mp-123']]);

        $response = $this->controller()->mercadopagoWebhook($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateQuotationSuccess(): void
    {
        $this->quotationRepository->expects($this->once())->method('save');

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/quotations')
            ->withParsedBody([
                'service_order_id' => 10,
                'total_in_cents' => 2000,
                'currency' => 'BRL',
            ]);

        $response = $this->controller()->createQuotation($request, new Response());

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreatePaymentSuccess(): void
    {
        $quotation = new Quotation(10, 2000, 'BRL');

        $this->quotationRepository
            ->method('findById')
            ->with(10)
            ->willReturn($quotation);

        $this->gateway
            ->method('createPreference')
            ->willReturn(['id' => 'pref-123', 'init_point' => 'https://checkout.mercadopago.com/init']);

        $this->paymentRepository->expects($this->once())->method('save');
        $this->eventRepository->expects($this->once())->method('save');

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments')
            ->withParsedBody([
                'quotation_id' => 10,
                'amount_in_cents' => 2000,
                'currency' => 'BRL',
                'payment_method_id' => 'pix',
            ]);

        $response = $this->controller()->createPayment($request, new Response());

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testGetPaymentSuccess(): void
    {
        $payment = new Payment(10, 2000, 'mercadopago');

        $this->paymentRepository
            ->method('findById')
            ->with(10)
            ->willReturn($payment);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/payments/10');

        $response = $this->controller()->getPayment($request, new Response(), ['id' => 10]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetQuotationSuccess(): void
    {
        $quotation = new Quotation(10, 2000, 'BRL');

        $this->quotationRepository
            ->method('findById')
            ->with(10)
            ->willReturn($quotation);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/quotations/10');

        $response = $this->controller()->getQuotation($request, new Response(), ['id' => 10]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testWebhookPublishesPaymentFailedWhenRejected(): void
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
            ->willReturn(['status' => 'rejected', 'external_reference' => '99']);

        $this->publisher->expects($this->once())->method('publishPaymentFailed');
        $this->publisher->expects($this->never())->method('publishPaymentApproved');
        $this->paymentRepository->expects($this->once())->method('save');
        $this->eventRepository->expects($this->once())->method('save');

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/payments/webhook/mercadopago')
            ->withParsedBody(['type' => 'payment', 'data' => ['id' => 'mp-123']]);

        $response = $this->controller()->mercadopagoWebhook($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
    }
}
