<?php

declare(strict_types=1);

namespace App\Domain\Service;

interface PaymentGatewayInterface
{
    public function createPreference(array $payload): array;

    public function getPayment(string $paymentId): array;
}
