<?php
session_start();
require_once("../config/db.php");

if (!isset($_GET['call_id'])) {
    echo "❌ No call ID!"; exit;
}

$call_id = intval($_GET['call_id']); // sanitized

$conn->query("UPDATE video_calls SET status='active' WHERE id='$call_id'");

header("Location: ../video_call.php?call_id=$call_id");
exit;
?>