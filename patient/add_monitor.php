<?php
session_start();
require_once("../config/db.php");

$patient_id = $_SESSION['user_id'];

if (isset($_POST['add'])) {
    $monitor_email = $_POST['email'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $monitor_email);
    $stmt->execute();
    $user = $stmt->get_result();

    if ($user->num_rows > 0) {
        $row = $user->fetch_assoc();
        $monitor_id = $row['id'];

        $ins = $conn->prepare("INSERT INTO patient_monitors (patient_id, monitor_id) VALUES (?, ?)");
        $ins->bind_param("ii", $patient_id, $monitor_id);
        $ins->execute();

        echo "✅ Monitor added!";
    } else {
        echo "❌ User not found!";
    }
}
?>

<h2>Add Monitor</h2>

<form method="POST">
    <input type="email" name="email" placeholder="Enter user email" required><br><br>
    <button type="submit" name="add">Add Monitor</button>
</form>