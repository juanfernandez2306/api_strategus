<?php

declare(strict_types=1);

namespace Tests\Integration\Actions\User\Register;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Faker\Factory;
use Faker\Generator;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class RegisterActionIntegrationTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private Generator $faker;
    private string $createdEmail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create('es_ES');

        $projectRoot = 'C:/xampp/htdocs/api-gepad';

        // 1. Cargar variables del entorno .env
        if (file_exists($projectRoot . '/.env')) {
            $dotenv = Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        }

        $basePath = $projectRoot . '/app';

        // 2. Construir el contenedor de dependencias
        $containerBuilder = new ContainerBuilder();

        // Cargar settings.php
        $settings = require $basePath . '/settings.php';
        $settings($containerBuilder);

        // Cargar dependencies.php
        $dependencies = require $basePath . '/dependencies.php';
        $dependencies($containerBuilder);

        // Cargar repositories.php (Aquí está la solución a tu error previo)
        $repositories = require $basePath . '/repositories.php';
        $repositories($containerBuilder);

        $container = $containerBuilder->build();

        // 3. Crear la App Slim
        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        // Agregar el RoutingMiddleware es obligatorio en Slim 4
        $this->app->addRoutingMiddleware();

        // 4. Cargar Middlewares y Rutas globales
        $middleware = require $basePath . '/middleware.php';
        $middleware($this->app);

        $routes = require $basePath . '/routes.php';
        $routes($this->app);

        // 5. Obtener PDO real
        $this->pdo = $container->get(PDO::class);
    }

    protected function tearDown(): void
    {
        // Limpieza de datos en la BD de desarrollo
        if (!empty($this->createdEmail)) {
            // 1. Buscamos primero el ID del usuario creado
            $stmtUser = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmtUser->execute(['email' => $this->createdEmail]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 2. Borramos los registros en password_resets usando user_id
                $stmtReset = $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
                $stmtReset->execute(['user_id' => $user['id']]);

                // 3. Borramos el usuario de la tabla users
                $stmtDeleteUser = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmtDeleteUser->execute(['id' => $user['id']]);
            }
        }

        parent::tearDown();
    }

    public function testExecuteRegisterIntegrationFlowSuccessfully(): void
    {
        // Generación de datos respetando tus reglas
        $firstName = $this->faker->regexify('[A-Za-zÑñ]{6}');
        $lastName  = $this->faker->regexify('[A-Za-zÑñ]{8}');
        $password  = $this->faker->regexify('[A-Za-z]{4}[0-9]{2}') . '!';
        $this->createdEmail = 'test_' . $this->faker->unique()->safeEmail();

        $payload = [
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'email'                 => $this->createdEmail,
            'password'              => $password,
            'password_confirmation' => $password,
        ];

        // Se envía la petición a la ruta exacta '/users/register'
        $serverRequestFactory = new ServerRequestFactory();
        $request = $serverRequestFactory->createServerRequest('POST', '/users/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($payload);

        // Disparar la ejecución de Slim
        $response = $this->app->handle($request);

        // 1. Verificación HTTP 201
        $this->assertEquals(201, $response->getStatusCode());

        // 2. Verificación en la tabla `users` en BD real
        $stmtUser = $this->pdo->prepare('SELECT id, email FROM users WHERE email = :email');
        $stmtUser->execute(['email' => $this->createdEmail]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($user, 'El usuario no fue insertado en la base de datos.');
        $this->assertEquals($this->createdEmail, $user['email']);

        // 3. Verificación en la tabla `password_resets` en BD real
        $stmtToken = $this->pdo->prepare('SELECT user_id, token FROM password_resets WHERE user_id = :user_id');
        $stmtToken->execute(['user_id' => $user['id']]);
        $resetRecord = $stmtToken->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($resetRecord, 'El token no fue registrado en password_resets.');
        $this->assertEquals(64, strlen($resetRecord['token']));
    }
}
