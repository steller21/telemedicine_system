<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];
ensureVideoCallSchema($conn);
expireWaitingCalls($conn);
$stmt = $conn->prepare("SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.address AS patient_address, p.dob AS patient_dob FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC");

if ($stmt !== false) {
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Appointments - TELEMEDICINE</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');

:root {
    --teal: #0EB8A0;
    --teal-dark: #0A8A78;
    --teal-glow: rgba(14,184,160,0.15);
    --navy: #f8fafc;
    --navy-mid: #ffffff;
    --navy-light: #f1f5f9;
    --navy-card: #ffffff;
    --white: #1e293b;
    --cream: #F5F0E8;
    --muted: #64748b;
    --muted-dim: #94a3b8;
    --accent: #FF6B4A;
    --success: #22C55E;
    --warning: #F59E0B;
    --danger: #EF4444;
    --border: rgba(0,0,0,0.08);
    --radius: 14px;
    --radius-lg: 22px;
    --shadow: 0 4px 20px rgba(0,0,0,0.05);
}

body.dark-mode {
    --navy: #0B1526;
    --navy-mid: #112035;
    --navy-light: #1A3050;
    --navy-card: #0F1E36;
    --white: #FFFFFF;
    --muted: #7A8EA8;
    --muted-dim: #4A5E78;
    --border: rgba(255,255,255,0.07);
    --shadow: 0 8px 32px rgba(0,0,0,0.25);
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
    position: fixed;
    inset: 0;
    z-index: -1;
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
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 50;
    padding: 24px 0;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 24px 28px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
    text-decoration: none;
}

.logo-dot {
    width: 9px;
    height: 9px;
    background: var(--teal);
    border-radius: 50%;
    box-shadow: 0 0 10px var(--teal);
    animation: blink 2s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.logo-text {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--white);
}

.nav-section { padding: 0 12px; margin-bottom: 8px; }

.nav-section-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted-dim);
    font-weight: 600;
    padding: 0 12px 8px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    color: var(--muted);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
    margin-bottom: 2px;
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
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 4px;
}

.page-header p {
    color: var(--muted);
    font-size: 0.9rem;
}

.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow);
}

.table-wrap { overflow-x: auto; border-radius: var(--radius); }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }

thead th {
    text-align: left;
    padding: 12px 16px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
}

tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: top;
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 600;
}

.badge::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
}

.badge-success { background: rgba(34,197,94,0.12); color: var(--success); }
.badge-info { background: var(--teal-glow); color: var(--teal); }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}

.empty-state .empty-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--white);
}

.empty-state p { font-size: 0.85rem; }
.muted-text { color: var(--muted); }

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0; max-width: 100%; padding: 20px; }
}
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
<script src="../js/page-transition.js"></script>
</head>
<body>
<div class="page-bg"></div>
<div class="layout">
<aside class="sidebar">
    <div class="sidebar-logo" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">TELEMEDICINE</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">&#127769;</button>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">&#127968;</span> Dashboard</a>
        <a href="appointments.php" class="nav-link active"><span class="nav-icon">&#128197;</span> Appointments</a>
        <a href="patients.php" class="nav-link"><span class="nav-icon">&#128101;</span> Patients</a>
        <a href="account.php" class="nav-link"><span class="nav-icon">&#128172;</span> My Account</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <h1>&#128197; My Appointments</h1>
        <p>All patient appointments scheduled with you.</p>
    </div>

    <div class="card">
    <?php if ($result && $result->num_rows > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Age</th>
                        <th>Phone Number</th>
                        <th>Address</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $isPast = strtotime($row['appointment_date']) < time();
                    $patientAge = (!empty($row['patient_dob']) && $row['patient_dob'] !== '0000-00-00')
                        ? date_diff(date_create($row['patient_dob']), date_create('today'))->y
                        : null;
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:38px;height:38px;background:var(--teal-glow);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;">&#128100;</div>
                                <strong><?php echo htmlspecialchars($row['patient_name']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo $patientAge !== null ? $patientAge . ' yrs' : '<span class="muted-text">Not provided</span>'; ?></td>
                        <td><?php echo !empty($row['patient_phone']) ? htmlspecialchars($row['patient_phone']) : '<span class="muted-text">Not provided</span>'; ?></td>
                        <td style="max-width:260px;"><?php echo !empty($row['patient_address']) ? htmlspecialchars($row['patient_address']) : '<span class="muted-text">Not provided</span>'; ?></td>
                        <td><?php echo date('D, d M Y - h:i A', strtotime($row['appointment_date'])); ?></td>
                        <td><?php if ($isPast): ?><span class="badge badge-info">Completed</span><?php else: ?><span class="badge badge-success">Upcoming</span><?php endif; ?></td>
                        <td>
                            <?php if (!$isPast): ?>
                                <a href="initiate_call.php?appointment_id=<?php echo (int)$row['id']; ?>" style="display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:var(--teal);color:var(--navy);font-weight:700;text-decoration:none;">Call Patient</a>
                            <?php else: ?>
                                <span class="muted-text">Unavailable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">&#128197;</div>
            <h3>No appointments yet</h3>
            <p>Patients will appear here once they book with you.</p>
        </div>
    <?php endif; ?>
    </div>
</main>
</div>

<script>
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
}

themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
});

setTimeout(() => {
    document.querySelectorAll('.alert').forEach((a) => {
        a.style.transition = 'opacity 0.5s ease';
        a.style.opacity = '0';
        setTimeout(() => {
            a.style.display = 'none';
        }, 500);
    });
}, 7000);

if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('success');
    window.history.replaceState({}, document.title, url);
}
</script>
</body>
</html>

