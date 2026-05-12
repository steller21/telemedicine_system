<?php
session_start();
require_once("config/db.php");
require_once("includes/call_core.php");

ensureVideoCallSchema($conn);
expireWaitingCalls($conn);
 
if (!isset($_SESSION['user_id'])) { echo "❌ Please login first!"; exit; }
if (!isset($_GET['call_id'])) { echo "❌ No call ID!"; exit; }
 
$call_id = intval($_GET['call_id']);
$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");
$data = $call->fetch_assoc();

if (!$data || in_array(($data['status'] ?? ''), ['missed', 'declined', 'ended'], true)) {
    echo "This call is no longer available.";
    exit;
}
 
$user_id = $_SESSION['user_id'];
$isDoctor  = ($user_id == $data['doctor_id']);
$isPatient = ($user_id == $data['patient_id']);
 
if (!$isDoctor && !$isPatient) { echo "❌ Unauthorized"; exit; }
 
// Fetch current user name and gender
$userQuery = $isDoctor
    ? $conn->query("SELECT name, NULL as gender, 'doctor' as role FROM doctors WHERE id='".$user_id."'")
    : $conn->query("SELECT name, gender, 'patient' as role FROM patients WHERE id='".$user_id."'");
$userData = $userQuery->fetch_assoc();
$myPrefix = 'Mr.';
if ($userData && isset($userData['role']) && $userData['role'] === 'doctor') {
    $myPrefix = 'Dr.';
} elseif ($userData && isset($userData['gender']) && strtolower($userData['gender']) === 'female') {
    $myPrefix = 'Mrs.';
}
$myName = $myPrefix . ' ' . ($userData ? $userData['name'] : 'User');
 
// Fetch doctor and patient names with gender
$doctorQuery = $conn->query("SELECT name FROM doctors WHERE id='".$data['doctor_id']."'");
$doctorData = $doctorQuery->fetch_assoc();
$doctorName = 'Dr. ' . ($doctorData ? $doctorData['name'] : 'Doctor');
 
$patientQuery = $conn->query("SELECT name, gender FROM patients WHERE id='".$data['patient_id']."'");
$patientData = $patientQuery->fetch_assoc();
$patientPrefix = 'Mr.';
if ($patientData) {
    if (isset($patientData['gender']) && strtolower($patientData['gender']) === 'female') {
        $patientPrefix = 'Mrs.';
    }
    $patientName = $patientPrefix . ' ' . $patientData['name'];
}

