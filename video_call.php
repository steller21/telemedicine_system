<?php
session_start();
require_once("config/db.php");
 
if (!isset($_SESSION['user_id'])) {
    echo "❌ Please login first!"; exit;
}
if (!isset($_GET['call_id'])) {
    echo "❌ No call ID found!"; exit;
}
 
$call_id = intval($_GET['call_id']); // sanitized
 
$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");
if (!$call || $call->num_rows == 0) {
    echo "❌ Invalid call!"; exit;
}
 
$data = $call->fetch_assoc();
$user_id = $_SESSION['user_id'];
 
$isDoctor  = ($user_id == $data['doctor_id']);
$isPatient = ($user_id == $data['patient_id']);
 
if (!$isDoctor && !$isPatient) {
    echo "❌ Unauthorized access!"; exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Consultation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #1a1a2e;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        h2 { margin-bottom: 10px; font-size: 1.4rem; }
        #status {
            background: #0f3460;
            border-radius: 8px;
            padding: 8px 18px;
            margin-bottom: 16px;
            font-size: 0.95rem;
            min-width: 260px;
            text-align: center;
        }
        .video-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .video-box {
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        video {
            display: block;
            width: 320px;
            height: 240px;
            background: #000;
            border-radius: 10px;
        }
        .video-label {
            position: absolute;
            bottom: 6px;
            left: 10px;
            font-size: 0.8rem;
            background: rgba(0,0,0,0.55);
            padding: 2px 8px;
            border-radius: 4px;
        }
        #endBtn {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 12px 32px;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
        }
        #endBtn:hover { background: #c0392b; }
    </style>
</head>
<body>
 
<h2>🎥 Video Consultation</h2>
<div id="status">⏳ Starting camera...</div>
 
<div class="video-container">
    <div class="video-box">
        <video id="myVideo" autoplay muted playsinline></video>
        <span class="video-label">You (<?php echo $isDoctor ? 'Doctor' : 'Patient'; ?>)</span>
    </div>
    <div class="video-box">
        <video id="remoteVideo" autoplay playsinline></video>
        <span class="video-label">Remote</span>
    </div>
</div>
 
<!-- Camera switcher -->
<div style="margin-bottom:14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
    <label style="font-size:0.9rem;">📷 Camera:</label>
    <select id="cameraSelect" onchange="switchCamera()" style="padding:7px 12px; border-radius:6px; border:none; font-size:0.9rem; background:#0f3460; color:#fff; cursor:pointer;">
        <option value="">Loading cameras...</option>
    </select>
</div>
 
<div style="display:flex; gap:12px; justify-content:center;">
    <button id="endBtn" onclick="endCall()">📵 End Call</button>
    <button id="retryBtn" onclick="manualRetry()" style="display:none; background:#f39c12; color:#fff; border:none; padding:12px 32px; font-size:1rem; border-radius:8px; cursor:pointer;">🔄 Retry</button>
</div>
 
<!-- Use latest stable PeerJS from CDN -->
<script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>
 
<script>
const myVideo     = document.getElementById("myVideo");
const remoteVideo = document.getElementById("remoteVideo");
const statusBox   = document.getElementById("status");
 
const callId      = "<?php echo $call_id; ?>";
const isDoctor    = <?php echo $isDoctor ? 'true' : 'false'; ?>;
 
const myPeerId    = (isDoctor ? "doc_" : "pat_") + callId;
const otherPeerId = (isDoctor ? "pat_" : "doc_") + callId;
 
let peer, localStream, activeCall;
let retryCount = 0;
let callConnected = false;
const MAX_RETRIES = 20; // try for ~40 seconds
 
function setStatus(msg) {
    statusBox.textContent = msg;
    console.log(msg);
}
 
// ── 1. Get camera/mic ──────────────────────────────────────────
 
