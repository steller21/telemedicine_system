<?php
require_once("config/db.php");
require_once("includes/call_core.php");

header('Content-Type: application/json');

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

if (!isset($_GET['call_id'])) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

$call_id = intval($_GET['call_id']);

$stmt = $conn->prepare("SELECT status, created_at, ended_reason FROM video_calls WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error_prepare']);
    exit;
}

$stmt->bind_param("i", $call_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

$row = $result->fetch_assoc();
$secondsLeft = null;
if (($row['status'] ?? '') === 'waiting') {
    $secondsLeft = max(0, 60 - (time() - strtotime($row['created_at'])));
}

echo json_encode([
    'status' => $row['status'] ?? 'unknown',
    'ended_reason' => $row['ended_reason'] ?? null,
    'seconds_left' => $secondsLeft,
]);
