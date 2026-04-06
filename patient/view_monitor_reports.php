<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$monitor_id = $_SESSION['user_id'];

// Get monitored patients' reports
$patients = $conn->prepare("
    SELECT DISTINCT u.id, u.name
    FROM patient_monitors pm
    JOIN users u ON pm.patient_id = u.id
    WHERE pm.monitor_id = ?
");
if ($patients) {
    $patients->bind_param("i", $monitor_id);
    $patients->execute();
    $patients_result = $patients->get_result();
}

// If viewing specific patient's reports
$patient_reports = null;
$patient_name = null;
if (isset($_GET['patient_id'])) {
    $patient_id = intval($_GET['patient_id']);
    
    // Get patient name
    $pstmt = $conn->prepare("SELECT name FROM users WHERE id=?");
    if ($pstmt) {
        $pstmt->bind_param("i", $patient_id);
        $pstmt->execute();
        $pname = $pstmt->get_result()->fetch_assoc();
        $patient_name = $pname['name'] ?? 'Patient';
    }
    
    // Get all reports from this patient with access status
    $rstmt = $conn->prepare("
        SELECT r.*,
               CASE WHEN rsr.id IS NOT NULL AND rsr.status='accepted' THEN 1 ELSE 0 END as has_access,
               CASE WHEN rsr.id IS NOT NULL AND rsr.status='pending' THEN 1 ELSE 0 END as pending_request,
               rsr.id as request_id
        FROM reports r
        LEFT JOIN report_share_requests rsr ON r.id=rsr.report_id AND rsr.requester_id=? AND rsr.requester_role='monitor'
        WHERE r.patient_id=?
        ORDER BY r.created_at DESC
    ");
    if ($rstmt) {
        $rstmt->bind_param("ii", $monitor_id, $patient_id);
        $rstmt->execute();
        $patient_reports = $rstmt->get_result();
    }
}

// Handle request for access
if (isset($_POST['request_access'])) {
    $report_id = intval($_POST['report_id']);
    $patient_id = intval($_POST['patient_id']);
    
    $insert = $conn->prepare("INSERT INTO report_share_requests (report_id, patient_id, requester_id, requester_role, status) VALUES (?, ?, ?, 'monitor', 'pending')");
    if ($insert) {
        $insert->bind_param("iii", $report_id, $patient_id, $monitor_id);
        $insert->execute();
    }
    
    header("Location: view_monitor_reports.php?patient_id=$patient_id");
    exit;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patient Reports — MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
:root{--teal:#0EB8A0;--teal-dark:#0A8A78;--teal-glow:rgba(14,184,160,0.15);--navy:#0B1526;--navy-mid:#112035;--navy-light:#1A3050;--navy-card:#0F1E36;--white:#FFFFFF;--muted:#7A8EA8;--muted-dim:#4A5E78;--success:#22C55E;--warning:#F59E0B;--danger:#EF4444;--border:rgba(255,255,255,0.07);--radius:14px;}
*{box-sizing:border-box;margin:0;padding:0;}body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;line-height:1.6;}
.page-bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),var(--navy);}
.layout{display:flex;min-height:100vh;}.sidebar{width:240px;background:var(--navy-card);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;padding:24px 0;}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:0 24px 28px;border-bottom:1px solid var(--border);margin-bottom:16px;text-decoration:none;}
.logo-dot{width:9px;height:9px;background:var(--teal);border-radius:50%;box-shadow:0 0 10px var(--teal);animation:blink 2s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}
.logo-text{font-family:'Clash Display',sans-serif;font-size:1.15rem;font-weight:700;color:var(--white);}
.nav-section{padding:0 12px;margin-bottom:8px;}.nav-section-label{font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted-dim);font-weight:600;padding:0 12px 8px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:0.875rem;font-weight:500;transition:all 0.2s;margin-bottom:2px;}
.nav-link:hover{background:var(--teal-glow);color:var(--white);}.nav-link.active{background:var(--teal-glow);color:var(--teal);}
.main{margin-left:240px;max-width:calc(100% - 240px);padding:40px;overflow-y:auto;}
.page-header{margin-bottom:32px;}.page-header h1{font-family:'Clash Display',sans-serif;font-size:1.5rem;font-weight:700;margin-bottom:6px;}
.page-header p{color:var(--muted);font-size:0.9rem;}
.card{background:var(--navy-card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:24px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:50px;font-size:0.875rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s;}
.btn-primary{background:var(--teal);color:var(--navy);}.btn-primary:hover{background:var(--teal-dark);}
.btn-secondary{background:var(--navy-light);color:var(--white);border:1px solid var(--border);}.btn-secondary:hover{background:var(--navy-mid);}
.btn-sm{padding:7px 16px;font-size:0.8rem;}.table-wrap{overflow-x:auto;}table{width:100%;border-collapse:collapse;font-size:0.875rem;}
thead th{text-align:left;padding:12px 16px;font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);}
tbody td{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.04);}.badge{background:var(--teal-glow);color:var(--teal);display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:0.72rem;font-weight:600;}
.badge-success{background:rgba(34,197,94,0.12);color:var(--success);}.badge-warning{background:rgba(245,158,11,0.12);color:var(--warning);}.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}.empty-state h3{font-size:1rem;font-weight:600;margin-bottom:8px;color:var(--white);}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;max-width:100%;}}
</style>
</head><body><div class="page-bg"></div><div class="layout">
<aside class="sidebar">
    <a href="../index.php" class="sidebar-logo"><div class="logo-dot"></div><span class="logo-text">MediConnect</span></a>
    <div class="nav-section"><div class="nav-section-label">Monitoring</div>
        <a href="monitor_view.php" class="nav-link"><span style="font-size:1.1rem;">👥</span> My Patients</a>
        <a href="view_monitor_reports.php" class="nav-link active"><span style="font-size:1.1rem;">📄</span> View Reports</a>
    </div>
    <div class="sidebar-bottom"><a href="../logout.php" class="nav-link"><span style="font-size:1.1rem;">🚪</span> Logout</a></div>
