<?php
session_start(); require_once("../config/db.php");
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$patient_id = $_SESSION['user_id']; $msg=""; $msg_type="";

// Ensure monitor_requests table exists
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

// Handle delete monitor
if (isset($_GET['remove_monitor'])) {
    $monitor_id = intval($_GET['remove_monitor']);
    $delete = $conn->prepare("DELETE FROM patient_monitors WHERE patient_id=? AND monitor_id=?");
    if ($delete) {
        $delete->bind_param("ii", $patient_id, $monitor_id);
        $delete->execute();
    }
    header("Location: add_monitor.php?success=Monitor removed");
    exit;
}

if (isset($_GET['success'])) {
    $msg = htmlspecialchars($_GET['success']);
    $msg_type = "success";
}

// Add monitor - Send request instead of direct add
if (isset($_POST['add'])) {
    $monitor_email = $_POST['email'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?"); 
    $stmt->bind_param("s", $monitor_email); 
    $stmt->execute(); 
    $user = $stmt->get_result();
    if ($user->num_rows > 0) { 
        $monitor_id = $user->fetch_assoc()['id'];
        if ($monitor_id == $patient_id) {
            $msg = "You cannot add yourself as a monitor.";
            $msg_type = "error";
        } else {
            // Check if already have active monitor or pending request
            $check_active = $conn->prepare("SELECT id FROM patient_monitors WHERE patient_id=? AND monitor_id=?");
            if ($check_active) {
                $check_active->bind_param("ii", $patient_id, $monitor_id);
                $check_active->execute();
                $active_result = $check_active->get_result();
            } else {
                $active_result = null;
            }
            
            $check_pending = $conn->prepare("SELECT id FROM monitor_requests WHERE requester_id=? AND requested_user_id=? AND status='pending'");
            if ($check_pending) {
                $check_pending->bind_param("ii", $patient_id, $monitor_id);
                $check_pending->execute();
                $pending_result = $check_pending->get_result();
            } else {
                $pending_result = null;
            }
            
            if ($active_result && $active_result->num_rows > 0) {
                $msg = "This person is already monitoring you.";
                $msg_type = "warning";
            } else if ($pending_result && $pending_result->num_rows > 0) {
                $msg = "Request already sent. Waiting for their response.";
                $msg_type = "warning";
            } else {
                // Send monitor request
                $ins = $conn->prepare("INSERT INTO monitor_requests (requester_id, requested_user_id, status) VALUES (?, ?, 'pending')");
                if ($ins) {
                    $ins->bind_param("ii", $patient_id, $monitor_id);
                    $ins->execute();
                    $msg = "✅ Monitor request sent! They need to accept it before they can monitor you.";
                    $msg_type = "success";
                } else {
                    $msg = "Error sending request. Please try again.";
                    $msg_type = "error";
                }
            }
        }
    }
    else { $msg = "User not found with that email."; $msg_type = "error"; }
}

// Fetch current monitors (only accepted ones)
$monitors = $conn->query("SELECT u.id, u.name, u.email, u.gender FROM patient_monitors pm JOIN users u ON pm.monitor_id=u.id WHERE pm.patient_id='$patient_id' ORDER BY u.name ASC");

// Fetch pending requests sent by this user
$pending_requests = $conn->query("SELECT u.id, u.name, u.email, u.gender FROM monitor_requests mr JOIN users u ON mr.requested_user_id=u.id WHERE mr.requester_id='$patient_id' AND mr.status='pending' ORDER BY mr.created_at DESC");

// Fetch incoming requests for this user to accept/reject
$incoming_requests = $conn->query("SELECT mr.id, u.id as requester_id, u.name, u.email, u.gender, mr.created_at FROM monitor_requests mr JOIN users u ON mr.requester_id=u.id WHERE mr.requested_user_id='$patient_id' AND mr.status='pending' ORDER BY mr.created_at DESC");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Add Monitor — MediConnect</title>
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
</head><body><div class="page-bg"></div><div class="layout">
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section"><div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link"><span class="nav-icon">📅</span> Book Appointment</a>
        <a href="../chatbot.php" class="nav-link"><span class="nav-icon">🤖</span> Health Assistant</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Health</div>
        <a href="checklist.php" class="nav-link"><span class="nav-icon">💊</span> My Medicines</a>
        <a href="upload_report.php" class="nav-link"><span class="nav-icon">📄</span> Upload Report</a>
        <a href="share_reports.php" class="nav-link"><span class="nav-icon">📤</span> Share Requests</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link active"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
    <div class="sidebar-bottom"><a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a></div>
</aside>
<main class="main">
    <div class="page-header">
        <h1>👁️ Manage Monitors</h1>
        <p>Add trusted people to monitor your health activity, or remove existing monitors.</p>
        <div style="margin-top:16px;">
            <a href="share_reports.php" class="btn btn-secondary btn-sm">✅ Accept / Reject Report Requests</a>
        </div>
    </div>
    <div style="max-width:600px;">
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type;?>"><?php echo $msg_type=='success'?'✅':($msg_type=='warning'?'⚠️':'❌');?> <?php echo htmlspecialchars($msg);?></div><?php endif;?>
        
        <!-- INCOMING REQUESTS -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">📬 Incoming Monitor Requests</h2>
            <?php if ($incoming_requests && $incoming_requests->num_rows > 0): ?>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <?php while ($row = $incoming_requests->fetch_assoc()): ?>
                        <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:var(--radius);padding:18px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:600;margin-bottom:4px;"><?php echo htmlspecialchars($row['name']); ?></div>
                                <div style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></div>
                                <div style="color:var(--muted-dim);font-size:0.8rem;margin-top:6px;">Requested on <?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                            </div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <a href="monitor_requests.php?accept=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">✅ Accept</a>
                                <a href="monitor_requests.php?reject=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">❌ Reject</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:16px;color:var(--muted);">
                    <p style="font-size:0.9rem;">No incoming monitor requests.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- PENDING REQUESTS SENT -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">⏳ Pending Requests Sent</h2>
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead style="background:var(--navy-light);">
                            <tr>
                                <th style="padding:12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);font-size:0.85rem;">Name</th>
                                <th style="padding:12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);font-size:0.85rem;">Email</th>
                                <th style="padding:12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);font-size:0.85rem;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $pending_requests->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:14px;font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="padding:14px;color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="padding:14px;"><span style="background:rgba(245,158,11,0.12);color:var(--warning);padding:4px 10px;border-radius:50px;font-size:0.8rem;font-weight:600;">⏳ Awaiting Response</span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:16px;color:var(--muted);">
                    <p style="font-size:0.9rem;">No pending requests.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- CURRENT MONITORS -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">👥 Current Monitors</h2>
            <?php if ($monitors && $monitors->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead style="background:var(--navy-light);">
                            <tr>
                                <th style="padding:12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);font-size:0.85rem;">Name</th>
                                <th style="padding:12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);font-size:0.85rem;">Email</th>
                                <th style="padding:12px;text-align:left;font-weight:600;color:var(--muted);border-bottom:1px solid var(--border);font-size:0.85rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $monitors->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid var(--border);">
                                <td style="padding:14px;font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="padding:14px;color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="padding:14px;">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showConfirmModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['name']); ?>');">🗑️ Remove</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:24px;color:var(--muted);">
                    <p style="font-size:0.9rem;">No monitors added yet. Add someone below to get started.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ADD MONITOR FORM -->
        <div class="card">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">➕ Add New Monitor</h2>
            <p style="color:var(--muted);font-size:0.875rem;margin-bottom:24px;">Enter the email address of a trusted person (they must have a MediConnect account).</p>
            <form method="POST">
                <div class="form-group"><label class="form-label">Monitor's Email</label><input class="form-input" type="email" name="email" placeholder="monitor@example.com" required></div>
                <button class="btn btn-primary btn-full" type="submit" name="add">➕ Add Monitor</button>
            </form>
        </div>
    </div>
</main></div>

<!-- REMOVE MONITOR CONFIRMATION MODAL -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,0.4);animation:modalSlideIn 0.3s ease-out;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:12px;color:var(--white);">Remove Monitor?</h2>
        <p style="color:var(--muted);font-size:0.9rem;margin-bottom:28px;">Are you sure you want to remove "<span id="monitorName" style="font-weight:600;color:var(--teal);"></span>" from your monitors? They will no longer be able to see your health information.</p>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="button" onclick="hideConfirmModal()" style="padding:10px 24px;background:var(--navy-light);border:1px solid var(--border);border-radius:50px;color:var(--white);font-weight:600;cursor:pointer;font-size:0.875rem;transition:all 0.2s;" onmouseover="this.style.background='var(--navy-mid)'" onmouseout="this.style.background='var(--navy-light)';">Cancel</button>
            <button type="button" onclick="confirmRemove()" style="padding:10px 24px;background:#EF4444;border:none;border-radius:50px;color:var(--white);font-weight:600;cursor:pointer;font-size:0.875rem;transition:all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#EF4444';">Remove</button>
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
let pendingRemoveUrl = null;

function showConfirmModal(monitorId, monitorName) {
    pendingRemoveUrl = 'add_monitor.php?remove_monitor=' + monitorId;
    document.getElementById('monitorName').textContent = monitorName;
    document.getElementById('confirmModal').style.display = 'flex';
}

function hideConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    pendingRemoveUrl = null;
}

function confirmRemove() {
    if (pendingRemoveUrl) {
        window.location.href = pendingRemoveUrl;
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
<?php ?>
