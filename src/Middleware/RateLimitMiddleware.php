<?php

namespace App\Middleware;

use App\Users\Repositories\Auth\RateLimitCacheRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\SimpleCache\CacheInterface;
use Slim\Psr7\Response as SlimResponse;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimitCacheRepository $repository;
    private int $limit;
    private int $windowSeconds;

    public function __construct(CacheInterface $cache, int $limit = 60, int $windowSeconds = 60)
    {
        $this->repository = new RateLimitCacheRepository($cache);
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        
        $clientIdentifier = $request->getHeaderLine('Authorization')
            ? md5($request->getHeaderLine('Authorization'))
            : ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1');

        $endpoint = $request->getUri()->getPath();

        
        $currentTime = time();
        $windowBlock = (int) floor($currentTime / $this->windowSeconds);
        $rateKey = 'ratelimit_' . md5($clientIdentifier . ':' . $endpoint . ':' . $windowBlock);

        $resetTime = ($windowBlock + 1) * $this->windowSeconds;
        $ttlSeconds = max(1, $resetTime - $currentTime);

        
        $currentHits = $this->repository->incrementHit($rateKey, $ttlSeconds);
        $remaining = max(0, $this->limit - $currentHits);

        
        if ($currentHits > $this->limit) {
            $response = new SlimResponse(429);
            $response->getBody()->write(json_encode([
                'status'  => 429,
                'error'   => 'Too Many Requests',
                'message' => 'Límite de peticiones alcanzado. Por favor espera antes de intentar nuevamente.'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-RateLimit-Limit', (string) $this->limit)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string) $resetTime)
                ->withHeader('Retry-After', (string) $ttlSeconds);
        }

        
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }
}
