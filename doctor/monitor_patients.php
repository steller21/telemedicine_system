<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);

// Get patients' medicines for this doctor (only patients who added doctor as monitor)
$patients_medicines = $conn->query("SELECT DISTINCT u.id, u.name, u.gender, ci.id as medicine_id, 
                                    ci.medicine_name, ci.dosage, ci.due_time, ci.status, ci.medicine_image
                                    FROM users u
                                    JOIN patient_monitors pm ON u.id = pm.patient_id
                                    LEFT JOIN checklists cl ON u.id = cl.patient_id
                                    LEFT JOIN checklist_items ci ON cl.id = ci.checklist_id
                                    WHERE pm.monitor_id='$doctor_id'
                                    ORDER BY u.name ASC, ci.due_time ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Monitor Patients — MediConnect</title>
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

/* SIDEBAR */
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

/* MAIN */
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

/* CARD */
.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow);
}

/* TABLE */
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
    padding: 12px; text-align: left;
    font-weight: 600; color: var(--muted);
    border-bottom: 1px solid var(--border);
    font-size: 0.85rem;
    background: var(--navy-light);
}

tbody tr { border-bottom: 1px solid var(--border); }
tbody tr:hover { background: rgba(14,184,160,0.05); }
tbody td { padding: 14px; }

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}

.empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }

@media (max-width: 768px) {
    .sidebar { width: 200px; }
    .main { margin-left: 200px; max-width: calc(100% - 200px); padding: 20px; }
}
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
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
        <a href="monitor_patients.php" class="nav-link active"><span class="nav-icon">👥</span> Monitor Patients</a>
    </div>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="nav-link"><span class="nav-icon">🚪</span> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <h1>👥 Monitor Patients</h1>
        <p>Track your patients' health checklists and medicine progress.</p>
    </div>

    <div class="card">
        <?php if ($patients_medicines && $patients_medicines->num_rows > 0): 
            // Fetch all data into array for grouping
            $medicines_by_patient = [];
            while ($row = $patients_medicines->fetch_assoc()) {
                if (!isset($medicines_by_patient[$row['id']])) {
                    $medicines_by_patient[$row['id']] = [
                        'name' => $row['name'],
                        'gender' => $row['gender'],
                        'medicines' => []
                    ];
                }
                if ($row['medicine_name']) {
                    $medicines_by_patient[$row['id']]['medicines'][] = [
                        'medicine_name' => $row['medicine_name'],
                        'dosage' => $row['dosage'],
                        'due_time' => $row['due_time'],
                        'status' => $row['status']
                    ];
                }
            }
        ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Medicine Name</th>
                            <th>Dosage</th>
                            <th>Due Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicines_by_patient as $patient_id => $patient_data): 
                            $title = ($patient_data['gender'] === 'male') ? 'Mr.' : (($patient_data['gender'] === 'female') ? 'Mrs.' : 'Mx.');
                            $patient_name = htmlspecialchars($title . ' ' . $patient_data['name']);
                            $first_medicine = true;
                        ?>
                            <?php if (count($patient_data['medicines']) > 0): ?>
                                <?php foreach ($patient_data['medicines'] as $med): 
                                    $status_badge = ($med['status'] === 'completed') ? '✅ Taken' : '⏳ Pending';
                                    $status_color = ($med['status'] === 'completed') ? 'var(--success)' : 'var(--warning)';
                                ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo ($first_medicine) ? $patient_name : ''; ?></td>
                                    <td style="color:var(--muted);"><?php echo htmlspecialchars($med['medicine_name'] ?? '—'); ?></td>
                                    <td style="color:var(--muted);"><?php echo htmlspecialchars($med['dosage'] ?? '—'); ?></td>
                                    <td style="color:var(--muted);"><?php echo htmlspecialchars($med['due_time'] ?? '—'); ?></td>
                                    <td style="color:<?php echo $status_color; ?>;font-weight:600;"><?php echo $status_badge; ?></td>
                                </tr>
                                <?php $first_medicine = false; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo $patient_name; ?></td>
                                    <td colspan="4" style="color:var(--muted);font-style:italic;">No medicines added</td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>No medicines found</h3>
                <p style="font-size:0.9rem;">Medicine data from patients who added you as a monitor will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>
</body>
</html>
