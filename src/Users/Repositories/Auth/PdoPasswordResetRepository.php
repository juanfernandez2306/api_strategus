<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

use PDO;

class PdoPasswordResetRepository implements PasswordResetRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(int $userId, string $tokenPlain, string $expiresAt): bool
    {

        $this->deleteByUserId($userId);

        $sql = "INSERT INTO 
                password_resets (user_id, token, expires_at) 
                VALUES (:user_id, :token, :expires_at)";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':user_id'    => $userId,
            ':token'      => hash('sha256', $tokenPlain),
            ':expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);
    }

    public function findValidToken(string $hashedToken): ?array
    {
        $sql = "SELECT user_id, token, expires_at 
                FROM password_resets WHERE token = :token 
                AND expires_at > NOW() LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([':token' => $hashedToken]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }

    public function deleteByUserId(int $userId): bool
    {
        $sql = "DELETE FROM password_resets 
                WHERE user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':user_id' => $userId]);
    }

    public function deleteByToken(string $hashedToken): bool
    {
        $sql = "DELETE FROM password_resets WHERE token = :token";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':token' => $hashedToken]);
    }
}
