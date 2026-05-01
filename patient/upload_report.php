<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// Handle profile update
if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_address = trim($_POST['address']);
    
    $update_sql = "UPDATE users SET name=?, address=? WHERE id=?";
    $params = [$new_name, $new_address, $patient_id];
    $types = "ssi";

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../images/profiles/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_file_name = uniqid('profile_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_file_name)) {
                $db_path = "images/profiles/" . $new_file_name;
                $update_sql = "UPDATE users SET name=?, address=?, profile_picture=? WHERE id=?";
                $params = [$new_name, $new_address, $db_path, $patient_id];
                $types = "sssi";
            }
        }
    }

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $_SESSION['name'] = $new_name;
        header("Location: upload_report.php?success=ProfileUpdated");
        exit;
    }
}

// Ensure share request table exists
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

// Handle share request accept
if (isset($_GET['accept'])) {
    $request_id = intval($_GET['accept']);
    $req = $conn->prepare("SELECT id FROM report_share_requests WHERE id=? AND patient_id=? AND status='pending'");
    if ($req) {
        $req->bind_param("ii", $request_id, $patient_id);
        $req->execute();
        $result = $req->get_result();
        if ($result && $result->num_rows > 0) {
            $acc = $conn->prepare("UPDATE report_share_requests SET status='accepted' WHERE id=?");
            if ($acc) {
                $acc->bind_param("i", $request_id);
                $acc->execute();
                header("Location: upload_report.php?share_success=accepted");
                exit;
            }
        }
    }
    header("Location: upload_report.php");
    exit;
}

// Handle share request reject
if (isset($_GET['reject'])) {
    $request_id = intval($_GET['reject']);
    $req = $conn->prepare("SELECT id FROM report_share_requests WHERE id=? AND patient_id=? AND status='pending'");
    if ($req) {
        $req->bind_param("ii", $request_id, $patient_id);
        $req->execute();
        $result = $req->get_result();
        if ($result && $result->num_rows > 0) {
            $rej = $conn->prepare("UPDATE report_share_requests SET status='rejected' WHERE id=?");
            if ($rej) {
                $rej->bind_param("i", $request_id);
                $rej->execute();
                header("Location: upload_report.php?share_success=rejected");
                exit;
            }
        }
    }
    header("Location: upload_report.php");
    exit;
}

// Success messages
if (isset($_GET['success'])) {
    $msg = "Report uploaded successfully!";
    $msg_type = "success";
} elseif (isset($_GET['share_success'])) {
    if ($_GET['share_success'] === 'accepted') {
        $msg = "Report request accepted successfully.";
        $msg_type = "success";
    } elseif ($_GET['share_success'] === 'rejected') {
        $msg = "Report request rejected successfully.";
        $msg_type = "warning";
    }
}

// Upload report
if (isset($_POST['upload'])) {
    $report_name = trim($_POST['report_name']);
    $report_type = $_POST['report_type'];

    if (!empty($_FILES['report_file']['name'])) {
        $upload_dir = "../uploads/reports/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            $msg = "Only PDF, JPG, PNG allowed";
            $msg_type = "error";
        } elseif ($_FILES['report_file']['size'] > 5 * 1024 * 1024) {
            $msg = "File must be under 5MB";
            $msg_type = "error";
        } else {
            $filename = time() . "_" . basename($_FILES['report_file']['name']);
            $target = $upload_dir . $filename;
            $db_path = "uploads/reports/" . $filename;

            if (move_uploaded_file($_FILES['report_file']['tmp_name'], $target)) {
                $stmt = $conn->prepare("INSERT INTO reports (patient_id, report_name, report_type, file_path, created_at) VALUES (?, ?, ?, ?, NOW())");

                if ($stmt) {
                    $stmt->bind_param("isss", $patient_id, $report_name, $report_type, $db_path);
                    $stmt->execute();
                    header("Location: upload_report.php?success=1");
                    exit;
                }
            } else {
                $msg = "Upload failed.";
                $msg_type = "error";
            }
        }
    } else {
        $msg = "Select a file";
        $msg_type = "error";
    }
}

