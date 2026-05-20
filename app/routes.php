<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Presentation\Controller\BillingController;
use App\Presentation\Controller\HealthController;
use App\Presentation\Controller\MetricsController;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/metrics', [MetricsController::class, 'index']);

    $app->group('/api/v1', function (Group $group) {
        $group->get('/health', [HealthController::class, 'check']);
        $group->get('/ready', [HealthController::class, 'ready']);

        $group->post('/quotations', [BillingController::class, 'createQuotation']);
        $group->get('/quotations/{id}', [BillingController::class, 'getQuotation']);

        $group->post('/payments', [BillingController::class, 'createPayment']);
        $group->get('/payments/{id}', [BillingController::class, 'getPayment']);
        $group->post('/payments/webhook/mercadopago', [BillingController::class, 'mercadopagoWebhook']);
    });
};
