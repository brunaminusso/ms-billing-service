<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Quotation;

interface QuotationRepositoryInterface
{
    public function findById(int $id): ?Quotation;

    public function save(Quotation $quotation): void;
}