// Fetch pending share requests
$pending_share_requests = null;
$stmt = $conn->prepare("
    SELECT rsr.*, r.report_name, u.name as requester_name
    FROM report_share_requests rsr
    JOIN reports r ON rsr.report_id = r.id
    JOIN users u ON rsr.requester_id = u.id
    WHERE rsr.patient_id = ? AND rsr.status = 'pending'
    ORDER BY rsr.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $pending_share_requests = $stmt->get_result();
}

// Fetch accepted shares
$accepted_shares = null;
$stmt = $conn->prepare("
    SELECT rsr.*, r.report_name, u.name as requester_name
    FROM report_share_requests rsr
    JOIN reports r ON rsr.report_id = r.id
    JOIN users u ON rsr.requester_id = u.id
    WHERE rsr.patient_id = ? AND rsr.status = 'accepted'
    ORDER BY rsr.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $accepted_shares = $stmt->get_result();
}

// Fetch reports
$reports = null;
$stmt = $conn->prepare("SELECT * FROM reports WHERE patient_id=? ORDER BY created_at DESC");
if ($stmt !== false) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $reports = $stmt->get_result();
} else {
    $reports = false;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Upload Report — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');

:root {
    --teal: #0EB8A0;
    --teal-dark: #0A8A78;
    --teal-glow: rgba(14,184,160,0.15);
    --navy: #f8fafc;
    --navy-mid: #ffffff;
    --navy-light: #f1f5f9;
    --navy-card: #ffffff;
    --white: #1e293b;
    --cream: #F5F0E8;
    --muted: #64748b;
    --muted-dim: #94a3b8;
    --accent: #FF6B4A;
    --success: #22C55E;
    --warning: #F59E0B;
    --danger: #EF4444;
    --border: rgba(0,0,0,0.08);
    --radius: 14px;
    --radius-lg: 22px;
    --shadow: 0 4px 20px rgba(0,0,0,0.05);
}

body.dark-mode {
    --navy: #0B1526;
    --navy-mid: #112035;
    --navy-light: #1A3050;
    --navy-card: #0F1E36;
    --white: #FFFFFF;
    --muted: #7A8EA8;
    --muted-dim: #4A5E78;
    --border: rgba(255,255,255,0.07);
    --shadow: 0 8px 32px rgba(0,0,0,0.25);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--navy);
    color: var(--white);
    min-height: 100vh;
    line-height: 1.6;
}

.page-bg {
    position: fixed; inset: 0; z-index: -1;
    background:
        radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),
        var(--navy);
}

.layout { display: flex; min-height: 100vh; }

.sidebar {
    width: 240px;
    background: var(--navy-card);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 50;
    padding: 24px 0;
}
.sidebar-logo {
    display: flex; align-items: center; gap: 10px;
    padding: 0 24px 28px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
    text-decoration: none;
}
.logo-dot {
    width: 9px; height: 9px;
    background: var(--teal);
    border-radius: 50%;
    box-shadow: 0 0 10px var(--teal);
    animation: blink 2s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }
.logo-text {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.15rem; font-weight: 700;
    color: var(--white);
}

.nav-section { padding: 0 12px; margin-bottom: 8px; }
.nav-section-label {
    font-size: 0.65rem; text-transform: uppercase;
    letter-spacing: 0.1em; color: var(--muted-dim);
    font-weight: 600; padding: 0 12px 8px;
}
.nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    color: var(--muted); text-decoration: none;
    font-size: 0.875rem; font-weight: 500;
    transition: all 0.2s; margin-bottom: 2px;
}
.nav-link:hover { background: var(--teal-glow); color: var(--white); }
.nav-link.active { background: var(--teal-glow); color: var(--teal); }
.nav-icon { font-size: 1rem; width: 20px; text-align: center; }

.sidebar-bottom {
    margin-top: auto;
    padding: 16px 12px 0;
    border-top: 1px solid var(--border);
}

.main {
    margin-left: 240px;
    flex: 1;
    padding: 36px 40px;
    max-width: calc(100% - 240px);
}

.page-header { margin-bottom: 32px; }
.page-header h1 {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.8rem; font-weight: 600;
    margin-bottom: 4px;
}
.page-header p { color: var(--muted); font-size: 0.9rem; }

.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}

.grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }

