<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Middleware\RateLimitMiddleware;
use App\Users\Repositories\Auth\RateLimitCacheRepository;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Slim\Psr7\Response;
use Tests\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    public function testRateLimitBlocksRequestsWhenLimitExceeded(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();


        $cache = $container->get(CacheInterface::class);
        $cache->clear();


        $testLimit = 5;
        $totalRequests = 8;

        $repository = new RateLimitCacheRepository($cache);
        $middleware = new RateLimitMiddleware(
            $repository,
            limit: $testLimit,
            windowSeconds: 60
        );


        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response(200));


        for ($i = 0; $i < $totalRequests; $i++) {
            $request = $this->createRequest('GET', '/test-route');
            $response = $middleware->process($request, $handler);

            if ($i < $testLimit) {
                $this->assertEquals(
                    200,
                    $response->getStatusCode(),
                    "Falló permitiendo en la iteración $i"
                );
            } else {
                $this->assertEquals(
                    429,
                    $response->getStatusCode(),
                    "Falló bloqueando en la iteración $i"
                );
                $this->assertTrue($response->hasHeader('Retry-After'));
            }
        }
    }
}
