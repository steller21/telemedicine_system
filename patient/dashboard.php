<?php
session_start();
require_once("../config/db.php");
require_once("monitor_core.php");
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') { 
    header("Location: ../login.php"); 
    exit; 
}
 
$patient_id = intval($_SESSION['user_id']);

// Ensure vitals table exists
$conn->query("CREATE TABLE IF NOT EXISTS patient_vitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    systolic INT,
    diastolic INT,
    glucose DECIMAL(5,2),
    spo2 INT,
    heart_rate INT,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
)");

// Handle vitals logging
if (isset($_POST['log_vitals'])) {
    $sys = intval($_POST['systolic']);
    $dia = intval($_POST['diastolic']);
    $glu = floatval($_POST['glucose']);
    $spo = intval($_POST['spo2']);
    $hr = intval($_POST['heart_rate']);
    $stmt_v = $conn->prepare("INSERT INTO patient_vitals (patient_id, systolic, diastolic, glucose, spo2, heart_rate) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_v->bind_param("iiidii", $patient_id, $sys, $dia, $glu, $spo, $hr);
    $stmt_v->execute();
    header("Location: dashboard.php?success=Vitals logged successfully!");
    exit;
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_address = trim($_POST['address']);
    
    $update_sql = "UPDATE patients SET name=?, address=? WHERE id=?";
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
                $update_sql = "UPDATE patients SET name=?, address=?, profile_picture=? WHERE id=?";
                $params = [$new_name, $new_address, $db_path, $patient_id];
                $types = "sssi";
            }
        }
    }

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $_SESSION['name'] = $new_name;
        header("Location: dashboard.php?success=Profile updated successfully!");
        exit;
    }
}
 
