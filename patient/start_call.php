<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    echo "❌ Please login first!"; exit;
}

if (!isset($_GET['appointment_id'])) {
    echo "❌ Invalid request"; exit;
}

$patient_id     = intval($_SESSION['user_id']);
$appointment_id = intval($_GET['appointment_id']);

// Get doctor_id safely
$stmt = $conn->prepare("SELECT doctor_id FROM appointments WHERE id=?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    echo "❌ Appointment not found!"; exit;
}

$doctor_id = intval($row['doctor_id']);

// Insert call record
$stmt2 = $conn->prepare("INSERT INTO video_calls (appointment_id, patient_id, doctor_id) VALUES (?, ?, ?)");
$stmt2->bind_param("iii", $appointment_id, $patient_id, $doctor_id);
$stmt2->execute();

$call_id = $conn->insert_id;

echo "<script>
alert('📞 Calling doctor...');
window.location.href='wait_call.php?call_id=$call_id';
</script>";
exit;
