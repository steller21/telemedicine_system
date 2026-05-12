<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['call_id'])) {
    header("Location: dashboard.php");
    exit;
}

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

$call_id = intval($_GET['call_id']);
$patient_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("
    SELECT vc.doctor_id, vc.patient_id, p.name AS patient_name
    FROM video_calls vc
    JOIN patients p ON p.id = vc.patient_id
    WHERE vc.id = ?
      AND vc.patient_id = ?
      AND vc.initiated_by = 'doctor'
      AND vc.status = 'missed'
      AND COALESCE(vc.patient_ready_notified, 0) = 0
");
$stmt->bind_param("ii", $call_id, $patient_id);
$stmt->execute();
$call = $stmt->get_result()->fetch_assoc();

if ($call) {
    addGenericUserNotification(
        $conn,
        (int)$call['doctor_id'],
        "Patient Ready for Consultancy",
        ($call['patient_name'] ?? 'Your patient') . " is ready for consultancy now."
    );

    $update = $conn->prepare("UPDATE video_calls SET patient_ready_notified = 1 WHERE id = ?");
    $update->bind_param("i", $call_id);
    $update->execute();
}

header("Location: dashboard.php?success=doctor_notified");
exit;
