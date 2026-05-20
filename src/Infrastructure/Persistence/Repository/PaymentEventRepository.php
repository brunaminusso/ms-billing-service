<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\PaymentEvent;
use App\Domain\Repository\PaymentEventRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class PaymentEventRepository implements PaymentEventRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function save(PaymentEvent $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }
}
