<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    exit("Please login first.");
}

if (!isset($_GET['appointment_id'])) {
    exit("Invalid request.");
}

$patient_id = intval($_SESSION['user_id']);
$appointment_id = intval($_GET['appointment_id']);

// Patient callback is allowed only after a missed doctor call and a 5 minute wait.
$stmt = $conn->prepare("
    SELECT a.doctor_id
    FROM appointments a
    WHERE a.id = ?
      AND a.patient_id = ?
      AND EXISTS (
          SELECT 1
          FROM video_calls vc
          WHERE vc.appointment_id = a.id
            AND vc.patient_id = a.patient_id
            AND vc.doctor_id = a.doctor_id
            AND vc.initiated_by = 'doctor'
            AND vc.status = 'missed'
            AND vc.created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
      )
");
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    exit("You can call the doctor only 5 minutes after a missed doctor consultation call.");
}

$doctor_id = intval($row['doctor_id']);

$stmt2 = $conn->prepare("INSERT INTO video_calls (appointment_id, patient_id, doctor_id, initiated_by, status) VALUES (?, ?, ?, 'patient', 'waiting')");
$stmt2->bind_param("iii", $appointment_id, $patient_id, $doctor_id);
$stmt2->execute();

$call_id = $conn->insert_id;

echo "<script>
alert('Calling doctor...');
window.location.href='wait_call.php?call_id=$call_id';
</script>";
exit;
