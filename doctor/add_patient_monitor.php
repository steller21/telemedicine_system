<?php
session_start(); 
require_once("../config/db.php");
require_once("../patient/monitor_core.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') { 
    header("Location: ../login.php"); 
    exit; 
}

require_once("monitor_core.php");
$doctor_id = $_SESSION['user_id'];
$msg = ""; 
$msg_type = "";

if (isset($_GET['success'])) {
    $msg = htmlspecialchars($_GET['success']);
    $msg_type = "success";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    if (isset($_POST['email']) && !empty(trim($_POST['email']))) {
        $email = trim($_POST['email']);
        $res = sendMonitorRequest($conn, $doctor_id, $email, 'patient');
        $msg = $res['msg']; 
        $msg_type = $res['type'];
    } else {
        $msg = "Please enter a valid email address.";
        $msg_type = "error";
    }
}

// Fetch patients this doctor is monitoring
$monitoring_query = $conn->prepare("
    SELECT u.id, u.name, u.email, u.gender 
    FROM patient_monitors pm 
    JOIN users u ON pm.patient_id=u.id 
    WHERE pm.monitor_id=? 
    ORDER BY u.name ASC
");
$monitoring = null;
if ($monitoring_query) {
    $monitoring_query->bind_param("i", $doctor_id);
    if ($monitoring_query->execute()) {
        $monitoring = $monitoring_query->get_result();
    }
}

// Fetch pending requests sent by this doctor
$pending_query = $conn->prepare("
    SELECT u.id, u.name, u.email, u.gender, mr.created_at
    FROM monitor_requests mr 
    JOIN users u ON mr.requested_user_id=u.id 
    WHERE mr.requester_id=? AND mr.status='pending' 
    ORDER BY mr.created_at DESC
");
$pending_requests = null;
if ($pending_query) {
    $pending_query->bind_param("i", $doctor_id);
    if ($pending_query->execute()) {
        $pending_requests = $pending_query->get_result();
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Add Patient Monitor — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
 
:root {
    --teal:       #0EB8A0;
    --teal-dark:  #0A8A78;
    --teal-glow:  rgba(14,184,160,0.15);
    --navy:       #f8fafc;
    --navy-mid:   #ffffff;
    --navy-light: #f1f5f9;
    --navy-card:  #ffffff;
    --white:      #1e293b;
    --cream:      #F5F0E8;
    --muted:      #64748b;
    --muted-dim:  #94a3b8;
    --accent:     #FF6B4A;
    --success:    #22C55E;
    --warning:    #F59E0B;
    --danger:     #EF4444;
    --border:     rgba(0,0,0,0.08);
    --radius:     14px;
    --radius-lg:  22px;
    --shadow:     0 4px 20px rgba(0,0,0,0.05);
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
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 24px 28px; padding-right: 15px;
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
}

.alert {
    padding: 14px 18px; border-radius: var(--radius);
    font-size: 0.875rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.2);  color: var(--danger); }
.alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: var(--warning); }

.form-group { margin-bottom: 20px; }
.form-label {
    display: block; font-size: 0.8rem;
    font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.form-input {
    width: 100%; padding: 12px 16px;
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--white); font-size: 0.9rem;
    font-family: 'DM Sans', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.form-input:focus {
    border-color: rgba(14,184,160,0.5);
    box-shadow: 0 0 0 3px rgba(14,184,160,0.1);
}
.form-input::placeholder { color: var(--muted-dim); }

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
}
.btn-danger { background: rgba(239,68,68,0.15); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }
.btn-danger:hover { background: rgba(239,68,68,0.25); }
.btn-sm { padding: 7px 16px; font-size: 0.8rem; }
.btn-full { width: 100%; justify-content: center; }

table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
    text-align: left; padding: 12px 16px;
    font-size: 0.72rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--muted); border-bottom: 1px solid var(--border);
}
tbody td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); }
tbody tr:hover { background: rgba(255,255,255,0.02); }

.empty-state {
    text-align: center; padding: 40px 20px;
    color: var(--muted);
}
.empty-icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5; }

