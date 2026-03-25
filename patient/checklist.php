<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
}

$patient_id = $_SESSION['user_id'];

// Get checklist
$check = $conn->query("SELECT id FROM checklists WHERE patient_id='$patient_id' LIMIT 1");

if ($check->num_rows > 0) {
    $row = $check->fetch_assoc();
    $checklist_id = $row['id'];
} else {
    echo "⚠️ No checklist found. Please add medicine first.";
    exit;
}

// Handle mark as taken
if (isset($_POST['mark_done'])) {
    $item_id = $_POST['item_id'];
    $conn->query("UPDATE checklist_items SET status='completed' WHERE id='$item_id'");
}

// Fetch medicines
$sql = "SELECT * FROM checklist_items 
        WHERE checklist_id = '$checklist_id'
        ORDER BY due_time ASC";

$result = $conn->query($sql);
?>

<h2>My Medicines</h2>

<table border="1" cellpadding="10">
<tr>
    <th>Image</th>
    <th>Name</th>
    <th>Dosage</th>
    <th>Time</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php if ($result->num_rows > 0) { ?>
    <?php while($row = $result->fetch_assoc()) { ?>
    <tr>
        <td>
            <?php if (!empty($row['medicine_image'])) { ?>
                <img src="<?php echo $row['medicine_image']; ?>" width="80">
            <?php } else { echo "No Image"; } ?>
        </td>

        <td><?php echo $row['medicine_name']; ?></td>
        <td><?php echo $row['dosage']; ?></td>
        <td><?php echo $row['due_time']; ?></td>

        <td>
            <?php echo ($row['status'] == 'completed') ? "✅ Taken" : "⏳ Pending"; ?>
        </td>

        <td>
            <?php if ($row['status'] == 'pending') { ?>
                <form method="POST">
                    <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="mark_done">✔ Mark Taken</button>
                </form>
            <?php } else { ?>
                ✔ Done
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
<?php } else { ?>
<tr>
    <td colspan="6">No medicines added</td>
</tr>
<?php } ?>

</table>