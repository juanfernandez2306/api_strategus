<?php

declare(strict_types=1);

namespace App\Users\Repositories\Auth;

use PDO;

class PdoTokenRepository implements TokenRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(int $userId, string $tokenName, string $tokenPlain): bool
    {
        $sql = "INSERT INTO personal_access_tokens (
                user_id, name, 
                token, expires_at
            ) VALUES (
                :user_id, :name, 
                :token, :expires_at
            )";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':user_id' => $userId,
            ':name'     => $tokenName,
            ':token'      => hash('sha256', $tokenPlain),
            ':expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ]);
    }

    public function delete(string $hashedToken): bool
    {
        $sql = "DELETE FROM personal_access_tokens WHERE token = :token";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['token' => $hashedToken]);
    }

    public function deleteAllByUserId(int $userId): bool
    {
        $sql = "DELETE FROM personal_access_tokens 
                WHERE user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['user_id' => $userId]);
    }

    public function getAccessToken(string $tokenHashed): array
    {
        $sql = "SELECT 
                    ut.user_id, 
                    ut.expires_at, 
                    u.role_id, 
                    u.is_active, 
                    u.email_verified_at
                FROM personal_access_tokens ut
                INNER JOIN users u ON ut.user_id = u.id
                WHERE ut.token = :token LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':token' => $tokenHashed]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }
}
