<?php
/**
 * monitor_core.php - Centralized Monitoring Logic (ENHANCED WITH DEBUG INFO)
 * Changes: All SQL queries use prepared statements, detailed error logging
 */

if (!isset($conn)) {
    require_once(__DIR__ . "/../config/db.php");
}

/**
 * Initialize necessary tables if they don't exist
 */
function initMonitorTables($conn) {
    // Create monitor_requests table
    $sql1 = "CREATE TABLE IF NOT EXISTS monitor_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requester_id INT NOT NULL,
        requested_user_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_request (requester_id, requested_user_id),
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($sql1)) {
        error_log("Error creating monitor_requests table: " . $conn->error);
    }

    // Create report_share_requests table
    $sql2 = "CREATE TABLE IF NOT EXISTS report_share_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        patient_id INT NOT NULL,
        requester_id INT NOT NULL,
        requester_role ENUM('doctor', 'monitor') NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_request (report_id, requester_id),
        FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($sql2)) {
        error_log("Error creating report_share_requests table: " . $conn->error);
    }
}

initMonitorTables($conn);

// Handle Global Actions (Accept/Reject for both Monitors and Reports)
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $current_page = basename($_SERVER['PHP_SELF']);
    if (strpos($current_page, '?') !== false) $current_page = explode('?', $current_page)[0];

    // Handle Accept/Reject/Remove via GET
    if (isset($_GET['accept']) || isset($_GET['reject']) || isset($_GET['remove_monitor']) || isset($_GET['remove_patient'])) {
        
        if (isset($_GET['accept'])) {
            $request_id = intval($_GET['accept']);
            
            // Get request details safely
            $req = $conn->prepare("SELECT requester_id, requested_user_id FROM monitor_requests WHERE id=? AND status='pending'");
            if (!$req) {
                header("Location: $current_page?error=Database error occurred"); 
                exit;
            }
            
            $req->bind_param("i", $request_id);
            if (!$req->execute()) {
                header("Location: $current_page?error=Database error occurred"); 
                exit;
            }
            
            $res = $req->get_result();
            if ($row = $res->fetch_assoc()) {
                $requester_id = $row['requester_id'];
                $requested_id = $row['requested_user_id'];
                
                // Verify current user is the one being requested
                if ($requested_id != $current_user_id) {
                    header("Location: $current_page?error=Invalid request"); 
                    exit;
                }
                
                // Determine patient and monitor based on requester's role
                $role_check = $conn->prepare("SELECT role FROM users WHERE id=?");
                if ($role_check) {
                    $role_check->bind_param("i", $requester_id);
                    $role_check->execute();
                    $role_result = $role_check->get_result()->fetch_assoc();
                    $requester_role = $role_result['role'] ?? 'patient';
                    
                    // If requester is patient: they want to be monitored
                    // patient_id = requester, monitor_id = current_user
                    // If requester is doctor/other: they want to monitor
                    // patient_id = current_user, monitor_id = requester
                    if ($requester_role === 'patient') {
                        $p_id = $requester_id;
                        $m_id = $current_user_id;
                    } else {
                        $p_id = $current_user_id;
                        $m_id = $requester_id;
                    }

                    $ins = $conn->prepare("INSERT IGNORE INTO patient_monitors (patient_id, monitor_id) VALUES (?, ?)");
                    if ($ins) {
                        $ins->bind_param("ii", $p_id, $m_id);
                        if ($ins->execute()) {
                            $upd = $conn->prepare("UPDATE monitor_requests SET status='accepted' WHERE id=?");
                            if ($upd) {
                                $upd->bind_param("i", $request_id);
                                $upd->execute();
                            }
                        } else {
                            error_log("Failed to insert into patient_monitors: " . $ins->error);
                        }
                    }
                }
            }
            header("Location: $current_page?success=Request accepted!"); 
            exit;
        }

        if (isset($_GET['reject'])) {
            $request_id = intval($_GET['reject']);
            $del = $conn->prepare("DELETE FROM monitor_requests WHERE id=? AND requested_user_id=?");
            if ($del) {
                $del->bind_param("ii", $request_id, $current_user_id);
                $del->execute();
            }
            header("Location: $current_page?success=Request rejected"); 
            exit;
        }

        if (isset($_GET['remove_monitor']) || isset($_GET['remove_patient'])) {
            $target_id = intval($_GET['remove_monitor'] ?? $_GET['remove_patient']);
            $del = $conn->prepare("DELETE FROM patient_monitors WHERE (patient_id=? AND monitor_id=?) OR (patient_id=? AND monitor_id=?)");
            if ($del) {
                $del->bind_param("iiii", $current_user_id, $target_id, $target_id, $current_user_id);
                $del->execute();
            }
            header("Location: $current_page?success=Monitoring link removed"); 
            exit;
        }
    }

    // Handle Report Share Actions
    if (isset($_GET['accept_report'])) {
        $request_id = intval($_GET['accept_report']);
        $upd = $conn->prepare("UPDATE report_share_requests SET status='accepted' WHERE id=? AND patient_id=?");
        if ($upd) {
            $upd->bind_param("ii", $request_id, $current_user_id);
            $upd->execute();
        }
        header("Location: $current_page?success=Report access granted!"); 
        exit;
    }

    if (isset($_GET['reject_report'])) {
        $request_id = intval($_GET['reject_report']);
        $upd = $conn->prepare("UPDATE report_share_requests SET status='rejected' WHERE id=? AND patient_id=?");
        if ($upd) {
            $upd->bind_param("ii", $request_id, $current_user_id);
            $upd->execute();
        }
        header("Location: $current_page?success=Report request rejected"); 
        exit;
    }
}

