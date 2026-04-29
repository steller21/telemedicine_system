<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);

// Ensure checklist_items has completed_at column
$conn->query("ALTER TABLE checklist_items ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL");

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

// Handle accept request
if (isset($_GET['accept'])) {
    $request_id = intval($_GET['accept']);
    $req = $conn->prepare("SELECT requester_id FROM monitor_requests WHERE id=? AND requested_user_id=? AND status='pending'");
    if ($req) {
        $req->bind_param("ii", $request_id, $doctor_id);
        $req->execute();
        $result = $req->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $patient_id = $row['requester_id'];
            $ins = $conn->prepare("INSERT INTO patient_monitors (patient_id, monitor_id) VALUES (?, ?)");
            if ($ins) {
                $ins->bind_param("ii", $patient_id, $doctor_id);
                $ins->execute();
                $upd = $conn->prepare("UPDATE monitor_requests SET status='accepted' WHERE id=?");
                if ($upd) {
                    $upd->bind_param("i", $request_id);
                    $upd->execute();
                }
            }
        }
    }
    header("Location: monitor_patients.php");
    exit;
}

// Handle reject request
if (isset($_GET['reject'])) {
    $request_id = intval($_GET['reject']);
    $del = $conn->prepare("DELETE FROM monitor_requests WHERE id=? AND requested_user_id=?");
    if ($del) {
        $del->bind_param("ii", $request_id, $doctor_id);
        $del->execute();
    }
    header("Location: monitor_patients.php");
    exit;
}

// Fetch pending monitor requests
$pending_requests = $conn->query("SELECT mr.id, u.id as patient_id, u.name, u.gender, mr.created_at FROM monitor_requests mr JOIN users u ON mr.requester_id=u.id WHERE mr.requested_user_id='$doctor_id' AND mr.status='pending' ORDER BY mr.created_at DESC");

