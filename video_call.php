<?php
session_start();
require_once("config/db.php");

if (!isset($_SESSION['user_id'])) { echo "❌ Please login first!"; exit; }
if (!isset($_GET['call_id'])) { echo "❌ No call ID!"; exit; }

$call_id = intval($_GET['call_id']);
$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");
$data = $call->fetch_assoc();

$user_id = $_SESSION['user_id'];
$isDoctor  = ($user_id == $data['doctor_id']);
$isPatient = ($user_id == $data['patient_id']);

if (!$isDoctor && !$isPatient) { echo "❌ Unauthorized"; exit; }

$conn->query("UPDATE video_calls SET status='active' WHERE id='$call_id'");
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
    </style>
</head>
<body>

<div class="container">
    <h2>🎥 Video Consultation</h2>
    <p id="status">Starting...</p>

    <div class="video-container">
        <div class="video-box">
            <video id="myVideo" autoplay muted playsinline></video>
            <span class="video-label">📱 You</span>
        </div>
        <div class="video-box">
            <video id="remoteVideo" autoplay playsinline></video>
            <span class="video-label">👨‍⚕️ <?php echo $isDoctor ? 'Patient' : 'Doctor'; ?></span>
        </div>
    </div>

    <div class="controls">
        <div class="control-group">
            <label>📷 Camera:</label>
            <select id="cameraSelect" onchange="switchCamera()"><option>Loading cameras...</option></select>
        </div>
    </div>

    <div style="text-align: center;">
        <button class="btn-end" onclick="endCall()">📵 End Call</button>
    </div>
</div>

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

        cameras.forEach((cam, index) => {
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
        setTimeout(callOther, 2000);
    });

    peer.on('disconnected', () => {
        statusBox.innerText = "⚠️ Server disconnected";
    });

    peer.on('error', (err) => {
        statusBox.innerText = "❌ Error: " + err.type;
        console.error("Peer error:", err);
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
            statusBox.innerText = "Call ended";
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
        statusBox.innerText = "Call ended";
    });
}

// ================= END =================
function endCall() {
    statusBox.innerText = "📵 Ending call...";
    
    if (activeCall) activeCall.close();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (peer) peer.destroy();
    
    myVideo.srcObject = null;
    remoteVideo.srcObject = null;
    
    setTimeout(() => {
        window.location.href = "dashboard.php";
    }, 1500);
}

// START
startCamera();
</script>

</body>
</html>