<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Observability;

use App\Infrastructure\Observability\PrometheusMetricsRegistry;
use PHPUnit\Framework\TestCase;

class PrometheusMetricsRegistryTest extends TestCase
{
    public function testRenderExposesCountersAndHistogramSeries(): void
    {
        $registry = new PrometheusMetricsRegistry('billing-api');

        $registry->observeHttpRequest('POST', '/payments', 201, 0.08);
        $registry->incOrdersCreated('created');
        $registry->incQuotationsCreated();
        $registry->incPaymentsCreated();
        $registry->incIntegrationError('gateway_timeout');

        $output = $registry->render();

        $this->assertStringContainsString('# TYPE app_http_requests_total counter', $output);
        $this->assertStringContainsString(
            'app_http_requests_total{service="billing-api",method="POST",route="/payments",status="201"} 1',
            $output,
        );
        $this->assertStringContainsString('# TYPE app_http_request_duration_seconds histogram', $output);
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_bucket'
            . '{service="billing-api",method="POST",route="/payments",status="201",le="0.1"} 1',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_bucket'
            . '{service="billing-api",method="POST",route="/payments",status="201",le="0.05"} 0',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_bucket'
            . '{service="billing-api",method="POST",route="/payments",status="201",le="+Inf"} 1',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_sum'
            . '{service="billing-api",method="POST",route="/payments",status="201"} 0.08',
            $output,
        );
        $this->assertStringContainsString(
            'app_orders_created_total{service="billing-api",status="created"} 1',
            $output,
        );
        $this->assertStringContainsString('app_quotations_created_total{service="billing-api"} 1', $output);
        $this->assertStringContainsString('app_payments_created_total{service="billing-api"} 1', $output);
        $this->assertStringContainsString(
            'app_integration_errors_total{service="billing-api",reason="gateway_timeout"} 1',
            $output,
        );
    }

    public function testRenderEscapesLabelValuesAndKeepsCountersAccumulated(): void
    {
        $registry = new PrometheusMetricsRegistry('billing"api');

        $registry->incIntegrationError('gateway\\timeout');
        $registry->incIntegrationError('gateway\\timeout');

        $output = $registry->render();

        $this->assertStringContainsString(
            'app_integration_errors_total{service="billing\\"api",reason="gateway\\\\timeout"} 2',
            $output,
        );
    }
}