.form-group { margin-bottom: 20px; }
.form-label {
    display: block; font-size: 0.8rem;
    font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.form-input, .form-select {
    width: 100%; padding: 12px 16px;
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--white); font-size: 0.9rem;
    font-family: 'DM Sans', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.form-input:focus, .form-select:focus {
    border-color: rgba(14,184,160,0.5);
    box-shadow: 0 0 0 3px rgba(14,184,160,0.1);
}
.form-input::placeholder { color: var(--muted-dim); }
.form-select option { background: var(--navy-mid); }

.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px; border-radius: 50px;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; border: none;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none; transition: all 0.2s;
}
.btn-primary {
    background: var(--teal); color: var(--navy);
    box-shadow: 0 0 20px rgba(14,184,160,0.25);
}
.btn-primary:hover {
    background: var(--teal-dark); color: var(--white);
    transform: translateY(-1px);
    box-shadow: 0 0 30px rgba(14,184,160,0.35);
}
.btn-secondary {
    background: var(--navy-light); color: var(--white);
    border: 1px solid var(--border);
}
.btn-secondary:hover { background: var(--navy-mid); transform: translateY(-1px); }
.btn-danger { background: rgba(239,68,68,0.15); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }
.btn-danger:hover { background: rgba(239,68,68,0.25); }
.btn-sm { padding: 7px 16px; font-size: 0.8rem; }
.btn-full { width: 100%; justify-content: center; }

.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 50px;
    font-size: 0.72rem; font-weight: 600;
}
.badge-success { background: rgba(34,197,94,0.12); color: var(--success); }
.badge-warning { background: rgba(245,158,11,0.12); color: var(--warning); }

.alert {
    padding: 14px 18px; border-radius: var(--radius);
    font-size: 0.875rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--danger); }
.alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: var(--warning); }

.empty-state {
    text-align: center; padding: 40px 20px;
    color: var(--muted);
}
.empty-state .empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 1rem; font-weight: 600; margin-bottom: 8px; color: var(--white); }
.empty-state p { font-size: 0.85rem; }

.upload-zone {
    border: 2px dashed rgba(14,184,160,0.3);
    border-radius: var(--radius);
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: rgba(14,184,160,0.03);
    position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--teal);
    background: rgba(14,184,160,0.07);
}
.upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-icon { font-size: 2.5rem; margin-bottom: 12px; }
.upload-zone h3 { font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; }
.upload-zone p { font-size: 0.8rem; color: var(--muted); }
.file-name-display {
    margin-top: 12px; font-size: 0.85rem;
    color: var(--teal); font-weight: 500;
    display: none;
}

.request-item {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:16px;
    background:var(--navy-light);
    border:1px solid var(--border);
    border-radius:var(--radius);
    margin-bottom:12px;
    flex-wrap:wrap;
}
.request-info { flex:1; min-width:240px; }
.request-title { font-weight:600; font-size:0.95rem; margin-bottom:4px; }
.request-meta { color:var(--muted); font-size:0.82rem; }
.request-actions { display:flex; gap:10px; flex-wrap:wrap; }

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0; max-width: 100%; padding: 20px; }
    .grid-2 { grid-template-columns: 1fr; }
}
</style>
</head><body><div class="page-bg"></div><div class="layout">

