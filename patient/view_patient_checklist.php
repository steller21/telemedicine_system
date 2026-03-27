<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['patient_id'])) {
    echo "❌ No patient selected";
    exit;
}

$patient_id = $_GET['patient_id'];

// Get checklist
$stmt = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$check = $stmt->get_result();

if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    $checklist_id = $row['id'];
} else {
    echo "⚠️ No checklist found for this patient.";
    exit;
}

// Fetch medicines
$stmt = $conn->prepare("SELECT * FROM checklist_items WHERE checklist_id = ?");
$stmt->bind_param("i", $checklist_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Patient Checklist</h2>

<table border="1">
<tr>
    <th>Name</th>
    <th>Time</th>
    <th>Status</th>
</tr>

<?php if ($result && $result->num_rows > 0) { ?>
    <?php while($row = $result->fetch_assoc()) { ?>
    <tr>
        <td><?php echo $row['medicine_name']; ?></td>
        <td><?php echo $row['due_time']; ?></td>
        <td><?php echo $row['status']; ?></td>
    </tr>
    <?php } ?>
<?php } else { ?>
<tr>
    <td colspan="3">No medicines found</td>
</tr>
<?php } ?>

</table>