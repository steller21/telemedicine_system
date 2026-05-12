<?php
session_start();
require_once("../config/db.php");
require_once("../includes/admin_core.php");

ensureAdminSchema($conn);
requireAdminSession();

$admin_id = (int)$_SESSION['user_id'];
$msg = '';
$msg_type = 'success';

if (isset($_GET['verify_doctor'])) {
    $doctor_id = (int)$_GET['verify_doctor'];
    $credCheck = $conn->prepare("SELECT COUNT(*) AS total FROM doctor_credentials WHERE doctor_id = ?");
    $credCheck->bind_param("i", $doctor_id);
    $credCheck->execute();
    $credTotal = (int)($credCheck->get_result()->fetch_assoc()['total'] ?? 0);

    if ($credTotal > 0) {
        $stmt = $conn->prepare("UPDATE doctors SET verification_status = 'verified', verified_at = NOW(), verified_by_admin_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $doctor_id);
        $stmt->execute();
        $msg = "Doctor verified successfully.";
    } else {
        $msg = "Cannot verify a doctor without uploaded credentials.";
        $msg_type = 'error';
    }
}

if (isset($_GET['reject_doctor'])) {
    $doctor_id = (int)$_GET['reject_doctor'];
    $stmt = $conn->prepare("UPDATE doctors SET verification_status = 'rejected', verified_at = NULL, verified_by_admin_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $admin_id, $doctor_id);
    $stmt->execute();
    $msg = "Doctor marked as rejected.";
}

$doctorCount = (int)($conn->query("SELECT COUNT(*) AS total FROM doctors")->fetch_assoc()['total'] ?? 0);
$patientCount = (int)($conn->query("SELECT COUNT(*) AS total FROM patients")->fetch_assoc()['total'] ?? 0);
$verifiedDoctorCount = (int)($conn->query("SELECT COUNT(*) AS total FROM doctors WHERE verification_status = 'verified'")->fetch_assoc()['total'] ?? 0);

$doctors = $conn->query("
    SELECT
        d.*,
        a.name AS verified_by_name,
        COUNT(dc.id) AS credential_count
    FROM doctors d
    LEFT JOIN admins a ON a.id = d.verified_by_admin_id
    LEFT JOIN doctor_credentials dc ON dc.doctor_id = d.id
    GROUP BY d.id
    ORDER BY
        CASE d.verification_status
            WHEN 'pending' THEN 0
            WHEN 'rejected' THEN 1
            ELSE 2
        END,
        d.created_at DESC
");

$doctorCredentials = [];
$credentialRes = $conn->query("SELECT * FROM doctor_credentials ORDER BY uploaded_at DESC");
if ($credentialRes) {
    while ($cred = $credentialRes->fetch_assoc()) {
        $doctorCredentials[(int)$cred['doctor_id']][] = $cred;
    }
}

$patients = $conn->query("SELECT * FROM patients ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--teal:#0EB8A0;--teal-dark:#0A8A78;--navy:#0B1526;--navy-mid:#112035;--navy-light:#1A3050;--navy-card:#0F1E36;--white:#fff;--muted:#7A8EA8;--border:rgba(255,255,255,0.07);--danger:#EF4444;--warning:#F59E0B;--success:#22C55E;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;line-height:1.6;}
.bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse 60% 50% at 15% 0%,rgba(14,184,160,0.1) 0%,transparent 60%),radial-gradient(ellipse 40% 40% at 85% 90%,rgba(14,184,160,0.07) 0%,transparent 50%),var(--navy);}
.layout{display:flex;min-height:100vh;}
.sidebar{width:240px;background:var(--navy-card);border-right:1px solid var(--border);padding:24px 0;position:fixed;inset:0 auto 0 0;}
.logo{display:flex;align-items:center;gap:10px;padding:0 24px 28px;border-bottom:1px solid var(--border);margin-bottom:16px;text-decoration:none;color:var(--white);}
.logo-dot{width:9px;height:9px;background:var(--teal);border-radius:50%;box-shadow:0 0 10px var(--teal);}
.logo-text{font-family:'Clash Display',sans-serif;font-size:1.15rem;font-weight:700;}
.nav{padding:0 12px;}
.nav-link{display:block;padding:10px 12px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:0.9rem;margin-bottom:4px;}
.nav-link.active,.nav-link:hover{background:rgba(14,184,160,0.12);color:var(--teal);}
.main{margin-left:240px;flex:1;padding:36px 40px;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;}
.title{font-family:'Clash Display',sans-serif;font-size:1.8rem;}
.logout{padding:10px 16px;border-radius:999px;background:rgba(239,68,68,0.12);color:#fecaca;text-decoration:none;font-weight:700;}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.stat{background:var(--navy-card);border:1px solid var(--border);border-radius:18px;padding:20px;}
.stat-value{font-family:'Clash Display',sans-serif;font-size:1.8rem;margin-bottom:4px;}
.stat-label{color:var(--muted);font-size:0.82rem;}
.card{background:var(--navy-card);border:1px solid var(--border);border-radius:22px;padding:24px;margin-bottom:24px;}
.card h2{font-family:'Clash Display',sans-serif;font-size:1.25rem;margin-bottom:16px;}
.alert{padding:12px 16px;border-radius:12px;margin-bottom:18px;font-size:0.9rem;}
.alert-success{background:rgba(34,197,94,0.12);color:#bbf7d0;border:1px solid rgba(34,197,94,0.25);}
.alert-error{background:rgba(239,68,68,0.12);color:#fecaca;border:1px solid rgba(239,68,68,0.25);}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:0.9rem;}
th,td{text-align:left;vertical-align:top;padding:14px 12px;border-bottom:1px solid var(--border);}
th{font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);}
tr:last-child td{border-bottom:none;}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:0.74rem;font-weight:700;text-transform:capitalize;}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 14px;border-radius:999px;text-decoration:none;font-size:0.82rem;font-weight:700;}
.btn-verify{background:rgba(34,197,94,0.16);color:#bbf7d0;}
.btn-reject{background:rgba(239,68,68,0.16);color:#fecaca;}
.btn-view{background:rgba(14,184,160,0.16);color:#99f6e4;}
.actions{display:flex;flex-wrap:wrap;gap:8px;}
.cred-list{display:flex;flex-direction:column;gap:8px;}
.cred-item{padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,0.03);}
.cred-title{font-weight:700;}
.muted{color:var(--muted);}
@media (max-width: 900px){.sidebar{position:static;width:100%;height:auto}.layout{display:block}.main{margin-left:0;padding:20px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="bg"></div>
<div class="layout">
    <aside class="sidebar">
        <a href="dashboard.php" class="logo">
            <div class="logo-dot"></div>
            <div class="logo-text">MediConnect Admin</div>
        </a>
        <div class="nav">
            <a href="#doctors" class="nav-link active">Doctor Verification</a>
            <a href="#patients" class="nav-link">Patients</a>
        </div>
    </aside>

    <main class="main">
        <div class="header">
            <div>
                <div class="title">Admin Dashboard</div>
                <div class="muted">Review doctor credentials and browse all user records.</div>
            </div>
            <a href="../logout.php" class="logout">Logout</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type === 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="stat">
                <div class="stat-value"><?php echo $doctorCount; ?></div>
                <div class="stat-label">Registered Doctors</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo $verifiedDoctorCount; ?></div>
                <div class="stat-label">Verified Doctors</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo $patientCount; ?></div>
                <div class="stat-label">Registered Patients</div>
            </div>
        </div>

        <section id="doctors" class="card">
            <h2>Doctors and Credentials</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Contact</th>
                            <th>Professional Info</th>
                            <th>Credentials</th>
                            <th>Verification</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($doctors && $doctors->num_rows > 0): ?>
                        <?php while ($doctor = $doctors->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor['name']); ?></strong><br>
                                    <span class="muted">Joined <?php echo date('M d, Y', strtotime($doctor['created_at'])); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($doctor['email']); ?><br>
                                    <span class="muted"><?php echo !empty($doctor['phone']) ? htmlspecialchars($doctor['phone']) : 'No phone'; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor['specialization'] ?? 'Not set'); ?></strong><br>
                                    <span class="muted">License: <?php echo htmlspecialchars($doctor['license_number'] ?? 'Not set'); ?></span><br>
                                    <span class="muted"><?php echo htmlspecialchars($doctor['affiliations'] ?? 'No affiliations'); ?></span>
                                </td>
                                <td>
                                    <?php $creds = $doctorCredentials[(int)$doctor['id']] ?? []; ?>
                                    <?php if (!empty($creds)): ?>
                                        <div class="cred-list">
                                            <?php foreach ($creds as $cred): ?>
                                                <div class="cred-item">
                                                    <div class="cred-title"><?php echo htmlspecialchars($cred['credential_name']); ?></div>
                                                    <div class="muted"><?php echo htmlspecialchars($cred['credential_type']); ?> · <?php echo date('M d, Y', strtotime($cred['uploaded_at'])); ?></div>
                                                    <div style="margin-top:8px;">
                                                        <a class="btn btn-view" href="../<?php echo htmlspecialchars($cred['file_path']); ?>" target="_blank">View File</a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">No credentials uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="<?php echo getVerificationBadgeStyles($doctor['verification_status']); ?>">
                                        <?php echo htmlspecialchars($doctor['verification_status']); ?>
                                    </span><br>
                                    <?php if (!empty($doctor['verified_at'])): ?>
                                        <span class="muted">Updated <?php echo date('M d, Y h:i A', strtotime($doctor['verified_at'])); ?></span><br>
                                    <?php endif; ?>
                                    <?php if (!empty($doctor['verified_by_name'])): ?>
                                        <span class="muted">By <?php echo htmlspecialchars($doctor['verified_by_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if (($doctor['verification_status'] ?? 'pending') === 'verified'): ?>
                                            <span class="btn btn-verify" style="cursor:default;pointer-events:none;">Approved</span>
                                        <?php else: ?>
                                            <a class="btn btn-verify" href="?verify_doctor=<?php echo (int)$doctor['id']; ?>">Approve</a>
                                            <a class="btn btn-reject" href="?reject_doctor=<?php echo (int)$doctor['id']; ?>">Reject</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="muted">No doctors found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="patients" class="card">
            <h2>Patients</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Personal Info</th>
                            <th>Address</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($patients && $patients->num_rows > 0): ?>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($patient['name']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($patient['email']); ?><br>
                                    <span class="muted"><?php echo !empty($patient['phone']) ? htmlspecialchars($patient['phone']) : 'No phone'; ?></span>
                                </td>
                                <td>
                                    <span class="muted">DOB: <?php echo !empty($patient['dob']) && $patient['dob'] !== '0000-00-00' ? htmlspecialchars($patient['dob']) : 'Not set'; ?></span><br>
                                    <span class="muted">Gender: <?php echo htmlspecialchars($patient['gender'] ?? 'Not set'); ?></span>
                                </td>
                                <td><?php echo !empty($patient['address']) ? htmlspecialchars($patient['address']) : '<span class="muted">Not provided</span>'; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($patient['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="muted">No patients found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
