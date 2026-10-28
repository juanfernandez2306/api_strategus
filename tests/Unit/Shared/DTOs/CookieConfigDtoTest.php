<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\DTOs;

use App\Shared\DTOs\CookieConfigDTO;
use PHPUnit\Framework\TestCase;

class CookieConfigDtoTest extends TestCase
{
    public function testCanCreateFromArrayWithCustomValues(): void
    {
        $config = [
            'name'     => 'custom_session',
            'lifetime' => 3600,
            'domain'   => 'api.example.com',
            'secure'   => true,
            'samesite' => 'Strict',
        ];

        $dto = CookieConfigDTO::fromArray($config);

        $this->assertSame('custom_session', $dto->name);
        $this->assertSame(3600, $dto->lifetime);
        $this->assertSame('api.example.com', $dto->domain);
        $this->assertTrue($dto->secure);
        $this->assertSame('Strict', $dto->samesite);
    }

    public function testUsesDefaultValuesWhenArrayIsEmpty(): void
    {
        $dto = CookieConfigDTO::fromArray([]);

        $this->assertSame('auth_token', $dto->name);
        $this->assertSame(60 * 60 * 24 * 7, $dto->lifetime);
        $this->assertNull($dto->domain);
        $this->assertFalse($dto->secure);
        $this->assertSame('Lax', $dto->samesite);
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $dto = new CookieConfigDTO('auth_token', 100, 'localhost', false, 'Lax');
        $array = $dto->toArray();

        $this->assertArrayHasKey('expires', $array);
        $this->assertSame('/', $array['path']);
        $this->assertSame('localhost', $array['domain']);
        $this->assertFalse($array['secure']);
        $this->assertTrue($array['httponly']);
        $this->assertSame('Lax', $array['samesite']);
    }
}
