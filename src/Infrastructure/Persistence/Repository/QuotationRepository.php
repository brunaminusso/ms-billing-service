<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Quotation;
use App\Domain\Repository\QuotationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class QuotationRepository implements QuotationRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Quotation::class);
    }

    public function findById(int $id): ?Quotation
    {
        /** @var Quotation|null */
        return $this->repository->find($id);
    }

    public function save(Quotation $quotation): void
    {
        $this->entityManager->persist($quotation);
        $this->entityManager->flush();
    }
}
