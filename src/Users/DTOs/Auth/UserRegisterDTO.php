<?php

declare(strict_types=1);

namespace App\Users\DTOs\Auth;

use DateTimeImmutable;

class UserRegisterDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $password,
        public readonly DateTimeImmutable $birthDate,
        public readonly string $dniType,
        public readonly string $dniNumber,
        public readonly string $phoneNumber
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: mb_strtolower(trim($data['first_name'])),
            lastName: mb_strtolower(trim($data['last_name'])),
            email: mb_strtolower(trim($data['email'])),
            password: $data['password'],
            birthDate: new DateTimeImmutable($data['birth_date']),
            dniType: mb_strtolower(trim($data['dni_type'])),
            dniNumber: trim($data['dni_number']),
            phoneNumber: trim($data['phone_number'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name'   => $this->firstName,
            'last_name'    => $this->lastName,
            'email'        => $this->email,
            'password'     => $this->password,
            'birth_date'   => $this->birthDate,
            'dni_type'     => $this->dniType,
            'dni_number'   => $this->dniNumber,
            'phone_number' => $this->phoneNumber,
        ];
    }

    public function getFullName(): string
    {
        $firstName = mb_convert_case(trim($this->firstName), MB_CASE_TITLE, 'UTF-8');
        $lastName  = mb_convert_case(trim($this->lastName), MB_CASE_TITLE, 'UTF-8');

        return "{$firstName} {$lastName}";
    }
}
