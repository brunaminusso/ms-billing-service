<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Payment;
use App\Domain\Entity\PaymentEvent;
use App\Domain\Entity\Quotation;
use App\Infrastructure\Persistence\Repository\PaymentEventRepository;
use App\Infrastructure\Persistence\Repository\PaymentRepository;
use App\Infrastructure\Persistence\Repository\QuotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class BillingRepositoriesTest extends TestCase
{
    public function testQuotationRepositoryFindsAndSavesEntities(): void
    {
        $quotation = new Quotation(22, 5000);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(22)
            ->willReturn($quotation);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Quotation::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($quotation);
        $entityManager->expects($this->once())
            ->method('flush');

        $quotationRepository = new QuotationRepository($entityManager);

        $this->assertSame($quotation, $quotationRepository->findById(22));
        $quotationRepository->save($quotation);
    }

    public function testPaymentRepositoryDelegatesFindsAndSave(): void
    {
        $payment = new Payment(22, 5000);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($payment);
        $repository->expects($this->exactly(2))
            ->method('findOneBy')
            ->withConsecutive(
                [['providerPaymentId' => 'mp-10']],
                [['quotationId' => 22]],
            )
            ->willReturnOnConsecutiveCalls($payment, $payment);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Payment::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($payment);
        $entityManager->expects($this->once())
            ->method('flush');

        $paymentRepository = new PaymentRepository($entityManager);

        $this->assertSame($payment, $paymentRepository->findById(12));
        $this->assertSame($payment, $paymentRepository->findByProviderPaymentId('mp-10'));
        $this->assertSame($payment, $paymentRepository->findByQuotationId(22));
        $paymentRepository->save($payment);
    }

    public function testPaymentEventRepositoryPersistsAndFlushes(): void
    {
        $event = new PaymentEvent(10, 'payment.created', ['id' => 'mp-10']);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($event);
        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new PaymentEventRepository($entityManager);
        $repository->save($event);

        $this->addToAssertionCount(1);
    }
}
