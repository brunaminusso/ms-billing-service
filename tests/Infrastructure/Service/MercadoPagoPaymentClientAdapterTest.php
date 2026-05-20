<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Service;

use App\Infrastructure\Service\MercadoPagoPaymentClientAdapter;
use PHPUnit\Framework\TestCase;

class MercadoPagoPaymentClientAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MP_ACCESS_TOKEN');
        putenv('MP_BASE_URI');
        putenv('MP_RUNTIME_ENVIRONMENT');
    }

    public function testConstructorThrowsWhenTokenMissing(): void
    {
        putenv('MP_ACCESS_TOKEN');

        $this->expectException(\RuntimeException::class);
        new MercadoPagoPaymentClientAdapter();
    }
}