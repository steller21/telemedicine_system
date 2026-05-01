<?php
session_start(); 
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit; 
}

require_once("monitor_core.php");
$patient_id = $_SESSION['user_id']; 
$msg = ""; 
$msg_type = "";

// Handle success/error messages
if (isset($_GET['success'])) {
    $msg = htmlspecialchars($_GET['success']);
    $msg_type = "success";
}

if (isset($_GET['error'])) {
    $msg = htmlspecialchars($_GET['error']);
    $msg_type = "error";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    if (isset($_POST['email']) && !empty(trim($_POST['email']))) {
        $email = trim($_POST['email']);
        $res = sendMonitorRequest($conn, $patient_id, $email);
        $msg = $res['msg']; 
        $msg_type = $res['type'];
    } else {
        $msg = "Please enter a valid email address.";
        $msg_type = "error";
    }
}

// Fetch current monitors (only accepted ones) - Use prepared statement
$monitors_query = $conn->prepare("
    SELECT u.id, u.name, u.email, u.gender 
    FROM patient_monitors pm 
    JOIN users u ON pm.monitor_id=u.id 
    WHERE pm.patient_id=? 
    ORDER BY u.name ASC
");
$monitors = null;
if ($monitors_query) {
    $monitors_query->bind_param("i", $patient_id);
    if ($monitors_query->execute()) {
        $monitors = $monitors_query->get_result();
    }
}

// Fetch pending requests sent by this user
$pending_query = $conn->prepare("
    SELECT u.id, u.name, u.email, u.gender, mr.created_at
    FROM monitor_requests mr 
    JOIN users u ON mr.requested_user_id=u.id 
    WHERE mr.requester_id=? AND mr.status='pending' 
    ORDER BY mr.created_at DESC
");
$pending_requests = null;
if ($pending_query) {
    $pending_query->bind_param("i", $patient_id);
    if ($pending_query->execute()) {
        $pending_requests = $pending_query->get_result();
    }
}

// Fetch incoming requests for this user to accept/reject
$incoming_query = $conn->prepare("
    SELECT mr.id, u.id as requester_id, u.name, u.email, u.gender, mr.created_at 
    FROM monitor_requests mr 
    JOIN users u ON mr.requester_id=u.id 
    WHERE mr.requested_user_id=? AND mr.status='pending' 
    ORDER BY mr.created_at DESC
");
$incoming_requests = null;
if ($incoming_query) {
    $incoming_query->bind_param("i", $patient_id);
    if ($incoming_query->execute()) {
        $incoming_requests = $incoming_query->get_result();
    }
}

// Fetch incoming report share requests
$report_query = $conn->prepare("
    SELECT rsr.id, r.report_name, u.name as requester_name, rsr.requester_role, rsr.created_at 
    FROM report_share_requests rsr 
    JOIN reports r ON rsr.report_id = r.id 
    JOIN users u ON rsr.requester_id = u.id 
    WHERE rsr.patient_id = ? AND rsr.status = 'pending' 
    ORDER BY rsr.created_at DESC
");
$report_requests = null;
if ($report_query) {
    $report_query->bind_param("i", $patient_id);
    if ($report_query->execute()) {
        $report_requests = $report_query->get_result();
    }
}
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

body.dark-mode {
    --navy:       #0B1526;
    --navy-mid:   #112035;
    --navy-light: #1A3050;
    --navy-card:  #0F1E36;
    --white:      #FFFFFF;
    --muted:      #7A8EA8;
    --muted-dim:  #4A5E78;
    --border:     rgba(255,255,255,0.07);
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
}

.alert {
    padding: 14px 18px; border-radius: var(--radius);
    font-size: 0.875rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--danger); }
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
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link active"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
</aside>

<main class="main">
    <div class="page-header"><h1>👁️ Manage Monitors & Requests</h1><p>Add monitors to view your health information and manage incoming requests.</p></div>
    <div style="max-width:700px;">
        <?php if($msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>">
                <?php 
                $icon = ($msg_type === 'success') ? '✅' : (($msg_type === 'warning') ? '⚠️' : '❌');
                echo $icon . ' ' . htmlspecialchars($msg);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- INCOMING REPORT REQUESTS -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">📄 Incoming Report Requests</h2>
            <?php if ($report_requests && $report_requests->num_rows > 0): ?>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <?php while ($row = $report_requests->fetch_assoc()): ?>
                        <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:var(--radius);padding:16px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:600;margin-bottom:4px;"><?php echo htmlspecialchars($row['requester_name']); ?> requested your report</div>
                                <div style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['report_name']); ?></div>
                                <div style="color:var(--muted-dim);font-size:0.8rem;margin-top:6px;">Requested on <?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="?accept_report=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-primary btn-sm">✅ Share</a>
                                <a href="?reject_report=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-danger btn-sm">❌ Deny</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:16px;color:var(--muted);"><p style="font-size:0.9rem;">No pending report requests.</p></div>
            <?php endif; ?>
        </div>

        <!-- INCOMING MONITOR REQUESTS -->
        <div class="card" style="margin-bottom:24px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">📬 Incoming Monitor Requests</h2>
            <?php if ($incoming_requests && $incoming_requests->num_rows > 0): ?>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <?php while ($row = $incoming_requests->fetch_assoc()): ?>
                        <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:var(--radius);padding:18px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                            <div>
                                <?php $pronoun = ($row['gender'] === 'male') ? 'him' : (($row['gender'] === 'female') ? 'her' : 'them'); ?>
                                <div style="font-weight:600;margin-bottom:4px;"><?php echo htmlspecialchars($row['name']); ?> wants you to monitor <?php echo $pronoun; ?></div>
                                <div style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></div>
                                <div style="color:var(--muted-dim);font-size:0.8rem;margin-top:6px;">Requested on <?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                            </div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <a href="?accept=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-primary btn-sm">✅ Accept</a>
                                <a href="?reject=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-danger btn-sm">❌ Reject</a>
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
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">⏳ Your Pending Requests</h2>
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $pending_requests->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span style="background:rgba(245,158,11,0.12);color:var(--warning);padding:4px 10px;border-radius:50px;font-size:0.8rem;font-weight:600;">⏳ Awaiting</span></td>
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
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">👥 Your Current Monitors</h2>
            <?php if ($monitors && $monitors->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $monitors->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="color:var(--muted);font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showConfirmModal('<?php echo htmlspecialchars($row['id']); ?>', '<?php echo htmlspecialchars($row['name']); ?>');">🗑️ Remove</button>
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
            <p style="color:var(--muted);font-size:0.875rem;margin-bottom:24px;">Enter the email address of a trusted person (they must have a MediConnect account). They'll receive a request to monitor your health.</p>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Monitor's Email Address</label>
                    <input class="form-input" type="email" name="email" placeholder="monitor@example.com" required>
                </div>
                <button class="btn btn-primary btn-full" type="submit" name="add">➕ Send Request</button>
            </form>
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
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(0.95);
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s ease;
}
.chatbot-modal.show { transform: translateY(0) scale(1); opacity: 1; pointer-events: auto; }
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
        chatbotFab.addEventListener('click', () => chatbotModal.classList.toggle('show'));
        chatbotClose.addEventListener('click', () => chatbotModal.classList.remove('show'));
        document.addEventListener('click', (e) => {
            if (!chatbotModal.contains(e.target) && !chatbotFab.contains(e.target) && chatbotModal.classList.contains('show')) chatbotModal.classList.remove('show');
        });
    }
});
</script>
</div>

