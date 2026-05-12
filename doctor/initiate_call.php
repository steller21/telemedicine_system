<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

if ($appointment_id > 0) {
    $appt = $conn->prepare("SELECT patient_id FROM appointments WHERE id = ? AND doctor_id = ?");
    $appt->bind_param("ii", $appointment_id, $doctor_id);
    $appt->execute();
    $apptRow = $appt->get_result()->fetch_assoc();

    if (!$apptRow) {
        header("Location: appointments.php?error=invalid_appointment");
        exit;
    }

    $patient_id = (int)$apptRow['patient_id'];

    $stmt = $conn->prepare("INSERT INTO video_calls (appointment_id, doctor_id, patient_id, initiated_by, status) VALUES (?, ?, ?, 'doctor', 'waiting')");
    $stmt->bind_param("iii", $appointment_id, $doctor_id, $patient_id);
    $stmt->execute();
    $call_id = $stmt->insert_id;

    header("Location: wait_for_patient.php?call_id=" . $call_id);
    exit;
}
header("Location: dashboard.php");