// FIRST: warn if not HTTPS (camera won't work on plain HTTP except localhost)
if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    setStatus("❌ Camera blocked — site must use HTTPS!");
    alert("⚠️ Camera Error!\n\nYour site is running on HTTP, but cameras only work on HTTPS.\n\nFix: Open your site using https:// instead of http://\n\nIf you're on localhost, it will work without HTTPS.");
    // stop here — no point continuing
} else if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    setStatus("❌ Camera API not supported in this browser.");
    alert("Your browser does not support camera access.\nPlease use Chrome or Firefox.");
} else {
    startCamera();
}
 
let currentDeviceId = null;
 
function startCamera(deviceId = null) {
    setStatus("📷 Requesting camera access...");
 
    const videoConstraints = deviceId
        ? { deviceId: { exact: deviceId } }
        : true;
 
    navigator.mediaDevices.getUserMedia({ video: videoConstraints, audio: true })
        .then(stream => {
            // Stop old stream tracks if switching camera
            if (localStream) {
                localStream.getTracks().forEach(t => t.stop());
            }
            localStream = stream;
            myVideo.srcObject = stream;
 
            // Save current device id
            const videoTrack = stream.getVideoTracks()[0];
            if (videoTrack) {
                currentDeviceId = videoTrack.getSettings().deviceId;
            }
 
            setStatus("📷 Camera ready. Connecting to server...");
 
            // Load camera list into dropdown
            loadCameraList();
 
            // Only init peer on first start, not on camera switch
            if (!peer) {
                initPeer();
            } else if (callConnected && activeCall) {
                // If already in a call, replace the video track live
                const sender = activeCall.peerConnection
                    ?.getSenders()
                    ?.find(s => s.track && s.track.kind === 'video');
                if (sender) {
                    sender.replaceTrack(videoTrack)
                        .then(() => setStatus("✅ Camera switched!"))
                        .catch(e => setStatus("⚠️ Could not switch live: " + e.message));
                }
            }
        })
        .catch(err => {
            let msg = "";
            switch(err.name) {
                case "NotAllowedError":
                case "PermissionDeniedError":
                    msg = "❌ Camera permission DENIED.\n\nFix:\n1. Click the camera icon 🎥 in your browser address bar\n2. Select 'Allow'\n3. Refresh the page";
                    break;
                case "NotFoundError":
                case "DevicesNotFoundError":
                    msg = "❌ No camera/mic found on this device.\n\nMake sure your camera is plugged in and not used by another app.";
                    break;
                case "NotReadableError":
                case "TrackStartError":
                    msg = "❌ Camera is already in use by another app.\n\nClose Zoom, Teams, or any other app using the camera, then refresh.";
                    break;
                case "OverconstrainedError":
                    msg = "❌ Camera does not meet requirements.\n\nTry refreshing the page.";
                    break;
                case "SecurityError":
                    msg = "❌ Camera blocked due to security policy.\n\nMake sure your site runs on HTTPS.";
                    break;
                default:
                    msg = "❌ Camera error: " + err.name + "\n" + err.message;
            }
            setStatus("❌ Camera failed: " + err.name);
            alert(msg);
            console.error("Camera error:", err);
        });
}
 
