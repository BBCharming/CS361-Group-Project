<?php
// analyst/dashboard.php
// Investigation panel: lists open incidents + their evidence, and lets an
// analyst update a case's status or trigger a LADINA re-analysis on a
// piece of evidence — either because the automatic run at upload time
// failed/wasn't configured, or because the case needs a hands-on second
// pass.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/ladina_runner.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Evidence.php';

$session = requireRole(['analyst']);

header('Content-Type: application/json');
header('Cache-Control: no-store');

$pdo = getDbConnection();

// ---- POST: update status or (re)run LADINA on a piece of evidence ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_status') {
            $incidentId = $_POST['incident_id'] ?? null;
            $newStatus  = $_POST['new_status'] ?? null;
            $notes      = trim($_POST['notes'] ?? '') ?: null;
            $allowed    = ['pending', 'investigating', 'resolved', 'closed'];

            if (!$incidentId || !in_array($newStatus, $allowed, true)) {
                http_response_code(422);
                echo json_encode(['error' => 'Valid incident_id and new_status are required']);
                exit;
            }

            $current = $pdo->prepare("SELECT incident_status FROM incidents WHERE id = :id");
            $current->execute([':id' => $incidentId]);
            $row = $current->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Incident not found']);
                exit;
            }

            $pdo->beginTransaction();

            $upd = $pdo->prepare("UPDATE incidents SET incident_status = :status, updated_at = NOW() WHERE id = :id");
            $upd->execute([':status' => $newStatus, ':id' => $incidentId]);

            $log = $pdo->prepare(
                "INSERT INTO incident_updates (incident_id, admin_id, previous_status, new_status, notes, updated_at)
                 VALUES (:incident_id, :admin_id, :previous_status, :new_status, :notes, NOW())"
            );
            $log->execute([
                ':incident_id'     => $incidentId,
                ':admin_id'        => $session['user_id'],
                ':previous_status' => $row['incident_status'],
                ':new_status'      => $newStatus,
                ':notes'           => $notes,
            ]);

            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'analyze_evidence') {
            $evidenceId = (int) ($_POST['evidence_id'] ?? 0);
            $evidenceModel = new Evidence($pdo);
            $evidence = $evidenceModel->findById($evidenceId);

            if (!$evidence) {
                http_response_code(404);
                echo json_encode(['error' => 'Evidence not found']);
                exit;
            }

            if (!ladinaIsConfigured()) {
                http_response_code(502);
                echo json_encode(['error' => 'GEMINI_API_KEY is not configured on this server — LADINA cannot run']);
                exit;
            }

            $filePath = realpath(__DIR__ . '/../' . $evidence['file_path']);
            if (!$filePath || !is_file($filePath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Evidence file missing from disk']);
                exit;
            }

            $result = runLadinaAnalysis($filePath);

            if ($result === null) {
                http_response_code(502);
                echo json_encode(['error' => 'LADINA analysis failed — check the server log for the Python error']);
                exit;
            }

            $evidenceModel->attachAnalysis($evidenceId, json_encode($result['mysql_ready'] ?? $result));

            echo json_encode(['success' => true, 'analysis' => $result]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    exit;
}

// ---- GET: list open incidents with their evidence + LADINA status ----
try {
    $counts = $pdo->query(
        "SELECT
            SUM(incident_status = 'pending') AS pending,
            SUM(incident_status = 'investigating') AS investigating,
            SUM(incident_status IN ('resolved','closed')) AS closed
         FROM incidents"
    )->fetch();

    $stmt = $pdo->query(
        "SELECT i.id, i.title, i.description, i.incident_status, i.suspect_phone,
                i.suspect_email, i.transaction_id, i.created_at, i.updated_at,
                COALESCE(u.full_name, 'Anonymous report') AS reported_by, c.category_name
         FROM incidents i
         LEFT JOIN users u ON u.id = i.user_id
         JOIN incident_categories c ON c.id = i.category_id
         WHERE i.incident_status IN ('pending', 'investigating')
         ORDER BY i.created_at ASC"
    );
    $incidents = $stmt->fetchAll();

    $evidenceModel = new Evidence($pdo);
    foreach ($incidents as &$incident) {
        $items = $evidenceModel->findByIncidentId((int) $incident['id']);

        foreach ($items as &$item) {
            // LADINA's output gets appended after this marker (see
            // models/Evidence.php::attachAnalysis) — its presence is how
            // the dashboard tells "already analysed" from "needs a run".
            $marker = '[LADINA ANALYSIS]';
            $pos = strpos((string) $item['description'], $marker);
            $item['has_analysis'] = $pos !== false;

            if ($item['has_analysis']) {
                $jsonPart = trim(substr($item['description'], $pos + strlen($marker)));
                $decoded = json_decode($jsonPart, true);
                $item['analysis'] = $decoded ?: null;
            } else {
                $item['analysis'] = null;
            }
        }
        unset($item);

        $incident['evidence'] = $items;
    }
    unset($incident);

    echo json_encode([
        'success'           => true,
        'ladina_configured' => ladinaIsConfigured(),
        'stats'             => [
            'pending'       => (int) ($counts['pending'] ?? 0),
            'investigating' => (int) ($counts['investigating'] ?? 0),
            'closed'        => (int) ($counts['closed'] ?? 0),
        ],
        'incidents' => $incidents,
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
