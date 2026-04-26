<?php
session_start(); require_once("../config/db.php");
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$doctor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT a.*, u.name AS patient_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC");
if ($stmt !== false) {
    $stmt->bind_param("i", $doctor_id); $stmt->execute(); $result = $stmt->get_result();
} else {
    $result = false;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Appointments — MediConnect</title>
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
        <a href="appointments.php" class="nav-link active"><span class="nav-icon">📅</span> Appointments</a>
        <a href="monitor_patients.php" class="nav-link"><span class="nav-icon">👥</span> Monitor Patients</a>
        <a href="friends.php" class="nav-link"><span class="nav-icon">💬</span> Friends & Chat</a>
    </div>
    <div class="sidebar-bottom"><a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a></div>
</aside>
<main class="main">
    <div class="page-header"><h1>📅 My Appointments</h1><p>All patient appointments scheduled with you.</p></div>
    <div class="card">
    <?php if($result && $result->num_rows > 0): ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Patient</th><th>Date & Time</th><th>Status</th></tr></thead>
        <tbody>
        <?php while($row=$result->fetch_assoc()): $isPast = strtotime($row['appointment_date']) < time(); ?>
        <tr>
            <td><div style="display:flex;align-items:center;gap:12px;"><div style="width:38px;height:38px;background:var(--teal-glow);border-radius:50%;display:flex;align-items:center;justify-content:center;">🙋</div><strong><?php echo htmlspecialchars($row['patient_name']); ?></strong></div></td>
            <td><?php echo date('D, d M Y · h:i A', strtotime($row['appointment_date'])); ?></td>
            <td><?php if($isPast): ?><span class="badge badge-info">Completed</span><?php else: ?><span class="badge badge-success">Upcoming</span><?php endif; ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
    <?php else: ?><div class="empty-state"><div class="empty-icon">📅</div><h3>No appointments yet</h3><p>Patients will appear here once they book with you.</p></div><?php endif; ?>
    </div>
</main></div></body></html>