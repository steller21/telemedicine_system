<?php
session_start();
require_once("../config/db.php");
 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}
 
$doctor_id = intval($_SESSION['user_id']);
 
// Get incoming calls
$calls = $conn->query("SELECT vc.*, u.name as patient_name 
                       FROM video_calls vc
                       LEFT JOIN users u ON u.id = vc.patient_id
                       WHERE vc.doctor_id='$doctor_id' AND vc.status='waiting'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f0f4f8;
            padding: 30px;
            color: #333;
        }
        h2 { margin-bottom: 20px; color: #2c3e50; }
        h3 { margin-bottom: 12px; color: #2c3e50; }
 
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
 
        .no-calls { color: #888; font-size: 0.95rem; }
 
        .call-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff8e1;
            border-left: 4px solid #f39c12;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 10px;
        }
        .call-item span { font-size: 1rem; }
        .accept-btn {
            background: #27ae60;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
        }
        .accept-btn:hover { background: #219150; }
 
        .links a {
            display: inline-block;
            margin-right: 12px;
            margin-bottom: 10px;
            background: #2980b9;
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .links a:hover { background: #1f6391; }
        .links a.logout { background: #e74c3c; }
        .links a.logout:hover { background: #c0392b; }
 
        /* ── Incoming call popup overlay ── */
        #callOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        #callOverlay.show { display: flex; }
        #callBox {
            background: #fff;
            border-radius: 16px;
            padding: 36px 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            animation: popIn 0.3s ease;
        }
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        #callBox h2 { font-size: 1.5rem; margin-bottom: 8px; color: #2c3e50; }
        #callBox p  { color: #666; margin-bottom: 24px; font-size: 1rem; }
        #callBox .ring { font-size: 3rem; animation: ring 0.6s infinite alternate; }
        @keyframes ring {
            from { transform: rotate(-15deg); }
            to   { transform: rotate(15deg); }
        }
        .popup-btns { display: flex; gap: 16px; justify-content: center; margin-top: 10px; }
        .popup-btns a {
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            text-decoration: none;
            font-weight: bold;
        }
        .btn-accept { background: #27ae60; color: #fff; }
        .btn-accept:hover { background: #219150; }
        .btn-decline { background: #e74c3c; color: #fff; }
        .btn-decline:hover { background: #c0392b; }
    </style>
</head>
<body>
 
<h2>Welcome, Dr. <?php echo htmlspecialchars($_SESSION['name']); ?> 👨‍⚕️</h2>
 
<!-- Incoming calls section -->
<div class="card">
    <h3>📞 Incoming Calls</h3>
    <div id="callList">
        <?php if ($calls && $calls->num_rows > 0): ?>
            <?php while($call = $calls->fetch_assoc()): ?>
                <div class="call-item">
                    <span>📞 <?php echo htmlspecialchars($call['patient_name'] ?? 'Patient'); ?> is calling...</span>
                    <a class="accept-btn" href="accept_call.php?call_id=<?php echo $call['id']; ?>">
                        ✅ Accept
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-calls" id="noCalls">No incoming calls right now.</p>
        <?php endif; ?>
    </div>
</div>
 
<!-- Quick links -->
<div class="card links">
    <h3>Quick Links</h3><br>
    <a href="appointments.php">📅 Appointments</a>
    <a href="../patient/monitor_view.php">👥 Monitor Patients</a>
    <a class="logout" href="../logout.php">🚪 Logout</a>
</div>
 
<!-- ── Incoming call popup ── -->
<div id="callOverlay">
    <div id="callBox">
        <div class="ring">📞</div>
        <h2>Incoming Call!</h2>
        <p id="callerName">A patient is calling you...</p>
        <div class="popup-btns">
            <a id="acceptLink" class="btn-accept" href="#">✅ Accept</a>
            <a id="declineLink" class="btn-decline" href="#">❌ Decline</a>
        </div>
    </div>
</div>
 
<script>
// Poll every 4 seconds for new incoming calls
let popupShown = false;
let knownCallIds = new Set([
    <?php
        // Reset query
        $conn->query("SELECT id FROM video_calls WHERE doctor_id='$doctor_id' AND status='waiting'");
        $existing = $conn->query("SELECT id FROM video_calls WHERE doctor_id='$doctor_id' AND status='waiting'");
        $ids = [];
        if ($existing) while($r = $existing->fetch_assoc()) $ids[] = $r['id'];
        echo implode(',', $ids);
    ?>
]);
 
function checkCalls() {
    fetch('check_calls.php')
        .then(r => r.json())
        .then(data => {
            if (data.calls && data.calls.length > 0) {
                // Update call list in page
                let listHtml = '';
                data.calls.forEach(call => {
                    listHtml += `
                        <div class="call-item">
                            <span>📞 ${call.patient_name} is calling...</span>
                            <a class="accept-btn" href="accept_call.php?call_id=${call.id}">✅ Accept</a>
                        </div>`;
 
                    // Show popup for NEW calls only
                    if (!knownCallIds.has(call.id) && !popupShown) {
                        popupShown = true;
                        knownCallIds.add(call.id);
                        document.getElementById('callerName').textContent = call.patient_name + ' is calling you...';
                        document.getElementById('acceptLink').href = 'accept_call.php?call_id=' + call.id;
                        document.getElementById('declineLink').href = 'decline_call.php?call_id=' + call.id;
                        document.getElementById('callOverlay').classList.add('show');
 
                        // Play sound
                        try {
                            let ctx = new AudioContext();
                            function beep() {
                                let o = ctx.createOscillator();
                                let g = ctx.createGain();
                                o.connect(g); g.connect(ctx.destination);
                                o.frequency.value = 520;
                                g.gain.setValueAtTime(0.3, ctx.currentTime);
                                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                                o.start(ctx.currentTime);
                                o.stop(ctx.currentTime + 0.4);
                            }
                            beep();
                            setTimeout(beep, 600);
                            setTimeout(beep, 1200);
                        } catch(e) {}
                    }
                });
                document.getElementById('callList').innerHTML = listHtml;
 
            } else {
                // No calls
                popupShown = false;
                document.getElementById('callOverlay').classList.remove('show');
                document.getElementById('callList').innerHTML = '<p class="no-calls">No incoming calls right now.</p>';
            }
        })
        .catch(e => console.log('Poll error:', e));
}
 
// Decline just hides popup, doesn't end call
document.getElementById('declineLink').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('callOverlay').classList.remove('show');
    popupShown = false;
});
 
// Poll every 4 seconds
setInterval(checkCalls, 4000);
</script>
 
</body>
</html>