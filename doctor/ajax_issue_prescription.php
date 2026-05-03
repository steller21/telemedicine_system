<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");

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

if (empty($patient_id) || empty($medicine_name) || empty($dosage) || empty($medicine_times)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

// Verify authorization
$check = $conn->prepare("SELECT u.id FROM users u 
                         LEFT JOIN patient_monitors pm ON u.id = pm.patient_id AND pm.monitor_id = ?
                         LEFT JOIN appointments a ON u.id = a.patient_id AND a.doctor_id = ?
                         LEFT JOIN video_calls vc ON u.id = vc.patient_id AND vc.doctor_id = ? AND vc.status = 'active'
                         WHERE u.id = ? AND (pm.id IS NOT NULL OR a.id IS NOT NULL OR vc.id IS NOT NULL) LIMIT 1");
$check->bind_param("iiii", $doctor_id, $doctor_id, $doctor_id, $patient_id);
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

$is = $conn->prepare("INSERT INTO checklist_items (checklist_id, medicine_name, dosage, times_of_day, status, prescribed_by) VALUES (?, ?, ?, ?, 'pending', ?)");
$is->bind_param("isssi", $checklist_id, $medicine_name, $dosage, $time_str, $doctor_id); // Changed due_time to times_of_day

if ($is->execute()) {
    addUserNotification($conn, $patient_id, "New Prescription Issued", "Dr. " . $_SESSION['name'] . " has issued a new prescription.");
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>
