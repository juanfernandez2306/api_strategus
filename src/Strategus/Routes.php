<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Strategus\Actions\SyncPositionRecordsAction;
use Slim\Routing\RouteCollectorProxy;

return function (RouteCollectorProxy $group): void{
    $group->group('/monitoring', function (RouteCollectorProxy $monitorinGroup) {
        $monitorinGroup->post('/sync', SyncPositionRecordsAction::class);
    })
        ->add(AuthMiddleware::class)
        ->add(new RoleMiddleware([2, 3]));
};
