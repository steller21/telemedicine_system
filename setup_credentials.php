<?php
require_once("config/db.php");

echo "<h2>Database Update</h2>";

// 1. Add affiliations to doctors table
$add_col = "ALTER TABLE doctors ADD COLUMN affiliations TEXT DEFAULT NULL";
if ($conn->query($add_col)) {
    echo "✅ affiliations column added to doctors table.<br>";
} else {
    if (strpos($conn->error, "Duplicate column name") !== false) {
        echo "✅ affiliations column already exists.<br>";
    } else {
        echo "❌ Error adding affiliations column: " . $conn->error . "<br>";
    }
}

// 2. Create doctor_credentials table
$create_table = "CREATE TABLE IF NOT EXISTS doctor_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    credential_type ENUM('License', 'Certification') NOT NULL,
    credential_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
)";
if ($conn->query($create_table)) {
    echo "✅ doctor_credentials table created successfully.<br>";
} else {
    echo "❌ Error creating doctor_credentials table: " . $conn->error . "<br>";
}

// 3. Create upload directory
$upload_dir = "uploads/credentials/";
if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Created uploads/credentials/ directory.<br>";
    } else {
        echo "❌ Failed to create directory.<br>";
    }
} else {
    echo "✅ uploads/credentials/ directory already exists.<br>";
}

echo "<br>Done.";
?>
