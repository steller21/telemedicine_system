<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// Fetch doctors
$doctors = $conn->query("SELECT * FROM users WHERE role='doctor'");

if (isset($_POST['book'])) {
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $patient_id, $doctor_id, $date);

    if ($stmt->execute()) {
        echo "✅ Appointment booked!";
    } else {
        echo "❌ Error: Could not book appointment.";
    }
}
?>

<h2>Book Appointment</h2>

<form method="POST">
    <label>Select Doctor:</label><br>
    <select name="doctor_id" required>
        <?php while($row = $doctors->fetch_assoc()) { ?>
            <option value="<?php echo $row['id']; ?>">
                Dr. <?php echo $row['name']; ?>
            </option>
        <?php } ?>
    </select><br><br>

    <label>Select Date & Time:</label><br>
    <input type="datetime-local" name="date" required><br><br>

    <button type="submit" name="book">Book Appointment</button>
</form>