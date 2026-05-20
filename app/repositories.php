<?php

declare(strict_types=1);

use App\Domain\Repository\QuotationRepositoryInterface;
use App\Domain\Repository\PaymentRepositoryInterface;
use App\Domain\Repository\PaymentEventRepositoryInterface;
use App\Infrastructure\Persistence\Repository\QuotationRepository;
use App\Infrastructure\Persistence\Repository\PaymentRepository;
use App\Infrastructure\Persistence\Repository\PaymentEventRepository;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        QuotationRepositoryInterface::class => \DI\autowire(QuotationRepository::class),
        PaymentRepositoryInterface::class => \DI\autowire(PaymentRepository::class),
        PaymentEventRepositoryInterface::class => \DI\autowire(PaymentEventRepository::class),
    ]);
};
