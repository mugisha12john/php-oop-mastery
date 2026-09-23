<?php

require_once __DIR__ . "/../connection.php";

class UserRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function getAllAssoc(): array
    {
        $statement = $this->connection->prepare("SELECT * FROM users ORDER BY user_id");
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllArray(): array
    {
        $statement = $this->connection->prepare("SELECT * FROM users ORDER BY user_id");
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_NUM);
    }

    public function getAllObject(): array
    {
        $statement = $this->connection->prepare("SELECT * FROM users ORDER BY user_id");
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function findByIdAssoc(int $userId): array|false
    {
        return $this->findById($userId, PDO::FETCH_ASSOC);
    }

    public function findByIdArray(int $userId): array|false
    {
        return $this->findById($userId, PDO::FETCH_NUM);
    }

    public function findByIdObject(int $userId): object|false
    {
        return $this->findById($userId, PDO::FETCH_OBJ);
    }

    public function insert(int $roleId, string $fullName, string $email, string $phoneNumber): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO users (role_id, full_name, email, phone_number, password_hash)
             VALUES (:role_id, :full_name, :email, :phone_number, :password_hash)"
        );
        $statement->execute([
            "role_id" => $roleId,
            "full_name" => $fullName,
            "email" => $email,
            "phone_number" => $phoneNumber,
            "password_hash" => password_hash("password123", PASSWORD_DEFAULT),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $userId, string $fullName, string $email, string $phoneNumber): int
    {
        $statement = $this->connection->prepare(
            "UPDATE users
             SET full_name = :full_name, email = :email, phone_number = :phone_number
             WHERE user_id = :user_id"
        );
        $statement->execute([
            "full_name" => $fullName,
            "email" => $email,
            "phone_number" => $phoneNumber,
            "user_id" => $userId,
        ]);

        return $statement->rowCount();
    }

    private function findById(int $userId, int $fetchMode): array|object|false
    {
        $statement = $this->connection->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $statement->execute(["user_id" => $userId]);

        return $statement->fetch($fetchMode);
    }
}