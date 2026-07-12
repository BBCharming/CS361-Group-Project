<?php
// auth/register.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone_number'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if ($fullName === '' || $email === '' || $phone === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Check for existing email or phone (both are UNIQUE in the schema)
    $check = $pdo->prepare("SELECT id FROM users WHERE email = :email OR phone_number = :phone");
    $check->execute([':email' => $email, ':phone' => $phone]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email or phone number already registered']);
        exit;
    }

    // Hash the password — never store plaintext
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, phone_number, password_hash, role, created_at)
         VALUES (:full_name, :email, :phone, :hash, 'user', NOW())"
    );
    $stmt->execute([
        ':full_name' => $fullName,
        ':email'     => $email,
        ':phone'     => $phone,
        ':hash'      => $hash,
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'user_id' => (int) $pdo->lastInsertId()]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