/**
 * Centralized function to send a monitor request (WITH DETAILED ERROR LOGGING)
 */
function sendMonitorRequest($conn, $requester_id, $target_email, $target_role = null) {
    // Validate input
    if (empty($target_email) || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
        error_log("sendMonitorRequest: Invalid email format: $target_email");
        return ["type" => "error", "msg" => "Please enter a valid email address."];
    }

    error_log("sendMonitorRequest: Attempting to find user with email: $target_email");

    // Find target user
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
    if (!$stmt) {
        error_log("sendMonitorRequest: Failed to prepare statement: " . $conn->error);
        return ["type" => "error", "msg" => "Database error. Please try again."];
    }
    
    $stmt->bind_param("s", $target_email);
    if (!$stmt->execute()) {
        error_log("sendMonitorRequest: Failed to execute select: " . $stmt->error);
        return ["type" => "error", "msg" => "Database error. Please try again."];
    }
    
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        error_log("sendMonitorRequest: User not found with email: $target_email");
        return ["type" => "error", "msg" => "User not found with that email."];
    }
    
    error_log("sendMonitorRequest: Found user ID: {$user['id']}, Role: {$user['role']}");
    
    if ($user['id'] == $requester_id) {
        error_log("sendMonitorRequest: User tried to add themselves");
        return ["type" => "error", "msg" => "You cannot add yourself as a monitor."];
    }
    
    if ($target_role && $user['role'] !== $target_role) {
        error_log("sendMonitorRequest: Role mismatch. Expected: $target_role, Got: {$user['role']}");
        return ["type" => "error", "msg" => "This user is not a " . htmlspecialchars($target_role) . "."];
    }

    $target_id = $user['id'];

    // Check if already monitoring
    $active = $conn->prepare("SELECT id FROM patient_monitors WHERE (patient_id=? AND monitor_id=?) OR (patient_id=? AND monitor_id=?)");
    if ($active) {
        $active->bind_param("iiii", $requester_id, $target_id, $target_id, $requester_id);
        if ($active->execute()) {
            if ($active->get_result()->num_rows > 0) {
                error_log("sendMonitorRequest: Monitoring relationship already exists");
                return ["type" => "warning", "msg" => "Monitoring relationship already exists."];
            }
        } else {
            error_log("sendMonitorRequest: Failed to check existing relationship: " . $active->error);
        }
    }

    // Check for existing pending request
    $pending = $conn->prepare("SELECT id FROM monitor_requests WHERE requester_id=? AND requested_user_id=? AND status='pending'");
    if ($pending) {
        $pending->bind_param("ii", $requester_id, $target_id);
        if ($pending->execute()) {
            if ($pending->get_result()->num_rows > 0) {
                error_log("sendMonitorRequest: Pending request already exists");
                return ["type" => "warning", "msg" => "Request already sent. Waiting for their response."];
            }
        } else {
            error_log("sendMonitorRequest: Failed to check pending request: " . $pending->error);
        }
    }

    // Insert new request
    error_log("sendMonitorRequest: Inserting new request. Requester: $requester_id, Requested: $target_id");
    
    $ins = $conn->prepare("INSERT INTO monitor_requests (requester_id, requested_user_id, status) VALUES (?, ?, 'pending')");
    if (!$ins) {
        error_log("sendMonitorRequest: Failed to prepare INSERT statement: " . $conn->error);
        return ["type" => "error", "msg" => "Error sending request. Please try again."];
    }
    
    $ins->bind_param("ii", $requester_id, $target_id);
    if ($ins->execute()) {
        error_log("sendMonitorRequest: SUCCESS! Request inserted with ID: " . $conn->insert_id);
        return ["type" => "success", "msg" => "✅ Request sent! They must accept before you can monitor."];
    }
    
    error_log("sendMonitorRequest: FAILED to insert request: " . $ins->error);
    return ["type" => "error", "msg" => "Error sending request. " . $ins->error];
}
?>