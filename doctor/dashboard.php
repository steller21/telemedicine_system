<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");
require_once("../includes/call_core.php");
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}
 
$doctor_id = intval($_SESSION['user_id']);
ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

// Get incoming calls
$calls = $conn->query("SELECT vc.*, u.name as patient_name 
                       FROM video_calls vc
                       LEFT JOIN patients u ON u.id = vc.patient_id
                       WHERE vc.doctor_id='$doctor_id' AND vc.status='waiting' AND vc.initiated_by='patient'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Doctor Dashboard — TELEMEDICINE</title>
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

/* Modal Styles */
#editProfileModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center; }
.modal-content { background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:450px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.4); }
@keyframes modalSlideIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

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
    .notif-container{position:fixed;top:25px;right:40px;display:inline-block;z-index:9999;}
    .notif-btn{background:var(--navy-mid);border:1px solid var(--border);color:var(--white);padding:10px 16px;border-radius:12px;cursor:pointer;display:flex;align-items:center;font-size:1.2rem;transition:0.2s;box-shadow:0 4px 15px rgba(0,0,0,0.2);}
    .notif-btn:hover{background:var(--navy-light);border-color:var(--teal);}
    .notif-badge{background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:50px;position:absolute;top:-5px;right:-5px;border:2px solid var(--navy-card);}
    .notif-dropdown{position:absolute;top:100%;right:0;width:300px;max-height:500px;background:var(--navy-card);border:1px solid var(--border);border-radius:16px;margin-top:12px;display:none;z-index:10000;box-shadow:0 25px 60px rgba(0,0,0,0.5);overflow:hidden;backdrop-filter:blur(20px);}
    .notif-dropdown.show{display:block;}
    .notif-list{max-height:400px;overflow-y:auto;}.notif-item{padding:16px;border-bottom:1px solid var(--border);transition:0.2s;text-decoration:none;color:inherit;display:block;text-align:left;}
    .notif-item:hover{background:rgba(255,255,255,0.04);}
    .notif-item-title{font-size:0.85rem;font-weight:600;color:var(--teal);margin-bottom:2px;}
    .notif-item-desc{font-size:0.75rem;color:var(--muted);line-height:1.4;}
    .notif-actions{display:flex;gap:8px;margin-top:10px;}
    .notif-btn-sm{padding:5px 12px;border-radius:50px;font-size:0.7rem;font-weight:600;text-decoration:none;transition:0.2s;flex:1;text-align:center;}
    .notif-btn-accept{background:rgba(34,197,94,0.2);color:var(--success);border:1px solid rgba(34,197,94,0.3);}
    .notif-btn-accept:hover{background:rgba(34,197,94,0.3);}
    .notif-btn-reject{background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.3);}
    .notif-btn-reject:hover{background:rgba(239,68,68,0.3);}
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
<script src="../js/page-transition.js"></script>
</head>
<body>
<div class="page-bg"></div>
<div class="layout">
<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">TELEMEDICINE</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
        <a href="patients.php" class="nav-link"><span class="nav-icon">👥</span> Patients</a>
        <a href="account.php" class="nav-link"><span class="nav-icon">👤</span> My Account</a>
    </div>
</aside>
 