// Fetch patient's vitals for doctor view
$v_trend = [];
$patient_reports = [];
if ($isDoctor) {
    $stmt = $conn->prepare("SELECT systolic, diastolic, glucose, spo2, heart_rate, DATE_FORMAT(logged_at, '%b %d') as label FROM patient_vitals WHERE patient_id = ? ORDER BY logged_at DESC LIMIT 10");
    $stmt->bind_param("i", $data['patient_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) { $v_trend[] = $row; }
    $v_trend = array_reverse($v_trend);
    // Fetch patient reports (accepted share OR appointment-based access)
    $rpt_stmt = $conn->prepare(
        "SELECT r.id, r.report_name, r.report_type, r.file_path, r.created_at 
         FROM reports r 
         LEFT JOIN report_share_requests rsr ON rsr.report_id = r.id AND rsr.requester_id = ? AND rsr.requester_role = 'doctor' AND rsr.status = 'accepted'
         LEFT JOIN appointments a ON a.patient_id = r.patient_id AND a.doctor_id = ?
         WHERE r.patient_id = ? AND (rsr.id IS NOT NULL OR a.id IS NOT NULL)
         GROUP BY r.id ORDER BY r.created_at DESC"
    );
    $rpt_stmt->bind_param("iii", $user_id, $user_id, $data['patient_id']);
    $rpt_stmt->execute();
    $rpt_res = $rpt_stmt->get_result();
    while($row = $rpt_res->fetch_assoc()) { $patient_reports[] = $row; }
}
 
$conn->query("UPDATE video_calls SET status='active', answered_at = COALESCE(answered_at, NOW()), ended_reason = NULL WHERE id='$call_id'");
?>
 
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Consultation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #1a1a2e;
            color: white;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            width: 100%;
        }
        h2 {
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.8em;
        }
        #status {
            text-align: center;
            background: #0f3460;
            padding: 10px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
            min-height: 20px;
        }
        .video-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            justify-items: center;
            align-items: center;
        }
        .video-box {
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 450px;
            aspect-ratio: 4/3;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .video-label {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(0,0,0,0.7);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: bold;
            z-index: 10;
        }
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }
        .control-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .control-group label {
            font-weight: bold;
            font-size: 0.95em;
        }
        select, button {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        select {
            background: #0f3460;
            color: white;
            min-width: 200px;
        }
        select:hover {
            background: #1a5490;
        }
        .btn-switch {
            background: #3498db;
            color: white;
            font-weight: bold;
        }
        .btn-switch:hover {
            background: #2980b9;
        }
        .btn-end {
            background: #e74c3c;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            font-size: 1em;
        }
        .btn-end:hover {
            background: #c0392b;
        }
        @media (max-width: 768px) {
            .video-container {
                grid-template-columns: 1fr;
            }
            .video-box {
                max-width: 100%;
            }
            h2 {
                font-size: 1.4em;
            }
        }
        /* Modal Styles */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:99999; align-items:center; justify-content:center; }
        .modal-content { background:#0f3460; border-radius:12px; padding:25px; max-width:600px; width:90%; color:white; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; }
        .modal-header h3 { margin:0; }
        .close-btn { background:none; border:none; color:white; font-size:1.5em; cursor:pointer; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 0.9em; }
        .form-group input[type="text"] { width: 100%; padding: 10px; border-radius: 6px; border: none; background: #1a1a2e; color: white; box-sizing: border-box; }
        .time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #1a1a2e; padding: 15px; border-radius: 8px; }
        .btn-primary { background: #0EB8A0; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .btn-primary:hover { background: #0A8A78; }
        .btn-action { background: #f39c12; color: white; border: none; border-radius: 6px; padding: 10px 15px; cursor: pointer; font-weight: bold; }
        .btn-action:hover { background: #e67e22; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="css/ui-refresh.css">
</head>
<body>
 
<div class="container">
    <h2>🎥 Video Consultation</h2>
    <p id="status">Starting...</p>
 
    <div class="video-container">
        <div class="video-box">
            <video id="myVideo" autoplay muted playsinline></video>
            <span class="video-label">📱 <?php echo htmlspecialchars($myName); ?></span>
        </div>
        <div class="video-box">
            <video id="remoteVideo" autoplay playsinline></video>
            <span class="video-label"><?php echo $isDoctor ? '👤 ' . htmlspecialchars($patientName) : '👨‍⚕️ ' . htmlspecialchars($doctorName); ?></span>
        </div>
    </div>
 
    <div class="controls">
        <div class="control-group">
            <label>📷 Camera:</label>
            <select id="cameraSelect" onchange="switchCamera()"><option>Loading cameras...</option></select>
        </div>
        <?php if ($isDoctor): ?>
            <div class="control-group">
                <button class="btn-action" onclick="showTrendsModal()">📊 Health Trends</button>
                <button class="btn-action" onclick="showPrescriptionModal()">💊 Prescribe</button>
                <button class="btn-action" style="background:#8b5cf6;" onclick="showReportsModal()">📄 Patient Reports</button>
            </div>
        <?php endif; ?>
    </div>
 
    <div style="text-align: center;">
        <button class="btn-end" onclick="endCall()">📵 End Call</button>
    </div>
</div>
 
<?php if ($isDoctor): ?>
<!-- Trends Modal -->
<div id="trendsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📊 Health Trends - <?php echo htmlspecialchars($patientName); ?></h3>
            <button class="close-btn" onclick="hideTrendsModal()">&times;</button>
        </div>
        <div style="height:300px; width:100%;"><canvas id="trendsChart"></canvas></div>
    </div>
</div>

<!-- Patient Reports Modal -->
<div id="reportsModal" class="modal">
    <div class="modal-content" style="max-width:700px; max-height:80vh; overflow-y:auto;">
        <div class="modal-header">
            <h3>📄 Patient Reports — <?php echo htmlspecialchars($patientName ?? 'Patient'); ?></h3>
            <button class="close-btn" onclick="hideReportsModal()">&times;</button>
        </div>
        <?php if (!empty($patient_reports)): ?>
            <div style="display:grid; gap:12px; margin-top:10px;">
            <?php foreach($patient_reports as $rpt):
                $ext = strtolower(pathinfo($rpt['file_path'], PATHINFO_EXTENSION));
                $icon = $ext === 'pdf' ? '📄' : '🖼️';
            ?>
                <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.07); padding:14px 18px; border-radius:10px; gap:12px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:1.5rem;"><?php echo $icon; ?></span>
                        <div>
                            <div style="font-weight:600; font-size:0.95rem;"><?php echo htmlspecialchars($rpt['report_name']); ?></div>
                            <div style="font-size:0.78rem; color:#7A8EA8;"><?php echo htmlspecialchars($rpt['report_type']); ?> · <?php echo date('d M Y', strtotime($rpt['created_at'])); ?></div>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($rpt['file_path']); ?>" target="_blank" style="background:#0EB8A0; color:white; padding:8px 16px; border-radius:20px; text-decoration:none; font-size:0.82rem; font-weight:600; white-space:nowrap;">👁️ View</a>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:40px 20px; color:#7A8EA8;">
                <div style="font-size:2.5rem; margin-bottom:12px;">📂</div>
                <p style="font-weight:600; margin-bottom:8px;">No reports available</p>
                <p style="font-size:0.85rem;">Patient has not shared any reports yet, or no appointments exist.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Prescription Modal -->
<div id="prescriptionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>💊 Issue Prescription</h3>
            <button class="close-btn" onclick="hidePrescriptionModal()">&times;</button>
        </div>
        <form id="prescriptionForm" onsubmit="submitPrescription(event)">
            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" name="medicine_name" required placeholder="e.g. Amoxicillin 500mg">
            </div>
            <div class="form-group">
                <label>Dosage Instructions</label>
                <input type="text" name="dosage" required placeholder="e.g. 1 capsule after meals">
            </div>
            <div class="form-group">
                <label>Daily Reminders</label>
                <div class="time-grid">
                    <label><input type="checkbox" name="medicine_time[]" value="morning"> 🌅 Morning</label>
                    <label><input type="checkbox" name="medicine_time[]" value="afternoon"> ☀️ Afternoon</label>
                    <label><input type="checkbox" name="medicine_time[]" value="evening"> 🌆 Evening</label>
                    <label><input type="checkbox" name="medicine_time[]" value="night"> 🌙 Night</label>
                </div>
            </div>
            <button type="submit" class="btn-primary" id="submitPrescriptionBtn">Issue Digital Prescription</button>
        </form>
    </div>
</div>

<script>
    const patientVitals = <?php echo json_encode($v_trend); ?>;
    let trendChart = null;

    function showTrendsModal() {
        document.getElementById('trendsModal').style.display = 'flex';
        const ctx = document.getElementById('trendsChart').getContext('2d');
        if (trendChart) trendChart.destroy();
        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: patientVitals.map(v => v.label),
                datasets: [
                    { label: 'Systolic', data: patientVitals.map(v => v.systolic), borderColor: '#0EB8A0', tension: 0.3 },
                    { label: 'Diastolic', data: patientVitals.map(v => v.diastolic), borderColor: '#22C55E', tension: 0.3 },
                    { label: 'Glucose', data: patientVitals.map(v => v.glucose), borderColor: '#F59E0B', tension: 0.3 },
                    { label: 'Heart Rate', data: patientVitals.map(v => v.heart_rate), borderColor: '#EF4444', tension: 0.3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'white' } },
                    x: { grid: { display: false }, ticks: { color: 'white' } }
                },
                plugins: { legend: { labels: { color: 'white' } } }
            }
        });
    }

    function hideTrendsModal() { document.getElementById('trendsModal').style.display = 'none'; }
    function showReportsModal()  { document.getElementById('reportsModal').style.display = 'flex'; }
    function hideReportsModal()  { document.getElementById('reportsModal').style.display = 'none'; }
    
    function showPrescriptionModal() { document.getElementById('prescriptionModal').style.display = 'flex'; }
    function hidePrescriptionModal() { document.getElementById('prescriptionModal').style.display = 'none'; document.getElementById('prescriptionForm').reset(); }

    function submitPrescription(e) {
        e.preventDefault();
        const btn = document.getElementById('submitPrescriptionBtn');
        btn.disabled = true;
        btn.innerText = 'Issuing...';
        
        const form = document.getElementById('prescriptionForm');
        const formData = new FormData(form);
        formData.append('patient_id', <?php echo $data['patient_id']; ?>);
        
        fetch('doctor/ajax_issue_prescription.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('✅ Prescription issued successfully!');
                hidePrescriptionModal();
            } else {
                alert('❌ Error: ' + (data.message || 'Failed to issue prescription'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ An error occurred.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = 'Issue Digital Prescription';
        });
    }
</script>
<?php endif; ?>
 
<script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>
 
<script>
const myVideo = document.getElementById("myVideo");
const remoteVideo = document.getElementById("remoteVideo");
const statusBox = document.getElementById("status");
 
const callId = "<?php echo $call_id; ?>";
const isDoctor = <?php echo $isDoctor ? 'true' : 'false'; ?>;
 
const myPeerId = (isDoctor ? "doc_" : "pat_") + callId;
const otherPeerId = (isDoctor ? "pat_" : "doc_") + callId;
 
let peer, localStream, activeCall;
let callConnected = false;
let currentDeviceId = null;
let callEnded = false;
let callStatusCheckInterval = null;
 
// ================= CAMERA =================
function startCamera(deviceId=null) {
    const constraints = deviceId 
        ? { video: { deviceId: { exact: deviceId } }, audio: true }
        : { video: { facingMode: 'user' }, audio: true };
 
    statusBox.innerText = "📷 Requesting camera...";
 
    navigator.mediaDevices.getUserMedia(constraints)
    .then(stream => {
        if (localStream) localStream.getTracks().forEach(t => t.stop());
 
        localStream = stream;
        myVideo.srcObject = stream;
 
        currentDeviceId = stream.getVideoTracks()[0].getSettings().deviceId;
        statusBox.innerText = "✅ Camera ready";
 
        loadCameras();
 
        if (!peer) initPeer();
    })
    .catch(err => {
        let message = "Camera error: " + err.message;
        switch(err.name) {
            case "NotAllowedError":
                message = "❌ Camera permission denied. Please allow camera access.";
                break;
            case "NotFoundError":
                message = "❌ No camera found on this device.";
                break;
            case "NotReadableError":
                message = "❌ Camera is in use by another application.";
                break;
        }
        statusBox.innerText = message;
        alert(message);
        console.error(err);
    });
}
 
function loadCameras() {
    navigator.mediaDevices.enumerateDevices().then(devices => {
        let select = document.getElementById("cameraSelect");
        select.innerHTML = "";
 
        let cameras = devices.filter(d => d.kind === "videoinput");
        
        if (cameras.length === 0) {
            select.innerHTML = "<option>No cameras found</option>";
            return;
        }
 
        let filtered = [];
        let seenFront = false;
        let seenBack = false;

        cameras.forEach(cam => {
            let label = cam.label.toLowerCase();
            let isFront = label.includes('front') || label.includes('user');
            let isBack = label.includes('back') || label.includes('rear') || label.includes('environment');

            if (isFront && !seenFront) {
                filtered.push(cam); seenFront = true;
            } else if (isBack && !seenBack) {
                filtered.push(cam); seenBack = true;
            } else if (!isFront && !isBack) {
                filtered.push(cam);
            }
        });

        filtered.forEach((cam, index) => {
            let opt = document.createElement("option");
            opt.value = cam.deviceId;
            opt.text = cam.label || ("Camera " + (index + 1));
            if (cam.deviceId === currentDeviceId) opt.selected = true;
            select.appendChild(opt);
        });
    }).catch(err => {
        console.error("Error enumerating devices:", err);
    });
}
 
function switchCamera() {
    let selectedId = document.getElementById("cameraSelect").value;
    if (!selectedId) return;
    
    statusBox.innerText = "🔄 Switching camera...";
    
    // Get new camera stream
    const constraints = {
        video: { deviceId: { exact: selectedId } },
        audio: true
    };
    
    navigator.mediaDevices.getUserMedia(constraints)
    .then(newStream => {
        const newVideoTrack = newStream.getVideoTracks()[0];
        
        // If call is active, replace the video track live
        if (callConnected && activeCall && activeCall.peerConnection) {
            const sender = activeCall.peerConnection
                .getSenders()
                .find(s => s.track && s.track.kind === 'video');
            
            if (sender) {
                sender.replaceTrack(newVideoTrack)
                    .then(() => {
                        // Stop old tracks and update
                        localStream.getTracks().forEach(t => {
                            if (t.kind === 'video') t.stop();
                        });
                        
                        // Replace local stream with new one (keep audio from old)
                        localStream.getTracks().forEach(t => {
                            if (t.kind === 'audio') {
                                newStream.addTrack(t);
                            }
                        });
                        localStream = newStream;
                        myVideo.srcObject = localStream;
                        
                        currentDeviceId = selectedId;
                        statusBox.innerText = "✅ Camera switched!";
                        setTimeout(() => { statusBox.innerText = "Connected"; }, 2000);
                    })
                    .catch(err => {
                        statusBox.innerText = "❌ Failed to switch: " + err.message;
                        console.error("Replace track error:", err);
                    });
            }
        } else {
            // Not in call, just restart camera normally
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            localStream = newStream;
            myVideo.srcObject = localStream;
            currentDeviceId = selectedId;
            statusBox.innerText = "✅ Camera switched!";
        }
    })
    .catch(err => {
        statusBox.innerText = "❌ Camera error: " + err.message;
        alert("Could not switch camera: " + err.message);
    });
}
 
// ================= PEER =================
function initPeer() {
    peer = new Peer(myPeerId, {
        config: {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                {
                    urls: 'turn:openrelay.metered.ca:80',
                    username: 'openrelayproject',
                    credential: 'openrelayproject'
                },
                {
                    urls: 'turn:openrelay.metered.ca:443?transport=tcp',
                    username: 'openrelayproject',
                    credential: 'openrelayproject'
                }
            ]
        }
    });
 
    peer.on('open', () => {
        statusBox.innerText = "📞 Connecting...";
        console.log("Peer opened with ID: " + myPeerId);
        setTimeout(callOther, 2000);
    });
 
    peer.on('disconnected', () => {
        if (!callEnded) {
            statusBox.innerText = "⚠️ Server disconnected, ending call...";
            endCallAuto();
        }
    });
 
    peer.on('error', (err) => {
        if (callEnded) return;
        console.error("Peer error:", err.type);
        // Only end call on truly fatal errors, not peer-unavailable (normal during connect)
        if (err.type === 'peer-unavailable') {
            statusBox.innerText = "⏳ Waiting for other person...";
            return; // Don't end — just wait
        }
        statusBox.innerText = "❌ Connection error: " + err.type;
        endCallAuto();
    });
 
    peer.on('call', call => {
        activeCall = call;
        call.answer(localStream);
 
        call.on('stream', stream => {
            remoteVideo.srcObject = stream;
            remoteVideo.onloadedmetadata = () => remoteVideo.play();
            callConnected = true;
            statusBox.innerText = "🟢 Connected";
        });
        
        call.on('close', () => {
            callConnected = false;
            endCallAuto(); // Always try to end call if PeerJS connection closes
        });
        
        call.on('error', (err) => {
            if (!callEnded) {
                statusBox.innerText = "❌ Incoming call error, ending call...";
                endCallAuto(); // Trigger auto-disconnect on incoming call error
                console.error("Incoming call error:", err);
            }
        });
    });
}
 
