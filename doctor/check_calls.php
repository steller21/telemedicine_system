<?php
session_start();
require_once("../config/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    echo json_encode(['calls' => []]);
    exit;
}

$doctor_id = intval($_SESSION['user_id']);

$result = $conn->query("SELECT vc.id, u.name as patient_name 
                        FROM video_calls vc
                        LEFT JOIN users u ON u.id = vc.patient_id
                        WHERE vc.doctor_id='$doctor_id' AND vc.status='waiting'");

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