// Get patients' medicines for this doctor (only patients who added doctor as monitor)
$patients_medicines = $conn->query("SELECT DISTINCT u.id, u.name, u.gender, ci.id as medicine_id, 
                                    ci.medicine_name, ci.dosage, ci.due_time, ci.status, ci.medicine_image, ci.completed_at
                                    FROM users u
                                    JOIN patient_monitors pm ON u.id = pm.patient_id
                                    LEFT JOIN checklists cl ON u.id = cl.patient_id
                                    LEFT JOIN checklist_items ci ON cl.id = ci.checklist_id
                                    WHERE pm.monitor_id='$doctor_id'
                                    ORDER BY u.name ASC, CAST(SUBSTRING_INDEX(ci.due_time, ',', 1) AS TIME) ASC");

require_once("../includes/helpers.php");
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
.sidebar-logo {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 24px 28px; padding-right: 15px;
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
    <div class="sidebar-logo">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">MediConnect</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
        <a href="monitor_patients.php" class="nav-link active"><span class="nav-icon">👥</span> Monitor Patients</a>
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
                                <?php elseif($n['type'] === 'chat'): ?>
                                    <a href="chat.php?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept" style="width:100%; text-align:center;">💬 Open Chat</a>
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
        </div>
    <div class="page-header">
        <h1>👥 Monitor Patients</h1>
        <p>Track your patients' health checklists and medicine progress.</p>
    </div>

    <!-- MONITORED PATIENTS MEDICINES -->
    <div class="card" style="margin-bottom: 24px;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">😷 Patient's medicine</h2>
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
                        'medicine_image' => $row['medicine_image'],
                        'dosage' => $row['dosage'],
                        'due_time' => $row['due_time'],
                        'status' => $row['status'],
                        'completed_at' => $row['completed_at']
                    ];
                }
            }
        ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Image</th>
                            <th>Medicine Name</th>
                            <th>Dosage</th>
                            <th>When to Take</th>
                            <th>Marked as Taken</th>
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
                                    $completed_time = ($med['completed_at']) ? date('M d, h:i A', strtotime($med['completed_at'])) : '—';
                                ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo ($first_medicine) ? $patient_name : ''; ?></td>
                                    <td style="cursor:pointer;" <?php if(!empty($med['medicine_image']) && imageExists($med['medicine_image'])): ?>onclick="openImageModal('<?php echo htmlspecialchars($med['medicine_image'], ENT_QUOTES); ?>')" title="Click to view"<?php endif; ?>>
                                        <?php if(!empty($med['medicine_image']) && imageExists($med['medicine_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($med['medicine_image']); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                                        <?php else: ?>
                                            <div style="width:48px;height:48px;background:var(--navy-light);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">💊</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:var(--muted);"><?php echo htmlspecialchars($med['medicine_name'] ?? '—'); ?></td>
                                    <td style="color:var(--muted);"><?php echo htmlspecialchars($med['dosage'] ?? '—'); ?></td>
                                    <td><?php echo getTimeInWords($med['due_time']); ?></td>
                                    <td>
                                        <?php if($med['completed_at']): ?>
                                            <div style="font-size:0.85rem;font-weight:600;"><?php echo date('M d, h:i A', strtotime($med['completed_at'])); ?></div>
                                        <?php else: ?>
                                            <span style="color:var(--muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:<?php echo $status_color; ?>;font-weight:600;"><?php echo $status_badge; ?></td>
                                </tr>
                                <?php $first_medicine = false; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo $patient_name; ?></td>
                                    <td colspan="6" style="color:var(--muted);font-style:italic;">No medicines added</td>
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
                <p style="font-size:0.9rem;">Once patients accept your monitoring requests, their medicine data will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- PENDING REQUESTS SECTION -->
    <div class="card" style="margin-bottom: 24px;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">📬 Pending Monitor Requests</h2>
        <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Requested Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $pending_requests->fetch_assoc()): 
                            $title = ($row['gender'] === 'male') ? 'Mr.' : (($row['gender'] === 'female') ? 'Mrs.' : 'Mx.');
                            $created = date('M d, Y', strtotime($row['created_at']));
                        ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($title . ' ' . $row['name']); ?></td>
                            <td style="color:var(--muted);"><?php echo $created; ?></td>
                            <td>
                                <a href="monitor_patients.php?accept=<?php echo $row['id']; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(34,197,94,0.2);color:var(--success);border:1px solid rgba(34,197,94,0.3);border-radius:50px;font-size:0.8rem;font-weight:600;text-decoration:none;cursor:pointer;transition:all 0.2s;margin-right:8px;" onmouseover="this.style.background='rgba(34,197,94,0.3)'" onmouseout="this.style.background='rgba(34,197,94,0.2)'">✅ Accept</a>
                                <a href="monitor_patients.php?reject=<?php echo $row['id']; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.3);border-radius:50px;font-size:0.8rem;font-weight:600;text-decoration:none;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.3)'" onmouseout="this.style.background='rgba(239,68,68,0.2)'">❌ Reject</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:24px;color:var(--muted);">
                <p style="font-size:0.9rem;">No pending requests. Patients who request monitoring will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- IMAGE MODAL -->
<div id="imageModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:1000;padding:20px;align-items:center;justify-content:center;">
    <div style="position:relative;max-width:90vw;max-height:90vh;background:var(--navy-card);border-radius:var(--radius);padding:20px;border:1px solid var(--border);">
        <button onclick="closeImageModal()" style="position:absolute;top:10px;right:10px;background:none;border:none;color:var(--white);font-size:1.5rem;cursor:pointer;z-index:1001;transition:all 0.2s;" onmouseover="this.style.color='var(--teal)'" onmouseout="this.style.color='var(--white)'">✕</button>
        <img id="modalImage" src="" alt="Medicine" style="max-width:100%;max-height:80vh;border-radius:10px;object-fit:contain;">
    </div>
</div>

<script>
function openImageModal(imagePath) {
    const modal = document.getElementById('imageModal');
    const img = document.getElementById('modalImage');
    img.src = imagePath;
    modal.style.display = 'flex';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

document.getElementById('imageModal').onclick = function(e) {
    if (e.target === this) closeImageModal();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImageModal();
});
document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
});
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){document.getElementById('notifDropdown').classList.remove('show');}
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
</script>

</body>
