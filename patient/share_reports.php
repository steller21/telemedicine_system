<?php
session_start(); 
require_once("../config/db.php");
require_once("monitor_core.php");
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') { 
    header("Location: ../login.php"); 
    exit; 
}

$patient_id = $_SESSION['user_id'];

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS report_share_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    patient_id INT NOT NULL,
    requester_id INT NOT NULL,
    requester_role ENUM('doctor', 'monitor') NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (report_id, requester_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
)");

// Handle accept request
if (isset($_GET['accept'])) {
    $request_id = intval($_GET['accept']);
    $req = $conn->prepare("SELECT requester_id FROM report_share_requests WHERE id=? AND patient_id=? AND status='pending'");
    if ($req) {
        $req->bind_param("ii", $request_id, $patient_id);
        $req->execute();
        $result = $req->get_result();
        if ($result->num_rows > 0) {
            $acc = $conn->prepare("UPDATE report_share_requests SET status='accepted' WHERE id=?");
            if ($acc) {
                $acc->bind_param("i", $request_id);
                $acc->execute();
            }
        }
    }
    header("Location: share_reports.php");
    exit;
}

// Handle reject request
if (isset($_GET['reject'])) {
    $request_id = intval($_GET['reject']);
    $req = $conn->prepare("SELECT requester_id FROM report_share_requests WHERE id=? AND patient_id=? AND status='pending'");
    if ($req) {
        $req->bind_param("ii", $request_id, $patient_id);
        $req->execute();
        $result = $req->get_result();
        if ($result->num_rows > 0) {
            $rej = $conn->prepare("UPDATE report_share_requests SET status='rejected' WHERE id=?");
            if ($rej) {
                $rej->bind_param("i", $request_id);
                $rej->execute();
            }
        }
    }
    header("Location: share_reports.php");
    exit;
}

// Fetch pending requests
$pending_requests = null;
$stmt = $conn->prepare("
    SELECT rsr.*, r.report_name, COALESCE(p.name, d.name) as requester_name, p.gender
    FROM report_share_requests rsr
    JOIN reports r ON rsr.report_id = r.id
    LEFT JOIN patients p ON rsr.requester_role = 'monitor' AND rsr.requester_id = p.id
    LEFT JOIN doctors d ON rsr.requester_role = 'doctor' AND rsr.requester_id = d.id
    WHERE rsr.patient_id = ? AND rsr.status = 'pending'
    ORDER BY rsr.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $pending_requests = $stmt->get_result();
}

// Fetch accepted shares
$accepted_shares = null;
$stmt = $conn->prepare("
    SELECT rsr.*, r.report_name, COALESCE(p.name, d.name) as requester_name
    FROM report_share_requests rsr
    JOIN reports r ON rsr.report_id = r.id
    LEFT JOIN patients p ON rsr.requester_role = 'monitor' AND rsr.requester_id = p.id
    LEFT JOIN doctors d ON rsr.requester_role = 'doctor' AND rsr.requester_id = d.id
    WHERE rsr.patient_id = ? AND rsr.status = 'accepted'
    ORDER BY rsr.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $accepted_shares = $stmt->get_result();
}

