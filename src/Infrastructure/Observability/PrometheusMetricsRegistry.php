<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

class PrometheusMetricsRegistry implements MetricsRegistryInterface
{
    private string $serviceName;
    private array $counters = [];
    private array $histograms = [];

    public function __construct(string $serviceName = 'billing-service')
    {
        $this->serviceName = $serviceName;
    }

    public function observeHttpRequest(string $method, string $route, int $status, float $durationSeconds): void
    {
        $labels = [
            'service' => $this->serviceName,
            'method' => $method,
            'route' => $route,
            'status' => (string)$status,
        ];

        $this->incrementCounter('app_http_requests_total', $labels);
        $this->observeHistogram('app_http_request_duration_seconds', $labels, $durationSeconds);
    }

    public function incOrdersCreated(string $status): void
    {
        $this->incrementCounter('app_orders_created_total', [
            'service' => $this->serviceName,
            'status' => $status,
        ]);
    }

    public function incQuotationsCreated(): void
    {
        $this->incrementCounter('app_quotations_created_total', [
            'service' => $this->serviceName,
        ]);
    }

    public function incPaymentsCreated(): void
    {
        $this->incrementCounter('app_payments_created_total', [
            'service' => $this->serviceName,
        ]);
    }

    public function incIntegrationError(string $reason): void
    {
        $this->incrementCounter('app_integration_errors_total', [
            'service' => $this->serviceName,
            'reason' => $reason,
        ]);
    }

    public function render(): string
    {
        $output = [];

        foreach ($this->counters as $name => $series) {
            $output[] = '# TYPE ' . $name . ' counter';
            foreach ($series as $labelSet => $value) {
                $output[] = $name . $labelSet . ' ' . $value;
            }
        }

        foreach ($this->histograms as $name => $series) {
            $output[] = '# TYPE ' . $name . ' histogram';
            foreach ($series as $labelSet => $data) {
                foreach ($data['buckets'] as $bucket => $count) {
                    $output[] = $name . '_bucket' . $this->appendLabel($labelSet, 'le', (string)$bucket) . ' ' . $count;
                }

                $output[] = $name . '_bucket' . $this->appendLabel($labelSet, 'le', '+Inf') . ' ' . $data['count'];
                $output[] = $name . '_sum' . $labelSet . ' ' . $data['sum'];
                $output[] = $name . '_count' . $labelSet . ' ' . $data['count'];
            }
        }

        return implode("\n", $output) . "\n";
    }

    private function incrementCounter(string $name, array $labels): void
    {
        $labelSet = $this->formatLabels($labels);
        $this->counters[$name][$labelSet] = ($this->counters[$name][$labelSet] ?? 0) + 1;
    }

    private function observeHistogram(string $name, array $labels, float $value): void
    {
        $labelSet = $this->formatLabels($labels);
        $buckets = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5];

        if (!isset($this->histograms[$name][$labelSet])) {
            $this->histograms[$name][$labelSet] = [
                'count' => 0,
                'sum' => 0.0,
                'buckets' => array_fill_keys(array_map('strval', $buckets), 0),
            ];
        }

        $this->histograms[$name][$labelSet]['count']++;
        $this->histograms[$name][$labelSet]['sum'] += $value;

        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $key = (string)$bucket;
                $this->histograms[$name][$labelSet]['buckets'][$key]++;
            }
        }
    }

    private function formatLabels(array $labels): string
    {
        $parts = [];

        foreach ($labels as $key => $value) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$value);
            $parts[] = $key . '="' . $escaped . '"';
        }

        return '{' . implode(',', $parts) . '}';
    }

    private function appendLabel(string $labelSet, string $label, string $value): string
    {
        $labels = trim($labelSet, '{}');
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        if ($labels === '') {
            return '{' . $label . '="' . $escaped . '"}';
        }

        return '{' . $labels . ',' . $label . '="' . $escaped . '"}';
    }
}
