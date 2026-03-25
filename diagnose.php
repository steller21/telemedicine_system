<?php
session_start();
require_once("./config/db.php");

if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

$patient_id = $_SESSION['user_id'];

echo "<h1>Medicine Checklist Diagnostic</h1>";
echo "<hr>";

echo "<h2>Your Info:</h2>";
echo "Patient ID: <strong>" . htmlspecialchars($patient_id) . "</strong><br>";
echo "Session User ID: <strong>" . htmlspecialchars($_SESSION['user_id']) . "</strong><br>";
echo "<hr>";

echo "<h2>All Checklists for You:</h2>";
$all_checklists = $conn->query("SELECT * FROM checklists WHERE patient_id = '$patient_id' ORDER BY created_at DESC");
if ($all_checklists && $all_checklists->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Created</th><th>Last Reset</th><th>Items</th></tr>";
    while($row = $all_checklists->fetch_assoc()) {
        $items_count = $conn->query("SELECT COUNT(*) as cnt FROM checklist_items WHERE checklist_id = '{$row['id']}'")->fetch_assoc()['cnt'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($row['last_reset'] ?? 'N/A') . "</td>";
        echo "<td>" . $items_count . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No checklists found!</p>";
}

echo "<hr>";
echo "<h2>All Medicines for All Checklists:</h2>";
$all_items = $conn->query("
    SELECT ci.*, c.id as checklist_id, c.title 
    FROM checklist_items ci
    JOIN checklists c ON ci.checklist_id = c.id
    WHERE c.patient_id = '$patient_id'
    ORDER BY ci.checklist_id DESC
");

if ($all_items && $all_items->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Item ID</th><th>Checklist</th><th>Medicine</th><th>Dosage</th><th>Time</th><th>Status</th></tr>";
    while($row = $all_items->fetch_assoc()) {
        $status = empty($row['completed_at']) ? 'Pending' : 'Completed at ' . htmlspecialchars($row['completed_at']);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['checklist_id']) . " - " . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['medicine_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dosage']) . "</td>";
        echo "<td>" . htmlspecialchars($row['due_time']) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No medicines found!</p>";
}

echo "<hr>";
echo "<a href='patient/checklist.php?debug=1'>Go to Checklist (with debug)</a> | ";
echo "<a href='patient/add_checklist.php'>Add Medicine</a>";
?>
