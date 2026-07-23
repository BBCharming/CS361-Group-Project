<?php
// victim/check_status.php
//
// No session. Deliberately returns only status-tracker-safe fields
// (title, category, status, timestamps, evidence count) — not suspect
// details, description, or anything an outsider who found/guessed a
// code shouldn't see beyond "here's where this case stands."
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$referenceCode = trim($_GET['reference_code'] ?? '');

if ($referenceCode === '') {
    http_response_code(422);
    echo json_encode(['error' => 'reference_code is required']);
    exit;
}

try {
    $pdo = getDbConnection();
    $incidentModel = new Incident($pdo);

    $incident = $incidentModel->findByReferenceCode($referenceCode);

    if (!$incident) {
        http_response_code(404);
        echo json_encode(['error' => 'No report found for that reference code']);
        exit;
    }

    echo json_encode(['success' => true, 'incident' => $incident]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
