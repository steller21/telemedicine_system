<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];

// Get incoming calls
$calls = $conn->query("SELECT * FROM video_calls 
                       WHERE doctor_id='$doctor_id' AND status='waiting'");
?>

<h2>Welcome Doctor, <?php echo $_SESSION['name']; ?> 👨‍⚕️</h2>

<h3>Incoming Calls</h3>

<?php if ($calls && $calls->num_rows > 0) { ?>
    <?php while($call = $calls->fetch_assoc()) { ?>
        <p>
            📞 Patient Calling...
            <a href="accept_call.php?call_id=<?php echo $call['id']; ?>">
                Accept Call
            </a>
        </p>
    <?php } ?>
<?php } else { ?>
    <p>No incoming calls</p>
<?php } ?>

<br>

<a href="appointments.php">📅 View Appointments</a><br><br>
<a href="../patient/monitor_view.php">👥 Monitor Patients</a><br><br>
<a href="../logout.php">🚪 Logout</a>