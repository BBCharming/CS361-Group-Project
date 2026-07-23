<?php
// police/dashboard.php
// Summary view for law enforcement: counts of verified cases awaiting export.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

requireRole(['police']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query(
        "SELECT incident_status, COUNT(*) AS total
         FROM incidents
         GROUP BY incident_status"
    );
    $statusCounts = $stmt->fetchAll();

    $recent = $pdo->query(
        "SELECT i.id, i.title, i.incident_status, i.updated_at, c.category_name
         FROM incidents i
         JOIN incident_categories c ON c.id = i.category_id
         WHERE i.incident_status IN ('resolved', 'closed')
         ORDER BY i.updated_at DESC
         LIMIT 20"
    )->fetchAll();

    echo json_encode([
        'success'       => true,
        'status_counts' => $statusCounts,
        'recent_cases'  => $recent,
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
