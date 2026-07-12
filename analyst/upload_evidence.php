<?php
// analyst/upload_evidence.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$incidentId   = $_POST['incident_id'] ?? null;
$evidenceType = $_POST['evidence_type'] ?? null;
$description  = trim($_POST['description'] ?? '') ?: null;

$allowedEvidenceTypes = ['Screenshot', 'Document', 'Email', 'Transaction Receipt', 'Other'];

if (!$incidentId || !in_array($evidenceType, $allowedEvidenceTypes, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Valid incident_id and evidence_type are required']);
    exit;
}

if (!isset($_FILES['evidence_file'])) {
    http_response_code(422);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['evidence_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'File upload failed']);
    exit;
}

// Validate real MIME type (never trust $_FILES['type'] from the client)
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$maxSize = 5 * 1024 * 1024; // 5MB

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Unsupported file type']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['error' => 'File too large (max 5MB)']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Confirm the incident actually exists before attaching evidence to it
    $check = $pdo->prepare("SELECT id FROM incidents WHERE id = :id");
    $check->execute([':id' => $incidentId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Incident not found']);
        exit;
    }

    // Build a random filename — never trust the client-supplied name
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $storageDir = __DIR__ . '/screenshots/';
    $destination = $storageDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        exit;
    }

    // Store a relative path in the DB, not an absolute filesystem path
    $relativePath = 'analyst/screenshots/' . $safeName;

    $stmt = $pdo->prepare(
        "INSERT INTO evidence (incident_id, evidence_type, file_path, description, updated_at)
         VALUES (:incident_id, :evidence_type, :file_path, :description, NOW())"
    );
    $stmt->execute([
        ':incident_id'   => $incidentId,
        ':evidence_type' => $evidenceType,
        ':file_path'     => $relativePath,
        ':description'   => $description,
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'evidence_id' => (int) $pdo->lastInsertId()]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
