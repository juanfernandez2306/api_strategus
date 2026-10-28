<?php

declare(strict_types=1);

namespace Tests\Unit\Users\DTOs\Token;

use App\Users\DTOs\Token\AccessTokenDto;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AccessTokenDtoTest extends TestCase
{
    public function testCanCreateFromArrayWithCorrectCasting(): void
    {
        $data = [
            'user_id'           => '15',
            'role_id'           => '3',
            'is_active'         => '1',
            'expires_at'        => '2030-01-01 12:00:00',
            'email_verified_at' => '2026-01-01 10:00:00',
        ];

        $dto = AccessTokenDto::fromArray($data);

        $this->assertSame(15, $dto->userId);
        $this->assertSame(3, $dto->roleId);
        $this->assertTrue($dto->isActive);
        $this->assertInstanceOf(DateTimeImmutable::class, $dto->expiresAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $dto->emailVerifiedAt);
    }

    public function testTokenWithoutExpirationIsNotExpired(): void
    {
        $dto = new AccessTokenDto(1, 1, true, null, null);

        $this->assertFalse($dto->isExpired());
    }

    public function testTokenWithFutureExpirationIsNotExpired(): void
    {
        $futureDate = (new DateTimeImmutable())->modify('+1 hour');
        $dto = new AccessTokenDto(1, 1, true, $futureDate, null);

        $this->assertFalse($dto->isExpired());
    }

    public function testTokenWithPastExpirationIsExpired(): void
    {
        $pastDate = (new DateTimeImmutable())->modify('-1 hour');
        $dto = new AccessTokenDto(1, 1, true, $pastDate, null);

        $this->assertTrue($dto->isExpired());
    }

    public function testIsValidUserReturnsTrueWhenActiveAndNotExpired(): void
    {
        $futureDate = (new DateTimeImmutable())->modify('+1 hour');
        $dto = new AccessTokenDto(1, 1, true, $futureDate, null);

        $this->assertTrue($dto->isValidUser());
    }

    public function testIsValidUserReturnsFalseWhenUserIsInactive(): void
    {
        $futureDate = (new DateTimeImmutable())->modify('+1 hour');
        $dto = new AccessTokenDto(1, 1, false, $futureDate, null);

        $this->assertFalse($dto->isValidUser());
    }

    public function testIsValidUserReturnsFalseWhenTokenIsExpired(): void
    {
        $pastDate = (new DateTimeImmutable())->modify('-1 hour');
        $dto = new AccessTokenDto(1, 1, true, $pastDate, null);

        $this->assertFalse($dto->isValidUser());
    }
}
