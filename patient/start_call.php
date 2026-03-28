<?php
session_start();
require_once("../config/db.php");

if (!isset($_GET['appointment_id'])) {
    echo "❌ Invalid request";
    exit;
}

$patient_id = $_SESSION['user_id'];
$appointment_id = $_GET['appointment_id'];

// Get doctor_id
$res = $conn->query("SELECT doctor_id FROM appointments WHERE id='$appointment_id'");
$row = $res->fetch_assoc();
$doctor_id = $row['doctor_id'];

// Insert call
$conn->query("INSERT INTO video_calls (appointment_id, patient_id, doctor_id) 
              VALUES ('$appointment_id', '$patient_id', '$doctor_id')");

$call_id = $conn->insert_id;

// Show feedback + redirect
echo "<script>
alert('📞 Calling doctor...');
window.location.href='wait_call.php?call_id=$call_id';
</script>";
exit;