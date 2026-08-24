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

        $projectRoot = dirname(__DIR__, 5);
        $basePath = $projectRoot . '/app';

        if (file_exists($projectRoot . '/.env')) {
            $dotenv = Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        }

        $basePath = $projectRoot . '/app';

        $containerBuilder = new ContainerBuilder();

        $settings = require $basePath . '/settings.php';
        $settings($containerBuilder);

        $dependencies = require $basePath . '/dependencies.php';
        $dependencies($containerBuilder);

        $repositories = require $basePath . '/repositories.php';
        $repositories($containerBuilder);

        $container = $containerBuilder->build();

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        $this->app->addRoutingMiddleware();

        $middleware = require $basePath . '/middleware.php';
        $middleware($this->app);

        $routes = require $basePath . '/routes.php';
        $routes($this->app);

        $this->pdo = $container->get(PDO::class);
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdEmail)) {
            $stmtUser = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmtUser->execute(['email' => $this->createdEmail]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stmtReset = $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
                $stmtReset->execute(['user_id' => $user['id']]);

                $stmtDeleteUser = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmtDeleteUser->execute(['id' => $user['id']]);
            }
        }

        parent::tearDown();
    }

    public function testExecuteRegisterIntegrationFlowSuccessfully(): void
    {
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

        $serverRequestFactory = new ServerRequestFactory();
        $request = $serverRequestFactory->createServerRequest('POST', '/users/register')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($payload);

        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $stmtUser = $this->pdo->prepare('SELECT id, email FROM users WHERE email = :email');
        $stmtUser->execute(['email' => $this->createdEmail]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($user, 'El usuario no fue insertado en la base de datos.');
        $this->assertEquals($this->createdEmail, $user['email']);

        $stmtToken = $this->pdo->prepare('SELECT user_id, token FROM password_resets WHERE user_id = :user_id');
        $stmtToken->execute(['user_id' => $user['id']]);
        $resetRecord = $stmtToken->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($resetRecord, 'El token no fue registrado en password_resets.');
        $this->assertEquals(64, strlen($resetRecord['token']));
    }
}
