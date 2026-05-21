<?php

declare(strict_types=1);

namespace App\Domain\Service;

use MercadoPago\Resources\Payment;

interface MercadoPagoPaymentClientInterface
{
    public function create(array $request): Payment;

    public function get(int $id): Payment;
}
