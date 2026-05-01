<?php
/**
 * SECTION 1: INITIALIZATION & SECURITY
 * Start the session, include database configuration, and verify user authentication.
 * monitor_core.php is required for shared monitoring and notification logic.
 */
session_start();
require_once("../config/db.php");
require_once("monitor_core.php");

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['user_id'];
$msg = ""; $msg_type = "";

/**
 * SECTION 2: DATABASE TABLE SETUP
 * Automates the creation of messaging and friendship tables if they aren't already present.
 * - friend_requests: Stores pending/rejected requests.
 * - friends: Stores bidirectional accepted friendships.
 * - messages: Stores individual chat history.
 */
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

/**
 * SECTION 3: FRIEND REQUEST HANDLERS (POST & GET)
 * Processes all user interactions for adding, accepting, or rejecting friends.
 */
if (isset($_POST['send_request'])) {
    $email = trim($_POST['email']);
    // Find the user ID associated with the provided email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $target_id = $row['id'];
        // Prevent users from adding themselves
        if ($target_id == $user_id) {
            $msg = "You cannot add yourself."; $msg_type = "error";
        } else {
            // Check if a request or friendship already exists between the two users
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

if (isset($_GET['accept'])) {
    $req_id = intval($_GET['accept']);
    // Verify the request exists and is pending for the current user
    $stmt = $conn->prepare("SELECT sender_id FROM friend_requests WHERE id=? AND receiver_id=? AND status='pending'");
    $stmt->bind_param("ii", $req_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $sender_id = $row['sender_id'];
        // Update request status to accepted
        $conn->query("UPDATE friend_requests SET status='accepted' WHERE id=$req_id");
        // Create a bidirectional friendship record
        $u1 = min($user_id, $sender_id); $u2 = max($user_id, $sender_id);
        $conn->query("INSERT IGNORE INTO friends (user_id1, user_id2) VALUES ($u1, $u2)");
        header("Location: friends.php?msg=Accepted"); exit;
    }
}

if (isset($_GET['reject'])) {
    $req_id = intval($_GET['reject']);
    // Fetch requester info before deletion to send a rejection notification
    $stmt = $conn->prepare("SELECT fr.sender_id, u.name as responder_name FROM friend_requests fr JOIN users u ON fr.receiver_id = u.id WHERE fr.id=? AND fr.receiver_id=?");
    $stmt->bind_param("ii", $req_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $target_id = $row['sender_id'];
        $responder = $row['responder_name'];
        
        // Remove the request and add an info notification for the sender
        $conn->query("DELETE FROM friend_requests WHERE id = $req_id");
        addUserNotification($conn, $target_id, "Friend Request Rejected", "$responder rejected your friend request.");
    }
    header("Location: friends.php?msg=Rejected");
    exit;
}

/**
 * SECTION 4: DATA FETCHING
 * Retrieves active friends and pending requests from the database for display in the UI.
 */
$friends = $conn->query("SELECT u.id, u.name, u.email, u.role FROM friends f JOIN users u ON (f.user_id1=u.id OR f.user_id2=u.id) WHERE (f.user_id1='$user_id' OR f.user_id2='$user_id') AND u.id != '$user_id'");
$received = $conn->query("SELECT fr.id, u.name, u.email FROM friend_requests fr JOIN users u ON fr.sender_id=u.id WHERE fr.receiver_id='$user_id' AND fr.status='pending'");
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><title>Friends & Messages — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    /**
     * SECTION 5: CSS STYLES
     * Custom theme variables and component styling for the messaging dashboard.
     */
    :root { --teal: #0EB8A0; --navy: #f8fafc; --navy-mid: #ffffff; --white: #1e293b; --muted: #64748b; --border: rgba(0,0,0,0.08); }
    body.dark-mode {
        --navy: #0B1526;
        --navy-mid: #112035;
        --white: #fff;
        --muted: #7A8EA8;
        --border: rgba(255,255,255,0.07);
    }
    body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: var(--white); margin: 0; display: flex; min-height: 100vh; }
    .sidebar { width: 240px; background: var(--navy-mid); border-right: 1px solid var(--border); padding: 24px; }
    .main { flex: 1; padding: 40px; }
    .card { background: var(--navy-mid); border: 1px solid var(--border); border-radius: 18px; padding: 24px; margin-bottom: 24px; }
    h1, h2 { font-family: 'Clash Display', sans-serif; margin-top: 0; }
    .form-input { width: 100%; padding: 12px; background: var(--navy-mid); border: 1px solid var(--border); border-radius: 10px; color: #fff; margin-bottom: 12px; }
    .btn { padding: 10px 20px; border-radius: 50px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-primary { background: var(--teal); color: var(--navy); }
    .btn-secondary { background: var(--navy-mid); color: #fff; border: 1px solid var(--border); }
    .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
    .alert-success { background: rgba(14,184,160,0.1); color: var(--teal); }
    .alert-error { background: rgba(239,68,68,0.1); color: #EF4444; }
    .alert-warning { background: rgba(245,158,11,0.1); color: #F59E0B; }
    .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
    .alert-success { background: rgba(14,184,160,0.1); color: var(--teal); }
    .alert-error { background: rgba(239,68,68,0.1); color: #EF4444; }
    .item-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .nav-link { color: var(--muted); text-decoration: none; display: block; padding: 10px 0; font-size: 0.9rem; }
    .nav-link:hover { color: var(--teal); }

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
    .chatbot-widget { background: rgba(14, 184, 160, 0.1) !important; color: var(--teal) !important; border: 1px dashed var(--teal) !important; margin-top: 20px; margin-bottom: 10px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 10px; padding: 12px 16px; text-decoration: none; font-size: 0.875rem; transition: all 0.3s; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(14, 184, 160, 0.4); } 70% { box-shadow: 0 0 0 8px rgba(14, 184, 160, 0); } 100% { box-shadow: 0 0 0 0 rgba(14, 184, 160, 0); } }
</style>
</head><body>
    
    <!-- SECTION 6: SIDEBAR COMPONENT -->
    <aside class="sidebar">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:28px;">
            <h2 style="color:var(--teal); margin:0; font-size:1.4rem;">MediConnect</h2>
            <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
        </div>
        <nav>
            <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
            <a href="book_appointment.php" class="nav-link">📅 Book Appointment</a>
            <a href="friends.php" class="nav-link" style="color:var(--white)">👥 Friends & Chat</a>
            <a href="../logout.php" class="nav-link">🚪 Logout</a>
        </nav>
    </aside>

    <main class="main">
        <!-- SECTION 7: NOTIFICATION COMPONENT
             Fetches combined request counts (Friends, Monitors, Reports) and unread messages.
        -->
        <?php 
        $notifCount = getPendingNotificationCount($conn, $user_id);
        $notifications = getPendingNotifications($conn, $user_id);
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

        <!-- SECTION 8: MAIN DASHBOARD HEADER -->
        <h1>👥 Friends & Messages</h1>
        
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg; ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <div>
                <!-- Add Friend Search Form -->
                <div class="card">
                    <h2>🔍 Add Friend</h2>
                    <p style="color:var(--muted); font-size: 0.85rem;">Search for a doctor or patient by their email address.</p>
                    <form method="POST">
                        <input type="email" name="email" class="form-input" placeholder="Enter email address..." required>
                        <button type="submit" name="send_request" class="btn btn-primary">Send Request</button>
                    </form>
                </div>

                <!-- Incoming Requests Inbox -->
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

            <!-- Friends List & Conversation Launchers -->
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
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(0.95);
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s ease;
}
.chatbot-modal.show { transform: translateY(0) scale(1); opacity: 1; pointer-events: auto; }
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

<script>
/**
 * SECTION 9: INTERACTIVE LOGIC
 * Toggles the visibility of the notification window and closes it on external clicks.
 */
document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
});
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){document.getElementById('notifDropdown').classList.remove('show');}
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
    url.searchParams.delete('msg');
    window.history.replaceState({}, document.title, url);
}

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
</body></html>