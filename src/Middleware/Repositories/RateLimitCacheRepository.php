<?php

namespace App\Middleware\Repositories;

use Psr\SimpleCache\CacheInterface;

class RateLimitCacheRepository
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function incrementHit(string $key, int $ttlSeconds): int
    {
        $hits = (int) $this->cache->get($key, 0);
        $hits++;
        $this->cache->set($key, $hits, $ttlSeconds);

        return $hits;
    }
}