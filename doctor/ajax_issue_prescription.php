<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");
require_once("../includes/prescription_pdf.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$doctor_id = intval($_SESSION['user_id']);
$patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$medicine_name = isset($_POST['medicine_name']) ? trim($_POST['medicine_name']) : '';
$dosage = isset($_POST['dosage']) ? trim($_POST['dosage']) : '';
$medicine_times = isset($_POST['medicine_time']) ? $_POST['medicine_time'] : [];
$duration_value = isset($_POST['duration_value']) ? max(1, intval($_POST['duration_value'])) : 1;
$duration_unit = $_POST['duration_unit'] ?? 'days';

if (empty($patient_id) || empty($medicine_name) || empty($dosage) || empty($medicine_times)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$duration_days = $duration_value;
if ($duration_unit === 'weeks') {
    $duration_days = $duration_value * 7;
} elseif ($duration_unit === 'months') {
    $start = new DateTime();
    $end = (clone $start)->modify('+' . $duration_value . ' month');
    $duration_days = max(1, (int) $start->diff($end)->days);
}

// Verify authorization through appointments or an active call.
$check = $conn->prepare("SELECT p.id, p.name FROM patients p
                         LEFT JOIN appointments a ON p.id = a.patient_id AND a.doctor_id = ?
                         LEFT JOIN video_calls vc ON p.id = vc.patient_id AND vc.doctor_id = ? AND vc.status = 'active'
                         WHERE p.id = ? AND (a.id IS NOT NULL OR vc.id IS NOT NULL) LIMIT 1");
$check->bind_param("iii", $doctor_id, $doctor_id, $patient_id);
$check->execute();
$patient_data = $check->get_result()->fetch_assoc();

if (!$patient_data) {
    echo json_encode(["success" => false, "message" => "Unauthorized to prescribe for this patient"]);
    exit;
}

// Ensure checklist exists
$stmt_checklist = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
$stmt_checklist->bind_param("i", $patient_id);
$stmt_checklist->execute();
$res_checklist = $stmt_checklist->get_result();

if ($res_checklist->num_rows > 0) {
    $checklist_id = $res_checklist->fetch_assoc()['id'];
} else {
    $cs = $conn->prepare("INSERT INTO checklists (patient_id, created_by, title) VALUES (?, ?, 'Daily Medicines')");
    $cs->bind_param("ii", $patient_id, $doctor_id);
    $cs->execute();
    $checklist_id = $cs->insert_id;
}

$time_str = implode(",", $medicine_times);

$prescriptionPdf = createPrescriptionPdf(
    $_SESSION['name'] ?? 'Doctor',
    $patient_data['name'] ?? 'Patient',
    $medicine_name,
    $dosage,
    $medicine_times,
    $duration_days
);
$prescriptionDbPath = $prescriptionPdf ? $prescriptionPdf['db_path'] : null;
$prescriptionPublicPath = $prescriptionPdf ? $prescriptionPdf['public_path'] : null;
$prescriptionFilename = $prescriptionPdf ? $prescriptionPdf['filename'] : null;

$is = $conn->prepare("INSERT INTO checklist_items (checklist_id, medicine_name, dosage, times_of_day, status, prescribed_by, duration_days, prescription_file) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)");
$is->bind_param("isssiis", $checklist_id, $medicine_name, $dosage, $time_str, $doctor_id, $duration_days, $prescriptionDbPath);

if ($is->execute()) {
    addUserNotification($conn, $patient_id, "New Prescription Issued", "Dr. " . $_SESSION['name'] . " has issued a new prescription.");
    echo json_encode([
        "success" => true,
        "message" => $prescriptionPublicPath ? "Prescription issued successfully." : "Prescription issued, but the PDF file could not be generated.",
        "download_url" => $prescriptionPublicPath,
        "download_name" => $prescriptionFilename
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>

