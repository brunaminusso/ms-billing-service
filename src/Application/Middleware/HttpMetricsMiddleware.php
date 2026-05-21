<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Infrastructure\Observability\MetricsRegistryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Routing\RouteContext;

class HttpMetricsMiddleware implements MiddlewareInterface
{
    public function __construct(private MetricsRegistryInterface $metrics)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path === '/metrics') {
            return $handler->handle($request);
        }

        $start = microtime(true);
        $response = $handler->handle($request);
        $duration = microtime(true) - $start;

        try {
            $route = RouteContext::fromRequest($request)->getRoute();
            $pattern = $route ? $route->getPattern() : $path;
        } catch (RuntimeException) {
            $pattern = $path;
        }
        $status = $response->getStatusCode();

        $this->metrics->observeHttpRequest($request->getMethod(), $pattern, $status, $duration);
        if ($status >= 500) {
            $this->metrics->incIntegrationError('http_5xx');
        }

        return $response;
    }
}
