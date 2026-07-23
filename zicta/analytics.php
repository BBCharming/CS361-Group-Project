<?php
// zicta/analytics.php
// Feeds assets/js/charts.js — scam-type and network-pattern breakdowns.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

requireRole(['zicta']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = getDbConnection();

    $byCategory = $pdo->query(
        "SELECT c.category_name, COUNT(i.id) AS total
         FROM incident_categories c
         LEFT JOIN incidents i ON i.category_id = c.id
         GROUP BY c.id, c.category_name
         ORDER BY total DESC"
    )->fetchAll();

    $byStatus = $pdo->query(
        "SELECT incident_status, COUNT(*) AS total FROM incidents GROUP BY incident_status"
    )->fetchAll();

    $byMonth = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
         FROM incidents
         GROUP BY month
         ORDER BY month ASC"
    )->fetchAll();

    echo json_encode([
        'success'     => true,
        'by_category' => $byCategory,
        'by_status'   => $byStatus,
        'by_month'    => $byMonth,
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
