<?php

declare(strict_types=1);

namespace App\Domain\Service;

use MercadoPago\Resources\Preference;
use MercadoPago\Resources\Payment;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function __construct(
        private MercadoPagoPaymentClientInterface $paymentClient,
        private MercadoPagoPreferenceClientInterface $preferenceClient
    ) {}

    public function createPreference(array $payload): array
    {
        return $this->normalize($this->preferenceClient->createPreference($payload));
    }

    public function getPayment(string $paymentId): array
    {
        return $this->normalize($this->paymentClient->get((int)$paymentId));
    }

    private function normalize(object $resource): array
    {
        $data = json_decode(json_encode($resource), true);

        return is_array($data) ? $this->clean($data) : [];
    }

    private function clean(array $data): array
    {
        $ignoreKeys = ['headers', 'created_source', 'created_by_app', 'map'];

        foreach ($ignoreKeys as $key) {
            unset($data[$key]);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->clean($value);
            }
        }

        return $data;
    }
}
