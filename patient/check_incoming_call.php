<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    echo json_encode(['call' => null]);
    exit;
}

$call = fetchIncomingDoctorCall($conn, (int)$_SESSION['user_id']);
if (!$call) {
    echo json_encode(['call' => null]);
    exit;
}

echo json_encode([
    'call' => [
        'id' => (int)$call['id'],
        'doctor_name' => $call['doctor_name'] ?? 'Doctor',
        'seconds_left' => (int)$call['seconds_left'],
    ]
]);
