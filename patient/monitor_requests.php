<?php
session_start(); require_once("../config/db.php");
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Handle profile update
if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_address = trim($_POST['address']);
    
    $update_sql = "UPDATE users SET name=?, address=? WHERE id=?";
    $params = [$new_name, $new_address, $user_id];
    $types = "ssi";

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../images/profiles/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_file_name = uniqid('profile_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_file_name)) {
                $db_path = "images/profiles/" . $new_file_name;
                $update_sql = "UPDATE users SET name=?, address=?, profile_picture=? WHERE id=?";
                $params = [$new_name, $new_address, $db_path, $user_id];
                $types = "sssi";
            }
        }
    }

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $_SESSION['name'] = $new_name;
        header("Location: monitor_requests.php?success=ProfileUpdated");
        exit;
    }
}

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
    // Get request details
    $req = $conn->prepare("SELECT requester_id FROM monitor_requests WHERE id=? AND requested_user_id=? AND status='pending'");
    if ($req) {
        $req->bind_param("ii", $request_id, $user_id);
        $req->execute();
        $result = $req->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $requester_id = $row['requester_id'];
            // Add to patient_monitors
            $ins = $conn->prepare("INSERT INTO patient_monitors (patient_id, monitor_id) VALUES (?, ?)");
            if ($ins) {
                $ins->bind_param("ii", $requester_id, $user_id);
                $ins->execute();
                // Update request status
                $upd = $conn->prepare("UPDATE monitor_requests SET status='accepted' WHERE id=?");
                if ($upd) {
                    $upd->bind_param("i", $request_id);
                    $upd->execute();
                }
            }
        }
    }
    header("Location: monitor_requests.php?success=Request accepted!");
    exit;
}

// Handle reject request
if (isset($_GET['reject'])) {
    $request_id = intval($_GET['reject']);
    $del = $conn->prepare("DELETE FROM monitor_requests WHERE id=? AND requested_user_id=?");
    if ($del) {
        $del->bind_param("ii", $request_id, $user_id);
        $del->execute();
    }
    header("Location: monitor_requests.php?success=Request rejected");
    exit;
}

$msg = ""; $msg_type = "";
if (isset($_GET['success'])) {
    $msg = htmlspecialchars($_GET['success']);
    $msg_type = "success";
}

// Fetch pending requests for this user
$requests = $conn->query("SELECT mr.id, u.id as requester_id, u.name, u.email, u.gender, mr.created_at FROM monitor_requests mr JOIN users u ON mr.requester_id=u.id WHERE mr.requested_user_id='$user_id' AND mr.status='pending' ORDER BY mr.created_at DESC");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Monitor Requests — MediConnect</title>
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
 
/* Modal Styles */
#editProfileModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;align-items:center;justify-content:center; }
.modal-content { background:var(--navy-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:450px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.4); }
@keyframes modalSlideIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

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
    position: fixed; inset: 0; z-index: -1;
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
 
.main {
    margin-left: 240px;
    flex: 1;
    padding: 36px 40px;
    max-width: calc(100% - 240px);
}
 
.page-header { margin-bottom: 32px; }
.page-header h1 {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.8rem; font-weight: 600;
    margin-bottom: 4px;
}
.page-header p { color: var(--muted); font-size: 0.9rem; }
 
.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow);
}

.alert {
    padding: 14px 18px; border-radius: var(--radius);
    font-size: 0.875rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
 
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 12px; border-radius: 50px;
    font-size: 0.8rem; font-weight: 600;
    cursor: pointer; border: none;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none; transition: all 0.2s;
}
.btn-success {
    background: rgba(34,197,94,0.2); color: var(--success);
    border: 1px solid rgba(34,197,94,0.3);
}
.btn-success:hover { background: rgba(34,197,94,0.3); }
.btn-danger { background: rgba(239,68,68,0.2); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
.btn-danger:hover { background: rgba(239,68,68,0.3); }

.request-card {
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.request-info h3 { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
.request-info p { color: var(--muted); font-size: 0.85rem; }
.request-actions { display: flex; gap: 8px; }

.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--muted);
}
.empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }

