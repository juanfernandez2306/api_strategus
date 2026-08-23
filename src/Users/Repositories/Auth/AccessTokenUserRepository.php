<?php

namespace App\Users\Repositories\Auth;

use PDO;

class AccessTokenUserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $tokenHashed]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }
}