// Load all available cameras into the dropdown
function loadCameraList() {
    navigator.mediaDevices.enumerateDevices()
        .then(devices => {
            const cameras = devices.filter(d => d.kind === 'videoinput');
            const select = document.getElementById('cameraSelect');
            select.innerHTML = '';
 
            if (cameras.length === 0) {
                select.innerHTML = '<option>No cameras found</option>';
                return;
            }
 
            cameras.forEach((cam, i) => {
                const opt = document.createElement('option');
                opt.value = cam.deviceId;
                // Label: use real label if available, else generic name
                opt.textContent = cam.label || ('Camera ' + (i + 1));
                // Mark current active camera
                if (cam.deviceId === currentDeviceId) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        });
}
 
// Called when user picks a different camera from dropdown
function switchCamera() {
    const select = document.getElementById('cameraSelect');
    const selectedId = select.value;
    if (selectedId && selectedId !== currentDeviceId) {
        setStatus("🔄 Switching camera...");
        startCamera(selectedId);
    }
}
 
// ── 2. Create Peer ─────────────────────────────────────────────
function initPeer() {
    // Default PeerJS cloud + Google STUN + free TURN for firewall traversal
    peer = new Peer(myPeerId, {
        debug: 2,
        config: {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                {
                    urls: 'turn:openrelay.metered.ca:80',
                    username: 'openrelayproject',
                    credential: 'openrelayproject'
                },
                {
                    urls: 'turn:openrelay.metered.ca:443',
                    username: 'openrelayproject',
                    credential: 'openrelayproject'
                }
            ]
        }
    });
 
    peer.on('open', id => {
        setStatus("✅ Connected! " + (isDoctor ? "Calling patient..." : "Waiting for doctor..."));
        // BOTH sides try to call each other — whoever connects first wins
        setTimeout(tryCall, 3000);
    });
 
    // BOTH sides listen for incoming calls
    peer.on('call', incomingCall => {
        // If already connected, ignore duplicate calls
        if (callConnected) {
            incomingCall.close();
            return;
        }
        setStatus("📞 Answering call...");
        activeCall = incomingCall;
        incomingCall.answer(localStream);
        incomingCall.on('stream', remoteStream => {
            if (remoteStream && remoteStream.getTracks().length > 0) {
                remoteVideo.srcObject = remoteStream;
                callConnected = true;
                document.getElementById('retryBtn').style.display = 'none';
                setStatus("🟢 Call connected!");
            }
        });
        incomingCall.on('close', () => {
            callConnected = false;
            setStatus("📵 Call ended.");
        });
        incomingCall.on('error', e => setStatus("⚠️ Call error: " + e));
    });
 
    peer.on('error', err => {
        if (err.type === 'peer-unavailable') {
            retryCount++;
            if (retryCount <= MAX_RETRIES) {
                setStatus(`⏳ Other person not ready yet... retrying (${retryCount}/${MAX_RETRIES})`);
                setTimeout(tryCall, 2000);
            } else {
                setStatus("❌ Could not connect. Click Retry or refresh.");
                document.getElementById('retryBtn').style.display = 'inline-block';
            }
        } else {
            setStatus("❌ Error: " + err.type + " — " + err.message);
            console.error(err);
        }
    });
 
    peer.on('disconnected', () => {
        setStatus("⚠️ Disconnected from server. Reconnecting...");
        peer.reconnect();
    });
}
 
// ── 3. Both sides try to call each other ──────────────────────
function tryCall() {
    if (!localStream || !peer || callConnected) return;
    setStatus("📞 Trying to connect...");
 
    try {
        let outCall = peer.call(otherPeerId, localStream);
        if (!outCall) return;
 
        outCall.on('stream', remoteStream => {
            if (callConnected) return; // already connected via incoming
            if (remoteStream && remoteStream.getTracks().length > 0) {
                activeCall = outCall;
                remoteVideo.srcObject = remoteStream;
                callConnected = true;
                retryCount = 0;
                document.getElementById('retryBtn').style.display = 'none';
                setStatus("🟢 Call connected!");
            }
        });
        outCall.on('close', () => {
            callConnected = false;
            setStatus("📵 Call ended.");
        });
        outCall.on('error', e => setStatus("⚠️ " + e));
    } catch(e) {
        setStatus("⚠️ Call attempt failed, retrying...");
    }
}
 
// ── Manual retry button ────────────────────────────────────────
function manualRetry() {
    retryCount = 0;
    callConnected = false;
    document.getElementById('retryBtn').style.display = 'none';
    setStatus("🔄 Retrying connection...");
    tryCall();
}
 
// ── 4. End call ────────────────────────────────────────────────
function endCall() {
    if (activeCall) activeCall.close();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (peer) peer.destroy();
    myVideo.srcObject = null;
    remoteVideo.srcObject = null;
    setStatus("📵 You ended the call.");
 
    // Update DB via fetch (optional, won't break if it fails)
    fetch('update_call_status.php?call_id=<?php echo $call_id; ?>&status=ended')
        .catch(() => {});
 
    setTimeout(() => {
        window.location.href = "dashboard.php";
    }, 2000);
}
</script>
 
</body>
</html>