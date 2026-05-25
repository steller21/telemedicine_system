<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['call_id'])) {
    exit("Invalid call request.");
}

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

$call_id = intval($_GET['call_id']);
$doctor_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("
    SELECT vc.*, p.name AS patient_name
    FROM video_calls vc
    JOIN patients p ON p.id = vc.patient_id
    WHERE vc.id = ? AND vc.doctor_id = ?
");
$stmt->bind_param("ii", $call_id, $doctor_id);
$stmt->execute();
$call = $stmt->get_result()->fetch_assoc();

if (!$call) {
    exit("Call not found.");
}

if (($call['status'] ?? '') === 'active') {
    header("Location: ../video_call.php?call_id={$call_id}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calling Patient</title>
<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, sans-serif; background: radial-gradient(circle at 14% 16%, rgba(14,184,160,0.18), transparent 28%), radial-gradient(circle at 88% 20%, rgba(255,122,89,0.14), transparent 24%), linear-gradient(180deg, #091324 0%, #0e1b2e 100%); color: #fff; min-height: 100vh; padding: 24px; }
.shell { width: min(1200px, 100%); min-height: calc(100vh - 48px); margin: 0 auto; display: flex; align-items: center; justify-content: center; }
.card { width: min(96vw, 920px); min-height: min(72vh, 680px); background: rgba(12, 24, 42, 0.82); border: 1px solid rgba(255,255,255,0.08); border-radius: 32px; padding: 40px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.35); display: flex; flex-direction: column; justify-content: center; }
.ring { width: 96px; height: 96px; margin: 0 auto 20px; border-radius: 50%; background: rgba(14,184,160,0.16); display: flex; align-items: center; justify-content: center; font-size: 40px; animation: pulse 1.4s infinite; }
.muted { color: #94a3b8; }
.actions { margin-top: 24px; display: flex; justify-content: center; gap: 12px; }
.btn { display: inline-block; padding: 12px 18px; border-radius: 999px; text-decoration: none; font-weight: 700; }
.btn-primary { background: #0EB8A0; color: #0f172a; }
.btn-secondary { background: rgba(255,255,255,0.08); color: #fff; }
@keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(14,184,160,0.45); } 70% { transform: scale(1.03); box-shadow: 0 0 0 20px rgba(14,184,160,0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(14,184,160,0); } }
@media (max-width: 768px) {
    body { padding: 14px; }
    .shell { min-height: calc(100vh - 28px); }
    .card { min-height: calc(100vh - 28px); border-radius: 24px; padding: 28px; }
}
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
<script src="../js/page-transition.js"></script>
</head>
<body>
    <div class="shell">
        <div class="card">
            <div class="ring">&#128222;</div>
            <h1 style="margin:0 0 10px;font-size:clamp(2rem,4vw,3.5rem);">Calling <?php echo htmlspecialchars($call['patient_name']); ?></h1>
            <p class="muted" id="statusText" style="font-size:clamp(1rem,1.7vw,1.35rem); max-width:720px; margin:0 auto;">The patient phone is ringing. This call will ring for up to 1 minute.</p>
            <p class="muted" id="timerText" style="font-size:clamp(1rem,1.6vw,1.3rem); margin-top:24px;"></p>
            <div class="actions">
                <a class="btn btn-secondary" href="appointments.php">Back to Appointments</a>
            </div>
        </div>
    </div>

    <script>
    const callId = <?php echo (int)$call_id; ?>;
    const timerText = document.getElementById('timerText');
    const statusText = document.getElementById('statusText');

    function pollCallStatus() {
        fetch('../check_call_status.php?call_id=' + callId)
            .then(response => response.json())
            .then(data => {
                if (typeof data.seconds_left === 'number') {
                    timerText.textContent = data.status === 'waiting'
                        ? 'Time left to answer: ' + data.seconds_left + 's'
                        : '';
                }

                if (data.status === 'active') {
                    window.location.href = '../video_call.php?call_id=' + callId;
                    return;
                }

                if (data.status === 'missed') {
                    statusText.textContent = 'The patient did not answer within 1 minute. They can notify you when they are ready.';
                    timerText.textContent = '';
                    return;
                }

                if (data.status === 'declined' || data.status === 'ended') {
                    statusText.textContent = 'This call is no longer active.';
                    timerText.textContent = '';
                }
            });
    }

    pollCallStatus();
    setInterval(pollCallStatus, 3000);
    </script>
</body>
</html>

