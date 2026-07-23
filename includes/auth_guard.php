<?php
// includes/auth_guard.php
//
// Shared authentication / role-based access control gate.
// Every protected endpoint (victim/*, analyst/*, police/*, zicta/*) requires
// this file first, then calls requireRole() with the roles allowed to
// touch that endpoint. Centralizing this here is what actually "joins"
// the auth layer to the four role dashboards instead of each folder
// re-implementing its own session checks.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure a user is logged in. Returns the session array on success,
 * otherwise sends a 401 JSON response and stops execution.
 */
function requireLogin(): array {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    return $_SESSION;
}

/**
 * Ensure the logged-in user's role is in $allowedRoles.
 * e.g. requireRole(['analyst']); requireRole(['police', 'zicta']);
 */
function requireRole(array $allowedRoles): array {
    $session = requireLogin();

    if (!in_array($session['role'], $allowedRoles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden — insufficient role']);
        exit;
    }

    return $session;
}
