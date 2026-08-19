<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use App\Middleware\RateLimitMiddleware;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([

        PDO::class => function (ContainerInterface $c) {
            /** @var SettingsInterface $settingsInstance */
            $settingsInstance = $c->get(SettingsInterface::class);
            
            // Obtenemos el array 'db' que lee del .env
            $dbSettings = $settingsInstance->get('db');

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $dbSettings['host'],
                $dbSettings['database'],
                $dbSettings['charset']
            );

            $username = $dbSettings['username'];
            $password = $dbSettings['password'];
            $options  = $dbSettings['options'] ?? [];

            return new PDO($dsn, $username, $password, $options);
        },
        
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        CacheInterface::class => function () {
            $cachePath = __DIR__ . '/../var/cache';
            $psr6Adapter = new FilesystemAdapter('api_limits', 0, $cachePath);
            return new Psr16Cache($psr6Adapter);
        },

        RateLimitMiddleware::class => function (ContainerInterface $c) {
            return new RateLimitMiddleware(
                $c->get(CacheInterface::class),
                40, // Límite de peticiones
                60  // Ventana de segundos
            );
        },

    ]);
};