// Fetch your reports
$reports = null;
$stmt = $conn->prepare("SELECT * FROM reports WHERE patient_id=? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $reports = $stmt->get_result();
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Share Reports — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
:root {
    --teal:#0EB8A0;--teal-dark:#0A8A78;--teal-glow:rgba(14,184,160,0.15);--navy:#0B1526;--navy-mid:#112035;--navy-light:#1A3050;--navy-card:#0F1E36;--white:#FFFFFF;--muted:#7A8EA8;--muted-dim:#4A5E78;--success:#22C55E;--warning:#F59E0B;--danger:#EF4444;--border:rgba(255,255,255,0.07);--radius:14px;
}
*{box-sizing:border-box;margin:0;padding:0;}body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;line-height:1.6;}
.page-bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),var(--navy);}
.layout{display:flex;min-height:100vh;}.sidebar{width:240px;background:var(--navy-card);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;padding:24px 0;}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:0 24px 28px;border-bottom:1px solid var(--border);margin-bottom:16px;text-decoration:none;}
.logo-dot{width:9px;height:9px;background:var(--teal);border-radius:50%;box-shadow:0 0 10px var(--teal);animation:blink 2s ease-in-out infinite;flex-shrink:0;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.logo-text{font-family:'Clash Display',sans-serif;font-size:1.15rem;font-weight:700;color:var(--white);}
.nav-section{padding:0 12px;margin-bottom:8px;}.nav-section-label{font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted-dim);font-weight:600;padding:0 12px 8px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:0.875rem;font-weight:500;transition:all 0.2s;margin-bottom:2px;}
.nav-link:hover{background:var(--teal-glow);color:var(--white);}
.nav-link.active{background:var(--teal-glow);color:var(--teal);}
.nav-icon{font-size:1.1rem;}
.sidebar-bottom{margin-top:auto;padding:16px 12px;border-top:1px solid var(--border);}
.main{margin-left:240px;max-width:calc(100% - 240px);padding:40px;overflow-y:auto;}
.page-header{margin-bottom:32px;}.page-header h1{font-family:'Clash Display',sans-serif;font-size:2rem;font-weight:700;margin-bottom:6px;}
.page-header p{color:var(--muted);font-size:0.9rem;}
.card{background:var(--navy-card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:50px;font-size:0.875rem;font-weight:600;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;text-decoration:none;transition:all 0.2s;}
.btn-primary{background:var(--teal);color:var(--navy);}
.btn-primary:hover{background:var(--teal-dark);transform:translateY(-1px);}
.btn-secondary{background:var(--navy-light);color:var(--white);border:1px solid var(--border);}
.btn-secondary:hover{background:var(--navy-mid);transform:translateY(-1px);}
.btn-sm{padding:7px 16px;font-size:0.8rem;}
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:0.72rem;font-weight:600;background:var(--teal-glow);color:var(--teal);}
.badge-success{background:rgba(34,197,94,0.12);color:var(--success);}
.badge-warning{background:rgba(245,158,11,0.12);color:var(--warning);}
.badge-danger{background:rgba(239,68,68,0.12);color:var(--danger);}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}.empty-state .empty-icon{font-size:3rem;margin-bottom:16px;opacity:0.5;}
.empty-state h3{font-size:1rem;font-weight:600;margin-bottom:8px;color:var(--white);}
.request-item{display:flex;align-items:center;justify-content:space-between;padding:14px;background:var(--navy-light);border-radius:var(--radius);margin-bottom:12px;border-left:2px solid var(--teal);}
.request-info{flex:1;}.request-name{font-weight:600;font-size:0.9rem;}.request-details{font-size:0.75rem;color:var(--muted);margin-top:4px;}
.request-actions{display:flex;gap:8px;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;max-width:100%;}}
.chatbot-widget { background: var(--teal-glow) !important; color: var(--teal) !important; border: 1px dashed var(--teal) !important; margin: 0 12px 10px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 10px; padding: 12px 16px; text-decoration: none; font-size: 0.875rem; transition: all 0.3s; animation: pulse 2s infinite; }
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
</head><body><div class="page-bg"></div><div class="layout">
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section"><div class="nav-section-label">Health</div>
        <a href="upload_report.php" class="nav-link"><span class="nav-icon">📄</span> Upload Report</a>
        <a href="share_reports.php" class="nav-link active"><span class="nav-icon">📤</span> Share Reports</a>
    </div>
