<?php

declare(strict_types=1);

use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use App\Users\Repositories\Auth\InterfacePasswordResetRepository;
use App\Users\Repositories\Auth\InterfaceUserRepository;
use App\Users\Repositories\Auth\PdoPasswordResetRepository;
use App\Users\Repositories\Auth\PdoUserRepository;
use App\Users\Repositories\Auth\RateLimitCacheRepository;
use App\Users\Repositories\Crud\PdoUserCrudRepository;
use App\Users\Repositories\Crud\UserCrudRepositoryInterface;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

use function DI\autowire;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        UserRepository::class                    => autowire(InMemoryUserRepository::class),
        InterfaceUserRepository::class           => autowire(PdoUserRepository::class),
        UserCrudRepositoryInterface::class       => autowire(PdoUserCrudRepository::class),
        InterfacePasswordResetRepository::class => autowire(PdoPasswordResetRepository::class),

        RateLimitCacheRepository::class => function (ContainerInterface $c) {
            return new RateLimitCacheRepository($c->get(CacheInterface::class));
        },
    ]);
};