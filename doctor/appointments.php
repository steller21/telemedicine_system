<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT a.*, u.name AS patient_name 
                        FROM appointments a 
                        JOIN users u ON a.patient_id = u.id 
                        WHERE a.doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>My Appointments</h2>

<?php if ($result->num_rows > 0) { ?>
<table border="1">
<tr>
    <th>Patient</th>
    <th>Date</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>
<tr>
    <td><?php echo $row['patient_name']; ?></td>
    <td><?php echo $row['appointment_date']; ?></td>
</tr>
<?php } ?>

</table>
<?php } else { ?>
<p>No appointments found.</p>
<?php } ?>