<aside class="sidebar">
    <div class="sidebar-logo" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">MediConnect</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section"><div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link"><span class="nav-icon">📅</span> Book Appointment</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Health</div>
        <a href="checklist.php" class="nav-link"><span class="nav-icon">💊</span> My Medicines</a>
        <a href="upload_report.php" class="nav-link active"><span class="nav-icon">📄</span> Upload Report</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <h1>📄 Upload Report</h1>
        <p>Upload your medical reports, review report requests, and approve access from one place.</p>
    </div>

    <?php if($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>">
        <?php echo $msg_type=='success' ? '✅' : ($msg_type=='warning' ? '⚠️' : '❌'); ?> <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:18px;">📬 Pending Report Requests</h2>
        <?php if ($pending_share_requests && $pending_share_requests->num_rows > 0): ?>
            <?php while ($req = $pending_share_requests->fetch_assoc()):
                $title = ($req['requester_role'] === 'doctor') ? 'Dr.' : 'Monitor';
            ?>
                <div class="request-item">
                    <div class="request-info">
                        <div class="request-title"><?php echo htmlspecialchars($title . ' ' . $req['requester_name']); ?> requested access</div>
                        <div class="request-meta"><?php echo htmlspecialchars($req['report_name']); ?> · <?php echo date('d M Y', strtotime($req['created_at'])); ?></div>
                    </div>
                    <div class="request-actions">
                        <a href="upload_report.php?accept=<?php echo $req['id']; ?>" class="btn btn-primary btn-sm">✅ Accept</a>
                        <a href="upload_report.php?reject=<?php echo $req['id']; ?>" class="btn btn-danger btn-sm">❌ Reject</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No pending report requests</h3>
                <p>Any doctor or monitor requests for your reports will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:18px;">✅ Accepted Report Access</h2>
        <?php if ($accepted_shares && $accepted_shares->num_rows > 0): ?>
            <?php while ($share = $accepted_shares->fetch_assoc()):
                $title = ($share['requester_role'] === 'doctor') ? 'Dr.' : 'Monitor';
            ?>
                <div class="request-item">
                    <div class="request-info">
                        <div class="request-title"><?php echo htmlspecialchars($title . ' ' . $share['requester_name']); ?> has access</div>
                        <div class="request-meta"><?php echo htmlspecialchars($share['report_name']); ?> · Shared since <?php echo date('d M Y', strtotime($share['created_at'])); ?></div>
                    </div>
                    <div>
                        <span class="badge badge-success">Shared</span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔐</div>
                <h3>No accepted shares yet</h3>
                <p>Accepted report access will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid-2" style="align-items:start;">
        <div class="card">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:20px;">Upload New Report</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-group">
                    <label class="form-label">Report Name</label>
                    <input class="form-input" type="text" name="report_name" placeholder="e.g. Blood Test - March 2026" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="report_type" required>
                        <option value="">— Select type —</option>
                        <option value="Blood Test">🩸 Blood Test</option>
                        <option value="X-Ray">🦴 X-Ray</option>
                        <option value="MRI">🧠 MRI Scan</option>
                        <option value="CT Scan">🔬 CT Scan</option>
                        <option value="Urine Test">🧪 Urine Test</option>
                        <option value="ECG">❤️ ECG</option>
                        <option value="Prescription">💊 Prescription</option>
                        <option value="Other">📋 Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">File</label>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file" name="report_file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" required onchange="showFileName(this)">
                        <div class="upload-icon">📁</div>
                        <h3>Click or drag file here</h3>
                        <p>PDF, JPG, PNG — max 5MB</p>
                        <div class="file-name-display" id="fileNameDisplay"></div>
                    </div>
                </div>

                <button class="btn btn-primary btn-full" type="submit" name="upload">📤 Upload Report</button>
            </form>
        </div>

        <div class="card">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:20px;">Previous Reports</h2>

            <?php if($reports && $reports->num_rows > 0): ?>
                <?php while($row = $reports->fetch_assoc()):
                    $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                    $icon = $ext === 'pdf' ? '📄' : '🖼️';
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);gap:12px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;background:var(--teal-glow);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><?php echo $icon; ?></div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($row['report_name']); ?></div>
                            <div style="font-size:0.75rem;color:var(--muted);"><?php echo htmlspecialchars($row['report_type']); ?> · <?php echo date('d M Y', strtotime($row['created_at'])); ?></div>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars('../' . $row['file_path']); ?>" target="_blank" class="btn btn-secondary btn-sm">👁️ View</a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📂</div>
                    <h3>No reports uploaded yet</h3>
                    <p>Your uploaded reports will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Floating Chatbot Widget -->
<div class="chatbot-fab" id="chatbotFab">
    <span>🤖</span>
</div>

<div class="chatbot-modal" id="chatbotModal">
    <div class="chatbot-header">
        <span>AI Health Assistant</span>
        <button class="chatbot-close" id="chatbotClose">✕</button>
    </div>
    <iframe id="chatbotIframe" src="../chatbot_widget_content.php" frameborder="0"></iframe>
</div>

<style>
/* Floating Action Button for Chatbot */
.chatbot-fab {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: var(--teal);
    color: var(--navy);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 1000;
    transition: all 0.3s ease;
}
.chatbot-fab:hover { background: var(--teal-dark); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }

