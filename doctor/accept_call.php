<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

if (!isset($_GET['call_id'])) {
    die("❌ No call_id received");
}

$call_id = intval($_GET['call_id']);

// 🔍 DEBUG: check if record exists
$check = $conn->query("SELECT * FROM video_calls WHERE id = $call_id");

if (!$check) {
    die("❌ SELECT error: " . $conn->error);
}

if ($check->num_rows == 0) {
    die("❌ No call found with ID: " . $call_id);
}

// 🔥 FORCE UPDATE
$update = $conn->query("UPDATE video_calls SET status='active', answered_at = COALESCE(answered_at, NOW()), ended_reason = NULL WHERE id = $call_id");

if (!$update) {
    die("❌ UPDATE failed: " . $conn->error);
}

// 🔍 VERIFY UPDATE
$verify = $conn->query("SELECT status FROM video_calls WHERE id = $call_id");
$row = $verify->fetch_assoc();

echo "Status after update: " . $row['status'];

// ✅ If working → redirect
if ($row['status'] == 'active') {
    header("Refresh:1; url=../video_call.php?call_id=$call_id");
    exit;
} else {
    die("❌ Still not updated");
}
?>
