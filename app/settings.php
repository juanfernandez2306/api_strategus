<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            return new Settings([
                'displayErrorDetails' => true, // Should be set to false in production
                'logError'            => true,
                'logErrorDetails'     => true,
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Logger::DEBUG,
                ],
                'db' => [
                    'host'      => $_ENV['DB_HOST'],
                    'database'  => $_ENV['DB_NAME'],
                    'username'  => $_ENV['DB_USER'],
                    'password'  => $_ENV['DB_PASS'],
                    'charset'   => $_ENV['DB_CHARSET'],
                    'options'   => [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                ],

                'cors' => [
                    'allowed_origins' => !empty($_ENV['CORS_ALLOWED_ORIGINS'])
                        ? array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS']))
                        : [],
                    'allowed_headers' => 'X-Requested-With, Content-Type, Accept, Origin, Authorization',
                    'allowed_methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                ],

                'cookie' => [
                    'name'     => 'auth_token',
                    'lifetime' => 60 * 60 * 24 * 7,
                    'domain'   => $_ENV['COOKIE_DOMAIN'],
                    'secure'   => filter_var($_ENV['COOKIE_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'samesite' => 'Lax',
                ],

                'mail' => [
                    'host'       => $_ENV['MAIL_HOST'],
                    'port'       => (int) ($_ENV['MAIL_PORT']),
                    'username'   => $_ENV['MAIL_USER'],
                    'password'   => $_ENV['MAIL_PASSWORD'],
                    'from'       => $_ENV['MAIL_FROM_ADDRESS'],
                    'from_name'  => $_ENV['MAIL_FROM_NAME'],
                    'encryption' => $_ENV['MAIL_ENCRYPTION'],
                    'logoUrl' => ($_ENV['APP_URL'] === 'http://localhost/api-gepad') 
                        ? 'https://api.juanfgeo.com/assets/img/logo_sigepad.png' 
                        : rtrim($_ENV['APP_URL'] ?? '', '/') . '/assets/img/logo_sigepad.png',
                ],

            ]);
        }
    ]);
};
