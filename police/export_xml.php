<?php
// police/export_xml.php
// Exports a single verified incident + its evidence trail as
// evidence_report.xml for law-enforcement interoperability.
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Evidence.php';

requireRole(['police']);

$incidentId = $_GET['incident_id'] ?? null;

if (!$incidentId) {
    http_response_code(400);
    header('Content-Type: application/json');
header('Cache-Control: no-store');
    echo json_encode(['error' => 'incident_id is required']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "SELECT i.*, COALESCE(u.full_name, 'Anonymous report') AS reported_by,
                u.phone_number AS reporter_phone, u.email AS reporter_email, c.category_name
         FROM incidents i
         LEFT JOIN users u ON u.id = i.user_id
         JOIN incident_categories c ON c.id = i.category_id
         WHERE i.id = :id AND i.incident_status IN ('resolved', 'closed')"
    );
    $stmt->execute([':id' => $incidentId]);
    $incident = $stmt->fetch();

    if (!$incident) {
        http_response_code(404);
        header('Content-Type: application/json');
header('Cache-Control: no-store');
        echo json_encode(['error' => 'No verified incident found with that ID']);
        exit;
    }

    $evidenceModel = new Evidence($pdo);
    $evidenceRows = $evidenceModel->findByIncidentId((int) $incidentId);

    $xml = new SimpleXMLElement('<evidence_report/>');
    $xml->addAttribute('generated_at', date('c'));
    $xml->addAttribute('source', 'Zatcher — Operation Spectre');

    $case = $xml->addChild('case');
    $case->addAttribute('id', (string) $incident['id']);
    $case->addChild('title', htmlspecialchars($incident['title']));
    $case->addChild('category', htmlspecialchars($incident['category_name']));
    $case->addChild('status', htmlspecialchars($incident['incident_status']));
    $case->addChild('description', htmlspecialchars($incident['description']));
    $case->addChild('suspect_phone', htmlspecialchars((string) $incident['suspect_phone']));
    $case->addChild('suspect_email', htmlspecialchars((string) $incident['suspect_email']));
    $case->addChild('transaction_id', htmlspecialchars((string) $incident['transaction_id']));
    $case->addChild('reported_at', htmlspecialchars($incident['created_at']));
    $case->addChild('last_updated', htmlspecialchars($incident['updated_at']));

    $reporter = $case->addChild('reporter');
    $reporter->addChild('full_name', htmlspecialchars($incident['reported_by']));
    $reporter->addChild('phone_number', htmlspecialchars($incident['reporter_phone']));
    $reporter->addChild('email', htmlspecialchars($incident['reporter_email']));

    $evidenceNode = $case->addChild('evidence_items');
    foreach ($evidenceRows as $item) {
        $node = $evidenceNode->addChild('item');
        $node->addAttribute('id', (string) $item['id']);
        $node->addChild('type', htmlspecialchars($item['evidence_type']));
        $node->addChild('file_path', htmlspecialchars($item['file_path']));
        $node->addChild('description', htmlspecialchars((string) $item['description']));
        $node->addChild('updated_at', htmlspecialchars($item['updated_at']));
    }

    header('Content-Type: application/xml');
header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="evidence_report_' . $incidentId . '.xml"');
    echo $xml->asXML();

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
header('Cache-Control: no-store');
    echo json_encode(['error' => 'Server error']);
}
