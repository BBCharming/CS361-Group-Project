<?php
// victim/upload_evidence_anonymous.php
//
// No session — possession of the reference code is the only "auth"
// here, same model as a shipping tracking number. Only matches
// anonymous incidents, since logged-in reports never get a
// reference_code in the first place.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ladina_runner.php';
require_once __DIR__ . '/../includes/evidence_upload.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../models/Evidence.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$referenceCode = trim($_POST['reference_code'] ?? '');
$evidenceType  = $_POST['evidence_type'] ?? 'Other';

if ($referenceCode === '') {
    http_response_code(422);
    echo json_encode(['error' => 'reference_code is required']);
    exit;
}

if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(422);
    echo json_encode(['error' => 'No file uploaded']);
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

    $result = handleEvidenceUpload($pdo, (int) $incident['id'], $_FILES['evidence_file'], $evidenceType);

    if (!$result['success']) {
        http_response_code(422);
        echo json_encode($result);
        exit;
    }

    http_response_code(201);
    echo json_encode($result);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
