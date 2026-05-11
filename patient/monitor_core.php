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
        UNIQUE KEY unique_request (requester_id, requested_user_id)
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
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($sql2)) {
        error_log("Error creating report_share_requests table: " . $conn->error);
    }

    // Create user_notifications table for non-request alerts
    $sql3 = "CREATE TABLE IF NOT EXISTS user_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql3)) {
        error_log("Error creating user_notifications table: " . $conn->error);
    }

    // Ensure checklist_items has prescribed_by column for digital prescriptions
    if ($conn->query("SHOW COLUMNS FROM checklist_items LIKE 'prescribed_by'")->num_rows == 0) {
        $conn->query("ALTER TABLE checklist_items ADD COLUMN prescribed_by INT NULL DEFAULT NULL");
    }
}

initMonitorTables($conn);

// Handle Global Actions (Accept/Reject for both Monitors and Reports)
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $current_page = basename($_SERVER['PHP_SELF']);
    if (strpos($current_page, '?') !== false) $current_page = explode('?', $current_page)[0];

    /**
     * Helper to add a persistent notification for a user
     */
    function addUserNotification($conn, $user_id, $title, $message) {
        $stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $message);
        return $stmt->execute();
    }

    // Handle Accept/Reject/Remove via GET
    if (isset($_GET['accept']) || isset($_GET['reject']) || isset($_GET['remove_monitor']) || isset($_GET['remove_patient']) || 
        isset($_GET['accept_report']) || isset($_GET['reject_report']) || 
        isset($_GET['accept_friend']) || isset($_GET['reject_friend']) ||
        isset($_GET['clear_notif'])) {
        
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

                $ins = $conn->prepare("INSERT IGNORE INTO patient_monitors (patient_id, monitor_id) VALUES (?, ?)");
                if ($ins) {
                    $ins->bind_param("ii", $requester_id, $current_user_id);
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
            header("Location: $current_page?success=Request accepted!"); 
            exit;
        }

        if (isset($_GET['reject'])) {
            $request_id = intval($_GET['reject']);
            // Get requester info before deleting to notify them
            $stmt = $conn->prepare("SELECT mr.requester_id, p.name as responder_name FROM monitor_requests mr JOIN patients p ON mr.requested_user_id = p.id WHERE mr.id=? AND mr.requested_user_id=?");
            $stmt->bind_param("ii", $request_id, $current_user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($row = $res->fetch_assoc()) {
                $target_id = $row['requester_id'];
                $responder = $row['responder_name'];
                
                // Delete the request record completely
                $conn->query("DELETE FROM monitor_requests WHERE id = $request_id");
                
                // Send notification to the original requester
                addUserNotification($conn, $target_id, "Monitor Request Rejected", "$responder has rejected your monitoring request.");
            }
            header("Location: $current_page?success=Request rejected"); 
            exit;
        }

        if (isset($_GET['remove_monitor']) || isset($_GET['remove_patient'])) {
            $target_id = intval($_GET['remove_monitor'] ?? $_GET['remove_patient']);
            
            // Remove the relationship
            $del_link = $conn->prepare("DELETE FROM patient_monitors WHERE (patient_id=? AND monitor_id=?) OR (patient_id=? AND monitor_id=?)");
            if ($del_link) {
                $del_link->bind_param("iiii", $current_user_id, $target_id, $target_id, $current_user_id);
                $del_link->execute();
            }
            
            // Also clear the request record to allow re-requesting in the future and cleanup both accounts
            $del_req = $conn->prepare("DELETE FROM monitor_requests WHERE (requester_id=? AND requested_user_id=?) OR (requester_id=? AND requested_user_id=?)");
            if ($del_req) {
                $del_req->bind_param("iiii", $current_user_id, $target_id, $target_id, $current_user_id);
                $del_req->execute();
            }
            header("Location: $current_page?success=Monitoring link removed"); 
            exit;
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
            $del = $conn->prepare("DELETE FROM report_share_requests WHERE id=? AND patient_id=?");
            if ($del) {
                $del->bind_param("ii", $request_id, $current_user_id);
                $del->execute();
            }
            header("Location: $current_page?success=Report request rejected"); 
            exit;
        }

        // Handle Friend Request Actions
        if (isset($_GET['accept_friend'])) {
            $req_id = intval($_GET['accept_friend']);
            $stmt = $conn->prepare("SELECT sender_id FROM friend_requests WHERE id=? AND receiver_id=? AND status='pending'");
            if ($stmt) {
                $stmt->bind_param("ii", $req_id, $current_user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $sender_id = $row['sender_id'];
                    $conn->query("UPDATE friend_requests SET status='accepted' WHERE id=$req_id");
                    $u1 = min($current_user_id, $sender_id); $u2 = max($current_user_id, $sender_id);
                    $conn->query("INSERT IGNORE INTO friends (user_id1, user_role1, user_id2, user_role2) VALUES ($u1, 'patient', $u2, 'patient')");
                    header("Location: $current_page?success=Friend request accepted!"); exit;
                }
            }
        }

        if (isset($_GET['reject_friend'])) {
            $req_id = intval($_GET['reject_friend']);
            $del = $conn->prepare("DELETE FROM friend_requests WHERE id=? AND receiver_id=?");
            if ($del) {
                $del->bind_param("ii", $req_id, $current_user_id);
                $del->execute();
            }
            header("Location: $current_page?success=Friend request rejected"); exit;
        }

        if (isset($_GET['clear_notif'])) {
            $notif_id = intval($_GET['clear_notif']);
            $del = $conn->prepare("DELETE FROM user_notifications WHERE id=? AND user_id=?");
            $del->bind_param("ii", $notif_id, $current_user_id);
            $del->execute();
            header("Location: $current_page"); exit;
        }
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

    // Find target user among patients only
    // Patients can only request to monitor other patients
    $stmt = $conn->prepare("SELECT id, 'patient' as role FROM patients WHERE email = ?");
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

    // Check for existing request (any status)
    $existing = $conn->prepare("SELECT id, status FROM monitor_requests WHERE requester_id=? AND requested_user_id=?");
    if ($existing) {
        $existing->bind_param("ii", $requester_id, $target_id);
        $existing->execute();
        $res = $existing->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['status'] === 'pending') {
                return ["type" => "warning", "msg" => "Request already sent. Waiting for their response."];
            }
            // If status is 'accepted' but reached here, or 'rejected', remove it to allow a new request
            $del = $conn->prepare("DELETE FROM monitor_requests WHERE id=?");
            $del->bind_param("i", $row['id']);
            $del->execute();
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

/**
 * Get total count of pending notifications (requests) for a user
 */
function getPendingNotificationCount($conn, $user_id) {
    $count = 0;
    
    // Monitor requests received
    $stmt1 = $conn->prepare("SELECT COUNT(*) FROM monitor_requests WHERE requested_user_id = ? AND status = 'pending'");
    if ($stmt1) {
        $stmt1->bind_param("i", $user_id);
        $stmt1->execute();
        $count += $stmt1->get_result()->fetch_row()[0];
    }

    // Report share requests received
    $stmt2 = $conn->prepare("SELECT COUNT(*) FROM report_share_requests WHERE patient_id = ? AND status = 'pending'");
    if ($stmt2) {
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $count += $stmt2->get_result()->fetch_row()[0];
    }

    // Friend requests received
    $stmt3 = $conn->prepare("SELECT COUNT(*) FROM friend_requests WHERE receiver_id = ? AND status = 'pending'");
    if ($stmt3) {
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();
        $count += $stmt3->get_result()->fetch_row()[0];
    }

    // Unread messages count
    $stmt5 = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    if ($stmt5) {
        $stmt5->bind_param("i", $user_id);
        $stmt5->execute();
        $count += $stmt5->get_result()->fetch_row()[0];
    }

    // General notifications count
    $stmt4 = $conn->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
    if ($stmt4) {
        $stmt4->bind_param("i", $user_id);
        $stmt4->execute();
        $count += $stmt4->get_result()->fetch_row()[0];
    }

    return $count;
}

/**
 * Get a combined list of all pending requests as notifications
 */
function getPendingNotifications($conn, $user_id) {
    $notifications = [];

    // Monitor requests
    $stmt1 = $conn->prepare("
        SELECT mr.id, p.name as sender_name, p.gender, 'monitor' as type, mr.created_at 
        FROM monitor_requests mr 
        JOIN patients p ON mr.requester_id = p.id 
        WHERE mr.requested_user_id = ? AND mr.status = 'pending'
        ORDER BY mr.created_at DESC
    ");
    if ($stmt1) {
        $stmt1->bind_param("i", $user_id);
        $stmt1->execute();
        $res1 = $stmt1->get_result();
        while ($row = $res1->fetch_assoc()) {
            $pronoun = ($row['gender'] === 'male') ? 'him' : (($row['gender'] === 'female') ? 'her' : 'them');
            $notifications[] = [
                'id' => $row['id'], 
                'title' => 'Monitor Request', 
                'desc' => $row['sender_name'] . ' wants you to monitor ' . $pronoun, 
                'type' => 'monitor', 
                'time' => $row['created_at'], 
                'param' => 'accept',
                'reject_param' => 'reject'
            ];
        }
    }

    // Report share requests
    $stmt2 = $conn->prepare("
        SELECT rsr.id, COALESCE(p.name, d.name) as sender_name, r.report_name, rsr.created_at 
        FROM report_share_requests rsr 
        JOIN reports r ON rsr.report_id = r.id
        LEFT JOIN patients p ON rsr.requester_role = 'monitor' AND rsr.requester_id = p.id
        LEFT JOIN doctors d ON rsr.requester_role = 'doctor' AND rsr.requester_id = d.id
        WHERE rsr.patient_id = ? AND rsr.status = 'pending'
        ORDER BY rsr.created_at DESC
    ");
    if ($stmt2) {
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'], 
                'title' => 'Report Request', 
                'desc' => $row['sender_name'] . ' requested access to ' . $row['report_name'], 
                'type' => 'report', 
                'time' => $row['created_at'], 
                'param' => 'accept_report',
                'reject_param' => 'reject_report'
            ];
        }
    }

    // Friend requests
    $stmt3 = $conn->prepare("
        SELECT fr.id, u.name as sender_name, fr.created_at 
        FROM friend_requests fr 
        JOIN users u ON fr.sender_id = u.id AND fr.sender_role = u.role
        WHERE fr.receiver_id = ? AND fr.status = 'pending'
        ORDER BY fr.created_at DESC
    ");
    if ($stmt3) {
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        while ($row = $res3->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'], 
                'title' => 'Friend Request', 
                'desc' => $row['sender_name'] . ' sent you a friend request', 
                'type' => 'friend', 
                'time' => $row['created_at'], 
                'param' => 'accept_friend',
                'reject_param' => 'reject_friend'
            ];
        }
    }

    // Fetch Unread Messages as notifications
    $stmt5 = $conn->prepare("
        SELECT m.sender_id, u.name as sender_name, COUNT(*) as msg_count 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id AND m.sender_role = u.role
        WHERE m.receiver_id = ? AND m.is_read = 0 
        GROUP BY m.sender_id
    ");
    if ($stmt5) {
        $stmt5->bind_param("i", $user_id);
        $stmt5->execute();
        $res5 = $stmt5->get_result();
        while ($row = $res5->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['sender_id'], 
                'title' => 'New Message', 
                'desc' => 'You have ' . $row['msg_count'] . ' unread message(s) from ' . $row['sender_name'], 
                'type' => 'chat', 
                'time' => date('Y-m-d H:i:s'), 
                'param' => 'friend_id'
            ];
        }
    }

    // Fetch General Notifications (Rejections, etc.)
    $stmt4 = $conn->prepare("
        SELECT id, title, message, created_at 
        FROM user_notifications 
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
    ");
    if ($stmt4) {
        $stmt4->bind_param("i", $user_id);
        $stmt4->execute();
        $res4 = $stmt4->get_result();
        while ($row = $res4->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'], 
                'title' => $row['title'], 
                'desc' => $row['message'], 
                'type' => 'info', 
                'time' => $row['created_at'], 
                'param' => 'clear_notif'
            ];
        }
    }
    return $notifications;
}
?>
