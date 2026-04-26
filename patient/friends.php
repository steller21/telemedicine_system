<?php
session_start();
require_once("../config/db.php");
require_once("monitor_core.php");

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['user_id'];
$msg = ""; $msg_type = "";

// Initialize Tables
$conn->query("CREATE TABLE IF NOT EXISTS friend_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (sender_id, receiver_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id1 INT NOT NULL,
    user_id2 INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id1, user_id2)
)");

$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle Sending Request
if (isset($_POST['send_request'])) {
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $target_id = $row['id'];
        if ($target_id == $user_id) {
            $msg = "You cannot add yourself."; $msg_type = "error";
        } else {
            $check = $conn->prepare("SELECT id FROM friend_requests WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)");
            $check->bind_param("iiii", $user_id, $target_id, $target_id, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $msg = "A request or friendship already exists."; $msg_type = "warning";
            } else {
                $ins = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
                $ins->bind_param("ii", $user_id, $target_id);
                $ins->execute();
                $msg = "Friend request sent to $email!"; $msg_type = "success";
            }
        }
    } else { $msg = "User with email $email not found."; $msg_type = "error"; }
}

// Handle Accept/Reject
if (isset($_GET['accept'])) {
    $req_id = intval($_GET['accept']);
    $stmt = $conn->prepare("SELECT sender_id FROM friend_requests WHERE id=? AND receiver_id=? AND status='pending'");
    $stmt->bind_param("ii", $req_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $sender_id = $row['sender_id'];
        $conn->query("UPDATE friend_requests SET status='accepted' WHERE id=$req_id");
        $u1 = min($user_id, $sender_id); $u2 = max($user_id, $sender_id);
        $conn->query("INSERT IGNORE INTO friends (user_id1, user_id2) VALUES ($u1, $u2)");
        header("Location: friends.php?msg=Accepted"); exit;
    }
}

if (isset($_GET['reject'])) {
    $req_id = intval($_GET['reject']);
    $conn->query("DELETE FROM friend_requests WHERE id=$req_id AND receiver_id=$user_id");
    header("Location: friends.php?msg=Rejected"); exit;
}

