<?php
// models/Incident.php

class Incident {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int {
        $sql = "INSERT INTO incidents 
                (user_id, category_id, title, description, suspect_phone, suspect_email, transaction_id, reference_code, incident_status, created_at, updated_at)
                VALUES (:user_id, :category_id, :title, :description, :suspect_phone, :suspect_email, :transaction_id, :reference_code, 'pending', NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id'        => $data['user_id'] ?? null, // NULL = anonymous report
            ':category_id'    => $data['category_id'],
            ':title'          => $data['title'],
            ':description'    => $data['description'],
            ':suspect_phone'  => $data['suspect_phone'] ?? null,
            ':suspect_email'  => $data['suspect_email'] ?? null,
            ':transaction_id' => $data['transaction_id'] ?? null,
            ':reference_code' => $data['reference_code'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Looks up an incident by its anonymous-tracking reference code.
    // Logged-in reports never have one, so this can only ever match an
    // anonymous submission.
    public function findByReferenceCode(string $code): ?array {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.reference_code, i.title, i.incident_status, i.created_at, i.updated_at,
                    c.category_name,
                    (SELECT COUNT(*) FROM evidence e WHERE e.incident_id = i.id) AS evidence_count
             FROM incidents i
             JOIN incident_categories c ON c.id = i.category_id
             WHERE i.reference_code = :code"
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Generates a short, unambiguous (no 0/O/1/I) tracking code and
    // guarantees it's unique before handing it back.
    public static function generateReferenceCode(PDO $db): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'ZR-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $stmt = $db->prepare("SELECT 1 FROM incidents WHERE reference_code = :code");
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetch());

        return $code;
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