/* Chatbot Modal/Panel */
.chatbot-modal {
    position: fixed;
    bottom: 90px; /* Above the FAB */
    right: 20px;
    width: 350px;
    height: 500px;
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    z-index: 999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(0.95);
    opacity: 0;
    pointer-events: none;
}
.chatbot-modal.show { display: flex; transform: translateY(0) scale(1); opacity: 1; pointer-events: auto; }
.chatbot-header { background: var(--teal); color: var(--navy); padding: 12px 15px; font-weight: 600; font-size: 1.1rem; display: flex; justify-content: space-between; align-items: center; }
.chatbot-close { background: none; border: none; color: var(--navy); font-size: 1.2rem; cursor: pointer; opacity: 0.8; }
.chatbot-close:hover { opacity: 1; }
.chatbot-modal iframe { flex: 1; width: 100%; height: 100%; border: none; }

@media (max-width: 600px) {
    .chatbot-fab { bottom: 15px; right: 15px; width: 50px; height: 50px; font-size: 1.5rem; }
    .chatbot-modal { bottom: 75px; right: 15px; width: calc(100% - 30px); height: 70vh; max-height: 500px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chatbotFab = document.getElementById('chatbotFab');
    const chatbotModal = document.getElementById('chatbotModal');
    const chatbotClose = document.getElementById('chatbotClose');

    if (chatbotFab && chatbotModal && chatbotClose) {
        chatbotFab.addEventListener('click', () => {
            chatbotModal.classList.toggle('show');
        });

        chatbotClose.addEventListener('click', () => {
            chatbotModal.classList.remove('show');
        });

        // Close if clicked outside modal
        document.addEventListener('click', (event) => {
            if (!chatbotModal.contains(event.target) && !chatbotFab.contains(event.target) && chatbotModal.classList.contains('show')) {
                chatbotModal.classList.remove('show');
            }
        });
    }
});
</script>
</div>

<!-- Edit Profile Modal -->
<div id="editProfileModal">
    <div class="modal-content" style="animation:modalSlideIn 0.3s ease-out;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:20px;color:var(--white);">Edit Profile</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="text-align:center; margin-bottom:20px;">
                <label for="profilePictureInput" style="cursor:pointer; display:inline-block; position:relative;">
                    <img id="profilePicturePreview" src="../<?php echo htmlspecialchars($user_pic_acc ?: 'images/default_user.png'); ?>" 
                         style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid var(--teal);">
                    <div style="position:absolute; bottom:0; right:0; background:var(--teal); color:var(--navy); border-radius:50%; padding:6px; font-size:0.9rem; line-height:1; border:2px solid var(--navy-card);">✏️</div>
                </label>
                <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" style="display:none;" onchange="previewProfilePicture(event)">
            </div>
            <div class="form-group">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="<?php echo htmlspecialchars($user_name_acc); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input class="form-input" type="text" name="address" value="<?php echo htmlspecialchars($user_data_acc['address'] ?? ''); ?>">
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="hideEditProfileModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function showEditProfileModal() { document.getElementById('editProfileModal').style.display = 'flex'; }
function hideEditProfileModal() { document.getElementById('editProfileModal').style.display = 'none'; }
function hideAccountDropdown() { const d = document.getElementById('accountDropdown'); if(d) d.classList.remove('show'); }
function previewProfilePicture(event) {
    const reader = new FileReader();
    reader.onload = function(){ document.getElementById('profilePicturePreview').src = reader.result; };
    reader.readAsDataURL(event.target.files[0]);
}
function showFileName(input) {
    const display = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        display.textContent = '📎 ' + input.files[0].name;
        display.style.display = 'block';
    }
}

const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', () => zone.classList.add('dragover'));
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', () => zone.classList.remove('dragover'));

// Auto-hide alerts after 7 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.5s ease';
        a.style.opacity = '0';
        setTimeout(() => a.style.display = 'none', 500);
    });
}, 7000);

// Clear URL parameters so they don't reappear on refresh
if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('success'); 
    url.searchParams.delete('share_success');
    window.history.replaceState({}, document.title, url);
}

// Theme Toggle Logic
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
}
themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
});
</script>
</body></html>