</aside>
<main class="main">
    <?php if (!isset($_GET['patient_id'])): ?>
        <div class="page-header"><h1>📄 Patient Reports</h1><p>Request and view reports from your patients.</p></div>
        <div class="card">
            <?php if ($patients_result && $patients_result->num_rows > 0): ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Patient Name</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while ($patient = $patients_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($patient['name']); ?></strong></td>
                            <td><a href="view_monitor_reports.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary btn-sm">📋 View Reports</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table></div>
            <?php else: ?>
                <div class="empty-state"><h3>No patients yet</h3><p>You're not monitoring any patients.</p></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
            <a href="view_monitor_reports.php" class="btn btn-secondary btn-sm">← Back</a>
            <h1 style="font-family:'Clash Display',sans-serif;flex:1;"><?php echo htmlspecialchars($patient_name); ?>'s Reports</h1>
        </div>
        <div class="card">
            <?php if ($patient_reports && $patient_reports->num_rows > 0): ?>
                <?php while ($report = $patient_reports->fetch_assoc()):
                    $ext = strtolower(pathinfo($report['file_path'], PATHINFO_EXTENSION));
                    $icon = $ext === 'pdf' ? '📄' : '🖼️';
                ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px;background:var(--navy-light);border-radius:var(--radius);margin-bottom:12px;border-left:2px solid <?php echo $report['has_access'] ? 'var(--success)' : 'var(--teal)'; ?>">
                        <div style="display:flex;align-items:center;gap:12px;flex:1;">
                            <div style="width:40px;height:40px;background:var(--teal-glow);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><?php echo $icon; ?></div>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($report['report_name']); ?></div>
                                <div style="font-size:0.75rem;color:var(--muted);"><?php echo htmlspecialchars($report['report_type']); ?> · <?php echo date('d M Y', strtotime($report['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php if ($report['has_access']): ?>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <span class="badge badge-success">✅ Shared</span>
                                <a href="<?php echo htmlspecialchars('../' . $report['file_path']); ?>" target="_blank" class="btn btn-secondary btn-sm">👁️ View</a>
                            </div>
                        <?php elseif ($report['pending_request']): ?>
                            <span class="badge badge-warning">⏳ Pending</span>
                        <?php else: ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <input type="hidden" name="patient_id" value="<?php echo $_GET['patient_id']; ?>">
                                <button type="submit" name="request_access" class="btn btn-primary btn-sm">📥 Request Access</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><h3>No reports found</h3><p>This patient hasn't uploaded any reports yet.</p></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</div></body></html>