<!-- REMOVE MONITOR CONFIRMATION MODAL -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,0.4);animation:modalSlideIn 0.3s ease-out;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:12px;color:var(--white);">Remove Monitor?</h2>
        <p style="color:var(--muted);font-size:0.9rem;margin-bottom:28px;">Are you sure you want to remove "<span id="monitorName" style="font-weight:600;color:var(--teal);"></span>" from your monitors? They will no longer see your health information.</p>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="button" onclick="hideConfirmModal()" style="padding:10px 24px;background:var(--navy-light);border:1px solid var(--border);border-radius:50px;color:var(--white);font-weight:600;cursor:pointer;font-size:0.875rem;transition:all 0.2s;" onmouseover="this.style.background='var(--navy-mid)'" onmouseout="this.style.background='var(--navy-light)';">Cancel</button>
            <button type="button" onclick="confirmRemove()" style="padding:10px 24px;background:#EF4444;border:none;border-radius:50px;color:var(--white);font-weight:600;cursor:pointer;font-size:0.875rem;transition:all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#EF4444';">Remove</button>
        </div>
    </div>
</div>

<style>
@keyframes modalSlideIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
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

document.addEventListener('click', function(event) {
    const modal = document.getElementById('confirmModal');
    if (event.target === modal) {
        hideConfirmModal();
    }
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
    url.searchParams.delete('success'); url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
}
</script>

</body></html>