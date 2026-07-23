<?php
// victim/dashboard.php
// JSON case-tracker endpoint — polled every 30s by assets/js/ajax-poll.js
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

$session = requireRole(['victim']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT i.id, i.title, i.incident_status, i.suspect_phone, i.suspect_email,
                i.transaction_id, i.created_at, i.updated_at,
                c.category_name,
                (SELECT COUNT(*) FROM evidence e WHERE e.incident_id = i.id) AS evidence_count
         FROM incidents i
         JOIN incident_categories c ON c.id = i.category_id
         WHERE i.user_id = :user_id
         ORDER BY i.updated_at DESC"
    );
    $stmt->execute([':user_id' => $session['user_id']]);
    $incidents = $stmt->fetchAll();

    echo json_encode(['success' => true, 'incidents' => $incidents]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
