<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

use PDO;

class PdoUserRepository implements InterfaceUserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function existsByEmail(string $email): bool
    {
        $sql = "SELECT 1 FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);


        return (bool) $stmt->fetchColumn();
    }
}
