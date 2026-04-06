<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id > 0) {
    // Create a new video call record with 'waiting' status
    // The patient dashboard should poll for calls where they are the recipient
    $stmt = $conn->prepare("INSERT INTO video_calls (doctor_id, patient_id, status) VALUES (?, ?, 'waiting')");
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    $call_id = $stmt->insert_id;

    header("Location: ../video_call.php?call_id=" . $call_id);
    exit;
}
header("Location: dashboard.php");