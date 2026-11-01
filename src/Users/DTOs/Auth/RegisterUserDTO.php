<?php

declare(strict_types=1);

namespace App\Users\DTOs;

class RegisterUserDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $password,
        public readonly string $birthDate,
        public readonly string $dniType,
        public readonly string $dniNumber,
        public readonly string $phoneNumber
    ) {
    }


    public static function fromValidated(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'],
            password: $data['password'],
            birthDate: $data['birth_date'],
            dniType: $data['dni_type'],
            dniNumber: $data['dni_number'],
            phoneNumber: $data['phone_number']
        );
    }

    public function getFullName(): string
    {
        return ucfirst($this->firstName) . ' ' . ucfirst($this->lastName);
    }
}
