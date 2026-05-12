<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    echo json_encode(['calls' => []]);
    exit;
}

$doctor_id = intval($_SESSION['user_id']);
ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

$result = $conn->query("SELECT vc.id, p.name as patient_name 
                         FROM video_calls vc
                         LEFT JOIN patients p ON p.id = vc.patient_id
                         WHERE vc.doctor_id='$doctor_id' AND vc.status='waiting' AND vc.initiated_by='patient'");

$calls = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $calls[] = [
            'id'           => $row['id'],
            'patient_name' => $row['patient_name'] ?? 'Patient'
        ];
    }
}

echo json_encode(['calls' => $calls]);
