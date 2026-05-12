<?php
session_start();
require_once("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['patient_id'])) {
    echo "No patient selected";
    exit;
}

$patient_id = intval($_GET['patient_id']);
$monitor_id = intval($_SESSION['user_id']);

$access_stmt = $conn->prepare("SELECT 1 FROM patient_monitors WHERE patient_id = ? AND monitor_id = ? LIMIT 1");
$access_stmt->bind_param("ii", $patient_id, $monitor_id);
$access_stmt->execute();
if ($access_stmt->get_result()->num_rows === 0) {
    echo "Unauthorized access";
    exit;
}

$pstmt = $conn->prepare("SELECT name FROM patients WHERE id = ?");
$pstmt->bind_param("i", $patient_id);
$pstmt->execute();
$pname = $pstmt->get_result()->fetch_assoc()['name'] ?? 'Patient';

$medicine_rows = [];
$stmt = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$check = $stmt->get_result();

if ($check && $check->num_rows > 0) {
    $checklist_id = $check->fetch_assoc()['id'];
    $stmt2 = $conn->prepare("SELECT * FROM checklist_items WHERE checklist_id = ? ORDER BY start_date ASC");
    $stmt2->bind_param("i", $checklist_id);
    $stmt2->execute();
    $items_result = $stmt2->get_result();
    $today = date('Y-m-d');
    $today_dt = new DateTime($today);

    while ($item = $items_result->fetch_assoc()) {
        $item_id = $item['id'];
        $start_date = new DateTime($item['start_date']);
        $duration_days = max(1, (int) ($item['duration_days'] ?? 1));
        $times_of_day_slots = explode(',', $item['times_of_day'] ?? '');
        $end_date = clone $start_date;
        $end_date->modify('+' . ($duration_days - 1) . ' days');
        $current_date = clone $start_date;

        while ($current_date <= $today_dt && $current_date <= $end_date) {
            $scheduled_date_str = $current_date->format('Y-m-d');

            foreach ($times_of_day_slots as $slot) {
                $slot = trim($slot);
                if ($slot === '') {
                    continue;
                }

                $stmt_intake = $conn->prepare("SELECT status, completed_at FROM medicine_intakes WHERE checklist_item_id = ? AND scheduled_date = ? AND time_of_day_slot = ?");
                $stmt_intake->bind_param("iss", $item_id, $scheduled_date_str, $slot);
                $stmt_intake->execute();
                $intake_res = $stmt_intake->get_result();

                $status = 'pending';
                $completed_at = null;

                if ($intake_data = $intake_res->fetch_assoc()) {
                    $status = $intake_data['status'];
                    $completed_at = $intake_data['completed_at'];
                } elseif ($scheduled_date_str < $today) {
                    $status = 'missed';
                }

                $medicine_rows[] = [
                    'medicine_name' => $item['medicine_name'],
                    'scheduled_date' => $scheduled_date_str,
                    'time_slot' => $slot,
                    'status' => $status,
                    'completed_at' => $completed_at
                ];
            }

            $current_date->modify('+1 day');
        }
    }

    usort($medicine_rows, function ($a, $b) {
        $date_compare = strcmp($b['scheduled_date'], $a['scheduled_date']);
        if ($date_compare !== 0) {
            return $date_compare;
        }
        $order = ['morning' => 1, 'afternoon' => 2, 'evening' => 3, 'night' => 4];
        return ($order[$a['time_slot']] ?? 99) <=> ($order[$b['time_slot']] ?? 99);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patient Checklist - MediConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --teal: #0EB8A0;
    --teal-dark: #0A8A78;
    --teal-glow: rgba(14,184,160,0.15);
    --navy: #f8fafc;
    --navy-card: #ffffff;
    --navy-light: #f1f5f9;
    --white: #1e293b;
    --muted: #64748b;
    --success: #22C55E;
    --warning: #F59E0B;
    --danger: #EF4444;
    --border: rgba(0,0,0,0.08);
    --radius: 14px;
    --shadow: 0 4px 20px rgba(0,0,0,0.05);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--navy);
    color: var(--white);
    min-height: 100vh;
    line-height: 1.6;
}
.page-bg {
    position: fixed;
    inset: 0;
    z-index: -1;
    background:
        radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),
        var(--navy);
}
.main {
    max-width: 1100px;
    margin: 0 auto;
    padding: 36px 24px;
}
.page-header {
    margin-bottom: 24px;
}
.page-header h1 {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.9rem;
    margin-bottom: 4px;
}
.page-header p {
    color: var(--muted);
}
.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: 22px;
    padding: 24px;
    box-shadow: var(--shadow);
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    background: var(--navy-light);
    color: var(--white);
    margin-bottom: 18px;
}
.table-wrap {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
thead th {
    text-align: left;
    padding: 12px 16px;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
}
tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-success { background: rgba(34,197,94,0.12); color: var(--success); }
.badge-warning { background: rgba(245,158,11,0.12); color: var(--warning); }
.badge-danger { background: rgba(239,68,68,0.12); color: var(--danger); }
.empty-state {
    text-align: center;
    padding: 48px 20px;
    color: var(--muted);
}
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
</head>
<body>
<div class="page-bg"></div>
<main class="main">
    <div class="page-header">
        <h1><?php echo htmlspecialchars($pname); ?>'s Medicines</h1>
        <p>Medicine adherence checklist for this patient.</p>
    </div>
    <a href="monitor_view.php" class="btn">Back</a>
    <div class="card">
        <?php if (!empty($medicine_rows)): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Scheduled Time</th>
                            <th>Status</th>
                            <th>Time Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicine_rows as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($row['scheduled_date'])) . ' - ' . htmlspecialchars(ucfirst($row['time_slot'])); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'completed'): ?>
                                        <span class="badge badge-success">Taken</span>
                                    <?php elseif ($row['status'] === 'missed'): ?>
                                        <span class="badge badge-danger">Missed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--muted);">
                                    <?php echo !empty($row['completed_at']) ? date('M d, h:i A', strtotime($row['completed_at'])) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No medicines found</h3>
                <p>This patient hasn't added any medicines yet.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

