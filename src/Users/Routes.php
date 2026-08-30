<?php

use Slim\Routing\RouteCollectorProxy;
use App\Middleware\RateLimitMiddleware;
use App\Users\Repositories\Auth\RateLimitCacheRepository;
use App\Users\Actions\Auth\RegisterAction;
use App\Users\Actions\Auth\LoginAction;
use App\Users\Actions\Auth\VerifyEmailAction;
use App\Users\Actions\Auth\ResendVerificationEmailAction;
use App\Users\Actions\Auth\SendPasswordResetEmailAction;
use App\Users\Actions\Auth\ResetPasswordAction;

return function (RouteCollectorProxy $group) {

    $container = $group->getContainer();
    $cacheRepo = $container->get(RateLimitCacheRepository::class);


    $authLimiter = new RateLimitMiddleware($cacheRepo, limit: 5, windowSeconds: 60);
    $mailLimiter = new RateLimitMiddleware($cacheRepo, limit: 3, windowSeconds: 300);


    $group->post('/register', RegisterAction::class)->add($authLimiter);
    $group->post('/login', LoginAction::class)->add($authLimiter);

    $group->group('/email', function (RouteCollectorProxy $emailGroup) use ($cacheRepo) {
        $emailGroup->get('/verify', VerifyEmailAction::class)
            ->add(new RateLimitMiddleware($cacheRepo, limit: 10, windowSeconds: 60));

        $emailGroup->post('/resend', ResendVerificationEmailAction::class);
    })->add($mailLimiter);

    $group->group('/password', function (RouteCollectorProxy $passwordGroup) {
        $passwordGroup->post('/forgot', SendPasswordResetEmailAction::class);
        $passwordGroup->post('/reset', ResetPasswordAction::class);
    })->add($mailLimiter);
};
