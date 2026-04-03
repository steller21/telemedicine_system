<?php
session_start();
require_once("../config/db.php");

if (!isset($_GET['call_id'])) {
    echo "❌ No call ID!";
    exit;
}

$call_id = intval($_GET['call_id']);

$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");

if (!$call || $call->num_rows == 0) {
    echo "❌ Call not found!";
    exit;
}

$row = $call->fetch_assoc();

// ✅ DEBUG (REMOVE LATER)
echo "<!-- STATUS: " . $row['status'] . " -->";

// ✅ MAIN FIX
if ($row['status'] == 'active') {
    header("Location: ../video_call.php?call_id=" . $row['id']);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta http-equiv="refresh" content="2">
</head>
<body style="text-align:center; padding:50px; background:#1a1a2e; color:white;">
<h2>⏳ Waiting for doctor to join...</h2>
</body>
</html>