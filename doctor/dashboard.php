<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}
 
$doctor_id = intval($_SESSION['user_id']);

// Get incoming calls
$calls = $conn->query("SELECT vc.*, u.name as patient_name 
                       FROM video_calls vc
                       LEFT JOIN users u ON u.id = vc.patient_id
                       WHERE vc.doctor_id='$doctor_id' AND vc.status='waiting'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Doctor Dashboard — MediConnect</title>
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
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
 
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
 
/* CALL STYLES */
.call-item {
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.2);
    border-radius: var(--radius);
    padding: 16px 20px; margin-bottom: 12px;
}
.no-calls { color: var(--muted); font-size: 0.9rem; text-align: center; padding: 20px; }
 
/* POPUP OVERLAY */
#callOverlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.8); backdrop-filter: blur(8px);
    z-index: 9999; align-items: center; justify-content: center;
}
#callOverlay.show { display: flex; }
#callBox {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 40px; text-align: center;
    box-shadow: var(--shadow);
    animation: popIn 0.3s ease;
}
@keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
#callBox h2 { font-family: 'Clash Display', sans-serif; font-size: 1.5rem; margin-bottom: 8px; }
#callBox p { color: var(--muted); margin-bottom: 24px; }
.ring { font-size: 3rem; margin-bottom: 10px; animation: ring 0.6s infinite alternate; }
@keyframes ring { from { transform: rotate(-15deg); } to { transform: rotate(15deg); } }
</style>
</head>
<body>
<div class="page-bg"></div>
<div class="layout">
<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
        <a href="monitor_patients.php" class="nav-link"><span class="nav-icon">👥</span> Monitor Patients</a>
        <a href="friends.php" class="nav-link"><span class="nav-icon">💬</span> Friends & Chat</a>
    </div>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a>
    </div>
</aside>
 
<!-- MAIN -->
<main class="main">
    <?php 
    $notifCount = getPendingNotificationCount($conn, $doctor_id);
    $notifications = getPendingNotifications($conn, $doctor_id);
    ?>
    <div class="notif-container">
        <!-- 🔔 Bell Button -->
        <div class="notif-btn" id="notifBtn">
            🔔
            <?php if ($notifCount > 0): ?>
                <span class="notif-badge"><?php echo $notifCount; ?></span>
            <?php endif; ?>
        </div>

        <!-- 🔔 Dropdown -->
        <div class="notif-dropdown" id="notifDropdown">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-item">
                        <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="notif-desc"><?php echo htmlspecialchars($n['desc']); ?></div>
                        <div class="notif-actions">
                            <?php if($n['type'] === 'info'): ?>
                                <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>">Dismiss</a>
                            <?php elseif($n['type'] === 'chat'): ?>
                                <a href="friends.php?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>">💬 Open Chat</a>
                            <?php else: ?>
                                <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>">✅ Accept</a>
                                <a href="?<?php echo $n['reject_param']; ?>=<?php echo $n['id']; ?>">❌ Reject</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notif-item">No notifications</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-header">
        <h1>Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['name']); ?> 👨‍⚕️</h1>
        <p>Your practice is online. Manage your incoming calls and appointments here.</p>
    </div>
 
    <!-- Incoming calls section -->
    <div class="card" style="margin-bottom: 28px;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:20px;">📞 Incoming Calls</h2>
        <div id="callList">
            <?php if ($calls && $calls->num_rows > 0): ?>
                <?php while($call = $calls->fetch_assoc()): ?>
                    <div class="call-item">
                        <span style="font-weight: 500;">📞 <?php echo htmlspecialchars($call['patient_name'] ?? 'Patient'); ?> is calling...</span>
                        <a class="btn btn-primary btn-sm" href="accept_call.php?call_id=<?php echo $call['id']; ?>">
                            ✅ Accept Call
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-calls" id="noCalls">No incoming calls right now. Waiting for patients...</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick links -->
    <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:20px;">Quick Actions</h2>
    <div class="grid-4">
        <a href="appointments.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">📅</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:white;">Appointments</div><div style="font-size:0.8rem;color:var(--muted);">View your schedule</div></div>
        </a>
        <a href="monitor_patients.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">👥</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:white;">Monitor Patients</div><div style="font-size:0.8rem;color:var(--muted);">Track patient progress</div></div>
        </a>
        <a href="friends.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">💬</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:white;">Messages</div><div style="font-size:0.8rem;color:var(--muted);">Chat with patients</div></div>
        </a>
        <a href="../logout.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">🚪</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:white;">Logout</div><div style="font-size:0.8rem;color:var(--muted);">End your session</div></div>
        </a>
    </div>
