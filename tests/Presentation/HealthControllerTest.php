<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Presentation\Controller\HealthController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class HealthControllerTest extends TestCase
{
    public function testCheckReturnsHealthy(): void
    {
        $controller = new HealthController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');

        $response = $controller->check($request, new Response());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('healthy', $payload['status']);
        $this->assertSame('Billing Service', $payload['service']);
    }

    public function testReadyReturnsReady(): void
    {
        $controller = new HealthController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/ready');

        $response = $controller->ready($request, new Response());
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ready', $payload['status']);
        $this->assertSame('Billing Service', $payload['service']);
    }
}
