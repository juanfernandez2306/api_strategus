<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

readonly class CookieConfigDTO
{
    public function __construct(
        public string $name,
        public int $lifetime,
        public ?string $domain,
        public bool $secure,
        public string $samesite
    ) {
    }


    public static function fromArray(array $config): self
    {
        return new self(
            name: $config['name'] ?? 'auth_token',
            lifetime: $config['lifetime'] ?? (60 * 60 * 24 * 7),
            domain: $config['domain'] ?? null,
            secure: filter_var($config['secure'] ?? false, FILTER_VALIDATE_BOOLEAN),
            samesite: $config['samesite'] ?? 'Lax'
        );
    }


    public function toArray(): array
    {
        return [
            'expires' => time() + $this->lifetime,
            'path' => '/',
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => $this->samesite,
        ];
    }
}
