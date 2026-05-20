<?php

declare(strict_types=1);

use App\Application\Middleware\HttpMetricsMiddleware;
use App\Application\Middleware\SessionMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(HttpMetricsMiddleware::class);
    $app->add(SessionMiddleware::class);
};
