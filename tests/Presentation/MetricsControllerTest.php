<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Infrastructure\Observability\MetricsRegistryInterface;
use App\Presentation\Controller\MetricsController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class MetricsControllerTest extends TestCase
{
    public function testIndexWritesMetricsWithPrometheusContentType(): void
    {
        $metrics = $this->createMock(MetricsRegistryInterface::class);
        $metrics->expects($this->once())
            ->method('render')
            ->willReturn("metric 1\n");

        $controller = new MetricsController($metrics);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/metrics');
        $response = $controller->index($request, new Response());

        $this->assertSame("metric 1\n", (string) $response->getBody());
        $this->assertSame('text/plain; version=0.0.4', $response->getHeaderLine('Content-Type'));
    }
}
