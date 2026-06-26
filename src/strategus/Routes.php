<?php

use Slim\Routing\RouteCollectorProxy;
use App\Strategus\Controllers\BatchMonitoreoController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Strategus\Controllers\ExportExcelController;
use App\Strategus\Controllers\GetResumenPorLoteController;
use App\Strategus\Controllers\GetMapMarkersController;

return function (RouteCollectorProxy $group) {
    
    $group->post('/sincronizar', BatchMonitoreoController::class)
        ->add(new RoleMiddleware([2, 3]))
        ->add(AuthMiddleware::class);

    $group->post('/exportar/excel', ExportExcelController::class)
        ->add(AuthMiddleware::class);

    $group->post('/resumen-lotes', GetResumenPorLoteController::class)
        ->add(AuthMiddleware::class);

    $group->get('/mapa/marcadores', GetMapMarkersController::class)
        ->add(AuthMiddleware::class);
};