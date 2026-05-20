<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

interface MetricsRegistryInterface
{
    public function observeHttpRequest(string $method, string $route, int $status, float $durationSeconds): void;

    public function incOrdersCreated(string $status): void;

    public function incQuotationsCreated(): void;

    public function incPaymentsCreated(): void;

    public function incIntegrationError(string $reason): void;

    public function render(): string;
}
