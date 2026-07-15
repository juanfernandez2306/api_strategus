<?php

use Slim\Routing\RouteCollectorProxy;
use App\Strategus\Controllers\BatchMonitoreoController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Strategus\Controllers\ExportExcelController;
use App\Strategus\Controllers\GetResumenPorLoteController;
use App\Strategus\Controllers\GetMapMarkersController;
use App\Strategus\Controllers\GetRegistroSemanalStrategusController;

return function (RouteCollectorProxy $group) {
    
    $group->post('/sincronizar', BatchMonitoreoController::class)
        ->add(new RoleMiddleware([2, 3]))
        ->add(AuthMiddleware::class);

    $group->post('/exportar/excel', ExportExcelController::class)
        ->add(AuthMiddleware::class);

    $group->get('/resumen-lotes', GetResumenPorLoteController::class)
        ->add(AuthMiddleware::class);

    $group->get('/resumen-semanal', GetRegistroSemanalStrategusController::class)
        ->add(AuthMiddleware::class);

    $group->get('/mapa/ubicaciones', GetMapMarkersController::class)
        ->add(AuthMiddleware::class);
};