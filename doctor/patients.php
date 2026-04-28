<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);

// Get all patients that have appointments with this doctor
$patients = $conn->query("SELECT DISTINCT u.id, u.name, u.gender, COUNT(a.id) as total_appointments 
FROM users u
JOIN appointments a ON u.id = a.patient_id
WHERE a.doctor_id = '$doctor_id'
GROUP BY u.id, u.name, u.gender
ORDER BY u.name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Patients — MediConnect</title>
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
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--white);
}

.nav-section { padding: 0 12px 20px; }
.nav-section-label { font-size: 0.7rem; text-transform: uppercase; color: var(--muted-dim); font-weight: 600; padding: 16px 12px 8px; letter-spacing: 0.5px; }
.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius);
    color: var(--muted);
    text-decoration: none;
    transition: all 0.2s;
    margin-bottom: 4px;
}

.nav-link:hover { background: var(--navy-light); color: var(--teal); }
.nav-link.active { background: var(--teal-glow); color: var(--teal); }
.nav-icon { font-size: 1.2rem; min-width: 20px; }

.sidebar-bottom {
    margin-top: auto;
    padding: 0 12px 24px;
    border-top: 1px solid var(--border);
    padding-top: 24px;
}

/* MAIN */
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

.page-header p { color: var(--muted); font-size: 0.9rem; }

/* CARD */
.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    transition: all 0.2s;
}

.card:hover { border-color: rgba(14,184,160,0.3); }

/* TABLE */
.table-wrap { overflow-x: auto; border-radius: var(--radius); margin-top: 20px; }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
    padding: 14px;
    text-align: left;
    background: var(--navy-light);
    color: var(--muted);
    font-weight: 600;
    border-bottom: 1px solid var(--border);
}

tbody td { padding: 14px; border-bottom: 1px solid var(--border); }
tbody tr:hover { background: rgba(14,184,160,0.05); }

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}

.empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--teal);
    color: var(--navy);
}

.btn-primary:hover {
    background: var(--teal-dark);
    color: var(--white);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--navy-light);
    color: var(--white);
    border: 1px solid var(--border);
}

.btn-secondary:hover { background: var(--navy-mid); }
.btn-sm { padding: 7px 16px; font-size: 0.8rem; }

@media (max-width: 768px) {
    .sidebar { width: 200px; }
    .main { margin-left: 200px; max-width: calc(100% - 200px); padding: 20px; }
    .page-header h1 { font-size: 1.4rem; }
}
</style>
</head>
<body>
<div class="page-bg"></div>
<div class="layout">
<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
        <a href="dashboard.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">MediConnect</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Monitoring</div>
        <a href="patients.php" class="nav-link active"><span class="nav-icon">👥</span> My Patients</a>
    </div>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <h1>👥 My Patients</h1>
        <p>View all patients in your care.</p>
    </div>

    <div class="card">
        <?php if ($patients && $patients->num_rows > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Gender</th>
                            <th>Total Appointments</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $patients->fetch_assoc()): 
                            $title = ($row['gender'] === 'male') ? 'Mr.' : (($row['gender'] === 'female') ? 'Mrs.' : 'Mx.');
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div style="width:38px;height:38px;background:var(--teal-glow);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;">👤</div>
                                        <strong><?php echo htmlspecialchars($title . ' ' . $row['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo ucfirst($row['gender']); ?></td>
                                <td><?php echo $row['total_appointments']; ?></td>
                                <td><a href="add_prescription.php?patient_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">💊 Prescribe</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">👥</div>
                <h3>No patients found</h3>
                <p>Your patient list will appear here once you have appointments.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>
<script>
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
</script>
</body>
</html>