</main>
</div>
 
<!-- ── Incoming call popup ── -->
<div id="callOverlay">
    <div id="callBox">
        <div class="ring">📞</div>
        <h2>Incoming Call!</h2>
        <p id="callerName">A patient is calling you...</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <a id="acceptLink" class="btn btn-primary" href="#">✅ Accept</a>
            <a id="declineLink" class="btn btn-danger" href="#">❌ Decline</a>
        </div>
    </div>
</div>
 
<script>
// Poll every 4 seconds for new incoming calls
let popupShown = false;
let knownCallIds = new Set([
    <?php
        // Reset query
        $conn->query("SELECT id FROM video_calls WHERE doctor_id='$doctor_id' AND status='waiting'");
        $existing = $conn->query("SELECT id FROM video_calls WHERE doctor_id='$doctor_id' AND status='waiting'");
        $ids = [];
        if ($existing) while($r = $existing->fetch_assoc()) $ids[] = $r['id'];
        echo implode(',', $ids);
    ?>
]);
 
function checkCalls() {
    fetch('check_calls.php')
        .then(r => r.json())
        .then(data => {
            if (data.calls && data.calls.length > 0) {
                // Update call list in page
                let listHtml = '';
                data.calls.forEach(call => {
                    listHtml += `
                        <div class="call-item" style="display:flex; align-items:center; justify-content:space-between;">
                            <span style="font-weight: 500;">📞 ${call.patient_name} is calling...</span>
                            <a class="btn btn-primary btn-sm" href="accept_call.php?call_id=${call.id}">✅ Accept Call</a>
                        </div>`;
 
                    // Show popup for NEW calls only
                    if (!knownCallIds.has(call.id) && !popupShown) {
                        popupShown = true;
                        knownCallIds.add(call.id);
                        document.getElementById('callerName').textContent = call.patient_name + ' is calling you...';
                        document.getElementById('acceptLink').href = 'accept_call.php?call_id=' + call.id;
                        document.getElementById('declineLink').href = 'decline_call.php?call_id=' + call.id;
                        document.getElementById('callOverlay').classList.add('show');
 
                        // Play sound
                        try {
                            let ctx = new AudioContext();
                            function beep() {
                                let o = ctx.createOscillator();
                                let g = ctx.createGain();
                                o.connect(g); g.connect(ctx.destination);
                                o.frequency.value = 520;
                                g.gain.setValueAtTime(0.3, ctx.currentTime);
                                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                                o.start(ctx.currentTime);
                                o.stop(ctx.currentTime + 0.4);
                            }
                            beep();
                            setTimeout(beep, 600);
                            setTimeout(beep, 1200);
                        } catch(e) {}
                    }
                });
                document.getElementById('callList').innerHTML = listHtml;
 
            } else {
                // No calls
                popupShown = false;
                document.getElementById('callOverlay').classList.remove('show');
                document.getElementById('callList').innerHTML = '<div class="no-calls">No incoming calls right now. Waiting for patients...</div>';
            }
        })
        .catch(e => console.log('Poll error:', e));
}
 
// Decline just hides popup, doesn't end call
document.getElementById('declineLink').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('callOverlay').classList.remove('show');
    popupShown = false;
});
 
// Poll every 4 seconds
setInterval(checkCalls, 4000);
</script>
</body>
</html>