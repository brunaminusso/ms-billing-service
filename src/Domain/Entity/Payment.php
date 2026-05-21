<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $quotationId;

    #[ORM\Column(type: 'string', length: 40)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $providerPaymentId = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $status;

    #[ORM\Column(type: 'integer')]
    private int $amountInCents;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $quotationId,
        int $amountInCents,
        string $provider = 'mercadopago',
        string $currency = 'BRL'
    ) {
        $this->quotationId = $quotationId;
        $this->amountInCents = $amountInCents;
        $this->provider = $provider;
        $this->currency = $currency;
        $this->status = 'pending';
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuotationId(): int
    {
        return $this->quotationId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function setProviderPaymentId(?string $providerPaymentId): self
    {
        $this->providerPaymentId = $providerPaymentId;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
