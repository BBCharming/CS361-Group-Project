<?php
// victim/report_anonymous.php
//
// No session, no account required. Anyone can file a report here; in
// return they get a reference_code that's the ONLY way to check status
// later (see victim/check_status.php) — there's no login to fall back
// on, so losing the code means losing the ability to track the case.
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

$categoryId    = $_POST['category_id'] ?? null;
$title         = trim($_POST['title'] ?? '');
$description   = trim($_POST['description'] ?? '');
$suspectPhone  = trim($_POST['suspect_phone'] ?? '') ?: null;
$suspectEmail  = trim($_POST['suspect_email'] ?? '') ?: null;
$transactionId = trim($_POST['transaction_id'] ?? '') ?: null;

if (!$categoryId || $title === '' || $description === '') {
    http_response_code(422);
    echo json_encode(['error' => 'category_id, title, and description are required']);
    exit;
}

if ($suspectEmail !== null && !filter_var($suspectEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid suspect email format']);
    exit;
}

try {
    $pdo = getDbConnection();
    $incidentModel = new Incident($pdo);

    $referenceCode = Incident::generateReferenceCode($pdo);

    $incidentId = $incidentModel->create([
        'user_id'        => null, // anonymous
        'category_id'    => $categoryId,
        'title'          => $title,
        'description'    => $description,
        'suspect_phone'  => $suspectPhone,
        'suspect_email'  => $suspectEmail,
        'transaction_id' => $transactionId,
        'reference_code' => $referenceCode,
    ]);

    // Evidence is optional at submission time — a reporter can always
    // come back later with the reference code via upload_evidence_anonymous.php.
    $evidenceResult = null;

    if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $evidenceResult = handleEvidenceUpload($pdo, $incidentId, $_FILES['evidence_file'], $_POST['evidence_type'] ?? 'Other');
    }

    http_response_code(201);
    echo json_encode([
        'success'        => true,
        'incident_id'    => $incidentId,
        'reference_code' => $referenceCode,
        'evidence'       => $evidenceResult, // null if none was attached, else {success|error, ...}
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
