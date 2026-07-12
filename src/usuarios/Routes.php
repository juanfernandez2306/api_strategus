<?php
use Slim\Routing\RouteCollectorProxy;

// Importamos todos tus controladores de acción única
use App\Usuarios\Controllers\GetAllController;
use App\Usuarios\Controllers\CreateController;
use App\Usuarios\Controllers\UpdateController;
use App\Usuarios\Controllers\DeleteController;
use App\Usuarios\Controllers\UpdateStatusController;
use App\Usuarios\Controllers\LoginController;
use App\Usuarios\Controllers\LogoutController;
use App\Usuarios\Controllers\VerifyEmailController;
use App\Usuarios\Controllers\ForgotPasswordController;
use App\Usuarios\Controllers\ResetPasswordController;

use App\Middleware\AuthMiddleware;

use App\Middleware\RoleMiddleware;

/**
 * Definición de rutas para el módulo de Usuarios
 * * Este archivo se importa en tu index.php principal de la siguiente manera:
 * $app->group('/usuarios', require __DIR__ . '/src/Usuarios/Routes.php');
 */
return function (RouteCollectorProxy $group) {

   
    
    // -------------------------------------------------------------
    // 🔓 RUTAS PÚBLICAS (No requieren Token)
    // -------------------------------------------------------------
    $group->post('/login', LoginController::class);

    $group->post('/register', CreateController::class);

    $group->get('/verify-email', VerifyEmailController::class);

    $group->post('/forgot-password', ForgotPasswordController::class);

    $group->map(['GET', 'POST'], '/reset-password', ResetPasswordController::class);

    // -------------------------------------------------------------
    // 🔒 RUTAS PROTEGIDAS (Requieren pasar por el AuthMiddleware)
    // -------------------------------------------------------------
    
    //listar todo los usuarios
    $group->get('', GetAllController::class)
        ->add(new RoleMiddleware([1]))
        ->add(AuthMiddleware::class);

    $group->put('/{id:[0-9]+}', UpdateController::class)
        ->add(new RoleMiddleware([1]))
        ->add(AuthMiddleware::class);
    

    $group->delete('/{id:[0-9]+}', DeleteController::class)
        ->add(new RoleMiddleware([1]))
        ->add(AuthMiddleware::class);

    $group->patch('/{id:[0-9]+}/{status:[0-9]+}', UpdateStatusController::class)
        ->add(new RoleMiddleware([1]))
        ->add(AuthMiddleware::class);

    $group->post('/logout', LogoutController::class)
        ->add(AuthMiddleware::class);

};