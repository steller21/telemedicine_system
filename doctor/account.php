<?php
session_start();
require_once("../config/db.php");
require_once("../includes/admin_core.php");

ensureAdminSchema($conn);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);
$msg = ""; $msg_type = "";

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $specialization = trim($_POST['specialization']);
    $license_number = trim($_POST['license_number']);
    $affiliations = trim($_POST['affiliations']);
    $bio = trim($_POST['bio']);

    $update_sql = "UPDATE doctors SET name=?, specialization=?, license_number=?, affiliations=?, bio=? WHERE id=?";
    $params = [$name, $specialization, $license_number, $affiliations, $bio, $doctor_id];
    $types = "sssssi";

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../images/profiles/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_file_name = uniqid('profile_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_file_name)) {
                $db_path = "images/profiles/" . $new_file_name;
                $update_sql = "UPDATE doctors SET name=?, specialization=?, license_number=?, affiliations=?, bio=?, profile_picture=? WHERE id=?";
                $params = [$name, $specialization, $license_number, $affiliations, $bio, $db_path, $doctor_id];
                $types = "ssssssi";
            }
        }
    }

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        $msg = "Profile updated successfully!";
        $msg_type = "success";
    } else {
        $msg = "Failed to update profile.";
        $msg_type = "error";
    }
}

// Handle Credential Upload
if (isset($_POST['upload_credential'])) {
    $cred_type = $_POST['credential_type'];
    $cred_name = trim($_POST['credential_name']);
    
    if (isset($_FILES['credential_file']) && $_FILES['credential_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/credentials/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['credential_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $new_file_name = uniqid('cred_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['credential_file']['tmp_name'], $upload_dir . $new_file_name)) {
                $db_path = "uploads/credentials/" . $new_file_name;
                $stmt = $conn->prepare("INSERT INTO doctor_credentials (doctor_id, credential_type, credential_name, file_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $doctor_id, $cred_type, $cred_name, $db_path);
                if ($stmt->execute()) {
                    $conn->query("UPDATE doctors SET verification_status='pending', verified_at=NULL, verified_by_admin_id=NULL WHERE id='$doctor_id'");
                    $msg = "Credential uploaded successfully!";
                    $msg_type = "success";
                } else {
                    $msg = "Database error during upload.";
                    $msg_type = "error";
                }
            } else {
                $msg = "Failed to move uploaded file.";
                $msg_type = "error";
            }
        } else {
            $msg = "Invalid file type. Allowed: JPG, PNG, PDF.";
            $msg_type = "error";
        }
    } else {
        $msg = "Please select a valid file.";
        $msg_type = "error";
    }
}

// Handle Credential Deletion
if (isset($_POST['delete_credential'])) {
    $cred_id = intval($_POST['cred_id']);
    $chk = $conn->prepare("SELECT file_path FROM doctor_credentials WHERE id=? AND doctor_id=?");
    $chk->bind_param("ii", $cred_id, $doctor_id);
    $chk->execute();
    $res = $chk->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $file_to_del = "../" . $row['file_path'];
        if (file_exists($file_to_del)) unlink($file_to_del);
        
        $del = $conn->prepare("DELETE FROM doctor_credentials WHERE id=?");
        $del->bind_param("i", $cred_id);
        $del->execute();
        $conn->query("UPDATE doctors SET verification_status='pending', verified_at=NULL, verified_by_admin_id=NULL WHERE id='$doctor_id'");
        $msg = "Credential deleted.";
        $msg_type = "success";
    }
}

// Fetch Profile Data
$acc_stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$acc_stmt->bind_param("i", $doctor_id);
$acc_stmt->execute();
$user_data = $acc_stmt->get_result()->fetch_assoc();
$user_pic = $user_data['profile_picture'] ?? null;
$user_name_acc = $user_data['name'];