<!-- MAIN -->
<main class="main">
    <?php 
        $notifications = array_values(array_filter(
            getPendingNotifications($conn, $doctor_id),
            static fn($notification) => in_array($notification['type'], ['info', 'report'], true)
        ));
        $notifCount = count($notifications);
        ?>
            <?php 
    $acc_stmt = $conn->prepare("SELECT name, email, specialization, profile_picture FROM doctors WHERE id = ?");
    $acc_stmt->bind_param("i", $doctor_id);
    $acc_stmt->execute();
    $user_data_acc = $acc_stmt->get_result()->fetch_assoc();
    $user_name_acc = $user_data_acc['name'] ?? $_SESSION['name'];
    $user_email_acc = $user_data_acc['email'] ?? 'N/A';
    $user_address_acc = !empty($user_data_acc['specialization']) ? $user_data_acc['specialization'] : 'General Practice';
    $user_pic_acc = $user_data_acc['profile_picture'] ?? null;
    // Handle doctor profile update
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['name']);
        $new_spec = trim($_POST['specialization'] ?? '');
        $upd_sql = "UPDATE doctors SET name=?, specialization=? WHERE id=?";
        $upd_params = [$new_name, $new_spec, $doctor_id]; $upd_types = "ssi";
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../images/profiles/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $fn = uniqid('profile_').'.'.$ext;
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir.$fn)) {
                    $upd_sql = "UPDATE doctors SET name=?, specialization=?, profile_picture=? WHERE id=?";
                    $upd_params = [$new_name, $new_spec, "images/profiles/$fn", $doctor_id]; $upd_types = "sssi";
                }
            }
        }
        $us = $conn->prepare($upd_sql); $us->bind_param($upd_types, ...$upd_params);
        if ($us->execute()) { $_SESSION['name'] = $new_name; header("Location: dashboard.php?success=Profile updated!"); exit; }
    }
    ?>
    <div class="notif-container" style="display:flex; gap:15px; align-items:center;">
        <div style="position:relative; display:inline-block;">
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
        </div> <!-- end relative wrapper -->
        
        <!-- Account Dropdown -->
        <div style="position:relative; display:inline-block;">
            <div class="notif-btn" id="accountBtn" style="border-radius:50%; width:44px; height:44px; justify-content:center; padding:0; background:var(--teal-glow); color:var(--teal); border:1px solid rgba(14,184,160,0.3); overflow:hidden; display:flex; align-items:center; font-weight:700; font-size:1.1rem;">
                <?php if ($user_pic_acc): ?>
                    <img src="../<?php echo htmlspecialchars($user_pic_acc); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    <?php echo strtoupper(substr($user_name_acc, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="notif-dropdown" id="accountDropdown" style="right:0; width:280px; padding:16px;">
                <div style="text-align:center; margin-bottom:16px;">
                    <?php if ($user_pic_acc): ?>
                        <img src="../<?php echo htmlspecialchars($user_pic_acc); ?>" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--teal); margin:0 auto 12px auto; display:block; cursor:pointer;" onclick="openImageModal(this.src)" title="View profile picture">
                    <?php else: ?>
                        <div style="width:60px; height:60px; border-radius:50%; background:var(--teal-glow); color:var(--teal); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 12px auto; font-weight:bold;">
                            <?php echo strtoupper(substr($user_name_acc, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--white); margin-bottom:4px;"><?php echo htmlspecialchars($user_name_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted); margin-bottom:4px;">📧 <?php echo htmlspecialchars($user_email_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted);">📍 <?php echo htmlspecialchars($user_address_acc); ?></div>
                </div>
                <div style="margin-bottom: 8px;">
                    <a href="account.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:var(--navy-light); color:var(--white); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='var(--navy-mid)'" onmouseout="this.style.background='var(--navy-light)'">👤 My Account</a>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <a href="../logout.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:rgba(239,68,68,0.1); color:var(--danger); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">🚪 Logout</a>
                </div>
            </div>
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
        <a href="patients.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">👥</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:white;">Patients</div><div style="font-size:0.8rem;color:var(--muted);">View people in your care</div></div>
        </a>
        <a href="account.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">💬</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:white;">My Account</div><div style="font-size:0.8rem;color:var(--muted);">Manage your profile</div></div>
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
 
<!-- IMAGE MODAL -->
<div id="imageModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:100000;padding:20px;align-items:center;justify-content:center;">
    <div style="position:relative;max-width:90vw;max-height:90vh;background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <button onclick="closeImageModal()" style="position:absolute;top:10px;right:10px;background:none;border:none;color:var(--white);font-size:1.5rem;cursor:pointer;z-index:1001;transition:all 0.2s;" onmouseover="this.style.color='var(--teal)'" onmouseout="this.style.color='var(--white)'">✕</button>
        <img id="modalImage" src="" alt="Profile View" style="max-width:100%;max-height:80vh;border-radius:10px;object-fit:contain;">
    </div>
</div>

<script>
function hideAccountDropdown() { const d = document.getElementById('accountDropdown'); if(d) d.classList.remove('show'); }

function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    document.getElementById('modalImage').src = src;
    modal.style.display = 'flex';
}
function closeImageModal() { document.getElementById('imageModal').style.display = 'none'; }
window.addEventListener('click', function(e){
    if(e.target === document.getElementById('imageModal')) closeImageModal();
});

// Poll every 4 seconds for new incoming calls
let popupShown = false;
let knownCallIds = new Set([
    <?php
        // Reset query
        $conn->query("SELECT id FROM video_calls WHERE doctor_id='$doctor_id' AND status='waiting' AND initiated_by='patient'");
        $existing = $conn->query("SELECT id FROM video_calls WHERE doctor_id='$doctor_id' AND status='waiting' AND initiated_by='patient'");
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
    document.querySelectorAll('.alert, .call-item').forEach(a => {
        a.style.transition = 'opacity 0.5s ease';
        a.style.opacity = '0';
        setTimeout(() => a.style.display = 'none', 500);
    });
}, 7000);

// Clear URL parameters so they don't reappear on refresh
if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('success'); 
    url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
}

document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
    if(document.getElementById('accountDropdown')) document.getElementById('accountDropdown').classList.remove('show');
});
if(document.getElementById('accountBtn')) {
    document.getElementById('accountBtn').addEventListener('click', function(e){
        document.getElementById('accountDropdown').classList.toggle('show');
        if(document.getElementById('notifDropdown')) document.getElementById('notifDropdown').classList.remove('show');
    });
}
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){
        if(document.getElementById('notifDropdown')) document.getElementById('notifDropdown').classList.remove('show');
        if(document.getElementById('accountDropdown')) document.getElementById('accountDropdown').classList.remove('show');
    }
});
</script>
</body>
</html>