function callOther() {
    if (!localStream || callConnected) return;
 
    let call = peer.call(otherPeerId, localStream);
    activeCall = call;
 
    call.on('stream', stream => {
        remoteVideo.srcObject = stream;
        remoteVideo.onloadedmetadata = () => remoteVideo.play();
        callConnected = true;
        statusBox.innerText = "🟢 Connected";
    });
    
    call.on('close', () => {
        callConnected = false;
        endCallAuto(); // Always try to end call if PeerJS connection closes
    });
    
    call.on('error', (err) => {
        if (!callEnded) {
            statusBox.innerText = "❌ Outgoing call error, ending call...";
            endCallAuto(); // Trigger auto-disconnect on outgoing call error
            console.error("Outgoing call error:", err);
        }
    });
}
 
// ================= END =================
function startCallStatusMonitor() {
    clearInterval(callStatusCheckInterval);
    console.log("🟢 Starting call status monitor - polling every 500ms");
    
    let pollCount = 0;
    let lastActiveTime = Date.now();
    
    function doPoll() {
        if (callEnded) return;
        
        pollCount++;
        fetch('check_call_status.php?call_id=<?php echo $call_id; ?>&t=' + Date.now(), { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                const status = (data.status || '').trim();
                
                // Debug logs
                if (pollCount % 4 === 0) {  // Log every 4th poll to reduce noise
                    console.log("Poll #" + pollCount + ": status=" + status);
                }
                
                // If status is active, update last active time
                if (status === 'active') {
                    lastActiveTime = Date.now();
                }
                
                // Detect ended status
                if ((status === 'ended' || status === 'missed' || status === 'declined') && !callEnded) {
                    console.log("🔴 DETECTED: Call status is 'ended' - auto-disconnecting");
                    endCallAuto(); // Don't set callEnded here — endCallAuto() handles it
                }
            })
            .catch(err => {
                console.log("⚠️ Poll error: " + err.message);
            });
    }
    
    // Poll frequently
    doPoll();
    callStatusCheckInterval = setInterval(doPoll, 500);
    
    // Fallback: only disconnect if status stays 'ended' for 30s (not active timer)
    // Removed aggressive 10s timer — it was killing calls that haven't connected yet
}
 
