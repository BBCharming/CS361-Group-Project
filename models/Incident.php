<?php
// models/Incident.php

class Incident {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int {
        $sql = "INSERT INTO incidents 
                (user_id, category_id, title, description, suspect_phone, suspect_email, transaction_id, incident_status, created_at, updated_at)
                VALUES (:user_id, :category_id, :title, :description, :suspect_phone, :suspect_email, :transaction_id, 'pending', NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id'        => $data['user_id'],
            ':category_id'    => $data['category_id'],
            ':title'          => $data['title'],
            ':description'    => $data['description'],
            ':suspect_phone'  => $data['suspect_phone'] ?? null,
            ':suspect_email'  => $data['suspect_email'] ?? null,
            ':transaction_id' => $data['transaction_id'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM incidents WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Useful for LADINA cross-matching by phone/transaction
    public function findBySuspectIdentifier(string $phone = null, string $email = null): array {
        $sql = "SELECT * FROM incidents WHERE 1=0";
        $params = [];

        if ($phone) {
            $sql .= " OR suspect_phone = :phone";
            $params[':phone'] = $phone;
        }
        if ($email) {
            $sql .= " OR suspect_email = :email";
            $params[':email'] = $email;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
