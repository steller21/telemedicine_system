<?php
session_start();
require_once("../config/db.php");

$call_id = $_GET['call_id'];

// Activate call
$conn->query("UPDATE video_calls SET status='active' WHERE id='$call_id'");

// Redirect to video
header("Location: ../video_call.php?call_id=$call_id");
exit;