@media (max-width: 768px) {
    .sidebar { width: 200px; }
    .main { margin-left: 200px; max-width: calc(100% - 200px); padding: 20px; }
    .request-card { flex-direction: column; gap: 12px; align-items: flex-start; }
    .request-actions { width: 100%; }
}
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(14, 184, 160, 0.4); } 70% { box-shadow: 0 0 0 8px rgba(14, 184, 160, 0); } 100% { box-shadow: 0 0 0 0 rgba(14, 184, 160, 0); } }
</style>
</head><body>
<div class="page-bg"></div>
<div class="layout">
<aside class="sidebar">
    <a href="dashboard.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section"><div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link"><span class="nav-icon">📅</span> Book Appointment</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Health</div>
        <a href="checklist.php" class="nav-link"><span class="nav-icon">💊</span> My Medicines</a>
        <a href="upload_report.php" class="nav-link"><span class="nav-icon">📄</span> Upload Report</a>
    </div>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
        <a href="monitor_requests.php" class="nav-link active"><span class="nav-icon">📬</span> Monitor Requests</a>
    </div>
</aside>
<main class="main">

<?php 
    $acc_stmt = $conn->prepare("SELECT name, email, address, profile_picture FROM users WHERE id = ?");
    $acc_stmt->bind_param("i", $user_id);
    $acc_stmt->execute();
    $user_data_acc = $acc_stmt->get_result()->fetch_assoc();
    $user_name_acc = $user_data_acc['name'] ?? $_SESSION['name'];
    $user_email_acc = $user_data_acc['email'] ?? 'N/A';
    $user_address_acc = !empty($user_data_acc['address']) ? $user_data_acc['address'] : 'Not provided';
    $user_pic_acc = $user_data_acc['profile_picture'] ?? null;
    ?>

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
                            <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept">✅ Accept</a>
                            <a href="?<?php echo $n['reject_param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-reject">❌ Reject</a>
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
                <div style="margin-bottom: 8px;">
                    <a href="#" onclick="event.preventDefault(); hideAccountDropdown(); showEditProfileModal();" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:var(--navy-light); color:var(--white); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='var(--navy-mid)'" onmouseout="this.style.background='var(--navy-light)'">✏️ Edit Profile</a>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <a href="../logout.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:rgba(239,68,68,0.1); color:var(--danger); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>
    <div class="page-header">
        <h1>📬 Monitor Requests</h1>
        <p>Review and approve requests from users who want to monitor your health.</p>
    </div>

    <div style="max-width:700px;">
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type;?>">✅ <?php echo htmlspecialchars($msg);?></div><?php endif;?>
        
        <div class="card">
            <?php if ($requests && $requests->num_rows > 0): ?>
                <?php while ($row = $requests->fetch_assoc()): 
                    $created = date('M d, Y', strtotime($row['created_at']));
                    $title = ($row['gender'] === 'male') ? 'Mr.' : (($row['gender'] === 'female') ? 'Mrs.' : 'Mx.');
                ?>
                <div class="request-card">
                    <div class="request-info">
                        <h3><?php echo htmlspecialchars($title . ' ' . $row['name']); ?></h3>
                        <p><?php echo htmlspecialchars($row['email']); ?></p>
                        <p style="font-size:0.8rem;margin-top:6px;">Requested on <?php echo $created; ?></p>
                    </div>
                    <div class="request-actions">
                        <a href="monitor_requests.php?accept=<?php echo $row['id']; ?>" class="btn btn-success">✅ Accept</a>
                        <a href="monitor_requests.php?reject=<?php echo $row['id']; ?>" class="btn btn-danger">❌ Reject</a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📬</div>
                    <h3>No pending requests</h3>
                    <p style="font-size:0.9rem;">When someone requests to monitor you, it will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Edit Profile Modal -->
<div id="editProfileModal">
    <div class="modal-content" style="animation:modalSlideIn 0.3s ease-out;">
        <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;font-weight:600;margin-bottom:20px;color:var(--white);">Edit Profile</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="text-align:center; margin-bottom:20px;">
                <label for="profilePictureInput" style="cursor:pointer; display:inline-block; position:relative;">
                    <img id="profilePicturePreview" src="../<?php echo htmlspecialchars($user_pic_acc ?: 'images/default_user.png'); ?>" 
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
</div>
<script>
// Auto-hide alerts after 7 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.5s ease';
        a.style.opacity = '0';
        setTimeout(() => a.style.display = 'none', 500);
    });
}, 7000);

if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('success'); url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
}

function showEditProfileModal() { document.getElementById('editProfileModal').style.display = 'flex'; }
function hideEditProfileModal() { document.getElementById('editProfileModal').style.display = 'none'; }
function hideAccountDropdown() { const d = document.getElementById('accountDropdown'); if(d) d.classList.remove('show'); }
function previewProfilePicture(event) {
    const reader = new FileReader();
    reader.onload = function(){ document.getElementById('profilePicturePreview').src = reader.result; };
    reader.readAsDataURL(event.target.files[0]);
}
</script>
</body>
</html>
