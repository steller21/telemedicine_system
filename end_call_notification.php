<?php
// Updates call status to ended in DB
require_once("config/db.php");

if (!isset($_GET['call_id'])) {
    echo 'error'; exit;
}

$call_id = intval($_GET['call_id']);
$stmt = $conn->prepare("UPDATE video_calls SET status='ended' WHERE id=?");
if ($stmt === false) {
    echo 'error_prepare'; exit;
}
$stmt->bind_param("i", $call_id);
$stmt->execute();
echo 'ended';