// Start monitoring immediately on page load
startCallStatusMonitor();
 
function endCallAuto() {
    if (callEnded) {
        console.log("Call already marked as ended");
        return;
    }
    callEnded = true;
    clearInterval(callStatusCheckInterval);
    console.log("Auto-disconnecting call");
    
    statusBox.innerText = "📵 Call ended by other person";
    
    try {
        if (activeCall) {
            activeCall.close();
            console.log("Active call closed");
        }
    } catch(e) {
        console.warn("Error closing call:", e);
    }
    
    try {
        if (localStream) {
            localStream.getTracks().forEach(t => {
                try { t.stop(); } catch(e) { console.warn("Error stopping track:", e); }
            });
            console.log("Local stream stopped");
        }
    } catch(e) {
        console.warn("Error stopping stream:", e);
    }
    
    try {
        if (peer) {
            peer.destroy();
            console.log("Peer destroyed");
        }
    } catch(e) {
        console.warn("Error destroying peer:", e);
    }
    
    myVideo.srcObject = null;
    remoteVideo.srcObject = null;
    
    // Redirect immediately with fallback
    console.log("Attempting redirect from auto-disconnect");
    
    const isDoctor = <?php echo $isDoctor ? 'true' : 'false'; ?>;
    const redirectUrl = isDoctor ? "doctor/dashboard.php" : "patient/dashboard.php";
    
    console.log("Is Doctor:", isDoctor, "Redirect URL:", redirectUrl);
    
    try {
        window.location.href = redirectUrl;
    } catch(e) {
        console.error("href redirect failed:", e);
        window.location.replace(redirectUrl);
    }
}
 
