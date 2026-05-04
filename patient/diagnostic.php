<?php
session_start();
require_once("../config/db.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== MEDICONNECT DATABASE DIAGNOSTIC ===\n\n";

// Test 1: Check if monitor_requests table exists
echo "1. Checking monitor_requests table...\n";
$check_table = $conn->query("SHOW TABLES LIKE 'monitor_requests'");
if ($check_table->num_rows > 0) {
    echo "   ✅ Table exists\n";
    
    // Show table structure
    $desc = $conn->query("DESCRIBE monitor_requests");
    echo "   Structure:\n";
    while ($row = $desc->fetch_assoc()) {
        echo "      - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "   ❌ Table DOES NOT EXIST - Creating it now...\n";
    $create = $conn->query("CREATE TABLE IF NOT EXISTS monitor_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requester_id INT NOT NULL,
        requested_user_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_request (requester_id, requested_user_id)
    )");
    if ($create) {
        echo "   ✅ Table created successfully!\n";
    } else {
        echo "   ❌ Error creating table: " . $conn->error . "\n";
    }
}

echo "\n2. Checking patient_monitors table...\n";
$check_pm = $conn->query("SHOW TABLES LIKE 'patient_monitors'");
if ($check_pm->num_rows > 0) {
    echo "   ✅ Table exists\n";
} else {
    echo "   ❌ Table DOES NOT EXIST - Creating it now...\n";
    $create_pm = $conn->query("CREATE TABLE IF NOT EXISTS patient_monitors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        monitor_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_relationship (patient_id, monitor_id),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (monitor_id) REFERENCES patients(id) ON DELETE CASCADE
    )");
    if ($create_pm) {
        echo "   ✅ Table created successfully!\n";
    } else {
        echo "   ❌ Error creating table: " . $conn->error . "\n";
    }
}

echo "\n3. Checking users VIEW (patients + doctors)...\n";
$check_users = $conn->query("SHOW FULL TABLES WHERE Table_type='VIEW' AND Tables_in_" . $conn->query("SELECT DATABASE()")->fetch_row()[0] . " LIKE 'users'");
if ($check_users && $check_users->num_rows > 0) {
    echo "   ✅ users VIEW exists\n";
    $user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc();
    echo "   Total users in VIEW: " . $user_count['count'] . "\n";
} else {
    $pat = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc();
    $doc = $conn->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc();
    echo "   ℹ️  users is a VIEW. Patients: " . $pat['c'] . ", Doctors: " . $doc['c'] . "\n";
}

echo "\n4. Testing Prepared Statements...\n";
$test_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if ($test_stmt) {
    echo "   ✅ Prepared statements work\n";
} else {
    echo "   ❌ Prepared statements error: " . $conn->error . "\n";
}

    echo "   ⚠️  Skipping INSERT test — users is now a VIEW. Registration inserts into patients or doctors directly.\n";

echo "\n=== END DIAGNOSTIC ===\n";
?>