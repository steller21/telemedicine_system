<?php
session_start();
require_once("../config/db.php");
if (!isset($_SESSION['user_id']) || !isset($_GET['friend_id']) || $_SESSION['role'] != 'doctor') { header("Location: friends.php"); exit; }

$user_id = $_SESSION['user_id'];
$friend_id = intval($_GET['friend_id']);

// Verify Friendship
$check = $conn->query("SELECT id FROM friends WHERE (user_id1=$user_id AND user_id2=$friend_id) OR (user_id1=$friend_id AND user_id2=$user_id)");
if ($check->num_rows == 0) { die("You are not friends with this user."); }

$friend = $conn->query("SELECT name FROM users WHERE id=$friend_id")->fetch_assoc();

// Handle Send
if (isset($_POST['send'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $friend_id, $msg);
        $stmt->execute();
    }
    header("Location: chat.php?friend_id=$friend_id"); exit;
}

// Fetch Messages
$messages = $conn->query("SELECT * FROM messages WHERE (sender_id=$user_id AND receiver_id=$friend_id) OR (sender_id=$friend_id AND receiver_id=$user_id) ORDER BY created_at ASC");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Chat with <?php echo htmlspecialchars($friend['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    :root { --teal: #0EB8A0; --navy: #f8fafc; --navy-mid: #ffffff; --white: #1e293b; --border: rgba(0,0,0,0.08); }
    body.dark-mode {
        --navy: #0B1526;
        --navy-mid: #112035;
        --white: #fff;
        --border: rgba(255,255,255,0.07);
    }
    body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: var(--white); margin: 0; display: flex; flex-direction: column; height: 100vh; }
    header { padding: 20px; background: var(--navy-mid); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 15px; }
    .chat-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
    .msg { max-width: 70%; padding: 12px 16px; border-radius: 15px; font-size: 0.95rem; }
    .msg-sent { align-self: flex-end; background: var(--teal); color: var(--navy); }
    .msg-received { align-self: flex-start; background: var(--navy-mid); border: 1px solid var(--border); color: var(--white); }
    footer { padding: 20px; background: var(--navy-mid); border-top: 1px solid var(--border); }
    .input-wrap { display: flex; gap: 10px; }
    input { flex: 1; padding: 12px 18px; background: var(--navy); border: 1px solid var(--border); border-radius: 50px; color: var(--white); outline: none; }
    button { background: var(--teal); border: none; padding: 0 25px; border-radius: 50px; font-weight: 700; cursor: pointer; }
    .back-btn { color: #fff; text-decoration: none; }
</style>
</head><body>
    <header>
        <a href="friends.php" class="back-btn">←</a>
        <h2 style="flex:1;"><?php echo htmlspecialchars($friend['name']); ?></h2>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.2rem;" title="Toggle Theme">🌓</button>
    </header>
    <div class="chat-box" id="chatBox">
        <?php while($m = $messages->fetch_assoc()): $isSent = ($m['sender_id'] == $user_id); ?>
            <div class="msg <?php echo $isSent ? 'msg-sent' : 'msg-received'; ?>">
                <?php echo htmlspecialchars($m['message']); ?>
            </div>
        <?php endwhile; ?>
    </div>
    <footer>
        <form method="POST" class="input-wrap">
            <input type="text" name="message" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" name="send">SEND</button>
        </form>
    </footer>
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
</script>
</body></html>