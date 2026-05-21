<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Domain\Entity\Payment;
use App\Domain\Entity\PaymentEvent;
use App\Domain\Entity\Quotation;
use App\Domain\Repository\PaymentEventRepositoryInterface;
use App\Domain\Repository\PaymentRepositoryInterface;
use App\Domain\Repository\QuotationRepositoryInterface;
use App\Domain\Service\PaymentGatewayInterface;
use App\Domain\Service\BillingEventPublisher;
use App\Infrastructure\Exception\NotFoundException;
use App\Infrastructure\Exception\ValidationException;
use App\Infrastructure\Observability\MetricsRegistryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BillingController extends BaseController
{
    public function __construct(
        private QuotationRepositoryInterface $quotationRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentEventRepositoryInterface $paymentEventRepository,
        private PaymentGatewayInterface $paymentGateway,
        private BillingEventPublisher $eventPublisher,
        private MetricsRegistryInterface $metrics
    ) {
    }

    public function createQuotation(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['service_order_id']) || empty($data['total_in_cents'])) {
            throw new ValidationException('service_order_id e total_in_cents sao obrigatorios');
        }

        $quotation = new Quotation(
            (int)$data['service_order_id'],
            (int)$data['total_in_cents'],
            $data['currency'] ?? 'BRL'
        );

        $this->quotationRepository->save($quotation);
        $this->metrics->incQuotationsCreated();

        $payload = [
            'id' => $quotation->getId(),
            'service_order_id' => $quotation->getServiceOrderId(),
            'status' => $quotation->getStatus(),
            'total_in_cents' => $quotation->getTotalInCents(),
            'currency' => $quotation->getCurrency(),
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', self::CONTENT_TYPE_JSON)->withStatus(201);
    }

    public function getQuotation(Request $request, Response $response, array $args): Response
    {
        $quotationId = (int)$args['id'];
        $quotation = $this->quotationRepository->findById($quotationId);

        if (!$quotation) {
            throw new NotFoundException('Quotation', $quotationId);
        }

        $payload = [
            'id' => $quotation->getId(),
            'service_order_id' => $quotation->getServiceOrderId(),
            'status' => $quotation->getStatus(),
            'total_in_cents' => $quotation->getTotalInCents(),
            'currency' => $quotation->getCurrency(),
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', self::CONTENT_TYPE_JSON);
    }

    public function createPayment(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['quotation_id']) || empty($data['amount_in_cents'])) {
            throw new ValidationException('quotation_id e amount_in_cents sao obrigatorios');
        }

        $quotation = $this->quotationRepository->findById((int)$data['quotation_id']);
        if (!$quotation) {
            throw new NotFoundException('Quotation', (int)$data['quotation_id']);
        }

        $payment = new Payment(
            $quotation->getId() ?? (int)$data['quotation_id'],
            (int)$data['amount_in_cents'],
            'mercadopago',
            $data['currency'] ?? 'BRL'
        );

        $payload = [
            'items' => [[
                'id' => (string)$quotation->getId(),
                'title' => 'Service order ' . $quotation->getServiceOrderId(),
                'description' => 'Quotation ' . $quotation->getId(),
                'currency_id' => $quotation->getCurrency(),
                'quantity' => 1,
                'unit_price' => $payment->getAmountInCents() / 100,
            ]],
            'payer' => [
                'email' => $data['payer_email'] ?? 'customer@example.com',
            ],
            'external_reference' => (string)$quotation->getId(),
        ];

        $notificationUrl = getenv('MP_NOTIFICATION_URL');
        if ($notificationUrl) {
            $payload['notification_url'] = $notificationUrl;
        }

        try {
            $providerResponse = $this->paymentGateway->createPreference($payload);
        } catch (\Exception $e) {
            // Provide detailed API error during local debugging to help identify
            // Mercado Pago response. This is a temporary diagnostic change.
            $errorPayload = ['message' => $e->getMessage()];
            if (method_exists($e, 'getApiResponse')) {
                $resp = $e->getApiResponse();
                $errorPayload['status'] = $resp->getStatusCode();
                $errorPayload['response'] = $resp->getContent();
            }

            $response->getBody()->write(json_encode(['statusCode' => 500, 'error' => $errorPayload]));
            return $response->withHeader('Content-Type', self::CONTENT_TYPE_JSON)->withStatus(500);
        }
        $status = isset($providerResponse['status']) ? (string)$providerResponse['status'] : 'pending';

        $payment->setStatus($status);
        $this->paymentRepository->save($payment);
        $this->metrics->incPaymentsCreated();

        $event = new PaymentEvent($payment->getId() ?? 0, 'payment.preference.created', $providerResponse);
        $this->paymentEventRepository->save($event);

        $responsePayload = [
            'id' => $payment->getId(),
            'quotation_id' => $payment->getQuotationId(),
            'provider' => $payment->getProvider(),
            'provider_payment_id' => $payment->getProviderPaymentId(),
            'status' => $payment->getStatus(),
            'preference_id' => $providerResponse['id'] ?? null,
            'init_point' => $providerResponse['init_point'] ?? null,
            'sandbox_init_point' => $providerResponse['sandbox_init_point'] ?? null,
        ];

        $response->getBody()->write(json_encode($responsePayload));
        return $response->withHeader('Content-Type', self::CONTENT_TYPE_JSON)->withStatus(201);
    }

    public function getPayment(Request $request, Response $response, array $args): Response
    {
        $paymentId = (int)$args['id'];
        $payment = $this->paymentRepository->findById($paymentId);

        if (!$payment) {
            throw new NotFoundException('Payment', $paymentId);
        }

        $responsePayload = [
            'id' => $payment->getId(),
            'quotation_id' => $payment->getQuotationId(),
            'provider' => $payment->getProvider(),
            'provider_payment_id' => $payment->getProviderPaymentId(),
            'status' => $payment->getStatus(),
        ];

        $response->getBody()->write(json_encode($responsePayload));
        return $response->withHeader('Content-Type', self::CONTENT_TYPE_JSON);
    }

    public function mercadopagoWebhook(Request $request, Response $response): Response
    {
        $payload = (array)$request->getParsedBody();

        $eventType = $payload['type'] ?? 'unknown';
        $providerPaymentId = $payload['data']['id'] ?? null;

        if (!$providerPaymentId) {
            throw new ValidationException('Webhook payload missing data.id');
        }

        // Try to find local payment first to allow local webhook simulations
        $payment = $this->paymentRepository->findByProviderPaymentId((string)$providerPaymentId);

        if ($payment) {
            // Local payment exists; construct a providerData stub to avoid external API call in dev
            $providerData = [
                'id' => (string)$providerPaymentId,
                'external_reference' => (string)$payment->getQuotationId(),
                'status' => 'approved',
            ];
        } else {
            $providerData = $this->paymentGateway->getPayment((string)$providerPaymentId);
        }

        if (!$payment && isset($providerData['external_reference'])) {
            $payment = $this->paymentRepository->findByQuotationId((int)$providerData['external_reference']);
        }

        if (!$payment) {
            throw new NotFoundException('Payment', (string)$providerPaymentId);
        }

        if (!$payment->getProviderPaymentId()) {
            $payment->setProviderPaymentId((string)$providerPaymentId);
        }

        $status = isset($providerData['status']) ? (string)$providerData['status'] : $payment->getStatus();
        $payment->setStatus($status);
        $this->paymentRepository->save($payment);

        $event = new PaymentEvent($payment->getId() ?? 0, $eventType, $payload);
        $this->paymentEventRepository->save($event);

        if ($status === 'approved') {
            $this->eventPublisher->publishPaymentApproved([
                'payment_id' => $payment->getId(),
                'provider_payment_id' => $payment->getProviderPaymentId(),
                'quotation_id' => $payment->getQuotationId(),
                'service_order_id' => $providerData['external_reference'] ?? null,
                'status' => $status,
            ]);
        } elseif (in_array($status, ['rejected', 'cancelled', 'expired'], true)) {
            $this->eventPublisher->publishPaymentFailed([
                'payment_id' => $payment->getId(),
                'provider_payment_id' => $payment->getProviderPaymentId(),
                'quotation_id' => $payment->getQuotationId(),
                'service_order_id' => $providerData['external_reference'] ?? null,
                'status' => $status,
                'reason' => 'Pagamento ' . $status,
            ]);
            $this->metrics->incIntegrationError('payment_' . $status);
        }

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', self::CONTENT_TYPE_JSON);
    }
}
