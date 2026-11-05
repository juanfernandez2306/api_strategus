<?php

declare(strict_types=1);

use App\Users\Repositories\Auth\PasswordResetRepositoryInterface;
use App\Users\Repositories\Auth\UserRepositoryInterface;
use App\Users\Repositories\Auth\PdoPasswordResetRepository;
use App\Users\Repositories\Auth\PdoUserRepository;
use App\Users\Repositories\Auth\RateLimitCacheRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

use function DI\autowire;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        UserRepositoryInterface::class           => autowire(PdoUserRepository::class),
        PasswordResetRepositoryInterface::class => autowire(PdoPasswordResetRepository::class),

        RateLimitCacheRepository::class => function (ContainerInterface $c) {
            return new RateLimitCacheRepository($c->get(CacheInterface::class));
        },
    ]);
};