<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Strategus\Actions\SyncPositionRecordsAction;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/monitoring', function (RouteCollectorProxy $group) {
        $group->post('/sync', SyncPositionRecordsAction::class);
    })
        ->add(AuthMiddleware::class)
        ->add(new RoleMiddleware([2, 3]));
};
