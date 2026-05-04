<?php
require_once("config/db.php");
$msg = ""; $msg_type = "";
$role_pre = isset($_GET['role']) ? $_GET['role'] : 'patient';
if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];
    $phone    = trim($_POST['phone'] ?? '');
    $profile_pic_path = null;

    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "images/profiles/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_file_name = uniqid('profile_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $new_file_name)) {
                $profile_pic_path = $upload_dir . $new_file_name;
            }
        }
    }

    if ($role === 'patient') {
        $gender  = $_POST['gender'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $dob     = $_POST['dob'] ?? null;
        // Check duplicate email
        $chk = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $chk->bind_param("s", $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = "Email already exists!"; $msg_type = "error";
        } else {
            $s = $conn->prepare("INSERT INTO patients (name, email, password, phone, dob, gender, address, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $s->bind_param("ssssssss", $name, $email, $password, $phone, $dob, $gender, $address, $profile_pic_path);
            if ($s->execute()) { $msg = "Registered successfully! You can now login."; $msg_type = "success"; }
            else { $msg = "Registration failed. Please try again."; $msg_type = "error"; }
        }
    } else {
        $specialization  = trim($_POST['specialization'] ?? '');
        $license_number  = trim($_POST['license_number'] ?? '');
        $bio             = trim($_POST['bio'] ?? '');
        // Check duplicate email
        $chk = $conn->prepare("SELECT id FROM doctors WHERE email = ?");
        $chk->bind_param("s", $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = "Email already exists!"; $msg_type = "error";
        } else {
            $s = $conn->prepare("INSERT INTO doctors (name, email, password, phone, specialization, license_number, bio, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $s->bind_param("ssssssss", $name, $email, $password, $phone, $specialization, $license_number, $bio, $profile_pic_path);
            if ($s->execute()) { $msg = "Registered successfully! You can now login."; $msg_type = "success"; }
            else { $msg = "Registration failed. Please try again."; $msg_type = "error"; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--teal:#0EB8A0;--teal-dark:#0A8A78;--navy:#0B1526;--navy-mid:#112035;--navy-light:#1A3050;--navy-card:#0F1E36;--white:#fff;--muted:#7A8EA8;--border:rgba(255,255,255,0.07);--danger:#EF4444;--success:#22C55E;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;}
.bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse 60% 50% at 80% 10%,rgba(14,184,160,0.1) 0%,transparent 60%),radial-gradient(ellipse 50% 50% at 20% 80%,rgba(14,184,160,0.07) 0%,transparent 50%),var(--navy);}
.wrap{width:100%;max-width:460px;}
.logo{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:36px;text-decoration:none;}
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
.form-input,.form-select{width:100%;padding:13px 16px;background:var(--navy-light);border:1px solid var(--border);border-radius:12px;color:var(--white);font-size:0.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
.form-input:focus,.form-select:focus{border-color:rgba(14,184,160,0.5);box-shadow:0 0 0 3px rgba(14,184,160,0.1);}
.form-input::placeholder{color:rgba(122,142,168,0.5);}
.form-select option{background:var(--navy-mid);}
.alert{padding:12px 16px;border-radius:10px;font-size:0.85rem;margin-bottom:20px;}
.alert-error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:var(--danger);}
.alert-success{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);color:var(--success);}
.btn{width:100%;padding:14px;border:none;border-radius:50px;background:var(--teal);color:var(--navy);font-weight:700;font-size:0.95rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;box-shadow:0 0 24px rgba(14,184,160,0.25);margin-top:8px;}
.btn:hover{background:var(--teal-dark);color:var(--white);transform:translateY(-1px);}
.footer-link{text-align:center;margin-top:24px;font-size:0.85rem;color:var(--muted);}
.footer-link a{color:var(--teal);text-decoration:none;font-weight:600;}
</style>
</head>
<body>
<div class="bg"></div>
<div class="wrap">
    <a href="index.php" class="logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="card">
        <h2>Create account</h2>
        <p class="sub">Join MediConnect — it's free</p>
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg_type=='success'?'✅':'❌'; ?> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <form method="POST" id="regForm" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">I am a</label>
                <div class="role-toggle">
                    <button type="button" class="role-btn <?php echo $role_pre=='patient'?'active':''; ?>" onclick="setRole('patient')">🙋 Patient</button>
                    <button type="button" class="role-btn <?php echo $role_pre=='doctor'?'active':''; ?>" onclick="setRole('doctor')">👨‍⚕️ Doctor</button>
                </div>
                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($role_pre); ?>">
            </div>
           <div class="form-group" style="text-align:center; margin-bottom:24px;">
                <label class="form-label" style="text-align:center; margin-bottom:20px;">Profile Picture (optional)</label>
                <label for="profilePictureInput" style="cursor:pointer; display:inline-block; position:relative;">
                    <img id="profilePicturePreview" src="images/default_user.svg"
                         style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid var(--teal);">
                    <div style="position:absolute; bottom:0; right:0; background:var(--teal); color:var(--navy); border-radius:50%; padding:6px; font-size:0.9rem; line-height:1; border:2px solid var(--navy-card);">✏️</div>
                </label>
                <input type="file" name="profile_pic" id="profilePictureInput" accept="image/*" style="display:none;" onchange="previewProfilePicture(event)">
            </div>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-input" type="text" name="name" placeholder="Your full name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-input" type="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input class="form-input" type="tel" name="phone" placeholder="Your phone number">
            </div>
            <!-- Patient-only fields -->
            <div id="patientFields">
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input class="form-input" type="date" name="dob">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select your gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input class="form-input" type="text" name="address" placeholder="Your current address">
                </div>
            </div>
            <!-- Doctor-only fields -->
            <div id="doctorFields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Specialization</label>
                    <input class="form-input" type="text" name="specialization" placeholder="e.g. Cardiology">
                </div>
                <div class="form-group">
                    <label class="form-label">License Number</label>
                    <input class="form-input" type="text" name="license_number" placeholder="Medical license number">
                </div>
                <div class="form-group">
                    <label class="form-label">Bio / About</label>
                    <textarea class="form-input" name="bio" rows="3" placeholder="Brief professional bio" style="resize:vertical;"></textarea>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input class="form-input" type="password" name="password" placeholder="Min. 8 characters" required>
            </div>
            <button class="btn" type="submit" name="register">Create Account →</button>
        </form>
        <div class="footer-link">Already have an account? <a href="login.php">Sign in</a></div>
    </div>
</div>
<script>
function setRole(r){
    document.getElementById('roleInput').value=r;
    document.querySelectorAll('.role-btn').forEach(b=>b.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('patientFields').style.display = (r==='patient') ? 'block' : 'none';
    document.getElementById('doctorFields').style.display  = (r==='doctor')  ? 'block' : 'none';
}
function previewProfilePicture(event) {
    const reader = new FileReader();
    reader.onload = function(){ document.getElementById('profilePicturePreview').src = reader.result; };
    reader.readAsDataURL(event.target.files[0]);
}
// Init visibility on load
document.addEventListener('DOMContentLoaded', function(){
    const role = document.getElementById('roleInput').value;
    document.getElementById('patientFields').style.display = (role==='patient') ? 'block' : 'none';
    document.getElementById('doctorFields').style.display  = (role==='doctor')  ? 'block' : 'none';
});
</script>
</body>
</html>