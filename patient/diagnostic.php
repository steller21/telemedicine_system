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
        UNIQUE KEY unique_request (requester_id, requested_user_id),
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_user_id) REFERENCES users(id) ON DELETE CASCADE
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
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (monitor_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    if ($create_pm) {
        echo "   ✅ Table created successfully!\n";
    } else {
        echo "   ❌ Error creating table: " . $conn->error . "\n";
    }
}

echo "\n3. Checking users table...\n";
$check_users = $conn->query("SHOW TABLES LIKE 'users'");
if ($check_users->num_rows > 0) {
    echo "   ✅ Table exists\n";
    $user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc();
    echo "   Users in database: " . $user_count['count'] . "\n";
} else {
    echo "   ❌ users table does not exist!\n";
}

echo "\n4. Testing Prepared Statements...\n";
$test_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if ($test_stmt) {
    echo "   ✅ Prepared statements work\n";
} else {
    echo "   ❌ Prepared statements error: " . $conn->error . "\n";
}

echo "\n5. Testing INSERT into monitor_requests...\n";
if (isset($_SESSION['user_id'])) {
    $test_user = $_SESSION['user_id'];
    $test_email = "test_monitor_" . time() . "@example.com";
    
    // Create a test monitor user first
    $create_test = $conn->query("INSERT INTO users (name, email, role, password, gender) 
                                  VALUES ('Test Monitor', '$test_email', 'monitor', 'hash', 'other')");
    
    if ($create_test) {
        $test_monitor_id = $conn->insert_id;
        echo "   Created test monitor user (ID: $test_monitor_id)\n";
        
        // Try to insert a request
        $test_insert = $conn->prepare("INSERT INTO monitor_requests (requester_id, requested_user_id, status) 
                                       VALUES (?, ?, 'pending')");
        if ($test_insert) {
            $test_insert->bind_param("ii", $test_user, $test_monitor_id);
            if ($test_insert->execute()) {
                echo "   ✅ INSERT test successful\n";
                
                // Clean up test data
                $cleanup = $conn->query("DELETE FROM monitor_requests WHERE id = " . $conn->insert_id);
                $cleanup_user = $conn->query("DELETE FROM users WHERE id = $test_monitor_id");
            } else {
                echo "   ❌ INSERT failed: " . $test_insert->error . "\n";
            }
        } else {
            echo "   ❌ Prepared statement creation failed: " . $conn->error . "\n";
        }
    } else {
        echo "   ❌ Could not create test user: " . $conn->error . "\n";
    }
} else {
    echo "   ⚠️  Not logged in - skipping INSERT test\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
?>