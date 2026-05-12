<?php
if (!isset($conn)) {
    require_once(__DIR__ . '/../config/db.php');
}

function ensureAdminSchema($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $doctorColumns = [
        'verification_status' => "ALTER TABLE doctors ADD COLUMN verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending' AFTER affiliations",
        'verified_at' => "ALTER TABLE doctors ADD COLUMN verified_at DATETIME DEFAULT NULL AFTER verification_status",
        'verified_by_admin_id' => "ALTER TABLE doctors ADD COLUMN verified_by_admin_id INT DEFAULT NULL AFTER verified_at",
    ];

    foreach ($doctorColumns as $column => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM doctors LIKE '{$column}'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $adminCount = $conn->query("SELECT COUNT(*) AS total FROM admins");
    $hasAdmin = $adminCount ? (int)($adminCount->fetch_assoc()['total'] ?? 0) : 0;
    if ($hasAdmin === 0) {
        $name = 'System Admin';
        $email = 'admin@mediconnect.local';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $name, $email, $password);
            $stmt->execute();
        }
    }
}

function requireAdminSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: ../login.php");
        exit;
    }
}

function getVerificationBadgeStyles($status) {
    switch ($status) {
        case 'verified':
            return 'background:rgba(34,197,94,0.14);color:#22C55E;border:1px solid rgba(34,197,94,0.28);';
        case 'rejected':
            return 'background:rgba(239,68,68,0.14);color:#EF4444;border:1px solid rgba(239,68,68,0.28);';
        default:
            return 'background:rgba(245,158,11,0.14);color:#F59E0B;border:1px solid rgba(245,158,11,0.28);';
    }
}
