<?php
session_start();
require_once("../config/db.php");
require_once("monitor_core.php");
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') { 
    header("Location: ../login.php"); 
    exit; 
}
 
$patient_id = intval($_SESSION['user_id']);
 
$appointments = $conn->query("SELECT a.id, a.appointment_date, u.name as doctor_name 
FROM appointments a 
JOIN users u ON a.doctor_id = u.id 
WHERE a.patient_id = '$patient_id' AND a.appointment_date >= NOW() 
ORDER BY a.appointment_date ASC");
 
$total_appts  = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE patient_id='$patient_id'")->fetch_assoc()['c'];
 
$pending_meds = $conn->query("SELECT COUNT(*) as c FROM checklist_items ci 
JOIN checklists cl ON ci.checklist_id=cl.id 
WHERE cl.patient_id='$patient_id' AND ci.status='pending'")->fetch_assoc()['c'];
 
$monitors     = $conn->query("SELECT COUNT(*) as c FROM patient_monitors WHERE patient_id='$patient_id'")->fetch_assoc()['c'];
 
/* ================= FIXED REPORTS ================= */
 
$reports_count = 0;
$recent_reports = [];
 
$stmt = $conn->prepare("SELECT * FROM reports WHERE patient_id=? ORDER BY created_at DESC");
if ($stmt === false) {
    // Table might not exist or connection error
    $recent_reports = [];
    $reports_count = 0;
} else {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
 
    while ($row = $result->fetch_assoc()) {
        $recent_reports[] = $row;
    }
 
    $reports_count = count($recent_reports);
    $recent_reports = array_slice($recent_reports, 0, 5);
}

// Handle success/error messages
$msg = "";
$msg_type = "";
if (isset($_GET['success'])) {
    $msg = htmlspecialchars($_GET['success']);
    $msg_type = "success";
}
if (isset($_GET['error'])) {
    $msg = htmlspecialchars($_GET['error']);
    $msg_type = "error";
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patient Dashboard — MediConnect</title>
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
.notif-container{position:relative;display:inline-block;}
.notif-btn{background:var(--navy-mid);border:1px solid var(--border);color:var(--white);padding:8px 14px;border-radius:12px;cursor:pointer;display:flex;align-items:center;font-size:1.1rem;transition:0.2s;}
.notif-btn:hover{background:var(--navy-light);border-color:var(--teal);}
.notif-badge{background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:50px;position:absolute;top:-5px;right:-5px;border:2px solid var(--navy-card);}
.notif-dropdown{position:absolute;top:100%;right:0;width:300px;background:var(--navy-card);border:1px solid var(--border);border-radius:12px;margin-top:12px;display:none;z-index:1000;box-shadow:0 20px 50px rgba(0,0,0,0.4);overflow:hidden;}
.notif-dropdown.show{display:block;}
.notif-header{padding:12px 16px;border-bottom:1px solid var(--border);font-family:'Clash Display',sans-serif;font-size:0.85rem;font-weight:600;background:rgba(255,255,255,0.02);text-align:left;}
.notif-item{padding:12px 16px;border-bottom:1px solid var(--border);transition:0.2s;text-decoration:none;color:inherit;display:block;text-align:left;}
.notif-item:hover{background:rgba(255,255,255,0.04);}
.notif-item-title{font-size:0.85rem;font-weight:600;color:var(--teal);margin-bottom:2px;}
.notif-item-desc{font-size:0.75rem;color:var(--muted);line-height:1.4;}
 
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
</head><body>
<div class="page-bg"></div>
<div class="layout">
<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link"><span class="nav-icon">📅</span> Book Appointment</a>
        <a href="../chatbot.php" class="nav-link"><span class="nav-icon">🤖</span> Health Assistant</a>
        <a href="friends.php" class="nav-link"><span class="nav-icon">👥</span> Friends & Chat</a>
    </div>
    <div class="nav-section">
    <div class="nav-section-label">Health</div>

    <a href="checklist.php" class="nav-link">
        <span class="nav-icon">💊</span> My Medicines
    </a>

    <a href="upload_report.php" class="nav-link">
        <span class="nav-icon">📄</span> Upload Report
    </a>
</div>
    <div class="nav-section">
        <div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a>
    </div>
</aside>
<!-- MAIN -->
<main class="main">
    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
        <?php 
        $notifCount = getPendingNotificationCount($conn, $patient_id);
        $notifications = getPendingNotifications($conn, $patient_id);
        ?>
        <div class="notif-container">
            <div class="notif-btn" onclick="toggleNotif()">🔔 <?php if($notifCount > 0): ?><span class="notif-badge"><?php echo $notifCount; ?></span><?php endif; ?></div>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">Pending Requests</div>
                <div class="notif-list">
                    <?php if(empty($notifications)): ?>
                        <div style="padding:20px;text-align:center;font-size:0.8rem;color:var(--muted);">No pending requests</div>
                    <?php else: foreach($notifications as $n): ?>
                        <a href="add_monitor.php?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-item">
                            <div class="notif-item-title"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div class="notif-item-desc"><?php echo htmlspecialchars($n['desc']); ?></div>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="page-header">
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
        <p>Here's an overview of your health activity today.</p>
    </div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>" style="margin-bottom:20px;">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>
    <!-- STATS -->
    <div class="grid-4" style="margin-bottom:28px;">
        <div class="stat-card"><div class="stat-card-icon">📅</div><div class="stat-card-value"><?php echo $total_appts; ?></div><div class="stat-card-label">Total Appointments</div></div>
        <div class="stat-card"><div class="stat-card-icon">💊</div><div class="stat-card-value"><?php echo $pending_meds; ?></div><div class="stat-card-label">Pending Medicines</div></div>
        <div class="stat-card"><div class="stat-card-icon">👁️</div><div class="stat-card-value"><?php echo $monitors; ?></div><div class="stat-card-label">Monitors</div></div>
        <div class="stat-card"><div class="stat-card-icon">📄</div><div class="stat-card-value"><?php echo $reports_count; ?></div><div class="stat-card-label">Reports Uploaded</div></div>
    </div>
    <!-- UPCOMING APPOINTMENTS -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;">📞 Upcoming Appointments</h2>
            <a href="book_appointment.php" class="btn btn-primary btn-sm">+ Book New</a>
        </div>
        <?php if($appointments && $appointments->num_rows > 0): ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Doctor</th><th>Date & Time</th><th>Action</th></tr></thead>
            <tbody>
            <?php while($row = $appointments->fetch_assoc()): ?>
            <tr>
                <td><strong>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></strong></td>
                <td><?php echo date('D, d M Y · h:i A', strtotime($row['appointment_date'])); ?></td>
                <td><a href="start_call.php?appointment_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">📞 Start Call</a></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📅</div>
            <h3>No upcoming appointments</h3>
            <p>Book an appointment with a doctor to get started.</p>
            <br><a href="book_appointment.php" class="btn btn-primary btn-sm">Book Now</a>
        </div>
        <?php endif; ?>
    </div>
    <!-- RECENT REPORTS -->
    <div class="card" style="margin-bottom:28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;">📄 Recent Reports (<?php echo $reports_count; ?>)</h2>
            <a href="upload_report.php" class="btn btn-primary btn-sm">+ Upload New</a>
        </div>
        <?php if(count($recent_reports) > 0): ?>
            <?php foreach($recent_reports as $row):
                $ext = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                $icon = ($ext === 'pdf') ? '📄' : (in_array($ext, ['jpg', 'jpeg', 'png']) ? '🖼️' : '📋');
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--teal-glow);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><?php echo $icon; ?></div>
                    <div>
                        <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($row['report_name']); ?></div>
                        <div style="font-size:0.75rem;color:var(--muted);"><?php echo htmlspecialchars($row['file_path']); ?> · <?php echo htmlspecialchars($row['created_at']); ?></div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <a href="<?php echo htmlspecialchars('../' . $row['file_path']); ?>" target="_blank" class="btn btn-primary btn-sm">👁️ View</a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="showConfirmModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['report_name']); ?>');">🗑️ Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:40px 20px;">
                <div class="empty-icon">📂</div>
                <h3>No reports uploaded yet</h3>
                <p>Upload your medical reports to share with your doctor.</p>
                <br><a href="upload_report.php" class="btn btn-primary btn-sm">Upload Report</a>
            </div>
        <?php endif; ?>
    </div>
    <!-- QUICK ACTIONS -->
    <div class="grid-3" style="margin-top:20px;">
        <a href="checklist.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">💊</div>
            <div><div style="font-weight:600;margin-bottom:2px;">My Medicines</div><div style="font-size:0.8rem;color:var(--muted);">Track daily intake</div></div>
        </a>
        <a href="../chatbot.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">🤖</div>
            <div><div style="font-weight:600;margin-bottom:2px;">Health Assistant</div><div style="font-size:0.8rem;color:var(--muted);">Ask health questions</div></div>
        </a>
        <a href="upload_report.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">📄</div>
            <div><div style="font-weight:600;margin-bottom:2px;">Upload Report</div><div style="font-size:0.8rem;color:var(--muted);">Share with doctor</div></div>
        </a>
        <a href="friends.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">💬</div>
            <div><div style="font-weight:600;margin-bottom:2px;">Messages</div><div style="font-size:0.8rem;color:var(--muted);">Chat with friends</div></div>
        </a>
    </div>
</main>
</div>

<!-- CUSTOM CONFIRMATION MODAL -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,0.4);animation:modalSlideIn 0.3s ease-out;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:12px;color:var(--white);">Delete Report?</h2>
        <p style="color:var(--muted);font-size:0.9rem;margin-bottom:28px;">Are you sure you want to delete "<span id="reportName" style="font-weight:600;color:var(--teal);"></span>"? This action cannot be undone.</p>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="button" onclick="hideConfirmModal()" style="padding:10px 24px;background:var(--navy-light);border:1px solid var(--border);border-radius:50px;color:var(--white);font-weight:600;cursor:pointer;font-size:0.875rem;transition:all 0.2s;" onmouseover="this.style.background='var(--navy-mid)'" onmouseout="this.style.background='var(--navy-light)';">Cancel</button>
            <button type="button" onclick="confirmDelete()" style="padding:10px 24px;background:#EF4444;border:none;border-radius:50px;color:var(--white);font-weight:600;cursor:pointer;font-size:0.875rem;transition:all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#EF4444';">Delete</button>
        </div>
    </div>
</div>

<style>
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>

<script>
let pendingDeleteId = null;
let pendingDeleteUrl = null;

function showConfirmModal(reportId, reportName) {
    pendingDeleteId = reportId;
    pendingDeleteUrl = 'delete_report.php?id=' + reportId;
    document.getElementById('reportName').textContent = reportName;
    document.getElementById('confirmModal').style.display = 'flex';
}

function hideConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    pendingDeleteId = null;
    pendingDeleteUrl = null;
}

function confirmDelete() {
    if (pendingDeleteUrl) {
        window.location.href = pendingDeleteUrl;
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('confirmModal');
    if (event.target === modal) {
        hideConfirmModal();
    }
});
</script>

</body></html>
