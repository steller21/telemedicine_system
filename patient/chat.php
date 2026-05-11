<?php
session_start();
require_once("../config/db.php");
if (!isset($_SESSION['user_id']) || !isset($_GET['friend_id'])) { header("Location: friends.php"); exit; }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';
$friend_id = intval($_GET['friend_id']);

// Verify Friendship
$check = $conn->query("SELECT id FROM friends WHERE ((user_id1=$user_id AND user_role1='patient' AND user_id2=$friend_id AND user_role2='patient') OR (user_id1=$friend_id AND user_role1='patient' AND user_id2=$user_id AND user_role2='patient'))");
if ($check->num_rows == 0) { die("You are not friends with this user."); }

$friend_stmt = $conn->prepare("SELECT name FROM patients WHERE id = ?");
$friend_stmt->bind_param("i", $friend_id);
$friend_stmt->execute();
$friend = $friend_stmt->get_result()->fetch_assoc();

$mark_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND sender_role = 'patient' AND receiver_id = ? AND receiver_role = 'patient' AND is_read = 0");
$mark_read->bind_param("ii", $friend_id, $user_id);
$mark_read->execute();

// Handle Send
if (isset($_POST['send'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message) VALUES (?, ?, ?, 'patient', ?)");
        $stmt->bind_param("isis", $user_id, $user_role, $friend_id, $msg);
        $stmt->execute();
    }
    header("Location: chat.php?friend_id=$friend_id"); exit;
}

// Fetch Messages
$messages = $conn->query("SELECT * FROM messages WHERE ((sender_id=$user_id AND sender_role='patient' AND receiver_id=$friend_id AND receiver_role='patient') OR (sender_id=$friend_id AND sender_role='patient' AND receiver_id=$user_id AND receiver_role='patient')) ORDER BY created_at ASC");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Chat with <?php echo htmlspecialchars($friend['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    :root { --teal: #0EB8A0; --navy: #0B1526; --navy-mid: #112035; --white: #fff; --border: rgba(255,255,255,0.07); }
    body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: #fff; margin: 0; display: flex; flex-direction: column; height: 100vh; }
    header { padding: 20px; background: #0F1E36; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 15px; }
    .chat-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
    .msg { max-width: 70%; padding: 12px 16px; border-radius: 15px; font-size: 0.95rem; line-height: 1.4; }
    .msg-sent { align-self: flex-end; background: var(--teal); color: var(--navy); border-bottom-right-radius: 4px; }
    .msg-received { align-self: flex-start; background: var(--navy-mid); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
    .msg-time { display: block; font-size: 0.7rem; opacity: 0.6; margin-top: 5px; }
    footer { padding: 20px; background: #0F1E36; border-top: 1px solid var(--border); }
    .input-wrap { display: flex; gap: 10px; }
    input { flex: 1; padding: 12px 18px; background: var(--navy-mid); border: 1px solid var(--border); border-radius: 50px; color: #fff; outline: none; }
    button { background: var(--teal); border: none; padding: 0 25px; border-radius: 50px; font-weight: 700; cursor: pointer; }
    .back-btn { color: #fff; text-decoration: none; font-size: 1.2rem; }
</style>
</head><body>
    <header>
        <a href="friends.php" class="back-btn">←</a>
        <div style="width:40px;height:40px;background:var(--teal);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:700;">
            <?php echo strtoupper(substr($friend['name'], 0, 1)); ?>
        </div>
        <h2 style="margin:0; font-size: 1.1rem;"><?php echo htmlspecialchars($friend['name']); ?></h2>
    </header>

    <div class="chat-box" id="chatBox">
        <?php while($m = $messages->fetch_assoc()): 
            $isSent = ($m['sender_id'] == $user_id);
        ?>
            <div class="msg <?php echo $isSent ? 'msg-sent' : 'msg-received'; ?>">
                <?php echo htmlspecialchars($m['message']); ?>
                <span class="msg-time"><?php echo date('h:i A', strtotime($m['created_at'])); ?></span>
            </div>
        <?php endwhile; ?>
    </div>

    <footer>
        <form method="POST" class="input-wrap">
            <input type="text" name="message" placeholder="Type a message..." required autocomplete="off" autofocus>
            <button type="submit" name="send">SEND</button>
        </form>
    </footer>

    <script>
        const chatBox = document.getElementById('chatBox');
        chatBox.scrollTop = chatBox.scrollHeight;
        
        // Simple auto-refresh for new messages
        setInterval(() => {
            fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newChat = doc.getElementById('chatBox').innerHTML;
                    if (chatBox.innerHTML !== newChat) {
                        chatBox.innerHTML = newChat;
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                });
        }, 3000);
    </script>
</body></html>
