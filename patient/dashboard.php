<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') {
    header("Location: ../login.php");
    exit;
}
?>

<h2>Welcome Patient, <?php echo $_SESSION['name']; ?> 👋</h2>

<a href="book_appointment.php">Book Appointment</a><br><br>
<a href="add_checklist.php">Add Medicine</a><br><br>
<a href="checklist.php">📋 View Checklist</a><br><br>
<a href="add_monitor.php">Add Monitor</a><br><br>
<a href="monitor_view.php">View Monitored Patients</a><br><br>
<a href="../logout.php">Logout</a>