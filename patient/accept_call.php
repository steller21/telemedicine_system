<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['call_id'])) {
    exit("Invalid call.");
}

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

$call_id = intval($_GET['call_id']);
$patient_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("
    UPDATE video_calls
    SET status = 'active', answered_at = NOW(), ended_reason = NULL
    WHERE id = ? AND patient_id = ? AND initiated_by = 'doctor' AND status = 'waiting'
");
$stmt->bind_param("ii", $call_id, $patient_id);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    header("Location: dashboard.php?error=call_unavailable");
    exit;
}

header("Location: ../video_call.php?call_id=" . $call_id);
exit;
