<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$monitor_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT DISTINCT users.id, users.name 
                        FROM patient_monitors 
                        JOIN users ON patient_monitors.patient_id = users.id 
                        WHERE patient_monitors.monitor_id = ?");
$stmt->bind_param("i", $monitor_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>

<h2>Patients You Monitor</h2>

<?php if ($result->num_rows > 0) { ?>
    <?php while($row = $result->fetch_assoc()) { ?>
        <p>
            <?php echo $row['name']; ?>
            <a href="view_patient_checklist.php?patient_id=<?php echo $row['id']; ?>">
                View Checklist
            </a>
        </p>
    <?php } ?>
<?php } else { ?>
    <p>No patients assigned.</p>
<?php } ?>