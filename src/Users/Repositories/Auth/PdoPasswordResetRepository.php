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

        return (bool) $stmt->execute([
            ':user_id'    => $userId,
            ':token'      => hash('sha256', $tokenPlain),
            ':expires_at' => $expiresAt
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function findByToken(string $tokenPlain): array
    {
        $sql = "SELECT 
                    pr.id, 
                    pr.user_id, 
                    pr.expires_at,
                    u.email, 
                    u.email_verified_at,
                    u.first_name,
                    u.last_name
                FROM password_resets pr
                INNER JOIN users u ON u.id = pr.user_id
                WHERE pr.token = :token
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':token' => hash('sha256', $tokenPlain)
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : [];
    }

    public function deleteByUserId(int $userId): bool
    {
        $sql = "DELETE FROM password_resets 
                WHERE user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':user_id' => $userId]);
    }

    public function deleteByToken(string $tokenPlain): bool
    {
        $sql = "DELETE FROM password_resets WHERE token = :token";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([':token' => hash('sha256', $tokenPlain)]);
    }
}
