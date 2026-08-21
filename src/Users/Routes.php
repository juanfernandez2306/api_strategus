<?php
use Slim\Routing\RouteCollectorProxy;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

use App\Users\Actions\Auth\RegisterAction;

return function (RouteCollectorProxy $group) {

    $group->post('/register', RegisterAction::class);

};