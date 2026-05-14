<?php
session_start();
require_once("../config/db.php");
require_once("../includes/call_core.php");

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);

if (!isset($_GET['call_id'])) {
    exit("No call ID.");
}

$call_id = intval($_GET['call_id']);
$call = $conn->query("SELECT * FROM video_calls WHERE id = '{$call_id}'");

if (!$call || $call->num_rows === 0) {
    exit("Call not found.");
}

$row = $call->fetch_assoc();
if (($row['status'] ?? '') === 'active') {
    header("Location: ../video_call.php?call_id=" . $row['id']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Waiting for Doctor</title>
<link rel="stylesheet" href="../css/ui-refresh.css">
<script src="../js/page-transition.js"></script>
</head>
<body style="text-align:center; padding:50px; background:#1a1a2e; color:white; font-family:Arial, sans-serif;">
<h2>Waiting for doctor to join...</h2>
<p id="waitStatus" style="color:#cbd5e1;">The doctor will be notified right away.</p>
<p id="waitTimer" style="color:#94a3b8;"></p>

<script>
const callId = <?php echo (int)$call_id; ?>;
const waitStatus = document.getElementById('waitStatus');
const waitTimer = document.getElementById('waitTimer');

function refreshCallState() {
    fetch('../check_call_status.php?call_id=' + callId)
        .then(response => response.json())
        .then(data => {
            if (typeof data.seconds_left === 'number') {
                waitTimer.textContent = data.status === 'waiting'
                    ? 'Time left for the doctor to answer: ' + data.seconds_left + 's'
                    : '';
            }

            if (data.status === 'active') {
                window.location.href = '../video_call.php?call_id=' + callId;
                return;
            }

            if (data.status === 'missed') {
                waitStatus.textContent = 'The doctor did not answer within 1 minute. You can try again later.';
                waitTimer.textContent = '';
                return;
            }

            if (data.status === 'declined' || data.status === 'ended') {
                waitStatus.textContent = 'This call is no longer active.';
                waitTimer.textContent = '';
            }
        });
}

refreshCallState();
setInterval(refreshCallState, 3000);
</script>
</body>
</html>

