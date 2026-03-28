<?php
session_start();
require_once("../config/db.php");

if (!isset($_GET['call_id'])) {
    echo "❌ No call ID!";
    exit;
}

$call_id = $_GET['call_id'];

$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");

if (!$call || $call->num_rows == 0) {
    echo "❌ Call not found!";
    exit;
}

$row = $call->fetch_assoc();

if ($row['status'] == 'active') {
    header("Location: ../video_call.php?call_id=".$row['id']);
    exit;
}
?>

<h3>Waiting for doctor to join...</h3>

<meta http-equiv="refresh" content="3">