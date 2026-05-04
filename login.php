<?php
session_start();
require_once("config/db.php");

$error = "";
if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $role = $_POST['role'] ?? 'patient';
    $table = ($role === 'doctor') ? 'doctors' : 'patients';

    $stmt = $conn->prepare("SELECT id, name, password FROM $table WHERE email = ?");
    $user = null;
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $user = $row;
            }
        }
    }

    if ($user && $role) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $role;
        $_SESSION['name']    = $user['name'];
        header("Location: " . ($role == 'doctor' ? "doctor/dashboard.php" : "patient/dashboard.php"));
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--teal:#0EB8A0;--teal-dark:#0A8A78;--navy:#0B1526;--navy-mid:#112035;--navy-light:#1A3050;--navy-card:#0F1E36;--white:#fff;--muted:#7A8EA8;--border:rgba(255,255,255,0.07);--danger:#EF4444;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse 60% 50% at 20% 10%,rgba(14,184,160,0.12) 0%,transparent 60%),radial-gradient(ellipse 50% 50% at 80% 80%,rgba(14,184,160,0.08) 0%,transparent 50%),var(--navy);}
.wrap{width:100%;max-width:420px;padding:20px;}
.logo{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:40px;text-decoration:none;}
.logo-dot{width:10px;height:10px;background:var(--teal);border-radius:50%;box-shadow:0 0 12px var(--teal);animation:blink 2s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.logo-text{font-family:'Clash Display',sans-serif;font-size:1.4rem;font-weight:700;color:var(--white);}
.card{background:var(--navy-card);border:1px solid var(--border);border-radius:22px;padding:40px 36px;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
h2{font-family:'Clash Display',sans-serif;font-size:1.5rem;font-weight:600;margin-bottom:6px;}
.sub{color:var(--muted);font-size:0.875rem;margin-bottom:28px;}
.role-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:24px;}
.role-btn{padding:12px;border-radius:12px;border:1px solid var(--border);background:transparent;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:0.875rem;font-weight:500;cursor:pointer;transition:all 0.2s;text-align:center;}
.role-btn.active{background:rgba(14,184,160,0.12);border-color:rgba(14,184,160,0.4);color:var(--teal);}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:0.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;}
.form-input{width:100%;padding:13px 16px;background:var(--navy-light);border:1px solid var(--border);border-radius:12px;color:var(--white);font-size:0.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
.form-input:focus{border-color:rgba(14,184,160,0.5);box-shadow:0 0 0 3px rgba(14,184,160,0.1);}
.form-input::placeholder{color:rgba(122,142,168,0.5);}
.alert-error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:var(--danger);padding:12px 16px;border-radius:10px;font-size:0.85rem;margin-bottom:20px;}
.btn{width:100%;padding:14px;border:none;border-radius:50px;background:var(--teal);color:var(--navy);font-weight:700;font-size:0.95rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;box-shadow:0 0 24px rgba(14,184,160,0.25);margin-top:8px;}
.btn:hover{background:var(--teal-dark);color:var(--white);transform:translateY(-1px);box-shadow:0 0 36px rgba(14,184,160,0.35);}
.footer-link{text-align:center;margin-top:24px;font-size:0.85rem;color:var(--muted);}
.footer-link a{color:var(--teal);text-decoration:none;font-weight:600;}
.footer-link a:hover{text-decoration:underline;}
.divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:var(--muted);font-size:0.8rem;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
</style>
</head>
<body>
<div class="bg"></div>
<div class="wrap">
    <a href="../index.php" class="logo">
        <div class="logo-dot"></div>
        <span class="logo-text">MediConnect</span>
    </a>
    <div class="card">
        <h2>Welcome back</h2>
        <p class="sub">Sign in to your account to continue</p>
        <?php if($error): ?><div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">I am a</label>
                <div class="role-toggle">
                    <button type="button" class="role-btn <?php echo (isset($_POST['role']) && $_POST['role']=='doctor')?'':'active'; ?>" onclick="setRole('patient', event)">🙋 Patient</button>
                    <button type="button" class="role-btn <?php echo (isset($_POST['role']) && $_POST['role']=='doctor')?'active':''; ?>" onclick="setRole('doctor', event)">👨‍⚕️ Doctor</button>
                </div>
                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($_POST['role'] ?? 'patient'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-input" type="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input class="form-input" type="password" name="password" placeholder="••••••••" required>
            </div>
            <button class="btn" type="submit" name="login">Sign In →</button>
        </form>
        <div class="footer-link">Don't have an account? <a href="register.php">Register here</a></div>
    </div>
</div>
<script>
function setRole(role, event) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
</script>
</body>
</html>
