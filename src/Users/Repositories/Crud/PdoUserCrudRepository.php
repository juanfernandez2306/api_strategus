<?php

declare(strict_types=1);

namespace App\Users\Repositories\Crud;

use PDO;

class PdoUserCrudRepository implements UserCrudRepositoryInterface
{
    
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO 
                users (first_name, last_name, email, password) 
                VALUES (:first_name, :last_name, :email, :password)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'first_name' => mb_strtolower($data['first_name']),
            'last_name'  => mb_strtolower($data['last_name']),
            'email'      => mb_strtolower($data['email']),
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT)
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getAll(int $limit = 30, int $offset = 0): array
    {
        $sql = "SELECT 
                id, first_name, last_name, email, 
                is_active, email_verified_at 
                FROM users LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE users SET 
                first_name = :first_name, 
                last_name = :last_name,
                is_active = :is_active 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id'         => $id,
            'first_name' => mb_strtolower($data['first_name']),
            'last_name'  => mb_strtolower($data['last_name']),
            'is_active'  => (int) $data['is_active']
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM users WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }
}