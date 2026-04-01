<?php
session_start(); require_once("../config/db.php");
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') { header("Location: ../login.php"); exit; }
$patient_id = $_SESSION['user_id'];
$msg = ""; $msg_type = "";
 
// Handle upload
if (isset($_POST['upload'])) {
    $report_name = trim($_POST['report_name']);
    $report_type = $_POST['report_type'];
 
    if (!empty($_FILES['report_file']['name'])) {
        $upload_dir = "../uploads/reports/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
 
        $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
 
        if (!in_array($ext, $allowed)) {
            $msg = "Only PDF, JPG, and PNG files are allowed."; $msg_type = "error";
        } elseif ($_FILES['report_file']['size'] > 5 * 1024 * 1024) {
            $msg = "File size must be under 5MB."; $msg_type = "error";
        } else {
            $filename = time() . "_" . basename($_FILES['report_file']['name']);
            $target   = $upload_dir . $filename;
 
            if (move_uploaded_file($_FILES['report_file']['tmp_name'], $target)) {
                // Save to DB — insert into reports table if it exists, else just confirm
                $check = $conn->query("SHOW TABLES LIKE 'reports'");
                if ($check->num_rows > 0) {
                    $stmt = $conn->prepare("INSERT INTO reports (patient_id, report_name, report_type, file_path) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("isss", $patient_id, $report_name, $report_type, $target);
                        $stmt->execute();
                    }
                }
                $msg = "Report uploaded successfully!"; $msg_type = "success";
            } else {
                $msg = "Upload failed. Please try again."; $msg_type = "error";
            }
        }
    } else {
        $msg = "Please select a file to upload."; $msg_type = "error";
    }
}
 
// Fetch previous reports if table exists
$reports = null;
$check = $conn->query("SHOW TABLES LIKE 'reports'");
if ($check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM reports WHERE patient_id = ? ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $patient_id); $stmt->execute();
        $reports = $stmt->get_result();
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Upload Report — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
 
:root {
    --teal:       #0EB8A0;
    --teal-dark:  #0A8A78;
    --teal-glow:  rgba(14,184,160,0.15);
    --navy:       #0B1526;
    --navy-mid:   #112035;
    --navy-light: #1A3050;
    --navy-card:  #0F1E36;
    --white:      #FFFFFF;
    --cream:      #F5F0E8;
    --muted:      #7A8EA8;
    --muted-dim:  #4A5E78;
    --accent:     #FF6B4A;
    --success:    #22C55E;
    --warning:    #F59E0B;
    --danger:     #EF4444;
    --border:     rgba(255,255,255,0.07);
    --radius:     14px;
    --radius-lg:  22px;
    --shadow:     0 8px 32px rgba(0,0,0,0.25);
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
 
/* BG */
.page-bg {
    position: fixed; inset: 0; z-index: -1;
    background:
        radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),
        var(--navy);
}
 
/* SIDEBAR NAV */
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
 
/* MAIN CONTENT */
.main {
    margin-left: 240px;
    flex: 1;
    padding: 36px 40px;
    max-width: calc(100% - 240px);
}
 
/* PAGE HEADER */
.page-header { margin-bottom: 32px; }
.page-header h1 {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.8rem; font-weight: 600;
    margin-bottom: 4px;
}
.page-header p { color: var(--muted); font-size: 0.9rem; }
 
/* CARDS */
.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow);
}
.card-sm { padding: 20px; border-radius: var(--radius); }
 
/* GRID */
.grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
 
/* STAT CARD */
.stat-card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 24px;
}
.stat-card-icon {
    width: 40px; height: 40px;
    background: var(--teal-glow);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin-bottom: 14px;
}
.stat-card-value {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.8rem; font-weight: 700;
    color: var(--white); line-height: 1;
    margin-bottom: 4px;
}
.stat-card-label { font-size: 0.78rem; color: var(--muted); font-weight: 500; }
 
/* FORMS */
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
 
/* BUTTONS */
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
 
/* TABLE */
.table-wrap { overflow-x: auto; border-radius: var(--radius); }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
    text-align: left; padding: 12px 16px;
    font-size: 0.72rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--muted); border-bottom: 1px solid var(--border);
}
tbody td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }
 
/* BADGES */
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 50px;
    font-size: 0.72rem; font-weight: 600;
}
.badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
.badge-success { background: rgba(34,197,94,0.12); color: var(--success); }
.badge-warning { background: rgba(245,158,11,0.12); color: var(--warning); }
.badge-danger  { background: rgba(239,68,68,0.12);  color: var(--danger); }
.badge-info    { background: var(--teal-glow); color: var(--teal); }
 
/* ALERT */
.alert {
    padding: 14px 18px; border-radius: var(--radius);
    font-size: 0.875rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.2);  color: var(--danger); }
.alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: var(--warning); }
 
/* DIVIDER */
.divider { height: 1px; background: var(--border); margin: 24px 0; }
 
/* EMPTY STATE */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--muted);
}
.empty-state .empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 1rem; font-weight: 600; margin-bottom: 8px; color: var(--white); }
.empty-state p { font-size: 0.85rem; }
 
/* RESPONSIVE */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0; max-width: 100%; padding: 20px; }
    .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
}
</style>
<style>
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
.upload-zone p  { font-size: 0.8rem; color: var(--muted); }
.file-name-display {
    margin-top: 12px; font-size: 0.85rem;
    color: var(--teal); font-weight: 500;
    display: none;
}
.report-icon { font-size: 1.4rem; }
</style>
</head><body><div class="page-bg"></div><div class="layout">
 
<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section"><div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link"><span class="nav-icon">📅</span> Book Appointment</a>
        <a href="../chatbot.php" class="nav-link"><span class="nav-icon">🤖</span> Health Assistant</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Health</div>
        <a href="checklist.php" class="nav-link"><span class="nav-icon">💊</span> My Medicines</a>
        <a href="add_checklist.php" class="nav-link"><span class="nav-icon">➕</span> Add Medicine</a>
        <a href="upload_report.php" class="nav-link active"><span class="nav-icon">📄</span> Upload Report</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
    <div class="sidebar-bottom"><a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a></div>
</aside>
 
<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <h1>📄 Upload Report</h1>
        <p>Upload your medical reports and lab results to share with your doctor.</p>
    </div>
 
    <div class="grid-2" style="align-items:start;">
 
        <!-- UPLOAD FORM -->
        <div class="card">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:20px;">Upload New Report</h2>
 
            <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo $msg_type=='success' ? '✅' : '❌'; ?> <?php echo htmlspecialchars($msg); ?>
            </div>
            <?php endif; ?>
 
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
 
        <!-- PREVIOUS REPORTS -->
        <div class="card">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:20px;">Previous Reports</h2>
 
            <?php if($reports && $reports->num_rows > 0): ?>
                <?php while($row = $reports->fetch_assoc()):
                    $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                    $icon = $ext === 'pdf' ? '📄' : '🖼️';
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;background:var(--teal-glow);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><?php echo $icon; ?></div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($row['report_name']); ?></div>
                            <div style="font-size:0.75rem;color:var(--muted);"><?php echo htmlspecialchars($row['report_type']); ?> · <?php echo date('d M Y', strtotime($row['created_at'])); ?></div>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="btn btn-secondary btn-sm">View</a>
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
</div>
 
<script>
function showFileName(input) {
    const display = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        display.textContent = '📎 ' + input.files[0].name;
        display.style.display = 'block';
    }
}
 
// Drag and drop highlight
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', () => zone.classList.add('dragover'));
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', () => zone.classList.remove('dragover'));
</script>
</body></html>