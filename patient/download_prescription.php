<?php
session_start();

require_once("../config/db.php");
require_once("../includes/prescription_pdf.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    http_response_code(403);
    exit("Unauthorized");
}

$patientId = (int) $_SESSION['user_id'];
$itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;

if ($itemId <= 0) {
    http_response_code(400);
    exit("Invalid prescription request.");
}

$stmt = $conn->prepare(
    "SELECT ci.id, ci.medicine_name, ci.dosage, ci.times_of_day, ci.duration_days, ci.prescription_file, ci.prescribed_by,
            p.name AS patient_name, d.name AS doctor_name
     FROM checklist_items ci
     INNER JOIN checklists c ON ci.checklist_id = c.id
     INNER JOIN patients p ON c.patient_id = p.id
     LEFT JOIN doctors d ON ci.prescribed_by = d.id
     WHERE ci.id = ? AND c.patient_id = ?
     LIMIT 1"
);
$stmt->bind_param("ii", $itemId, $patientId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    http_response_code(404);
    exit("Prescription not found.");
}

function resolvePrescriptionDiskPath($relativePath) {
    $trimmed = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relativePath), DIRECTORY_SEPARATOR);
    $rootPath = dirname(__DIR__);
    $fullPath = realpath($rootPath . DIRECTORY_SEPARATOR . $trimmed);

    if ($fullPath === false) {
        return false;
    }

    $normalizedRoot = realpath($rootPath);
    if ($normalizedRoot === false || strpos($fullPath, $normalizedRoot) !== 0) {
        return false;
    }

    return $fullPath;
}

function streamPrescriptionFile($diskPath, $downloadName) {
    $extension = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($diskPath));
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($diskPath);
    exit;
}

$storedPath = $item['prescription_file'] ?? '';
if (empty($item['prescribed_by'])) {
    if (!empty($storedPath)) {
        $diskPath = resolvePrescriptionDiskPath($storedPath);
        if ($diskPath && is_file($diskPath)) {
            streamPrescriptionFile($diskPath, basename($diskPath));
        }
    }

    http_response_code(404);
    exit("No downloadable prescription file is available for this medicine.");
}

$generatedPdf = createPrescriptionPdf(
    $item['doctor_name'] ?: 'Doctor',
    $item['patient_name'] ?: 'Patient',
    $item['medicine_name'] ?: 'Medicine',
    $item['dosage'] ?: 'As directed',
    array_filter(array_map('trim', explode(',', (string) ($item['times_of_day'] ?? '')))),
    max(1, (int) ($item['duration_days'] ?? 1))
);

if (!$generatedPdf) {
    http_response_code(500);
    exit("Unable to generate prescription PDF.");
}

$update = $conn->prepare("UPDATE checklist_items SET prescription_file = ? WHERE id = ? LIMIT 1");
$update->bind_param("si", $generatedPdf['db_path'], $itemId);
$update->execute();

$generatedDiskPath = resolvePrescriptionDiskPath($generatedPdf['db_path']);
if (!$generatedDiskPath || !is_file($generatedDiskPath)) {
    http_response_code(500);
    exit("Generated prescription file was not found.");
}

streamPrescriptionFile($generatedDiskPath, $generatedPdf['filename']);