function endCall() {
    if (callEnded) {
        console.log("Call already ended");
        return;
    }
    callEnded = true;
    clearInterval(callStatusCheckInterval);
    console.log("User ended call");
    
    statusBox.innerText = "📵 Ending call...";
    
    // Notify the other person
    console.log("Notifying other side...");
    fetch('end_call_notification.php?call_id=<?php echo $call_id; ?>')
        .then(res => res.text())
        .then(data => console.log("Notification response:", data))
        .catch(err => console.error("Notification error:", err));
    
    try {
        if (activeCall) {
            activeCall.close();
            console.log("Active call closed");
        }
    } catch(e) {
        console.warn("Error closing call:", e);
    }
    
    try {
        if (localStream) {
            localStream.getTracks().forEach(t => {
                try { t.stop(); } catch(e) { console.warn("Error stopping track:", e); }
            });
            console.log("Local stream stopped");
        }
    } catch(e) {
        console.warn("Error stopping stream:", e);
    }
    
    try {
        if (peer) {
            peer.destroy();
            console.log("Peer destroyed");
        }
    } catch(e) {
        console.warn("Error destroying peer:", e);
    }
    
    myVideo.srcObject = null;
    remoteVideo.srcObject = null;
    
    // Redirect immediately with fallback
    console.log("Attempting redirect after user ended call");
    
    const isDoctor = <?php echo $isDoctor ? 'true' : 'false'; ?>;
    const redirectUrl = isDoctor ? "doctor/dashboard.php" : "patient/dashboard.php";
    
    console.log("Is Doctor:", isDoctor, "Redirect URL:", redirectUrl);
    
    window.location.href = redirectUrl;
}
 
// Handle tab closing or refreshes
window.addEventListener('beforeunload', function (e) {
    if (!callEnded) {
        // We use sendBeacon for reliable delivery during page unload
        navigator.sendBeacon('end_call_notification.php?call_id=<?php echo $call_id; ?>');
        
        if (activeCall) activeCall.close();
        if (localStream) localStream.getTracks().forEach(t => t.stop());
        if (peer) peer.destroy();
    }
});
 
// START
startCamera();
</script>
 
</body>
</html>

