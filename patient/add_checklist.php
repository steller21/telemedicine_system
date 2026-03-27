<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

if (isset($_POST['add'])) {

    $medicine_name = $_POST['medicine_name'];
    $dosage = $_POST['dosage'];
    $time = $_POST['time'];

    // Image upload
    $target = null;
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image']['name'];
        $target = "../uploads/medicines/" . time() . "_" . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    // Get or create checklist
    $stmt = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $checklist_id = $row['id'];
    } else {
        $create_stmt = $conn->prepare("INSERT INTO checklists (patient_id, created_by, title) VALUES (?, ?, 'Daily Medicines')");
        $create_stmt->bind_param("ii", $patient_id, $patient_id);
        $create_stmt->execute();
        $checklist_id = $create_stmt->insert_id;
    }

    // Insert medicine
    $item_stmt = $conn->prepare("INSERT INTO checklist_items (checklist_id, medicine_name, medicine_image, dosage, due_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $item_stmt->bind_param("issss", $checklist_id, $medicine_name, $target, $dosage, $time);

    if ($item_stmt->execute()) {
        echo "<h3 style='color:green;'>✅ Medicine added successfully!</h3>";
        echo "<a href='checklist.php'>➡️ Go to Checklist</a>";
    } else {
        echo "❌ Error: Could not add medicine.";
    }
}
?>

<h2>Add Medicine</h2>

<form method="POST" enctype="multipart/form-data">
    <input type="text" name="medicine_name" placeholder="Medicine Name" required><br><br>
    
    <input type="text" name="dosage" placeholder="Dosage (e.g., 1 tablet after food)" required><br><br>
    
    <input type="time" name="time" required><br><br>

    <input type="file" name="image"><br><br>

    <button type="submit" name="add">Add Medicine</button>
</form>