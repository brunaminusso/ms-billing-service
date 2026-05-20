<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Entity\Quotation;
use PHPUnit\Framework\TestCase;

class QuotationTest extends TestCase
{
    public function testDefaultsAreSet(): void
    {
        $quotation = new Quotation(99, 5000, 'BRL');

        $this->assertSame(99, $quotation->getServiceOrderId());
        $this->assertSame(5000, $quotation->getTotalInCents());
        $this->assertSame('BRL', $quotation->getCurrency());
        $this->assertSame('pending', $quotation->getStatus());
    }

    public function testUpdatesStatus(): void
    {
        $quotation = new Quotation(99, 5000, 'BRL');

        $quotation->setStatus('approved');

        $this->assertSame('approved', $quotation->getStatus());
    }
}
