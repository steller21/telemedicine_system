<?php
session_start(); require_once("../config/db.php");
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') { header("Location: ../login.php"); exit; }
$patient_id = $_SESSION['user_id']; $msg=""; $msg_type="";

function convertDurationToDays($duration_value, $duration_unit) {
    $duration_value = max(1, (int) $duration_value);
    if ($duration_unit === 'weeks') {
        return $duration_value * 7;
    }
    if ($duration_unit === 'months') {
        $start = new DateTime();
        $end = (clone $start)->modify('+' . $duration_value . ' month');
        return max(1, (int) $start->diff($end)->days);
    }
    return $duration_value;
}

if (isset($_POST['add'])) {
    $medicine_names = $_POST['medicine_name'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $all_times = $_POST['medicine_time'] ?? [];
    $duration_values = $_POST['duration_value'] ?? [];
    $duration_units = $_POST['duration_unit'] ?? [];
    $images = $_FILES['image'] ?? [];
    $prescription_file = $_FILES['prescription_file'] ?? null;

    $success_count = 0;
    $error_occurred = false;

    if (empty($medicine_names) || empty($medicine_names[0])) {
        $msg = "Please add at least one medicine entry.";
        $msg_type = "error";
    } elseif (!$prescription_file || empty($prescription_file['name'])) {
        $msg = "Prescription attachment is required.";
        $msg_type = "error";
    } else {
        $shared_prescription_target = null;
        $prescription_dir = "../uploads/prescriptions/";
        if (!is_dir($prescription_dir)) mkdir($prescription_dir, 0777, true);

        $prescription_ext = strtolower(pathinfo($prescription_file['name'], PATHINFO_EXTENSION));
        $allowed_prescription_ext = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($prescription_ext, $allowed_prescription_ext, true)) {
            $shared_prescription_target = $prescription_dir . time() . "_rx_shared." . $prescription_ext;
            if (!move_uploaded_file($prescription_file['tmp_name'], $shared_prescription_target)) {
                $msg = "Unable to upload the prescription attachment.";
                $msg_type = "error";
            }
        } else {
            $msg = "Prescription attachment must be a PDF or image file.";
            $msg_type = "error";
        }

        if ($msg_type === "error") {
            $error_occurred = true;
        } else {
            $stmt_checklist = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
            $stmt_checklist->bind_param("i", $patient_id);
            $stmt_checklist->execute();
            $res_checklist = $stmt_checklist->get_result();
            
            if ($res_checklist->num_rows > 0) {
                $checklist_id = $res_checklist->fetch_assoc()['id'];
            } else {
                $cs = $conn->prepare("INSERT INTO checklists (patient_id, created_by, title) VALUES (?, ?, 'Daily Medicines')");
                $cs->bind_param("ii", $patient_id, $patient_id);
                $cs->execute();
                $checklist_id = $cs->insert_id;
            }

            foreach ($medicine_names as $index => $medicine_name) {
                $medicine_name = trim($medicine_name);
                $dosage = trim($dosages[$index]);
                $times_for_medicine = isset($all_times[$index]) ? $all_times[$index] : [];
                $duration_value = isset($duration_values[$index]) ? (int) $duration_values[$index] : 1;
                $duration_unit = $duration_units[$index] ?? 'days';
                $duration_days = convertDurationToDays($duration_value, $duration_unit);
                $time_str = implode(",", $times_for_medicine);
                $target = null;

                if (isset($images['name'][$index]) && !empty($images['name'][$index])) {
                    $upload_dir = "../uploads/medicines/";
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $target = $upload_dir . time() . "_" . basename($images['name'][$index]);
                    move_uploaded_file($images['tmp_name'][$index], $target);
                }

                if (!empty($medicine_name) && !empty($dosage) && !empty($time_str)) {
                    $is = $conn->prepare("INSERT INTO checklist_items (checklist_id, medicine_name, medicine_image, dosage, times_of_day, status, duration_days, prescription_file) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
                    $is->bind_param("issssis", $checklist_id, $medicine_name, $target, $dosage, $time_str, $duration_days, $shared_prescription_target);
                    
                    if ($is->execute()) {
                        $success_count++;
                    } else {
                        error_log("Error inserting medicine: " . $is->error);
                        $error_occurred = true;
                    }
                }
            }

            if ($success_count > 0) {
                $msg = "$success_count medicine(s) added successfully!";
                $msg_type = "success";
            } elseif (!$error_occurred) {
                $msg = "No valid medicine entries were provided.";
                $msg_type = "error";
            } else {
                $msg = "An error occurred while adding some medicines.";
                $msg_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Add Medicine — MediConnect</title>
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
        <a href="add_checklist.php" class="nav-link active"><span class="nav-icon">➕</span> Add Medicine</a>
        <a href="upload_report.php" class="nav-link"><span class="nav-icon">📄</span> Upload Report</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
</aside>
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

<main class="main">
    <div class="page-header"><h1>➕ Add Medicine</h1><p>Add a new medicine to your daily checklist.</p></div>
    <div style="max-width:600px;">
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type;?>"><?php echo $msg_type=='success'?'✅':'❌';?> <?php echo htmlspecialchars($msg);?> <?php if($msg_type=='success'): ?><a href="checklist.php" style="color:inherit;font-weight:700;">View checklist →</a><?php endif;?></div><?php endif;?>
        <div class="card">
            <form method="POST" enctype="multipart/form-data" id="add-medicine-form">
                <div class="form-group">
                    <label class="form-label">Prescription Attachment</label>
                    <input class="form-input" type="file" name="prescription_file" accept=".pdf,image/*" style="padding:10px;" required>
                    <div style="font-size:0.75rem;color:var(--muted);margin-top:6px;">This one prescription will be attached to all medicines added below.</div>
                </div>
                <div id="medicine-entries-container">
                    <div class="medicine-entry" id="medicine-entry-0">
                        <h3 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;margin-bottom:15px;color:var(--white);">Medicine #1</h3>
                        <div class="form-group">
                            <label class="form-label">Medicine Name</label>
                            <input class="form-input" type="text" name="medicine_name[]" placeholder="e.g. Paracetamol 500mg" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dosage Instructions</label>
                            <input class="form-input" type="text" name="dosage[]" placeholder="e.g. 1 tablet after food" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">When to Take (Select all that apply)</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="morning-0" name="medicine_time[0][]" value="morning" style="width:18px;height:18px;cursor:pointer;">
                                    <label for="morning-0" style="cursor:pointer;margin:0;font-size:0.9rem;color:var(--white);">🌅 Morning (6-12 AM)</label>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="afternoon-0" name="medicine_time[0][]" value="afternoon" style="width:18px;height:18px;cursor:pointer;">
                                    <label for="afternoon-0" style="cursor:pointer;margin:0;font-size:0.9rem;color:var(--white);">☀️ Afternoon (12-5 PM)</label>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="evening-0" name="medicine_time[0][]" value="evening" style="width:18px;height:18px;cursor:pointer;">
                                    <label for="evening-0" style="cursor:pointer;margin:0;font-size:0.9rem;color:var(--white);">🌆 Evening (5-8 PM)</label>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="night-0" name="medicine_time[0][]" value="night" style="width:18px;height:18px;cursor:pointer;">
                                    <label for="night-0" style="cursor:pointer;margin:0;font-size:0.9rem;color:var(--white);">🌙 Night (8-11 PM)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <input class="form-input" type="number" name="duration_value[]" min="1" value="1" required>
                                <select class="form-input" name="duration_unit[]">
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months">Months</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Medicine Image (optional)</label>
                            <input class="form-input" type="file" name="image[]" accept="image/*" style="padding:10px;">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm remove-medicine-btn" style="display:none;">Remove</button>
                    </div>
                </div>
                <button type="button" id="add-more-medicine" class="btn btn-secondary" style="width:100%; justify-content:center; margin-top:10px;">+ Add Another Medicine</button>
                <button class="btn btn-primary btn-full" type="submit" name="add">💊 Add Medicine</button>
            </form>
        </div>
    </div>
</main></div>
<script>
    let medicineCount = 1;
    document.getElementById('add-more-medicine').addEventListener('click', function() {
        const container = document.getElementById('medicine-entries-container');
        const template = document.getElementById('medicine-entry-0');
        const newEntry = template.cloneNode(true);
        
        newEntry.id = 'medicine-entry-' + medicineCount;
        newEntry.querySelector('h3').textContent = 'Medicine #' + (medicineCount + 1);
        newEntry.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
        newEntry.querySelectorAll('input[type="file"]').forEach(input => input.value = ''); // Clear file input
        newEntry.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
            checkbox.name = `medicine_time[${medicineCount}][]`;
            checkbox.id = checkbox.id.split('-')[0] + '-' + medicineCount; // Update ID for label
            checkbox.nextElementSibling.setAttribute('for', checkbox.id); // Update label for
        });
        newEntry.querySelector('.remove-medicine-btn').style.display = 'block';
        newEntry.querySelector('.remove-medicine-btn').onclick = function() {
            newEntry.remove();
            // Re-index titles if needed (optional, but good for UX)
            document.querySelectorAll('.medicine-entry').forEach((entry, idx) => {
                entry.querySelector('h3').textContent = 'Medicine #' + (idx + 1);
                // Re-index checkbox names and IDs
                entry.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                    const baseId = checkbox.id.split('-')[0];
                    checkbox.name = `medicine_time[${idx}][]`;
                    checkbox.id = baseId + '-' + idx;
                    checkbox.nextElementSibling.setAttribute('for', checkbox.id);
                });
            });
            medicineCount--;
        };
        container.appendChild(newEntry);
        medicineCount++;
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
    url.searchParams.delete('success'); 
    url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
}
</script>
</body></html>