$appointments = $conn->query("SELECT a.id, a.appointment_date, u.name as doctor_name 
FROM appointments a 
JOIN doctors u ON a.doctor_id = u.id 
WHERE a.patient_id = '$patient_id' AND DATE_ADD(a.appointment_date, INTERVAL 2 HOUR) >= NOW() 
ORDER BY a.appointment_date ASC");
 
$upcoming_appts_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE patient_id='$patient_id' AND DATE_ADD(appointment_date, INTERVAL 2 HOUR) >= NOW()")->fetch_assoc()['c'];
 
$pending_meds = 0;
$today = date('Y-m-d');
$checklist_stmt = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
if ($checklist_stmt) {
    $checklist_stmt->bind_param("i", $patient_id);
    $checklist_stmt->execute();
    $checklist_result = $checklist_stmt->get_result();

    if ($checklist_result && $checklist_result->num_rows > 0) {
        $checklist_id = (int) $checklist_result->fetch_assoc()['id'];
        $items_stmt = $conn->prepare("SELECT id, start_date, duration_days, times_of_day FROM checklist_items WHERE checklist_id = ?");

        if ($items_stmt) {
            $items_stmt->bind_param("i", $checklist_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();

            while ($item = $items_result->fetch_assoc()) {
                $start_date = !empty($item['start_date']) ? $item['start_date'] : $today;
                $duration_days = isset($item['duration_days']) ? (int) $item['duration_days'] : 0;

                if ($start_date > $today) {
                    continue;
                }

                if ($duration_days > 0) {
                    $end_date = date('Y-m-d', strtotime($start_date . ' +' . ($duration_days - 1) . ' days'));
                    if ($end_date < $today) {
                        continue;
                    }
                }

                $time_slots = array_filter(array_map('trim', explode(',', (string) ($item['times_of_day'] ?? ''))));
                foreach ($time_slots as $slot) {
                    $intake_stmt = $conn->prepare("SELECT status FROM medicine_intakes WHERE checklist_item_id = ? AND scheduled_date = ? AND time_of_day_slot = ?");
                    if ($intake_stmt) {
                        $item_id = (int) $item['id'];
                        $intake_stmt->bind_param("iss", $item_id, $today, $slot);
                        $intake_stmt->execute();
                        $intake_result = $intake_stmt->get_result();

                        if (!$intake_result || $intake_result->num_rows === 0) {
                            $pending_meds++;
                            continue;
                        }

                        $intake = $intake_result->fetch_assoc();
                        if (($intake['status'] ?? 'pending') !== 'completed') {
                            $pending_meds++;
                        }
                    }
                }
            }
        }
    }
}
 
$monitors     = $conn->query("SELECT COUNT(*) as c FROM patient_monitors WHERE patient_id='$patient_id'")->fetch_assoc()['c'];
 
// Fetch Vitals Trend Data
$vitals_query = $conn->prepare("SELECT systolic, diastolic, glucose, spo2, heart_rate, DATE_FORMAT(logged_at, '%b %d %H:%i') as label FROM patient_vitals WHERE patient_id = ? ORDER BY logged_at DESC LIMIT 10");
$vitals_query->bind_param("i", $patient_id);
$vitals_query->execute();
$vitals_res = $vitals_query->get_result();
$v_history = [];
while($v_row = $vitals_res->fetch_assoc()) { $v_history[] = $v_row; }
$v_history = array_reverse($v_history);
$vitals_count = $conn->query("SELECT COUNT(*) FROM patient_vitals WHERE patient_id='$patient_id'")->fetch_row()[0];

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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
</head><body>
<div class="page-bg"></div>
<div class="layout">
<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">MediConnect</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link active"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link"><span class="nav-icon">📅</span> Book Appointment</a>
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
</aside>
<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
        <p>Here's an overview of your health activity today.</p>
    </div>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>" style="margin-bottom:20px;">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <?php 
    $notifCount = getPendingNotificationCount($conn, $patient_id);
    $notifications = getPendingNotifications($conn, $patient_id);
    $chatNotifCount = count(array_filter($notifications, static fn($notification) => ($notification['type'] ?? '') === 'chat'));
    ?>
        <?php 
    $acc_stmt = $conn->prepare("SELECT name, email, address, profile_picture FROM patients WHERE id = ?");
    $acc_stmt->bind_param("i", $patient_id);
    $acc_stmt->execute();
    $user_data_acc = $acc_stmt->get_result()->fetch_assoc();
    $user_name_acc = $user_data_acc['name'] ?? $_SESSION['name'];
    $user_email_acc = $user_data_acc['email'] ?? 'N/A';
    $user_address_acc = !empty($user_data_acc['address']) ? $user_data_acc['address'] : 'Not provided';
    $user_pic_acc = $user_data_acc['profile_picture'] ?? null;
    ?>
    <div class="notif-container" style="display:flex; gap:15px; align-items:center;">
        <a href="friends.php" class="notif-btn" style="text-decoration:none; position:relative; <?php echo $chatNotifCount > 0 ? 'background:rgba(14,184,160,0.16); border-color:var(--teal); color:var(--teal); box-shadow:0 0 0 3px rgba(14,184,160,0.12);' : ''; ?>" title="Friends & Chat">💬<?php if($chatNotifCount > 0): ?><span class="notif-badge"><?php echo $chatNotifCount; ?></span><?php endif; ?></a>
        <div style="position:relative; display:inline-block;">
        <div class="notif-btn" id="notifBtn" style="<?php echo $notifCount > 0 ? 'background:rgba(14,184,160,0.16); border-color:var(--teal); color:var(--teal); box-shadow:0 0 0 3px rgba(14,184,160,0.12);' : ''; ?>">🔔 <?php if($notifCount > 0): ?><span class="notif-badge"><?php echo $notifCount; ?></span><?php endif; ?></div>
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
            <div class="notif-btn" id="accountBtn" style="border-radius:50%; width:44px; height:44px; justify-content:center; padding:0; background:<?php echo $user_pic_acc ? 'var(--teal-glow)' : 'var(--teal)'; ?>; color:var(--navy); border:1px solid rgba(14,184,160,0.3); overflow:hidden; display:flex; align-items:center; font-weight:700; font-size:1.1rem;">
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
                        <div style="width:60px; height:60px; border-radius:50%; background:var(--teal); color:var(--navy); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 12px auto; font-weight:bold;">
                            <?php echo strtoupper(substr($user_name_acc, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--white); margin-bottom:4px;"><?php echo htmlspecialchars($user_name_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted); margin-bottom:4px;">📧 <?php echo htmlspecialchars($user_email_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted);">📍 <?php echo htmlspecialchars($user_address_acc); ?></div>
                </div>
                <div style="margin-bottom: 8px;">
                    <a href="#" onclick="event.preventDefault(); hideAccountDropdown(); showEditProfileModal();" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:var(--navy-light); color:var(--white); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='var(--navy-mid)'" onmouseout="this.style.background='var(--navy-light)'">✏️ Edit Profile</a>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <a href="../logout.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:rgba(239,68,68,0.1); color:var(--danger); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="grid-4" style="margin-bottom:28px;">
        <div class="stat-card"><div class="stat-card-icon">📅</div><div class="stat-card-value"><?php echo $upcoming_appts_count; ?></div><div class="stat-card-label">Upcoming Appointments</div></div>
        <div class="stat-card"><div class="stat-card-icon">💊</div><div class="stat-card-value"><?php echo $pending_meds; ?></div><div class="stat-card-label">Pending Medicines</div></div>
        <div class="stat-card"><div class="stat-card-icon">👁️</div><div class="stat-card-value"><?php echo $monitors; ?></div><div class="stat-card-label">Monitors</div></div>
        <div class="stat-card"><div class="stat-card-icon">📊</div><div class="stat-card-value"><?php echo $vitals_count; ?></div><div class="stat-card-label">Vitals Logged</div></div>
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
    <!-- HEALTH TRENDS (VITALS) -->
    <div class="card" style="margin-bottom:28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;">📊 Health Trends</h2>
            <button onclick="showLogVitalsModal()" class="btn btn-primary btn-sm">+ Log Vitals</button>
        </div>
        <?php if($vitals_count > 0): ?>
            <div style="height:300px; width:100%;"><canvas id="vitalsChart"></canvas></div>
        <?php else: ?>
            <div class="empty-state" style="padding:40px 20px;">
                <div class="empty-icon">📊</div>
                <h3>No health records yet</h3>
                <p>Add your daily health details to see a simple summary here.</p>
                <br><button onclick="showLogVitalsModal()" class="btn btn-primary btn-sm">Log Vitals Now</button>
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
        <button onclick="showLogVitalsModal()" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;text-align:left;width:100%;cursor:pointer;border:none;background:var(--navy-card);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">📊</div>
            <div><div style="font-weight:600;margin-bottom:2px;color:var(--white);">Log Health Details</div><div style="font-size:0.8rem;color:var(--muted);">Add BP, heart rate, oxygen and sugar</div></div>
        </button>
        <a href="friends.php" class="card card-sm" style="text-decoration:none;display:flex;align-items:center;gap:14px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <div style="font-size:1.8rem;">💬</div>
            <div><div style="font-weight:600;margin-bottom:2px;">Messages</div><div style="font-size:0.8rem;color:var(--muted);">Chat with friends</div></div>
        </a>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:450px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.4);animation:modalSlideIn 0.3s ease-out;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:20px;color:var(--white);">Edit Profile</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group" style="text-align:center; margin-bottom:20px;">
                    <label for="profilePictureInput" style="cursor:pointer; display:inline-block; position:relative;">
                        <img id="profilePicturePreview" src="../<?php echo htmlspecialchars($user_pic_acc ?: 'images/default_user.svg'); ?>" 
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

    <!-- Log Vitals Modal -->
    <div id="logVitalsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center;">
        <div class="modal-content" style="max-width:500px; animation:modalSlideIn 0.3s ease-out;">
            <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:20px;color:var(--white);">Log Daily Health Details</h2>
            <form method="POST">
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Blood Pressure (BP) - Top Number</label><input class="form-input" type="number" name="systolic" placeholder="e.g. 120" required></div>
                    <div class="form-group"><label class="form-label">Blood Pressure (BP) - Bottom Number</label><input class="form-input" type="number" name="diastolic" placeholder="e.g. 80" required></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Blood Sugar</label><input class="form-input" type="number" step="0.1" name="glucose" placeholder="e.g. 95.5" required></div>
                    <div class="form-group"><label class="form-label">Oxygen Level (SpO2 %)</label><input class="form-input" type="number" name="spo2" placeholder="e.g. 98" required></div>
                </div>
                <div class="form-group"><label class="form-label">Heart Rate</label><input class="form-input" type="number" name="heart_rate" placeholder="e.g. 72" required></div>
                
                <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                    <button type="button" onclick="hideLogVitalsModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="log_vitals" class="btn btn-primary">Save Vitals</button>
                </div>
            </form>
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
    function showEditProfileModal() { document.getElementById('editProfileModal').style.display = 'flex'; }
    function hideEditProfileModal() { document.getElementById('editProfileModal').style.display = 'none'; }
    function showLogVitalsModal() { document.getElementById('logVitalsModal').style.display = 'flex'; }
    function hideLogVitalsModal() { document.getElementById('logVitalsModal').style.display = 'none'; }
    function hideAccountDropdown() { const d = document.getElementById('accountDropdown'); if(d) d.classList.remove('show'); }
    window.onclick = function(event) {
        const modal1 = document.getElementById('editProfileModal');
        const modal2 = document.getElementById('logVitalsModal');
        const modal3 = document.getElementById('imageModal');
        if (event.target == modal1) hideEditProfileModal();
        if (event.target == modal2) hideLogVitalsModal();
        if (event.target == modal3) closeImageModal();
    }

    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        document.getElementById('modalImage').src = src;
        modal.style.display = 'flex';
    }
    function closeImageModal() { document.getElementById('imageModal').style.display = 'none'; }

    function previewProfilePicture(event) {
        const reader = new FileReader();
        reader.onload = function(){
            const output = document.getElementById('profilePicturePreview');
            output.src = reader.result;
        };
        reader.readAsDataURL(event.target.files[0]);
    }
    </script>

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
            chatbotFab.addEventListener('click', () => chatbotModal.classList.toggle('show'));
            chatbotClose.addEventListener('click', () => chatbotModal.classList.remove('show'));
            document.addEventListener('click', (e) => {
                if (!chatbotModal.contains(e.target) && !chatbotFab.contains(e.target) && chatbotModal.classList.contains('show')) chatbotModal.classList.remove('show');
            });
        }
    });
    </script>
</main>

<script>
<?php if($vitals_count > 0): ?>
const ctxV = document.getElementById('vitalsChart').getContext('2d');
const vitalsHistory = <?php echo json_encode($v_history); ?>;
new Chart(ctxV, {
    type: 'line',
    data: {
        labels: vitalsHistory.map(v => v.label),
        datasets: [
            { label: 'Blood Pressure (BP) - Top Number', data: vitalsHistory.map(v => v.systolic), borderColor: '#0EB8A0', tension: 0.3 },
            { label: 'Blood Pressure (BP) - Bottom Number', data: vitalsHistory.map(v => v.diastolic), borderColor: '#22C55E', tension: 0.3 },
            { label: 'Blood Sugar', data: vitalsHistory.map(v => v.glucose), borderColor: '#F59E0B', tension: 0.3 },
            { label: 'Heart Rate', data: vitalsHistory.map(v => v.heart_rate), borderColor: '#EF4444', tension: 0.3 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#64748b' } } },
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b' } },
            x: { grid: { display: false }, ticks: { color: '#64748b' } }
        }
    }
});
<?php endif; ?>

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
    url.searchParams.delete('error');
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