</aside>
<main class="main">

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
        chatbotFab.addEventListener('click', () => {
            chatbotModal.classList.toggle('show');
        });

        chatbotClose.addEventListener('click', () => {
            chatbotModal.classList.remove('show');
        });

        // Close if clicked outside modal
        document.addEventListener('click', (event) => {
            if (!chatbotModal.contains(event.target) && !chatbotFab.contains(event.target) && chatbotModal.classList.contains('show')) {
                chatbotModal.classList.remove('show');
            }
        });
    }
});
</script>
    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
        <?php 
        $notifCount = getPendingNotificationCount($conn, $patient_id);
        $notifications = getPendingNotifications($conn, $patient_id);
        $chatNotifCount = getUnreadChatNotificationCount($conn, $patient_id, true);
        ?>
            <?php 
    $acc_stmt = $conn->prepare("SELECT name, email, address, profile_picture FROM patients WHERE id = ?");
    $acc_stmt->bind_param("i", $_SESSION['user_id']);
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
                                <?php if(($n['type'] ?? '') === 'chat' || ($n['type'] ?? '') === 'info'): ?>
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
    <div class="page-header"><h1>📤 Report Sharing</h1><p>Manage report sharing requests and view who has access to your reports.</p></div>

    <!-- PENDING REQUESTS SECTION -->
    <div class="card">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">📬 Pending Share Requests</h2>
        <?php if($pending_requests && $pending_requests->num_rows > 0): ?>
            <?php while($req = $pending_requests->fetch_assoc()):
                $title = ($req['requester_role'] === 'doctor') ? 'Dr.' : 'Monitor';
            ?>
                <div class="request-item">
                    <div class="request-info">
                        <div class="request-name"><?php echo htmlspecialchars($title . ' ' . $req['requester_name']); ?> requested your report</div>
                        <div class="request-details"><?php echo htmlspecialchars($req['report_name']); ?> · <?php echo date('d M Y', strtotime($req['created_at'])); ?></div>
                    </div>
                    <div class="request-actions">
                        <a href="share_reports.php?accept=<?php echo $req['id']; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(34,197,94,0.2);color:var(--success);border:1px solid rgba(34,197,94,0.3);border-radius:50px;font-size:0.8rem;font-weight:600;text-decoration:none;cursor:pointer;">✅ Accept</a>
                        <a href="share_reports.php?reject=<?php echo $req['id']; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.3);border-radius:50px;font-size:0.8rem;font-weight:600;text-decoration:none;cursor:pointer;">❌ Reject</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center;padding:24px;color:var(--muted);">
                <p style="font-size:0.9rem;">No pending requests.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ACCEPTED SHARES SECTION -->
    <div class="card">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">✅ Shared Reports</h2>
        <?php if($accepted_shares && $accepted_shares->num_rows > 0): ?>
            <?php while($share = $accepted_shares->fetch_assoc()):
                $title = ($share['requester_role'] === 'doctor') ? 'Dr.' : 'Monitor';
            ?>
                <div style="display:flex;align-items:center;padding:14px;background:var(--navy-light);border-radius:var(--radius);margin-bottom:12px;border-left:2px solid var(--success);">
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($title . ' ' . $share['requester_name']); ?> has access</div>
                        <div style="font-size:0.75rem;color:var(--muted);margin-top:4px;"><?php echo htmlspecialchars($share['report_name']); ?> · Shared since <?php echo date('d M Y', strtotime($share['created_at'])); ?></div>
                    </div>
                    <span class="badge badge-success">Shared</span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center;padding:24px;color:var(--muted);">
                <p style="font-size:0.9rem;">No shared reports yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- YOUR REPORTS SECTION -->
    <div class="card">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:16px;">📋 Your Reports</h2>
        <?php if($reports && $reports->num_rows > 0): ?>
            <?php while($report = $reports->fetch_assoc()):
                $ext = strtolower(pathinfo($report['file_path'], PATHINFO_EXTENSION));
                $icon = $ext === 'pdf' ? '📄' : '🖼️';
            ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;background:var(--teal-glow);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><?php echo $icon; ?></div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($report['report_name']); ?></div>
                            <div style="font-size:0.75rem;color:var(--muted);"><?php echo htmlspecialchars($report['report_type']); ?> · <?php echo date('d M Y', strtotime($report['created_at'])); ?></div>
                        </div>
                    </div>
                    <div style="display:flex; gap: 8px;">
                        <a href="<?php echo htmlspecialchars('../' . $report['file_path']); ?>" target="_blank" class="btn btn-secondary btn-sm">👁️ View</a>
                        <a href="delete_report.php?id=<?php echo $report['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this report? This action cannot be undone.');">🗑️ Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h3>No reports uploaded yet</h3>
                <p>Upload your first report to share it with doctors and monitors.</p><br>
                <a href="upload_report.php" class="btn btn-primary btn-sm">📤 Upload Report</a>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>
</body></html>

