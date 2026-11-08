<?php

declare(strict_types=1);

namespace App\Users\Validators\Auth;

use App\Shared\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid;

class LogoutValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        if (!empty($data['token']) && !Uuid::isValid($data['token'])) {
            throw new ValidationException([
                'token' => ['El formato del token es inválido.']
            ]);
        }

        return $data;
    }
}
