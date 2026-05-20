<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Infrastructure\Observability\MetricsRegistryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MetricsController
{
    public function __construct(private MetricsRegistryInterface $metrics)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $response->getBody()->write($this->metrics->render());

        return $response
            ->withHeader('Content-Type', 'text/plain; version=0.0.4');
    }
}
