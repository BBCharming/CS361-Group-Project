<?php
// includes/anonymous_evidence_upload.php
//
// Shared file-handling logic for evidence attached without a session —
// used by both victim/report_anonymous.php (evidence at submission
// time) and victim/upload_evidence_anonymous.php (evidence added later
// via reference code). Same validation approach as
// analyst/upload_evidence.php (real MIME check, random filename, 5MB
// cap) — kept separate since analyst uploads are session-authenticated
// and always succeed-or-fail the whole request, while these never fail
// the surrounding report/lookup over a bad attachment.

function handleAnonymousEvidenceUpload(PDO $pdo, int $incidentId, array $file, string $evidenceType): array {
    $allowedEvidenceTypes = ['Screenshot', 'Document', 'Email', 'Transaction Receipt', 'Other'];
    if (!in_array($evidenceType, $allowedEvidenceTypes, true)) {
        $evidenceType = 'Other';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed'];
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'Unsupported file type'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large (max 5MB)'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $storageDir = __DIR__ . '/../victim/uploads/';
    $destination = $storageDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }

    $relativePath = 'victim/uploads/' . $safeName;

    $evidenceModel = new Evidence($pdo);
    $evidenceId = $evidenceModel->create([
        'incident_id'   => $incidentId,
        'evidence_type' => $evidenceType,
        'file_path'     => $relativePath,
        'description'   => null,
    ]);

    $analysisStatus = 'not_configured';
    if (ladinaIsConfigured()) {
        $absolutePath = realpath($destination);
        $analysis = $absolutePath ? runLadinaAnalysis($absolutePath) : null;
        if ($analysis !== null) {
            $evidenceModel->attachAnalysis($evidenceId, json_encode($analysis['mysql_ready'] ?? $analysis));
            $analysisStatus = 'completed';
        } else {
            $analysisStatus = 'failed';
        }
    }

    return ['success' => true, 'evidence_id' => $evidenceId, 'analysis_status' => $analysisStatus];
}
