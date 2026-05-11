<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') { 
    header("Location: ../login.php"); 
    exit; 
}

$patient_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: upload_report.php?error=No report ID provided");
    exit;
}

$report_id = intval($_GET['id']);

// Get report details
$stmt = $conn->prepare("SELECT * FROM reports WHERE id=? AND patient_id=?");
$stmt->bind_param("ii", $report_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    header("Location: upload_report.php?error=Report not found or unauthorized");
    exit;
}

// Delete file from storage
$file_path = "../" . $report['file_path'];
if (file_exists($file_path)) {
    unlink($file_path);
}

// Delete from database
$delete_stmt = $conn->prepare("DELETE FROM reports WHERE id=? AND patient_id=?");
$delete_stmt->bind_param("ii", $report_id, $patient_id);
$delete_stmt->execute();

// Redirect back to upload_report.php
header("Location: upload_report.php?success=Report deleted successfully");
exit;
?>
