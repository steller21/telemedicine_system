<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') {
    header("Location: ../login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// Fetch upcoming appointments
$appointments = $conn->query("SELECT a.id, a.appointment_date, u.name as doctor_name 
                              FROM appointments a
                              JOIN users u ON a.doctor_id = u.id
                              WHERE a.patient_id = '$patient_id' AND a.appointment_date >= NOW()
                              ORDER BY a.appointment_date ASC");
?>

<h2>Welcome Patient, <?php echo $_SESSION['name']; ?> 👋</h2>

<a href="book_appointment.php">Book Appointment</a><br><br>
<a href="add_checklist.php">Add Medicine</a><br><br>
<a href="checklist.php">📋 View Checklist</a><br><br>
<a href="add_monitor.php">Add Monitor</a><br><br>
<a href="monitor_view.php">View Monitored Patients</a><br><br>


<h3>📞 Upcoming Appointments</h3>
<?php 
if ($appointments->num_rows > 0) {
    while ($row = $appointments->fetch_assoc()) {
        echo "<div>";
        echo "Dr. " . htmlspecialchars($row['doctor_name']) . " - " . $row['appointment_date'] . " ";
        echo "<a href='start_call.php?appointment_id=" . $row['id'] . "'>Start Call</a>";
        echo "</div><br>";
    }
} else {
    echo "No upcoming appointments. <a href='book_appointment.php'>Book one now</a>";
}
?>
<br><a href="../chatbot.php">🤖 Open Health Assistant</a>
<br>
<a href="../logout.php">Logout</a>