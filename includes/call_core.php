<?php
if (!isset($conn)) {
    require_once(__DIR__ . '/../config/db.php');
}

function ensureVideoCallSchema($conn) {
    $statusColumn = $conn->query("SHOW COLUMNS FROM video_calls LIKE 'status'");
    if ($statusColumn && ($row = $statusColumn->fetch_assoc())) {
        $type = $row['Type'] ?? '';
        if (strpos($type, "'missed'") === false || strpos($type, "'declined'") === false) {
            $conn->query("ALTER TABLE video_calls MODIFY COLUMN status ENUM('waiting','active','ongoing','ended','missed','declined') DEFAULT 'waiting'");
        }
    }

    $requiredColumns = [
        "initiated_by" => "ALTER TABLE video_calls ADD COLUMN initiated_by ENUM('doctor','patient') NOT NULL DEFAULT 'patient' AFTER doctor_id",
        "ended_reason" => "ALTER TABLE video_calls ADD COLUMN ended_reason VARCHAR(50) DEFAULT NULL AFTER status",
        "answered_at" => "ALTER TABLE video_calls ADD COLUMN answered_at DATETIME DEFAULT NULL AFTER created_at",
        "ended_at" => "ALTER TABLE video_calls ADD COLUMN ended_at DATETIME DEFAULT NULL AFTER answered_at",
        "patient_ready_notified" => "ALTER TABLE video_calls ADD COLUMN patient_ready_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER ended_at",
    ];

    foreach ($requiredColumns as $column => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM video_calls LIKE '{$column}'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

function addGenericUserNotification($conn, $user_id, $title, $message) {
    $conn->query("CREATE TABLE IF NOT EXISTS user_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iss", $user_id, $title, $message);
    return $stmt->execute();
}

function expireWaitingCalls($conn) {
    ensureVideoCallSchema($conn);

    $sql = "
        SELECT vc.id, vc.patient_id, vc.doctor_id, vc.initiated_by, d.name AS doctor_name
        FROM video_calls vc
        LEFT JOIN doctors d ON d.id = vc.doctor_id
        WHERE vc.status = 'waiting'
          AND vc.created_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ";
    $result = $conn->query($sql);
    if (!$result) {
        return;
    }

    $update = $conn->prepare("UPDATE video_calls SET status = 'missed', ended_reason = 'missed', ended_at = NOW() WHERE id = ? AND status = 'waiting'");
    if (!$update) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $callId = (int)$row['id'];
        $update->bind_param("i", $callId);
        $update->execute();

        if ($update->affected_rows < 1) {
            continue;
        }

        if (($row['initiated_by'] ?? '') === 'doctor' && !empty($row['patient_id'])) {
            $doctorName = trim((string)($row['doctor_name'] ?? 'Doctor'));
            addGenericUserNotification(
                $conn,
                (int)$row['patient_id'],
                "Missed Consultation Call",
                "Dr. {$doctorName} tried to call you for your consultation. Open your appointments and let the doctor know when you are ready."
            );
        }
    }
}

function fetchIncomingDoctorCall($conn, $patient_id) {
    ensureVideoCallSchema($conn);
    expireWaitingCalls($conn);

    $stmt = $conn->prepare("
        SELECT vc.*, d.name AS doctor_name
        FROM video_calls vc
        JOIN doctors d ON d.id = vc.doctor_id
        WHERE vc.patient_id = ?
          AND vc.initiated_by = 'doctor'
          AND vc.status = 'waiting'
        ORDER BY vc.created_at DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row) {
        return null;
    }

    $secondsLeft = max(0, 60 - (time() - strtotime($row['created_at'])));
    if ($secondsLeft <= 0) {
        expireWaitingCalls($conn);
        return null;
    }

    $row['seconds_left'] = $secondsLeft;
    return $row;
}
