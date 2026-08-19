<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use Psr\SimpleCache\CacheInterface;
use Tests\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    public function testRateLimitBlocksRequestsWhenLimitExceeded(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);
        $cache->clear();

        // Probamos 50 peticiones seguidas
        for ($i = 0; $i < 50; $i++) {
            $request = $this->createRequest('GET', '/');
            $response = $app->handle($request);

            if ($i < 40) {
                // De la petición 0 a la 39 (primeras 40)
                $this->assertEquals(200, $response->getStatusCode(), "Falló permitiendo en la iteración $i");
            } else {
                // De la petición 40 a la 49 (peticiones 41 a 50)
                $this->assertEquals(429, $response->getStatusCode(), "Falló bloqueando en la iteración $i");
                $this->assertTrue($response->hasHeader('Retry-After'));
            }
        }
    }
}