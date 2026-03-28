<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
    <title>Smart Health Assistant</title>

    <style>
        body { font-family: Arial; background: #f4f4f4; }
        #chatbox {
            width: 450px;
            margin: 30px auto;
            background: white;
            padding: 15px;
            border-radius: 10px;
        }
        #messages {
            height: 380px;
            overflow-y: scroll;
            border: 1px solid #ccc;
            padding: 10px;
        }
        .user { text-align: right; color: blue; }
        .bot { text-align: left; color: green; }
        input { width: 75%; padding: 10px; }
        button { padding: 10px; }
    </style>
</head>

<body>

<div id="chatbox">
    <h3>🚑 Smart Health Assistant</h3>

    <div id="messages"></div>

    <input type="text" id="userInput" placeholder="Describe symptoms...">
    <button onclick="sendMessage()">Send</button>
</div>

<script>
function sendMessage() {

    let input = document.getElementById("userInput").value;
    let messages = document.getElementById("messages");

    if (input.trim() === "") return;

    messages.innerHTML += "<p class='user'>" + input + "</p>";

    let text = input.toLowerCase();
    let response = "";

    // 🚨 EMERGENCY CASES

    if (text.includes("not breathing") || text.includes("no breathing")) {
        response = "🚨 EMERGENCY: Person is not breathing.\n\n" +
        "👉 Start CPR:\n" +
        "• 30 chest compressions (100–120/min)\n" +
        "• 2 rescue breaths\n" +
        "• Repeat continuously\n\n" +
        "📞 Call emergency services immediately!";
    }

    else if (text.includes("asthma") || text.includes("breathing problem")) {
        response = "😮‍💨 Asthma attack detected.\n\n" +
        "👉 What to do:\n" +
        "• Use inhaler immediately (Salbutamol)\n" +
        "• Sit upright and stay calm\n" +
        "• Take slow deep breaths\n\n" +
        "💊 Medicine: Asthalin inhaler (if prescribed)\n" +
        "⚠️ If severe → go to hospital immediately";
    }

    else if (text.includes("bleeding") || text.includes("cut")) {
        response = "🩸 Bleeding injury.\n\n" +
        "👉 Steps:\n" +
        "• Apply firm pressure with clean cloth\n" +
        "• Elevate the injured part\n" +
        "• Do not remove cloth if soaked\n\n" +
        "💊 Medicine: Use antiseptic (Betadine), pain relief (Paracetamol)\n" +
        "⚠️ If heavy bleeding → emergency care";
    }

    else if (text.includes("fracture") || text.includes("broken") || text.includes("leg")) {
        response = "🦴 Possible fracture detected.\n\n" +
        "👉 Steps:\n" +
        "• Do NOT move the limb\n" +
        "• Immobilize using support/splint\n" +
        "• Apply ice (wrapped cloth)\n\n" +
        "💊 Medicine: Paracetamol for pain\n" +
        "⚠️ Visit hospital for X-ray immediately";
    }

    else if (text.includes("fever")) {
        response = "🤒 Fever detected.\n\n" +
        "👉 Rest and drink fluids\n\n" +
        "💊 Medicine: Paracetamol (500mg)\n" +
        "⚠️ If >3 days → consult doctor";
    }

    else if (text.includes("headache")) {
        response = "🤕 Headache.\n\n" +
        "👉 Rest and hydrate\n\n" +
        "💊 Medicine: Paracetamol or Ibuprofen\n";
    }

    else if (text.includes("cough")) {
        response = "😷 Cough detected.\n\n" +
        "👉 Drink warm fluids\n\n" +
        "💊 Medicine: Cough syrup (like Benadryl)\n" +
        "⚠️ If persistent → consult doctor";
    }

    else if (text.includes("stomach pain")) {
        response = "🤢 Stomach pain.\n\n" +
        "👉 Eat light food, stay hydrated\n\n" +
        "💊 Medicine: Antacid (Digene)\n";
    }

    else {
        response = "⚠️ I could not fully understand.\n\n" +
        "👉 Please describe symptoms clearly or consult a doctor.";
    }

    messages.innerHTML += "<p class='bot'>" + response + "</p>";

    document.getElementById("userInput").value = "";
    messages.scrollTop = messages.scrollHeight;
}
</script>

</body>
</html>