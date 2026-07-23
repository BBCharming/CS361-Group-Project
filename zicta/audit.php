<?php
// zicta/audit.php
// System activity audit trail: every status change and analyst comment,
// so ZICTA can verify analyst conduct across the platform.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

requireRole(['zicta']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = getDbConnection();

    $updates = $pdo->query(
        "SELECT iu.id, iu.incident_id, u.full_name AS admin_name, iu.previous_status,
                iu.new_status, iu.notes, iu.updated_at
         FROM incident_updates iu
         JOIN users u ON u.id = iu.admin_id
         ORDER BY iu.updated_at DESC
         LIMIT 100"
    )->fetchAll();

    $comments = $pdo->query(
        "SELECT c.id, c.incident_id, u.full_name AS admin_name, c.comment, c.created_at
         FROM comments c
         JOIN users u ON u.id = c.admin_id
         ORDER BY c.created_at DESC
         LIMIT 100"
    )->fetchAll();

    echo json_encode([
        'success'         => true,
        'status_updates'  => $updates,
        'analyst_comments' => $comments,
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
