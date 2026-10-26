<?php

declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use App\Middleware\CorsMiddleware;
use Slim\App;
use App\Middleware\RateLimitMiddleware;

return function (App $app) {
    $app->add(SessionMiddleware::class);
    $app->add(RateLimitMiddleware::class);
    $app->add(CorsMiddleware::class);
};
