<?php
/**
 * monitor_core.php - Centralized Monitoring Logic
 */

if (!isset($conn)) {
    require_once(__DIR__ . "/../config/db.php");
}

/**
 * Initialize necessary tables if they don't exist
 */
function initMonitorTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS monitor_requests (
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

    $conn->query("CREATE TABLE IF NOT EXISTS report_share_requests (
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
    )");
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
            $req = $conn->prepare("SELECT requester_id FROM monitor_requests WHERE id=? AND requested_user_id=? AND status='pending'");
            $req->bind_param("ii", $request_id, $current_user_id);
            $req->execute();
            $res = $req->get_result();
            if ($row = $res->fetch_assoc()) {
                $requester_id = $row['requester_id'];
                // Re-link correctly based on who requested (requester becomes patient, current user becomes monitor OR vice versa)
                // Standard logic: Requester wants to monitor or be monitored. 
                // If Doctor requests Patient: requester=doctor, requested=patient.
                // If Patient requests Doctor: requester=patient, requested=doctor.
                // The table patient_monitors is (patient_id, monitor_id).
                
                $check_role = $conn->query("SELECT role FROM users WHERE id='$requester_id'")->fetch_assoc()['role'];
                $p_id = ($check_role == 'patient') ? $requester_id : $current_user_id;
                $m_id = ($check_role == 'patient') ? $current_user_id : $requester_id;

                $ins = $conn->prepare("INSERT IGNORE INTO patient_monitors (patient_id, monitor_id) VALUES (?, ?)");
                $ins->bind_param("ii", $p_id, $m_id);
                if($ins->execute()){
                    $upd = $conn->prepare("UPDATE monitor_requests SET status='accepted' WHERE id=?");
                    $upd->bind_param("i", $request_id);
                    $upd->execute();
                }
            }
            header("Location: $current_page?success=Request accepted!"); exit;
        }

        if (isset($_GET['reject'])) {
            $request_id = intval($_GET['reject']);
            $conn->query("DELETE FROM monitor_requests WHERE id='$request_id' AND requested_user_id='$current_user_id'");
            header("Location: $current_page?success=Request rejected"); exit;
        }

        if (isset($_GET['remove_monitor']) || isset($_GET['remove_patient'])) {
            $target_id = intval($_GET['remove_monitor'] ?? $_GET['remove_patient']);
            $conn->query("DELETE FROM patient_monitors WHERE (patient_id='$current_user_id' AND monitor_id='$target_id') OR (patient_id='$target_id' AND monitor_id='$current_user_id')");
            header("Location: $current_page?success=Monitoring link removed"); exit;
        }
    }

    // Handle Report Share Actions
    if (isset($_GET['accept_report'])) {
        $request_id = intval($_GET['accept_report']);
        $upd = $conn->prepare("UPDATE report_share_requests SET status='accepted' WHERE id=? AND patient_id=?");
        $upd->bind_param("ii", $request_id, $current_user_id);
        $upd->execute();
        header("Location: $current_page?success=Report access granted!"); exit;
    }

    if (isset($_GET['reject_report'])) {
        $request_id = intval($_GET['reject_report']);
        $upd = $conn->prepare("UPDATE report_share_requests SET status='rejected' WHERE id=? AND patient_id=?");
        $upd->bind_param("ii", $request_id, $current_user_id);
        $upd->execute();
        header("Location: $current_page?success=Report request rejected"); exit;
    }
}

/**
 * Centralized function to send a monitor request
 */
function sendMonitorRequest($conn, $requester_id, $target_email, $target_role = null) {
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $target_email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) return ["type" => "error", "msg" => "User not found with that email."];
    if ($user['id'] == $requester_id) return ["type" => "error", "msg" => "You cannot add yourself."];
    if ($target_role && $user['role'] !== $target_role) return ["type" => "error", "msg" => "Target user is not a $target_role."];

    $target_id = $user['id'];

    // Check existing
    $active = $conn->query("SELECT id FROM patient_monitors WHERE (patient_id='$requester_id' AND monitor_id='$target_id') OR (patient_id='$target_id' AND monitor_id='$requester_id')");
    if ($active->num_rows > 0) return ["type" => "warning", "msg" => "Monitoring link already exists."];

    $pending = $conn->query("SELECT id FROM monitor_requests WHERE requester_id='$requester_id' AND requested_user_id='$target_id' AND status='pending'");
    if ($pending->num_rows > 0) return ["type" => "warning", "msg" => "Request already sent. Waiting for response."];

    $ins = $conn->prepare("INSERT INTO monitor_requests (requester_id, requested_user_id, status) VALUES (?, ?, 'pending')");
    $ins->bind_param("ii", $requester_id, $target_id);
    if ($ins->execute()) {
        return ["type" => "success", "msg" => "✅ Request sent! They must accept before monitoring begins."];
    }
    return ["type" => "error", "msg" => "Error sending request."];
}
?>