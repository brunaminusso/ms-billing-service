<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\PaymentEvent;

interface PaymentEventRepositoryInterface
{
    public function save(PaymentEvent $event): void;
}