// Fetch Credentials
$creds = $conn->query("SELECT * FROM doctor_credentials WHERE doctor_id='$doctor_id' ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Account — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
:root { --teal:#0EB8A0; --teal-dark:#0A8A78; --teal-glow:rgba(14,184,160,0.15); --navy:#0B1526; --navy-mid:#112035; --navy-light:#1A3050; --navy-card:#0F1E36; --white:#FFFFFF; --muted:#7A8EA8; --muted-dim:#4A5E78; --border:rgba(255,255,255,0.07); --danger:#EF4444; --success:#22C55E; --shadow:0 8px 32px rgba(0,0,0,0.25); }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: var(--white); min-height: 100vh; line-height: 1.6; }
.page-bg { position: fixed; inset: 0; z-index: -1; background: radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%), radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%), var(--navy); }
.layout { display: flex; min-height: 100vh; }
.sidebar { width: 240px; background: var(--navy-card); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 50; padding: 24px 0; }
.sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 0 24px 28px; border-bottom: 1px solid var(--border); margin-bottom: 16px; text-decoration: none; }
.logo-dot { width: 9px; height: 9px; background: var(--teal); border-radius: 50%; box-shadow: 0 0 10px var(--teal); animation: blink 2s ease-in-out infinite; flex-shrink: 0; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }
.logo-text { font-family: 'Clash Display', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--white); }
.nav-section { padding: 0 12px; margin-bottom: 8px; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: var(--muted); text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; margin-bottom: 2px; }
.nav-link:hover { background: var(--teal-glow); color: var(--white); }
.nav-link.active { background: var(--teal-glow); color: var(--teal); }
.nav-icon { font-size: 1rem; width: 20px; text-align: center; }
.main { margin-left: 240px; flex: 1; padding: 36px 40px; max-width: 1200px; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
.page-title { font-family: 'Clash Display', sans-serif; font-size: 1.8rem; font-weight: 600; color: var(--white); }
.card { background: var(--navy-card); border: 1px solid var(--border); border-radius: 20px; padding: 28px; margin-bottom: 24px; box-shadow: var(--shadow); }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.form-input { width: 100%; padding: 12px 16px; background: var(--navy-light); border: 1px solid var(--border); border-radius: 12px; color: var(--white); font-size: 0.95rem; font-family: inherit; transition: 0.2s; }
.form-input:focus { border-color: rgba(14,184,160,0.5); box-shadow: 0 0 0 3px rgba(14,184,160,0.1); outline: none; }
.btn { padding: 12px 24px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; border: none; }
.btn-primary { background: var(--teal); color: var(--navy); }
.btn-primary:hover { background: var(--teal-dark); color: var(--white); transform: translateY(-1px); }
.btn-danger { background: rgba(239,68,68,0.1); color: var(--danger); }
.btn-danger:hover { background: rgba(239,68,68,0.2); }
.alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; font-size: 0.9rem; }
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: var(--danger); }
.cred-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--navy-light); border-radius: 12px; border: 1px solid var(--border); margin-bottom: 12px; }
.cred-info { display: flex; align-items: center; gap: 16px; }
.cred-icon { width: 44px; height: 44px; background: var(--teal-glow); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.cred-title { font-weight: 600; color: var(--white); font-size: 1rem; }
.cred-type { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-top: 4px; }
.cred-actions { display: flex; gap: 8px; }
</style>
</head>
<body class="dark-mode">
<div class="page-bg"></div>

<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-logo">
            <div class="logo-dot"></div>
            <div class="logo-text">MediConnect</div>
        </a>
        <div class="nav-section">
            <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
            <a href="appointments.php" class="nav-link"><span class="nav-icon">📅</span> Appointments</a>
            <a href="patients.php" class="nav-link"><span class="nav-icon">👥</span> Patients</a>
            <a href="account.php" class="nav-link active"><span class="nav-icon">👤</span> My Account</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <div class="header">
            <h1 class="page-title">My Account & Credentials</h1>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo $msg_type == 'success' ? '✅' : '❌'; ?> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Profile Edit Section -->
            <div class="card">
                <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;margin-bottom:20px;">Personal Information</h2>
                <div style="margin-bottom:18px;padding:14px 16px;border-radius:14px;<?php echo getVerificationBadgeStyles($user_data['verification_status'] ?? 'pending'); ?>">
                    <strong style="text-transform:capitalize;"><?php echo htmlspecialchars($user_data['verification_status'] ?? 'pending'); ?></strong>
                    <div style="font-size:0.85rem;margin-top:4px;opacity:0.95;">Your account verification is managed by admin based on uploaded credentials.</div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group" style="text-align:center; margin-bottom:24px;">
                        <label for="profilePictureInput" style="cursor:pointer; display:inline-block; position:relative;">
                            <?php if ($user_pic): ?>
                                <img id="profilePicturePreview" src="../<?php echo htmlspecialchars($user_pic); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid var(--teal);">
                            <?php else: ?>
                                <div id="profilePicturePreview" style="width:100px; height:100px; border-radius:50%; background:var(--teal-glow); color:var(--teal); display:flex; align-items:center; justify-content:center; font-size:2.5rem; font-weight:bold; border:3px solid var(--teal);">
                                    <?php echo strtoupper(substr($user_name_acc, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div style="position:absolute; bottom:0; right:0; background:var(--teal); color:var(--navy); border-radius:50%; padding:6px; font-size:0.9rem; line-height:1; border:2px solid var(--navy-card);">✏️</div>
                        </label>
                        <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" style="display:none;" onchange="previewProfilePicture(event)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input class="form-input" type="text" name="name" value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address (Read Only)</label>
                        <input class="form-input" type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" disabled style="opacity:0.7;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Specialization</label>
                        <input class="form-input" type="text" name="specialization" value="<?php echo htmlspecialchars($user_data['specialization']); ?>" placeholder="e.g. Cardiology">
                    </div>

                    <div class="form-group">
                        <label class="form-label">License Number</label>
                        <input class="form-input" type="text" name="license_number" value="<?php echo htmlspecialchars($user_data['license_number']); ?>" placeholder="Medical License Number">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Board Affiliations</label>
                        <input class="form-input" type="text" name="affiliations" value="<?php echo htmlspecialchars($user_data['affiliations'] ?? ''); ?>" placeholder="e.g. American Medical Association">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bio / About</label>
                        <textarea class="form-input" name="bio" rows="4" placeholder="Brief professional bio"><?php echo htmlspecialchars($user_data['bio']); ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary" style="width:100%; margin-top:10px;">Save Profile Changes</button>
                </form>
            </div>

            <!-- Credentials Section -->
            <div>
                <div class="card" style="margin-bottom:24px;">
                    <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;margin-bottom:20px;">Upload Credential</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Credential Type</label>
                            <select class="form-input" name="credential_type" required>
                                <option value="License">Medical License</option>
                                <option value="Certification">Certification / Degree</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Credential Name</label>
                            <input class="form-input" type="text" name="credential_name" placeholder="e.g. State Medical License" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Document File (PDF/Image)</label>
                            <input class="form-input" type="file" name="credential_file" accept=".pdf,image/*" required style="padding:10px;">
                        </div>
                        <button type="submit" name="upload_credential" class="btn btn-primary" style="width:100%;">Upload Credential</button>
                    </form>
                </div>

                <div class="card">
                    <h2 style="font-family:'Clash Display',sans-serif;font-size:1.3rem;margin-bottom:20px;">Verified Credentials</h2>
                    <?php if ($creds && $creds->num_rows > 0): ?>
                        <?php while ($cred = $creds->fetch_assoc()): 
                            $icon = $cred['credential_type'] == 'License' ? '🪪' : '🎓';
                        ?>
                            <div class="cred-item">
                                <div class="cred-info">
                                    <div class="cred-icon"><?php echo $icon; ?></div>
                                    <div>
                                        <div class="cred-title"><?php echo htmlspecialchars($cred['credential_name']); ?></div>
                                        <div class="cred-type"><?php echo htmlspecialchars($cred['credential_type']); ?> · <?php echo date('M d, Y', strtotime($cred['uploaded_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="cred-actions">
                                    <a href="../<?php echo htmlspecialchars($cred['file_path']); ?>" target="_blank" class="btn btn-primary" style="padding:8px 16px; text-decoration:none; font-size:0.85rem;">View</a>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this credential?');">
                                        <input type="hidden" name="cred_id" value="<?php echo $cred['id']; ?>">
                                        <button type="submit" name="delete_credential" class="btn btn-danger" style="padding:8px 16px; font-size:0.85rem;">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:30px 20px; color:var(--muted);">
                            <div style="font-size:2rem; margin-bottom:10px;">📄</div>
                            <p>No credentials uploaded yet.</p>
                            <p style="font-size:0.85rem;">Upload your licenses and certifications above.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function previewProfilePicture(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const preview = document.getElementById('profilePicturePreview');
        if(preview.tagName.toLowerCase() === 'div') {
            const img = document.createElement('img');
            img.id = 'profilePicturePreview';
            img.src = reader.result;
            img.style.cssText = preview.style.cssText;
            img.style.objectFit = 'cover';
            preview.parentNode.replaceChild(img, preview);
        } else {
            preview.src = reader.result;
        }
    };
    if(event.target.files[0]) reader.readAsDataURL(event.target.files[0]);
}
</script>
</body>
</html>

