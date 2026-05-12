<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get monitored/patients list
if ($role === 'doctor') {
    $patients_query = "
        SELECT DISTINCT p.id, p.name, p.gender
        FROM patient_monitors pm
        JOIN patients p ON pm.patient_id = p.id
        WHERE pm.monitor_id = ?
        UNION
        SELECT DISTINCT p.id, p.name, p.gender
        FROM patients p
        WHERE p.id IN (
            SELECT patient_id FROM checklists WHERE created_by = ?
        )
    ";
} else {
    $patients_query = "
        SELECT DISTINCT p.id, p.name, p.gender
        FROM patient_monitors pm
        JOIN patients p ON pm.patient_id = p.id
        WHERE pm.monitor_id = ?
    ";
}

$stmt = $conn->prepare($patients_query);
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $patients_result = $stmt->get_result();
}

// If viewing specific patient's reports
$patient_reports = null;
$patient_name = null;
$has_monitor_access = false;
if (isset($_GET['patient_id'])) {
    $patient_id = intval($_GET['patient_id']);
    
    $access_stmt = $conn->prepare("
        SELECT 1
        FROM patient_monitors
        WHERE patient_id = ? AND monitor_id = ?
        LIMIT 1
    ");
    if ($access_stmt) {
        $access_stmt->bind_param("ii", $patient_id, $user_id);
        $access_stmt->execute();
        $has_monitor_access = $access_stmt->get_result()->num_rows > 0;
    }

    // Get patient name
    $pstmt = $conn->prepare("SELECT name FROM patients WHERE id=?");
    if ($pstmt) {
        $pstmt->bind_param("i", $patient_id);
        $pstmt->execute();
        $pname = $pstmt->get_result()->fetch_assoc();
        $patient_name = $pname['name'] ?? 'Patient';
    }
    
    // Get all reports from this patient with share status
    $rstmt = $conn->prepare("
        SELECT r.*,
               CASE WHEN ? = 1 OR (rsr.id IS NOT NULL AND rsr.status='accepted') THEN 1 ELSE 0 END as has_access,
               CASE WHEN rsr.id IS NOT NULL AND rsr.status='pending' THEN 1 ELSE 0 END as pending_request,
               rsr.id as request_id
        FROM reports r
        LEFT JOIN report_share_requests rsr ON r.id=rsr.report_id AND rsr.requester_id=?
        WHERE r.patient_id=?
        ORDER BY r.created_at DESC
    ");
    if ($rstmt) {
        $has_monitor_access_int = $has_monitor_access ? 1 : 0;
        $rstmt->bind_param("iii", $has_monitor_access_int, $user_id, $patient_id);
        $rstmt->execute();
        $patient_reports = $rstmt->get_result();
    }
}

// Handle report share request
if (isset($_POST['request_report'])) {
    $report_id = intval($_POST['report_id']);
    $patient_id = intval($_POST['patient_id']);

    $access_stmt = $conn->prepare("
        SELECT 1
        FROM patient_monitors
        WHERE patient_id = ? AND monitor_id = ?
        LIMIT 1
    ");
    if ($access_stmt) {
        $access_stmt->bind_param("ii", $patient_id, $user_id);
        $access_stmt->execute();
        $has_monitor_access = $access_stmt->get_result()->num_rows > 0;
    }

    if (!$has_monitor_access) {
        $check = $conn->prepare("SELECT id FROM report_share_requests WHERE report_id=? AND requester_id=? AND status IN ('pending', 'accepted')");
        if ($check) {
            $check->bind_param("ii", $report_id, $user_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows === 0) {
                $insert = $conn->prepare("INSERT INTO report_share_requests (report_id, patient_id, requester_id, requester_role, status) VALUES (?, ?, ?, ?, 'pending')");
                if ($insert) {
                    $insert->bind_param("iiis", $report_id, $patient_id, $user_id, $role);
                    $insert->execute();
                }
            }
        }
    }

    header("Location: view_patient_reports.php?patient_id=$patient_id");
    exit;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patient Reports — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
:root{--teal:#0EB8A0;--teal-dark:#0A8A78;--teal-glow:rgba(14,184,160,0.15);--navy:#0B1526;--navy-mid:#112035;--navy-light:#1A3050;--navy-card:#0F1E36;--white:#FFFFFF;--muted:#7A8EA8;--muted-dim:#4A5E78;--success:#22C55E;--warning:#F59E0B;--danger:#EF4444;--border:rgba(255,255,255,0.07);--radius:14px;}
*{box-sizing:border-box;margin:0;padding:0;}body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;line-height:1.6;}
.page-bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),var(--navy);}
.layout{display:flex;min-height:100vh;}
.sidebar{width:240px;background:var(--navy-card);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;padding:24px 0;}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:0 24px 28px;border-bottom:1px solid var(--border);margin-bottom:16px;text-decoration:none;}
.logo-dot{width:9px;height:9px;background:var(--teal);border-radius:50%;box-shadow:0 0 10px var(--teal);animation:blink 2s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.logo-text{font-family:'Clash Display',sans-serif;font-size:1.15rem;font-weight:700;color:var(--white);}
.nav-section{padding:0 12px;margin-bottom:8px;}.nav-section-label{font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted-dim);font-weight:600;padding:0 12px 8px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:0.875rem;font-weight:500;transition:all 0.2s;margin-bottom:2px;}
.nav-link:hover{background:var(--teal-glow);color:var(--white);}.nav-link.active{background:var(--teal-glow);color:var(--teal);}
.main{margin-left:240px;max-width:calc(100% - 240px);padding:40px;overflow-y:auto;}
.page-header{margin-bottom:32px;}.page-header h1{font-family:'Clash Display',sans-serif;font-size:2rem;font-weight:700;margin-bottom:6px;}
.page-header p{color:var(--muted);font-size:0.9rem;}
.card{background:var(--navy-card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px;}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:50px;font-size:0.875rem;font-weight:600;cursor:pointer;border:none;}
.btn-primary{background:var(--teal);color:var(--navy);}.btn-primary:hover{background:var(--teal-dark);}
.btn-secondary{background:var(--navy-light);color:var(--white);border:1px solid var(--border);}.btn-secondary:hover{background:var(--navy-mid);}
.btn-sm{padding:7px 16px;font-size:0.8rem;}.table-wrap{overflow-x:auto;border-radius:var(--radius);}table{width:100%;border-collapse:collapse;font-size:0.875rem;}
thead th{text-align:left;padding:12px 16px;font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);}
tbody td{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.04);}.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:0.72rem;font-weight:600;background:var(--teal-glow);color:var(--teal);}
.badge-success{background:rgba(34,197,94,0.12);color:var(--success);}.badge-warning{background:rgba(245,158,11,0.12);color:var(--warning);}.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}.empty-state .empty-icon{font-size:3rem;margin-bottom:16px;opacity:0.5;}.empty-state h3{font-size:1rem;font-weight:600;margin-bottom:8px;color:var(--white);}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;max-width:100%;padding:20px;}.grid-2{grid-template-columns:1fr;}}
</style>
</head><body><div class="page-bg"></div><div class="layout">
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section"><div class="nav-section-label"><?php echo ucfirst($role); ?></div>
        <?php if ($role === 'doctor'): ?>
            <a href="monitor_patients.php" class="nav-link"><span style="font-size:1.1rem;">💊</span> Monitor Patients</a>
            <a href="view_patient_reports.php" class="nav-link active"><span style="font-size:1.1rem;">📄</span> Patient Reports</a>
        <?php else: ?>
            <a href="monitor_view.php" class="nav-link"><span style="font-size:1.1rem;">👥</span> Monitored Patients</a>
            <a href="view_patient_reports.php" class="nav-link active"><span style="font-size:1.1rem;">📄</span> View Reports</a>
        <?php endif; ?>
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
    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
        <?php 
        $notifCount = getPendingNotificationCount($conn, $user_id);
        $notifications = getPendingNotifications($conn, $user_id);
        $chatNotifCount = getUnreadChatNotificationCount($conn, $user_id, true);
        ?>
        <?php 
    $acc_stmt = $conn->prepare("SELECT name, email, address, profile_picture FROM patients WHERE id = ?");
    $acc_stmt->bind_param("i", $user_id);
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
            <div class="notif-btn" id="accountBtn" style="border-radius:50%; width:44px; height:44px; justify-content:center; padding:0; background:var(--teal-glow); color:var(--teal); border:1px solid rgba(14,184,160,0.3); overflow:hidden;">
                <?php if ($user_pic_acc): ?>
                    <img src="../<?php echo htmlspecialchars($user_pic_acc); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>👤<?php endif; ?>
            </div>
            <div class="notif-dropdown" id="accountDropdown" style="right:0; width:280px; padding:16px;">
                <div style="text-align:center; margin-bottom:16px;">
                    <?php if ($user_pic_acc): ?>
                        <img src="../<?php echo htmlspecialchars($user_pic_acc); ?>" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--teal); margin:0 auto 12px auto; display:block;">
                    <?php else: ?>
                        <div style="width:60px; height:60px; border-radius:50%; background:var(--teal); color:var(--navy); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 12px auto; font-weight:bold;">
                            <?php echo strtoupper(substr($user_name_acc, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--white); margin-bottom:4px;"><?php echo htmlspecialchars($user_name_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted); margin-bottom:4px;">📧 <?php echo htmlspecialchars($user_email_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted);">📍 <?php echo htmlspecialchars($user_address_acc); ?></div>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <a href="../logout.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:rgba(239,68,68,0.1); color:var(--danger); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>
    </div>
    <?php if (!isset($_GET['patient_id'])): ?>
        <div class="page-header"><h1>📄 Patient Reports</h1><p>Request and view reports from your patients.</p></div>
        <div class="card">
            <?php if ($patients_result && $patients_result->num_rows > 0): ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Patient Name</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while ($patient = $patients_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($patient['name']); ?></strong></td>
                            <td><a href="view_patient_reports.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary btn-sm">📋 View Reports</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table></div>
            <?php else: ?>
                <div class="empty-state"><div class="empty-icon">👥</div><h3>No patients available</h3><p>You don't have any patients yet. Add one to view their reports.</p></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
            <a href="view_patient_reports.php" class="btn btn-secondary btn-sm">← Back</a>
            <h1 style="font-family:'Clash Display',sans-serif;font-size:1.5rem;flex:1;">📄 <?php echo htmlspecialchars($patient_name); ?>'s Reports</h1>
        </div>
        <div class="card">
            <?php if ($patient_reports && $patient_reports->num_rows > 0): ?>
                <?php while ($report = $patient_reports->fetch_assoc()):
                    $ext = strtolower(pathinfo($report['file_path'], PATHINFO_EXTENSION));
                    $icon = $ext === 'pdf' ? '📄' : '🖼️';
                ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px;background:var(--navy-light);border-radius:var(--radius);margin-bottom:12px;border-left:2px solid <?php echo $report['has_access'] ? 'var(--success)' : 'var(--teal)'; ?>">
                        <div style="display:flex;align-items:center;gap:12px;flex:1;">
                            <div style="width:40px;height:40px;background:var(--teal-glow);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><?php echo $icon; ?></div>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($report['report_name']); ?></div>
                                <div style="font-size:0.75rem;color:var(--muted);"><?php echo htmlspecialchars($report['report_type']); ?> · <?php echo date('d M Y', strtotime($report['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php if ($report['has_access']): ?>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <span class="badge badge-success">✅ Shared</span>
                                <a href="<?php echo htmlspecialchars('../' . $report['file_path']); ?>" target="_blank" class="btn btn-secondary btn-sm">👁️ View</a>
                            </div>
                        <?php elseif ($report['pending_request']): ?>
                            <span class="badge badge-warning">⏳ Pending</span>
                        <?php else: ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <input type="hidden" name="patient_id" value="<?php echo $_GET['patient_id']; ?>">
                                <button type="submit" name="request_report" class="btn btn-primary btn-sm">📥 Request Access</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><div class="empty-icon">📂</div><h3>No reports found</h3><p>This patient hasn't uploaded any reports yet.</p></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main></div>
</body></html>
