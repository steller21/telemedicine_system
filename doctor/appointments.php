<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
}

$doctor_id = $_SESSION['user_id'];

$sql = "SELECT a.*, u.name as patient_name 
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = '$doctor_id'";

$result = $conn->query($sql);
?>

<h2>My Appointments</h2>

<table border="1">
<tr>
    <th>Patient Name</th>
    <th>Date</th>
    <th>Status</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>
<tr>
    <td><?php echo $row['patient_name']; ?></td>
    <td><?php echo $row['appointment_date']; ?></td>
    <td><?php echo $row['status']; ?></td>
</tr>
<?php } ?>

</table>