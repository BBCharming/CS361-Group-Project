<?php
// police/evidence.php
// Verified evidence viewer — police only see incidents an analyst has
// already marked resolved/closed, with their full evidence trail.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Evidence.php';

requireRole(['police']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = getDbConnection();
    $evidenceModel = new Evidence($pdo);

    $incidentId = $_GET['incident_id'] ?? null;

    if ($incidentId) {
        $stmt = $pdo->prepare(
            "SELECT i.*, COALESCE(u.full_name, 'Anonymous report') AS reported_by,
                    u.phone_number AS reporter_phone, c.category_name
             FROM incidents i
             LEFT JOIN users u ON u.id = i.user_id
             JOIN incident_categories c ON c.id = i.category_id
             WHERE i.id = :id AND i.incident_status IN ('resolved', 'closed')"
        );
        $stmt->execute([':id' => $incidentId]);
        $incident = $stmt->fetch();

        if (!$incident) {
            http_response_code(404);
            echo json_encode(['error' => 'No verified incident found with that ID']);
            exit;
        }

        $incident['evidence'] = $evidenceModel->findByIncidentId((int) $incidentId);

        echo json_encode(['success' => true, 'incident' => $incident]);
        exit;
    }

    // No incident_id given — list all verified cases
    $stmt = $pdo->query(
        "SELECT i.id, i.title, i.incident_status, i.updated_at, c.category_name,
                (SELECT COUNT(*) FROM evidence e WHERE e.incident_id = i.id) AS evidence_count
         FROM incidents i
         JOIN incident_categories c ON c.id = i.category_id
         WHERE i.incident_status IN ('resolved', 'closed')
         ORDER BY i.updated_at DESC"
    );

    echo json_encode(['success' => true, 'incidents' => $stmt->fetchAll()]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