@media (max-width: 768px) {
    .sidebar { width: 200px; }
    .main { margin-left: 200px; max-width: calc(100% - 200px); padding: 20px; }
}
</style>
</head><body>
<div class="page-bg"></div>
<div class="layout">
<aside class="sidebar">
    <div class="sidebar-logo">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">MediConnect</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section"><div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
        <a href="monitor_patients.php" class="nav-link"><span class="nav-icon">👥</span> Monitor Patients</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_patient_monitor.php" class="nav-link active"><span class="nav-icon">👁️</span> Add Patient Monitor</a>
        <a href="monitor_requests.php" class="nav-link"><span class="nav-icon">📬</span> Monitor Requests</a>
    </div>
    <div class="sidebar-bottom"><a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a></div>
</aside>
<main class="main">
    <?php 
    $notifCount = getPendingNotificationCount($conn, $doctor_id);
    $notifications = getPendingNotifications($conn, $doctor_id);
    ?>
    <div class="notif-container">
        <div class="notif-btn" id="notifBtn">🔔 <?php if($notifCount > 0): ?><span class="notif-badge"><?php echo $notifCount; ?></span><?php endif; ?></div>
        <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-list">
                <?php if(!empty($notifications)): foreach($notifications as $n): ?>
                    <div class="notif-item">
                        <div class="notif-item-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="notif-item-desc"><?php echo htmlspecialchars($n['desc']); ?></div>
                        <div class="notif-actions">
                            <?php if($n['type'] === 'info'): ?>
                                <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept" style="width:100%; text-align:center;">Dismiss</a>
                            <?php elseif($n['type'] === 'chat'): ?>
                                <a href="../patient/chat.php?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept" style="width:100%; text-align:center;">💬 Open Chat</a>
                            <?php else: ?>
                            <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept">✅ Accept</a>
                            <a href="?<?php echo $n['reject_param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-reject">❌ Reject</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div style="padding:20px; text-align:center; color:var(--muted); font-size:0.85rem;">No new notifications</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="page-header"><h1>👁️ Add Patient Monitor</h1><p>Search for patients and request to monitor their health activities.</p></div>
    <div style="max-width:600px;">
        <?php if($msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>">
                <?php 
                $icon = ($msg_type === 'success') ? '✅' : (($msg_type === 'warning') ? '⚠️' : '❌');
                echo $icon . ' ' . htmlspecialchars($msg);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- PENDING REQUESTS -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">⏳ Pending Requests</h2>
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $pending_requests->fetch_assoc()): 
                                $title = ($row['gender'] === 'male') ? 'Mr.' : (($row['gender'] === 'female') ? 'Mrs.' : 'Mx.');
                            ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($title . ' ' . $row['name']); ?></td>
                                <td style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span style="background:rgba(245,158,11,0.12);color:var(--warning);padding:4px 10px;border-radius:50px;font-size:0.8rem;font-weight:600;">⏳ Awaiting</span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:16px;color:var(--muted);"><p style="font-size:0.9rem;">No pending requests.</p></div>
            <?php endif; ?>
        </div>
        
        <!-- MONITORING PATIENTS -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">👥 Patients Being Monitored</h2>
            <?php if ($monitoring && $monitoring->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Email</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $monitoring->fetch_assoc()): 
                                $title = ($row['gender'] === 'male') ? 'Mr.' : (($row['gender'] === 'female') ? 'Mrs.' : 'Mx.');
                            ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($title . ' ' . $row['name']); ?></td>
                                <td style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><a href="add_patient_monitor.php?remove_patient=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this patient from monitoring?');">🗑️ Remove</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:24px;color:var(--muted);">
                    <p style="font-size:0.9rem;">Not monitoring any patients yet. Add below to get started.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ADD PATIENT FORM -->
        <div class="card">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">➕ Request to Monitor Patient</h2>
            <p style="color:var(--muted);font-size:0.875rem;margin-bottom:24px;">Enter the patient's email address. They must approve your request before monitoring begins.</p>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Patient Email</label>
                    <input class="form-input" type="email" name="email" placeholder="patient@example.com" required>
                </div>
                <button class="btn btn-primary btn-full" type="submit" name="add">➕ Send Request</button>
            </form>
        </div>
    </div>
</main>
</div>
<script>
// Auto-hide alerts after 7 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.5s ease';
        a.style.opacity = '0';
        setTimeout(() => a.style.display = 'none', 500);
    });
}, 7000);

if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('success'); url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
}
</script>

<script>
document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
});
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){document.getElementById('notifDropdown').classList.remove('show');}
});

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
</body>
</html>