<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
}
?>

<h2>Welcome Doctor, <?php echo $_SESSION['name']; ?> 👨‍⚕️</h2>


<a href="appointments.php">View Appointments</a><br><br>
<a href="../logout.php">Logout</a>
