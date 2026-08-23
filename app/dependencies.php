<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Middleware\RateLimitMiddleware;
use App\Shared\Services\Mail\InterfaceMailService;
use App\Shared\Services\Mail\PhpMailerService;
use App\Users\Repositories\Auth\RateLimitCacheRepository;
use App\Users\Services\Mail\InterfaceMailRegister;
use App\Users\Services\Mail\MailRegisterService;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

use function DI\autowire;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([

        InterfaceMailService::class  => autowire(PhpMailerService::class),
        InterfaceMailRegister::class => autowire(MailRegisterService::class),

        PDO::class => function (ContainerInterface $c) {
            $settingsInstance = $c->get(SettingsInterface::class);
            $dbSettings = $settingsInstance->get('db');

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $dbSettings['host'],
                $dbSettings['database'],
                $dbSettings['charset']
            );

            return new PDO(
                $dsn,
                $dbSettings['username'],
                $dbSettings['password'],
                $dbSettings['options'] ?? []
            );
        },

        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);
            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler($loggerSettings['path'], $loggerSettings['level']));

            return $logger;
        },

        CacheInterface::class => function () {
            $cachePath = __DIR__ . '/../var/cache';
            $psr6Adapter = new FilesystemAdapter('api_limits', 0, $cachePath);
            return new Psr16Cache($psr6Adapter);
        },

        RateLimitMiddleware::class => function (ContainerInterface $c) {
            return new RateLimitMiddleware(
                $c->get(RateLimitCacheRepository::class), // Se resuelve automáticamente desde repositories.php
                40,
                60
            );
        },

    ]);
};