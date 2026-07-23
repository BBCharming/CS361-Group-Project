<?php
// zicta/dashboard.php
// Regulator overview: platform-wide totals plus the roster of analysts
// awaiting or holding authorization.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';

requireRole(['zicta']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = getDbConnection();
    $userModel = new User($pdo);

    $totals = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM incidents) AS total_incidents,
            (SELECT COUNT(*) FROM incidents WHERE incident_status = 'pending') AS pending_incidents,
            (SELECT COUNT(*) FROM incidents WHERE incident_status = 'investigating') AS investigating_incidents,
            (SELECT COUNT(*) FROM incidents WHERE incident_status IN ('resolved','closed')) AS closed_incidents,
            (SELECT COUNT(*) FROM users) AS total_users"
    )->fetch();

    echo json_encode([
        'success'  => true,
        'totals'   => $totals,
        'analysts' => $userModel->findByRole('analyst'),
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
