<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Payment;
use App\Domain\Repository\PaymentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class PaymentRepository implements PaymentRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Payment::class);
    }

    public function findById(int $id): ?Payment
    {
        /** @var Payment|null */
        return $this->repository->find($id);
    }

    public function findByProviderPaymentId(string $providerPaymentId): ?Payment
    {
        /** @var Payment|null */
        return $this->repository->findOneBy(['providerPaymentId' => $providerPaymentId]);
    }

    public function findByQuotationId(int $quotationId): ?Payment
    {
        /** @var Payment|null */
        return $this->repository->findOneBy(['quotationId' => $quotationId]);
    }

    public function save(Payment $payment): void
    {
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }
}
