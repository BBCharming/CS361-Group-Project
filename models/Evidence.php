<?php
// models/Evidence.php

class Evidence {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO evidence (incident_id, evidence_type, file_path, description, updated_at)
             VALUES (:incident_id, :evidence_type, :file_path, :description, NOW())"
        );
        $stmt->execute([
            ':incident_id'   => $data['incident_id'],
            ':evidence_type' => $data['evidence_type'],
            ':file_path'     => $data['file_path'],
            ':description'   => $data['description'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM evidence WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIncidentId(int $incidentId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM evidence WHERE incident_id = :incident_id ORDER BY updated_at DESC"
        );
        $stmt->execute([':incident_id' => $incidentId]);
        return $stmt->fetchAll();
    }

    // Appends LADINA's analysis onto the evidence record's description,
    // since the schema doesn't have a dedicated intelligence column.
    public function attachAnalysis(int $id, string $analysisJson): bool {
        $stmt = $this->db->prepare(
            "UPDATE evidence
             SET description = CONCAT(COALESCE(description, ''), '\n\n[LADINA ANALYSIS]\n', :analysis),
                 updated_at = NOW()
             WHERE id = :id"
        );
        return $stmt->execute([':analysis' => $analysisJson, ':id' => $id]);
    }
}
