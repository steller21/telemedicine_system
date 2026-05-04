<?php
require_once("config/db.php");

echo "<h2>Database Check</h2>";

// Check if reports table exists
$check = $conn->query("SHOW TABLES LIKE 'reports'");
if ($check && $check->num_rows > 0) {
    echo "✅ Reports table EXISTS<br>";
    
    // Check table structure
    $columns = $conn->query("DESCRIBE reports");
    echo "<h3>Table Structure:</h3>";
    echo "<pre>";
    while ($row = $columns->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
} else {
    echo "❌ Reports table DOES NOT exist<br>";
    echo "<p>Creating table...</p>";
    
    $sql = "CREATE TABLE reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        report_name VARCHAR(255) NOT NULL,
        report_type VARCHAR(100),
        file_path VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        echo "✅ Reports table created successfully!<br>";
    } else {
        echo "❌ Error creating table: " . $conn->error;
    }
}

// Check upload directory
$upload_dir = "C:\\xampp\\htdocs\\telemedicine_system\\uploads\\reports\\";
echo "<h3>Upload Directory Check:</h3>";
if (is_dir($upload_dir)) {
    echo "✅ Directory EXISTS: " . $upload_dir . "<br>";
    if (is_writable($upload_dir)) {
        echo "✅ Directory is WRITABLE<br>";
    } else {
        echo "❌ Directory is NOT writable. Check permissions.<br>";
    }
} else {
    echo "❌ Directory DOES NOT exist: " . $upload_dir . "<br>";
    if (@mkdir($upload_dir, 0777, true)) {
        echo "✅ Directory created successfully!<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

echo "<p><a href='patient/upload_report.php'>Back to Upload</a></p>";
?>
