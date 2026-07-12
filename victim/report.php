<?php
// victim/report.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Incident.php';

header('Content-Type: application/json');

// Must be logged in
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

    $incidentId = $incidentModel->create([
        'user_id'        => $_SESSION['user_id'],
        'category_id'    => $categoryId,
        'title'          => $title,
        'description'    => $description,
        'suspect_phone'  => $suspectPhone,
        'suspect_email'  => $suspectEmail,
        'transaction_id' => $transactionId,
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'incident_id' => $incidentId]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
