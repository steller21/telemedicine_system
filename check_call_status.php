<?php
// No session check needed — call_id is not sensitive, just returns status
require_once("config/db.php");

if (!isset($_GET['call_id'])) {
    echo 'unknown'; exit;
}

$call_id = intval($_GET['call_id']);

$stmt = $conn->prepare("SELECT status FROM video_calls WHERE id=?");
if ($stmt === false) {
    echo 'error_prepare'; exit;
}
$stmt->bind_param("i", $call_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo 'unknown'; exit;
}
$row = $result->fetch_assoc();
echo $row['status']; // outputs: waiting / active / ended