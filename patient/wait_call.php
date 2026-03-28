<?php
session_start();
require_once("../config/db.php");

if (!isset($_GET['call_id'])) {
    echo "❌ No call ID!"; exit;
}

$call_id = intval($_GET['call_id']);

$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");

if (!$call || $call->num_rows == 0) {
    echo "❌ Call not found!"; exit;
}

$row = $call->fetch_assoc();

if ($row['status'] == 'active') {
    header("Location: ../video_call.php?call_id=" . $row['id']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Waiting for Doctor</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 60px; background: #1a1a2e; color: #fff; }
        .spinner { font-size: 2rem; animation: spin 1.5s linear infinite; display: inline-block; }
        @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
    </style>
    <!-- Auto-refresh every 3 seconds to check if doctor accepted -->
    <meta http-equiv="refresh" content="3">
</head>
<body>
    <div class="spinner">⏳</div>
    <h3>Waiting for doctor to join...</h3>
    <p>Please keep this page open. It will connect automatically.</p>
</body>
</html>