// Fetch Data
$friends = $conn->query("SELECT u.id, u.name, u.email, u.role FROM friends f JOIN users u ON (f.user_id1=u.id OR f.user_id2=u.id) WHERE (f.user_id1='$user_id' OR f.user_id2='$user_id') AND u.id != '$user_id'");
$received = $conn->query("SELECT fr.id, u.name, u.email FROM friend_requests fr JOIN users u ON fr.sender_id=u.id WHERE fr.receiver_id='$user_id' AND fr.status='pending'");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Friends & Messages — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root { --teal: #0EB8A0; --navy: #0B1526; --navy-mid: #112035; --white: #fff; --muted: #7A8EA8; --border: rgba(255,255,255,0.07); }
    .notif-container{position:fixed;top:25px;right:40px;display:inline-block;z-index:9990;}
    .notif-btn{background:var(--navy-mid);border:1px solid var(--border);color:var(--white);padding:8px 14px;border-radius:12px;cursor:pointer;display:flex;align-items:center;font-size:1.1rem;transition:0.2s;}
    .notif-btn:hover{background:var(--navy-mid);border-color:var(--teal);}
    .notif-badge{background:#EF4444;color:white;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:50px;position:absolute;top:-5px;right:-5px;border:2px solid #0F1E36;}
    .notif-dropdown{position:absolute;top:100%;right:0;width:300px;background:#0F1E36;border:1px solid var(--border);border-radius:12px;margin-top:12px;display:none;z-index:1000;box-shadow:0 20px 50px rgba(0,0,0,0.4);overflow:hidden;}
    .notif-dropdown.show{display:block;}
    .notif-header{padding:12px 16px;border-bottom:1px solid var(--border);font-family:'Clash Display',sans-serif;font-size:0.85rem;font-weight:600;background:rgba(255,255,255,0.02);text-align:left;}
    .notif-item{padding:12px 16px;border-bottom:1px solid var(--border);transition:0.2s;text-decoration:none;color:inherit;display:block;text-align:left;}
    .notif-item:hover{background:rgba(255,255,255,0.04);}
    .notif-item-title{font-size:0.85rem;font-weight:600;color:var(--teal);margin-bottom:2px;}
    .notif-item-desc{font-size:0.75rem;color:var(--muted);line-height:1.4;}
    .notif-actions{display:flex;gap:8px;margin-top:10px;}
    .notif-btn-sm{padding:5px 12px;border-radius:50px;font-size:0.7rem;font-weight:600;text-decoration:none;transition:0.2s;}
    .notif-btn-accept{background:rgba(34,197,94,0.2);color:#22C55E;border:1px solid rgba(34,197,94,0.3);}
    .notif-btn-reject{background:rgba(239,68,68,0.2);color:#EF4444;border:1px solid rgba(239,68,68,0.3);}
    body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: var(--white); margin: 0; display: flex; min-height: 100vh; }
    .sidebar { width: 240px; background: #0F1E36; border-right: 1px solid var(--border); padding: 24px; }
    .main { flex: 1; padding: 40px; }
    .card { background: #0F1E36; border: 1px solid var(--border); border-radius: 18px; padding: 24px; margin-bottom: 24px; }
    h1, h2 { font-family: 'Clash Display', sans-serif; margin-top: 0; }
    .form-input { width: 100%; padding: 12px; background: var(--navy-mid); border: 1px solid var(--border); border-radius: 10px; color: #fff; margin-bottom: 12px; }
    .btn { padding: 10px 20px; border-radius: 50px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-primary { background: var(--teal); color: var(--navy); }
    .btn-secondary { background: var(--navy-mid); color: #fff; border: 1px solid var(--border); }
    .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
    .alert-success { background: rgba(14,184,160,0.1); color: var(--teal); }
    .alert-error { background: rgba(239,68,68,0.1); color: #EF4444; }
    .item-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .nav-link { color: var(--muted); text-decoration: none; display: block; padding: 10px 0; font-size: 0.9rem; }
    .nav-link:hover { color: var(--teal); }
</style>
</head><body>
    <aside class="sidebar">
        <h2 style="color:var(--teal)">MediConnect</h2>
        <nav>
            <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="friends.php" class="nav-link" style="color:var(--white)">👥 Friends & Chat</a>
            <a href="../logout.php" class="nav-link">🚪 Logout</a>
        </nav>
    </aside>
    <main class="main">
        <div class="notif-container">
            <?php 
            $notifCount = getPendingNotificationCount($conn, $user_id);
            $notifications = getPendingNotifications($conn, $user_id);
            ?>
            <div class="notif-btn" id="notifBtn">🔔 <?php if($notifCount > 0): ?><span class="notif-badge"><?php echo $notifCount; ?></span><?php endif; ?></div>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">Pending Requests</div>
                <div class="notif-list">
                    <?php if(empty($notifications)): ?>
                        <div style="padding:20px;text-align:center;font-size:0.8rem;color:var(--muted);">No pending requests</div>
                    <?php else: foreach($notifications as $n): ?>
                        <div class="notif-item">
                            <div class="notif-item-title"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div class="notif-item-desc"><?php echo htmlspecialchars($n['desc']); ?></div>
                            <div class="notif-actions">
                                <?php if($n['type'] === 'info'): ?>
                                    <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept" style="width:100%; text-align:center;">Dismiss</a>
                                <?php else: ?>
                                <a href="?<?php echo $n['param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-accept">✅ Accept</a>
                                <a href="?<?php echo $n['reject_param']; ?>=<?php echo $n['id']; ?>" class="notif-btn-sm notif-btn-reject">❌ Reject</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <h1>👥 Friends & Messages</h1>
        
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <div>
                <div class="card">
                    <h2>🔍 Add Friend</h2>
                    <p style="color:var(--muted); font-size: 0.85rem;">Search for a doctor or patient by their email address.</p>
                    <form method="POST">
                        <input type="email" name="email" class="form-input" placeholder="Enter email address..." required>
                        <button type="submit" name="send_request" class="btn btn-primary">Send Request</button>
                    </form>
                </div>

                <div class="card">
                    <h2>📬 Pending Requests</h2>
                    <?php if($received->num_rows > 0): ?>
                        <?php while($r = $received->fetch_assoc()): ?>
                            <div class="item-row">
                                <div><strong><?php echo htmlspecialchars($r['name']); ?></strong><br><small><?php echo htmlspecialchars($r['email']); ?></small></div>
                                <div>
                                    <a href="?accept=<?php echo $r['id']; ?>" class="btn btn-primary btn-sm" style="padding: 5px 12px; font-size: 0.8rem;">Accept</a>
                                    <a href="?reject=<?php echo $r['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 5px 12px; font-size: 0.8rem;">Reject</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?><p style="color:var(--muted)">No incoming requests.</p><?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2>💬 Your Conversations</h2>
                <?php if($friends->num_rows > 0): ?>
                    <?php while($f = $friends->fetch_assoc()): ?>
                        <div class="item-row">
                            <div>
                                <strong><?php echo htmlspecialchars($f['name']); ?></strong> 
                                <span style="font-size: 0.7rem; background: var(--navy-mid); padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">
                                    <?php echo $f['role']; ?>
                                </span>
                            </div>
                            <a href="chat.php?friend_id=<?php echo $f['id']; ?>" class="btn btn-primary">Message</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?><p style="color:var(--muted)">You haven't added any friends yet.</p><?php endif; ?>
            </div>
        </div>
    </main>
<script>
document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
});
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){document.getElementById('notifDropdown').classList.remove('show');}
});
</script>
</body></html>