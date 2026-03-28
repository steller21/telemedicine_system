<?php
session_start();
require_once("config/db.php");

// 🔥 CHECK LOGIN FIRST
if (!isset($_SESSION['user_id'])) {
    echo "❌ Please login first!";
    exit;
}

// 🔥 CHECK CALL ID
if (!isset($_GET['call_id'])) {
    echo "❌ No call ID found!";
    exit;
}

$call_id = $_GET['call_id'];

// GET CALL DATA
$call = $conn->query("SELECT * FROM video_calls WHERE id='$call_id'");

if (!$call || $call->num_rows == 0) {
    echo "❌ Invalid call!";
    exit;
}

$data = $call->fetch_assoc();

$user_id = $_SESSION['user_id'];

// CHECK ROLE
$isDoctor = ($user_id == $data['doctor_id']);
$isPatient = ($user_id == $data['patient_id']);

if (!$isDoctor && !$isPatient) {
    echo "❌ Unauthorized access!";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Video Call</title>
</head>
<body>

<h2>Video Consultation 🎥</h2>

<video id="myVideo" autoplay muted width="300" style="border:1px solid black;"></video>
<video id="remoteVideo" autoplay width="300" style="border:1px solid black;"></video>

<script src="https://unpkg.com/peerjs@1.3.1/dist/peerjs.min.js"></script>

<script>
let myVideo = document.getElementById("myVideo");
let remoteVideo = document.getElementById("remoteVideo");

let callId = "<?php echo $call_id; ?>";
let isDoctor = <?php echo $isDoctor ? 'true' : 'false'; ?>;

// UNIQUE IDS
let myPeerId = isDoctor ? "doctor_" + callId : "patient_" + callId;
let otherPeerId = isDoctor ? "patient_" + callId : "doctor_" + callId;

// PEER CONNECTION
let peer = new Peer(myPeerId, {
    host: '0.peerjs.com',
    port: 443,
    secure: true
});

// GET CAMERA
navigator.mediaDevices.enumerateDevices().then(devices => {

    let videoDevices = devices.filter(d => d.kind === 'videoinput');

    let selectedDevice = videoDevices.find(d =>
        d.label.toLowerCase().includes('integrated') ||
        d.label.toLowerCase().includes('webcam')
    ) || videoDevices[0];

    return navigator.mediaDevices.getUserMedia({
        video: { deviceId: { exact: selectedDevice.deviceId } },
        audio: true
    });

}).then(stream => {

    myVideo.srcObject = stream;

    // ANSWER CALL
    peer.on('call', call => {
        call.answer(stream);

        call.on('stream', remoteStream => {
            remoteVideo.srcObject = remoteStream;
        });
    });

    // DOCTOR STARTS CALL
    peer.on('open', () => {

        if (isDoctor) {
            setTimeout(() => {

                let call = peer.call(otherPeerId, stream);

                call.on('stream', remoteStream => {
                    remoteVideo.srcObject = remoteStream;
                });

            }, 3000);
        }
    });

}).catch(err => {
    alert("Camera Error: " + err);
});
</script>

</body>
</html>