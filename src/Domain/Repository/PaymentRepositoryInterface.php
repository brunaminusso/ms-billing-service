<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Payment;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;

    public function findByProviderPaymentId(string $providerPaymentId): ?Payment;

    public function findByQuotationId(int $quotationId): ?Payment;

    public function save(Payment $payment): void;
}
