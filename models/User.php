<?php
// models/User.php

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, email, phone_number, role, created_at FROM users WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Used by ZICTA to authorize/list analysts and other back-office roles
    public function findByRole(string $role): array {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, email, phone_number, role, created_at FROM users WHERE role = :role ORDER BY full_name"
        );
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    }

    public function updateRole(int $id, string $role): bool {
        $stmt = $this->db->prepare("UPDATE users SET role = :role WHERE id = :id");
        return $stmt->execute([':role' => $role, ':id' => $id]);
    }
}
