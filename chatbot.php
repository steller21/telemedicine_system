<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Health Assistant - Telemedicine India</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 10px; }
        .container { width: 100%; max-width: 600px; }
        #chatbox { background: white; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-direction: column; height: 85vh; max-height: 720px; }
        .chat-header { padding: 15px 18px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .header-icon { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; }
        .header-content { color: white; }
        .header-title { font-size: 16px; font-weight: 600; }
        .header-sub { font-size: 11px; opacity: 0.9; }
        .emergency-bar { display: flex; gap: 6px; padding: 10px 14px; background: #FCEBEB; border-bottom: 1px solid #F7C1C1; flex-wrap: wrap; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: #791F1F; }
        .em-btn { background: #fff; border: 1px solid #F09595; border-radius: 16px; padding: 3px 10px; color: #791F1F; cursor: pointer; transition: background 0.2s; }
        .em-btn:hover { background: #FCEBEB; }
        #messages { flex: 1; overflow-y: auto; padding: 20px 14px; background: #f8f9fa; display: flex; flex-direction: column; gap: 12px; }
        .msg { display: flex; gap: 8px; max-width: 90%; }
        .msg.user { align-self: flex-end; flex-direction: row-reverse; }
        .msg-content { padding: 9px 13px; border-radius: 10px; font-size: 14px; line-height: 1.55; }
        .msg.bot .msg-content { background: #e9ecef; color: #333; border-radius: 4px 10px 10px 10px; }
        .msg.user .msg-content { background: #667eea; color: white; border-radius: 10px 4px 10px 10px; }
        .msg-content.emergency { background: #ff4757; color: white; font-weight: bold; }
        .msg-content.warning { background: #ffa502; color: white; }
        .input-area { display: flex; gap: 8px; padding: 12px 14px; border-top: 1px solid #e5e7eb; background: white; flex-shrink: 0; }
        input { flex: 1; font-size: 14px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb; color: #111827; outline: none; }
        input:focus { border-color: #667eea; background: white; }
        button { padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 600; }
        button:hover { background: #555bd8; }
        @media (max-width: 600px) { #chatbox { height: 100vh; max-height: none; border-radius: 0; } }
    </style>
<link rel="stylesheet" href="css/ui-refresh.css">
<script src="js/page-transition.js"></script>
<style>
    .assistant-tools, .triage-options, .triage-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .triage-btn { border: none; border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, opacity 0.2s ease; }
    .triage-btn:hover { transform: translateY(-1px); opacity: 0.96; }
    .triage-start { background: #0eb8a0; color: #062821; }
    .triage-lite { background: #eef2ff; color: #334155; border: 1px solid #cbd5e1; }
    .triage-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .triage-card strong { display: block; font-size: 15px; margin-bottom: 6px; }
    .triage-note { font-size: 12px; line-height: 1.5; color: #5f6b7a; margin-bottom: 10px; }
    .triage-question { font-size: 13px; font-weight: 600; color: #1f2937; }
    .triage-result { margin-top: 4px; padding: 12px; border-radius: 12px; background: #ffffff; color: #1f2937; border: 1px solid #dbe4f0; }
    .triage-result.emergency { background: #fff1f2; border-color: #fecdd3; }
    .triage-result.urgent { background: #fff7ed; border-color: #fed7aa; }
    .triage-result.routine { background: #eff6ff; border-color: #bfdbfe; }
    .triage-result.selfcare { background: #ecfdf5; border-color: #a7f3d0; }
    .triage-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 8px; }
    .triage-result.emergency .triage-badge { background: #fecdd3; color: #9f1239; }
    .triage-result.urgent .triage-badge { background: #fed7aa; color: #9a3412; }
    .triage-result.routine .triage-badge { background: #bfdbfe; color: #1d4ed8; }
    .triage-result.selfcare .triage-badge { background: #bbf7d0; color: #047857; }
    .triage-result ul { margin: 10px 0 0 18px; padding: 0; }
    .triage-result li { margin-bottom: 6px; }
</style>
</head>

<body>

<div class="container">
    <div id="chatbox">
        <div class="chat-header">
            <div class="header-icon">🚑</div>
            <div class="header-content">
                <div class="header-title">Smart Health Assistant</div>
                <div class="header-sub">India medical guidance & emergency help</div>
            </div>
        </div>

        <div class="emergency-bar">
            <span>🚨 Emergency:</span>
            <button class="em-btn" onclick="callEmergency('108')">🚑 108 Ambulance</button>
            <button class="em-btn" onclick="callEmergency('112')">📞 112 All</button>
            <button class="em-btn" onclick="callEmergency('100')">👮 100 Police</button>
            <button class="em-btn" onclick="callEmergency('101')">🚒 101 Fire</button>
        </div>

        <div id="messages" class="scrollbar"></div>

        <div class="input-area">
            <input type="text" id="userInput" placeholder="Type symptom: bleeding, fever, asthma, fracture..." autocomplete="off" onkeypress="if(event.key==='Enter')sendMessage()">
            <button onclick="sendMessage()">Send</button>
        </div>
    </div>
</div>

<script>
// Comprehensive merged knowledge base
const KB = [
  { keys: /bleed|bleeding|blood coming|khoon|haemorrhage|wound bleed|cut bleed|leg bleed|arm bleed|head bleed|nose bleed|blood.*not stop/i, title: "Bleeding", emergency: true, steps: ["Call 108 immediately for heavy bleeding.", "Place clean cloth on wound and apply FIRM pressure for 10-15 minutes.", "If blood soaks cloth, add another on top — do NOT remove first cloth.", "Elevate injured area above heart if possible.", "For limb bleeding, apply tourniquet above the wound if heavy.", "Keep person calm and lying down.", "Go to nearest hospital if heavy or ongoing bleeding."], note: "For deep cuts or wounds with objects embedded — go immediately to hospital. Tetanus injection is free at government hospitals." },
  
  { keys: /fracture|broken bone|haddi toot|leg.*broken|arm.*broken|broke.*leg|broke.*arm|fell.*broken|broken.*fell|hairline|bone crack|compound|open fracture|bone break/i, title: "Fracture", emergency: true, steps: ["DO NOT move the injured limb.", "Immobilize using support or splint immediately.", "Apply ice wrapped in cloth for 15-20 minutes.", "Elevate the limb if possible.", "For spine/neck injury: do NOT move — wait for 108 ambulance.", "Go to nearest hospital for X-ray and proper treatment.", "If open fracture (bone protruding) — call 108 immediately."], note: "X-rays are free at government district hospitals under health schemes. Never massage a suspected fracture." },
  
  { keys: /heart attack|dil ka daura|chest pain|chest.*tight|chest.*pressure|cardiac arrest|left arm.*pain|jaw.*pain|myocardial|dil.*dard/i, title: "Heart Attack", emergency: true, steps: ["Call 108 ambulance IMMEDIATELY.", "Sit/lie the person down in a comfortable position.", "If available: chew 1 aspirin (300-500mg).", "Loosen tight clothing.", "Keep person calm.", "If unconscious and not breathing: start CPR.", "Note the time symptoms started."], note: "Heart attacks are rising in young Indians. Every minute counts. Call 108 immediately." },
  
  { keys: /cpr|cardiac arrest|not breathing|stopped breathing|no pulse|resuscit|no heartbeat|unconscious.*breathing/i, title: "CPR - Cardiac First Aid", emergency: true, steps: ["Call 108 immediately.", "Place person on flat hard surface.", "Tilt head back, lift chin to open airway.", "Check for breathing — max 10 seconds.", "Place heel of hand on center of chest.", "Push hard and fast: 5-6 cm deep, 100-120 compressions per minute.", "Give 2 rescue breaths after every 30 compressions (if trained).", "Continue until ambulance arrives or person recovers.", "AED machines: use immediately if available."], note: "Hands-only CPR (without breaths) is also effective. Free CPR training available at Indian Red Cross Society branches." },
  
  { keys: /breathing tips|breathing help|breathing exercise|breathing exercises|saans tips|saans lene ki tips|how to breathe better/i, title: "Breathing Support Tips", emergency: false, steps: ["Sit upright and relax your shoulders.", "Breathe in slowly through your nose for 4 seconds.", "Breathe out gently through pursed lips for 6 seconds.", "Repeat for 5 to 10 breaths without forcing the chest.", "Sip water if your throat feels dry.", "Move to fresh air and loosen tight clothing.", "If you develop wheezing, blue lips, chest pain, or severe breathing trouble, call 108 immediately."], note: "These are general breathing tips for mild discomfort or calming yourself. They are not a substitute for asthma treatment or emergency care." },
  { keys: /asthma|saans.*nahi|breathless|wheezing|inhaler|bronchial|breathing.*difficult|saans phool|cant breathe|can't breathe|dyspnea/i, title: "Asthma Attack", emergency: true, steps: ["Sit upright immediately.", "Use blue/rescue inhaler (Salbutamol/Asthalin): 1-2 puffs, breathe in deeply.", "Wait 15 minutes for improvement.", "If no improvement: use inhaler again (1-2 puffs).", "Wait another 15 minutes.", "If still no relief or lips turning blue: call 108.", "Go to nearest PHC or hospital for nebulization if severe."], note: "Asthalin is available free at government hospitals and ~₹50 at Jan Aushadhi stores. Always carry your inhaler." },
  
  { keys: /fever|bukhar|high temperature|pyrexia|viral fever|body heat|temperature/i, title: "Fever", emergency: false, steps: ["Rest completely.", "Drink plenty of fluids: water, juice, warm tea.", "Take cool baths or use damp cloth on forehead.", "Wear light clothing.", "Use temperature thermometer to monitor.", "If fever > 3 days, > 103°F, or with rash/stiff neck: see doctor immediately."], note: "Persistent fever in India may be malaria, dengue, or typhoid. Get blood test at PHC if fever > 3 days — free or low cost." },
  
  { keys: /dengue|platelet|aedes|denge|dengue fever/i, title: "Dengue", emergency: true, steps: ["Rest completely and stay hydrated: ORS, coconut water, juice.", "Monitor platelet count — get daily blood test if hospitalized.", "Watch for severe abdominal pain, vomiting, bleeding from nose/gums, blood in urine/stool.", "Use mosquito nets and repellents.", "Do NOT take Aspirin, Ibuprofen, Combiflam, or Diclofenac — dangerous bleeding risk."], note: "Dengue NS1 test and CBC available free at government hospitals. Report to municipality for mosquito control." },
  
  { keys: /typhoid|motijhara|enteric|widal|typhoid fever/i, title: "Typhoid", emergency: false, steps: ["Get Widal test or blood culture at nearest lab.", "Rest completely.", "Eat soft, easily digestible food: rice, yogurt rice, boiled rice.", "Drink only boiled or filtered water.", "Maintain strict hand hygiene.", "Complete full antibiotic course — do NOT stop midway.", "Go to hospital if confusion, severe pain, or persistent fever."], note: "Typhoid vaccination (Typbar-TCV) available — recommended for children and travelers, free under government immunization." },
  
  { keys: /burn|jalna|scald|hot.*water|fire.*skin|chemical burn|acid attack|jalana/i, title: "Burns", emergency: true, steps: ["Call 108 for severe burns.", "Do NOT remove stuck clothing.", "Cool burn with cool water (not ice) for 10-20 minutes.", "Stop if person shivers (risk of hypothermia).", "Cover with clean, dry cloth.", "Do NOT apply: ice, toothpaste, ghee, turmeric, egg white.", "Do NOT burst blisters.", "Go to government hospital for: burns larger than palm, face/hands/genitals, or deep/charred burns."], note: "Government hospitals have free burn units. Firecracker and stove burns are common. For acid attacks — call 112 and flush continuously with water." },
  
  { keys: /snake|saanp|viper|cobra|krait|snake bite|sanp ka katna|banded krait|russell|snake.*bite/i, title: "Snake Bite", emergency: true, steps: ["Stay very calm to slow venom spread.", "Immobilize bitten limb — keep it below heart level.", "Remove rings, watches, tight clothing near bite.", "Do NOT cut, suck venom, apply tourniquet, or use herbs.", "Do NOT give food, water, or alcohol.", "Mark swelling edge with pen and note time.", "Rush to nearest government district hospital IMMEDIATELY.", "Anti-Snake Venom (ASV) is FREE at all government hospitals."], note: "India has ~50,000 snake bite deaths yearly. Big 4 venomous snakes: Cobra, Krait, Russell Viper, Saw-scaled Viper. Reach hospital within 1-2 hours." },
  
  { keys: /heat stroke|loo|sun stroke|sunstroke|loo lagna|heat exhaustion|garmi/i, title: "Heat Stroke", emergency: true, steps: ["Move to cool/shaded area or AC room immediately.", "Remove excess clothing.", "Cool body rapidly: wet cloths on neck, armpits, groin; fan them.", "Give ORS or water if conscious.", "Do NOT give paracetamol — doesn't help in heat stroke.", "Call 108 if confused, unconscious, or stopping sweating.", "This is a medical emergency — death can occur within hours."], note: "Hot winds (loo) common in North India May-June. Drink 3-4 liters water daily in summer. Avoid 12pm-4pm. Free ORS at PHC." },
  
  { keys: /diarrh|loose motion|loose stool|dast|ulti dast|vomiting.*loose|dehydrat|stomach.*running|watery stool|diarrhoea/i, title: "Diarrhea", emergency: false, steps: ["Start ORS immediately — mix 1 sachet in 1 liter clean water.", "Eat bland foods: rice, toast, bananas, boiled potatoes.", "Avoid dairy, fatty, spicy foods.", "Wash hands after bathroom.", "Give Zinc tablets for children under 5 for 14 days.", "Seek hospital if: blood in stool, vomiting everything, sunken eyes, or no urination 6+ hours."], note: "ORS + Zinc is WHO/UNICEF recommended. ORS sachets FREE at government health centers and from ASHA workers." },
  
  { keys: /chok|gala.*phansa|throat.*stuck|airway.*block|swallow.*wrong|food.*stuck|gagging|choking/i, title: "Choking", emergency: true, steps: ["Stay calm.", "If person can cough: encourage forceful coughing.", "If cannot cough/breathe — Heimlich maneuver: stand behind, fist above navel, press inward & upward quickly, repeat 5 times.", "Alternate: 5 back blows + 5 abdominal thrusts.", "For infants under 1 year: 5 back blows + 5 chest thrusts (NEVER abdominal).", "Call 108 if choking doesn't clear in 1 minute."], note: "Always seek medical evaluation after choking even if resolved. Keep small objects away from children." },
  
  { keys: /seizure|epilepsy|fit|dora|mirgi|convuls|mitti.*daura|shaking|fits/i, title: "Seizure", emergency: true, steps: ["Call 108 immediately.", "Do NOT restrain person.", "Move dangerous objects away.", "Place pillow under head.", "Turn person on side (recovery position).", "Stay with person — do NOT put objects in mouth.", "Give rescue medication if prescribed.", "Note duration of seizure.", "Call 108 if seizure > 5 minutes or back-to-back seizures."], note: "After seizure, stay with person until conscious. Never leave near water, fire, or height. Epilepsy treatment free at government hospitals." },
  
  { keys: /vomit|nausea|throwing up|ulti aana|matalic/i, title: "Nausea & Vomiting", emergency: false, steps: ["Rest in bed, sit upright.", "Sip water slowly — avoid large gulps.", "Eat light foods when ready: crackers, rice, toast.", "Avoid strong smells.", "Keep head elevated.", "Try ginger tea or lemon water."], note: "If vomiting > 2 hours, vomiting blood, or severe dehydration — seek hospital care." },
  
  { keys: /sore throat|throat pain|gala dard|pain.*swallow|swallowing.*pain|painful.*swallow|throat.*sore/i, title: "Sore Throat", emergency: false, steps: ["Gargle warm salt water 4-6 times daily.", "Drink warm tea with honey.", "Suck on lozenges.", "Avoid hot/cold foods.", "Rest voice.", "Stay hydrated."], note: "Natural remedies: honey-lemon tea, ginger tea, apple cider vinegar gargle. See doctor if fever > 3 days, difficulty swallowing, or rash." },
  
  { keys: /skin rash|itching|itch|rash|khujli|skin.*red|laal nishan|skin.*irritat/i, title: "Skin Rash", emergency: false, steps: ["Do not scratch — prevents infection.", "Keep skin clean and dry.", "Wear loose clothing.", "Apply cool compress.", "Use fragrance-free moisturizer.", "Wash with mild soap."], note: "See doctor if: rash spreads rapidly, signs of infection (pus), facial/respiratory rash, or rash with fever/joint pain." },
  
  { keys: /wound|cut|minor bleeding|ghaav|chot|chhil/i, title: "Wound or Cut", emergency: false, steps: ["Wash hands with soap.", "Apply pressure to stop bleeding (5-10 min).", "Rinse wound with clean water.", "Wash with soap.", "Remove debris with sterilized tweezers.", "Apply Betadine or Dettol solution.", "Apply antibiotic ointment and sterile bandage.", "Change bandage daily.", "Keep dry.", "Watch for infection signs."], note: "For deep cuts needing stitches, heavy bleeding, or infection signs — see doctor." },
  
  { keys: /insect bite|mosquito|bee sting|saanp ka gad|bite|mach.dar|sting/i, title: "Insect Bite or Sting", emergency: false, steps: ["Remove stinger if present (use card to scrape off).", "Wash with soap and cool water.", "Apply ice pack (15 min).", "Elevate if swollen.", "Do NOT scratch."], note: "Emergency signs (allergic reaction): difficulty breathing, facial swelling, severe itching/hives → use EpiPen if available, call 108." },
  
  { keys: /back pain|back strain|back.*dard|spine.*pain|lumber.*pain|kee dard/i, title: "Back Pain", emergency: false, steps: ["Rest — but not complete bed rest.", "Apply heat pad (15-20 min) after 2 days.", "Use proper posture.", "Sleep on firm mattress.", "Do gentle stretching.", "Avoid heavy lifting."], note: "See doctor if: pain > 2 weeks, numbness/tingling in legs, difficulty with bowel/bladder, or after trauma." },
  
  { keys: /migraine|sar.*dard.*ek taraf|sar dard|throbbing|headache.*migraine/i, title: "Migraine", emergency: false, steps: ["Rest in quiet, dark room.", "Apply cold compress on head.", "Drink water.", "Avoid screens/bright lights.", "Try to sleep."], note: "Prevention: identify triggers (stress, food, lack of sleep, caffeine). Regular sleep, exercise, hydration, stress management help." },
  
  { keys: /sleep|insomnia|can't sleep|nahi so raha|neend nahi aa rahi|sleepless/i, title: "Sleep Problems", emergency: false, steps: ["Maintain regular sleep schedule (same time daily).", "Keep bedroom dark, cool, quiet.", "Avoid screens 1 hour before bed.", "No caffeine after 3pm.", "Exercise during day — not before bed.", "Try relaxation: meditation, deep breathing.", "Warm milk or herbal tea before sleep."], note: "See doctor if: insomnia > 2 weeks, daytime drowsiness severe, or sleep apnea suspected." },
  
  { keys: /anxiety|panic|stress|dar.*laga|ghabra|tension/i, title: "Anxiety or Panic", emergency: false, steps: ["Deep breathing: 4 counts in, 4 counts out.", "Progressive muscle relaxation.", "Mindfulness of present moment.", "Exercise or walk.", "Warm bath.", "Talk to someone."], note: "Long-term: regular exercise (30 min, 5x/week), meditation/yoga, sleep schedule, limit caffeine/alcohol, connect with others." },
  
  { keys: /diabetes|blood sugar|hypoglycemia|glucose|shakar|shakara rog/i, title: "Diabetes", emergency: false, steps: ["Monitor blood sugar regularly.", "Take medicine on time (insulin/tablets).", "Eat balanced meals at fixed times.", "Exercise 30 min daily.", "Stay hydrated.", "Check feet daily for sores.", "For low blood sugar: drink juice/eat candy immediately."], note: "Normal blood sugar: 80-130 mg/dL fasting. Diet: complex carbs, high fiber, lean proteins, limit sugar/salt." },
  
  { keys: /blood pressure|hypertension|high bp|bp.*high|dabaav|dbaav/i, title: "High Blood Pressure", emergency: false, steps: ["Monitor BP regularly.", "Take medicine as prescribed.", "Reduce salt intake.", "Exercise 30 min daily.", "Maintain healthy weight.", "Manage stress.", "Limit alcohol.", "Get adequate sleep."], note: "Normal BP: <120/80 mmHg. DASH diet: low sodium, high potassium (bananas, spinach), whole grains, lean proteins, low-fat dairy." },
  
  { keys: /normal temperature|body temperature|healthy temperature|normal tapmaan|tapmaan/i, title: "Normal Body Temperature", emergency: false, steps: [], note: "Oral: 36.1-37.2°C (97-99°F) | Armpit: 36.5-37.5°C | Rectal: 37-38°C. Measure after 30 min rest. Avoid hot/cold drinks before measurement. Time of day, activity, hormones affect temperature." },
  
  { keys: /poison|overdose|toxic|swallow.*chemical|acid.*drink/i, title: "Poisoning", emergency: true, steps: ["Call 108 or Poison Control (1800-116-117) immediately.", "Identify what was taken and the amount.", "Do NOT induce vomiting unless instructed by a doctor.", "If on skin or in eyes: Rinse with plenty of water for 20 minutes.", "Keep the container/bottle to show medical staff."], note: "Poison Control (AIIMS Delhi): 1800-116-117. Act fast." },
  
  { keys: /allergic|anaphylaxis|swelling.*face|hives|rash.*breath|bee.*allergic/i, title: "Severe Allergic Reaction", emergency: true, steps: ["Call 108 immediately.", "Use an Epinephrine auto-injector (EpiPen) if available.", "Lie the person flat with legs raised.", "If breathing is difficult, sit them up.", "Stay with them until the ambulance arrives."], note: "Anaphylaxis is a life-threatening emergency. Do not wait to see if symptoms improve." },
  
  { keys: /cold|cough|sardi|khansi|runny.*nose|sneezing|congestion|blocked.*nose|gala dard|sardi|khansi|flu/i, title: "Cold & Cough", emergency: false, steps: ["Rest adequately (7-9 hours sleep).", "Stay hydrated: water, warm tea, soup.", "Gargle salt water for sore throat.", "Use saline nasal drops.", "Keep warm.", "Avoid smoking & passive smoke."], note: "Antibiotics NOT needed for viral cold. Avoid unnecessary antibiotics — India has antibiotic resistance problem. Natural remedies effective for mild symptoms." }
];

function findMatch(q) {
  q = q.toLowerCase();
  for (let entry of KB) {
    if (entry.keys.test(q)) return entry;
  }
  return null;
}

function formatResponse(data) {
  if (!data) return null;
  let html = '';
  if (data.emergency) html += '<span style="background:#FCEBEB;color:#791F1F;padding:3px 8px;border-radius:8px;font-weight:bold;font-size:12px;">🚨 Emergency — Call 108</span><br><br>';
  html += '<strong>' + data.title + '</strong><br><br>';
  if (data.steps && data.steps.length) {
    html += '<strong style="font-size:13px;">Steps:</strong><br>';
    data.steps.forEach(s => { html += '• ' + s + '<br>'; });
  }
  if (data.note) {
    html += '<br><em style="font-size:12px;color:#666;border-left:2px solid #ddd;padding-left:8px;display:block;margin-top:8px;">' + data.note + '</em>';
  }
  return html;
}

function initChat() {
  let msgs = document.getElementById("messages");
  msgs.innerHTML = '<div class="msg bot"><div class="msg-content">👋 Hello! I am your Smart Health Assistant.<br><br>Type any symptom or condition:<br>• Bleeding, chest pain, poisoning<br>• Fracture, burns, snake bite<br>• Fever, cough, diabetes, BP<br>• Diarrhea, headache, anxiety<br><br>I provide Indian medical guidance.<br><br>🚨 For life-threatening emergencies → Call <b>108</b> (Ambulance)</div></div>';
}

function callEmergency(num) {
  if (confirm('Call ' + num + ' ?')) {
    window.location.href = 'tel:' + num;
  }
}

function sendMessage() {
  let input = document.getElementById("userInput");
  let text = input.value.trim();
  if (!text) return;
  
  let msgs = document.getElementById("messages");
  msgs.innerHTML += '<div class="msg user"><div class="msg-content">' + escapeHtml(text) + '</div></div>';
  input.value = "";
  msgs.scrollTop = msgs.scrollHeight;
  
  setTimeout(() => {
    let data = findMatch(text);
    let response = data ? formatResponse(data) : '<span style="color:#666;">I could not find information about "' + escapeHtml(text) + '".</span><br><br>Try: bleeding, fever, fracture, asthma, dengue, cough, headache, burns, emergency...';
    let cls = (data && data.emergency) ? 'emergency' : (data && data.steps && data.steps.length) ? 'warning' : '';
    msgs.innerHTML += '<div class="msg bot"><div class="msg-content ' + cls + '">' + response + '</div></div>';
    msgs.scrollTop = msgs.scrollHeight;
  }, 300);
}

function escapeHtml(t) {
  let m = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'};
  return t.replace(/[&<>"\']/g, c => m[c]);
}

window.addEventListener('load', initChat);
</script>

<script>
const TRIAGE_SYMPTOMS = {
  chest: 'Chest pain or breathing trouble',
  fever: 'Fever, cold, cough or infection',
  injury: 'Injury, fall, burn or bleeding',
  stomach: 'Stomach pain, vomiting or diarrhea',
  head: 'Headache, dizziness or weakness',
  skin: 'Rash, swelling or allergy',
  other: 'Something else'
};

const TRIAGE_PROMPTS = {
  chest: 'chest pain',
  fever: 'fever',
  injury: 'fracture',
  stomach: 'diarrhea',
  head: 'headache',
  skin: 'skin rash',
  other: 'symptoms'
};

let checkerState = null;

function appendUserMessage(text) {
  const msgs = document.getElementById('messages');
  msgs.innerHTML += '<div class="msg user"><div class="msg-content">' + escapeHtml(text) + '</div></div>';
  msgs.scrollTop = msgs.scrollHeight;
}

function appendBotMessage(html, extraClass = '') {
  const msgs = document.getElementById('messages');
  msgs.innerHTML += '<div class="msg bot"><div class="msg-content ' + extraClass + '">' + html + '</div></div>';
  msgs.scrollTop = msgs.scrollHeight;
}

function renderAssistantTools() {
  return '<div class="assistant-tools">' +
    '<button type="button" class="triage-btn triage-start" onclick="startSymptomChecker()">Start Symptom Checker</button>' +
    '<button type="button" class="triage-btn triage-lite" onclick="handleUserText(\'fever\')">Fever Tips</button>' +
    '<button type="button" class="triage-btn triage-lite" onclick="handleUserText(\'breathing tips\')">Breathing Tips</button>' +
  '</div>';
}

function renderOptionButtons(options, handlerName) {
  return '<div class="triage-options">' + options.map(option =>
    '<button type="button" class="triage-btn ' + (option.variant || 'triage-lite') + '" onclick="' + handlerName + '(\'' + option.id + '\')">' + option.label + '</button>'
  ).join('') + '</div>';
}

function getProjectBase() {
  const marker = '/telemedicine_system';
  const path = window.location.pathname;
  const idx = path.toLowerCase().indexOf(marker);
  return idx === -1 ? window.location.origin : window.location.origin + path.substring(0, idx + marker.length);
}

function openBooking() {
  const target = getProjectBase() + '/patient/book_appointment.php';
  if (window.top && window.top !== window) {
    window.top.location.href = target;
    return;
  }
  window.location.href = target;
}

function startSymptomChecker() {
  checkerState = { symptom: null, danger: null, severity: null, duration: null, impact: null };
  appendBotMessage(
    '<div class="triage-card">' +
      '<strong>Symptom Checker</strong>' +
      '<div class="triage-note">This quick check helps you judge urgency before booking. It is not a diagnosis.</div>' +
      '<div class="triage-question">What best matches the main problem right now?</div>' +
      renderOptionButtons([
        { id: 'chest', label: 'Chest / Breathing', variant: 'triage-danger' },
        { id: 'fever', label: 'Fever / Infection' },
        { id: 'injury', label: 'Injury / Bleeding' },
        { id: 'stomach', label: 'Stomach / Vomiting' },
        { id: 'head', label: 'Headache / Weakness' },
        { id: 'skin', label: 'Rash / Allergy' },
        { id: 'other', label: 'Something Else' }
      ], 'chooseSymptom') +
    '</div>'
  );
}

function chooseSymptom(id) {
  checkerState.symptom = id;
  appendUserMessage(TRIAGE_SYMPTOMS[id]);
  appendBotMessage(
    '<div class="triage-card">' +
      '<div class="triage-question">Are any danger signs happening right now?</div>' +
      '<div class="triage-note">Examples: severe chest pain, trouble breathing, fainting, confusion, seizure, heavy bleeding, blue lips, or severe swelling.</div>' +
      renderOptionButtons([
        { id: 'yes', label: 'Yes, one or more', variant: 'triage-danger' },
        { id: 'no', label: 'No danger signs', variant: 'triage-lite' }
      ], 'chooseDanger') +
    '</div>'
  );
}

function chooseDanger(id) {
  checkerState.danger = id === 'yes';
  appendUserMessage(id === 'yes' ? 'Yes, there are danger signs' : 'No danger signs');
  appendBotMessage(
    '<div class="triage-card">' +
      '<div class="triage-question">How bad is it right now?</div>' +
      renderOptionButtons([
        { id: 'severe', label: 'Severe / getting worse', variant: 'triage-danger' },
        { id: 'moderate', label: 'Moderate' },
        { id: 'mild', label: 'Mild' }
      ], 'chooseSeverity') +
    '</div>'
  );
}

function chooseSeverity(id) {
  checkerState.severity = id;
  appendUserMessage(id === 'severe' ? 'Severe / getting worse' : id === 'moderate' ? 'Moderate' : 'Mild');
  appendBotMessage(
    '<div class="triage-card">' +
      '<div class="triage-question">When did this start?</div>' +
      renderOptionButtons([
        { id: 'today', label: 'Suddenly / today', variant: 'triage-danger' },
        { id: 'fewdays', label: '1 to 3 days' },
        { id: 'longer', label: 'More than 3 days' }
      ], 'chooseDuration') +
    '</div>'
  );
}

function chooseDuration(id) {
  checkerState.duration = id;
  appendUserMessage(id === 'today' ? 'Started suddenly / today' : id === 'fewdays' ? '1 to 3 days' : 'More than 3 days');
  appendBotMessage(
    '<div class="triage-card">' +
      '<div class="triage-question">How much is it affecting normal activity?</div>' +
      renderOptionButtons([
        { id: 'cannot', label: 'Cannot do normal activities', variant: 'triage-danger' },
        { id: 'soon', label: 'Can manage, but need help soon' },
        { id: 'manageable', label: 'Mostly manageable at home' }
      ], 'chooseImpact') +
    '</div>'
  );
}

function chooseImpact(id) {
  checkerState.impact = id;
  appendUserMessage(id === 'cannot' ? 'Cannot do normal activities' : id === 'soon' ? 'Can manage, but need help soon' : 'Mostly manageable at home');
  showTriageResult();
}

function evaluateTriage() {
  if (checkerState.danger) {
    return {
      level: 'emergency',
      badge: 'Emergency',
      title: 'Seek emergency care now',
      points: [
        'Your answers suggest danger signs that should not wait for an online booking.',
        'Call 108 immediately or go to the nearest emergency department.',
        'Use the chatbot again later for care tips once emergency help is on the way.'
      ]
    };
  }

  if (checkerState.symptom === 'chest' || checkerState.severity === 'severe' || checkerState.impact === 'cannot') {
    return {
      level: 'urgent',
      badge: 'Same-day care',
      title: 'Arrange a doctor consultation as soon as possible',
      points: [
        'This looks urgent enough for same-day medical advice.',
        'Book the earliest appointment available, and seek in-person care if symptoms worsen.',
        'If new danger signs appear, do not wait: call 108.'
      ]
    };
  }

  if (checkerState.severity === 'moderate' || checkerState.duration === 'longer' || checkerState.impact === 'soon') {
    return {
      level: 'routine',
      badge: 'Book soon',
      title: 'A routine consultation is a good next step',
      points: [
        'Your symptoms do not sound like an emergency, but they should be reviewed by a doctor.',
        'Book an appointment in the next 24 to 72 hours.',
        'If symptoms become severe or danger signs appear, switch to urgent care.'
      ]
    };
  }

  return {
    level: 'selfcare',
    badge: 'Monitor first',
    title: 'Home care and monitoring may be reasonable for now',
    points: [
      'This sounds mild based on your answers.',
      'Monitor symptoms, rest, hydrate, and use the chatbot for specific care tips.',
      'Book an appointment if symptoms persist, spread, or start affecting normal activities.'
    ]
  };
}

function showTriageResult() {
  const result = evaluateTriage();
  appendBotMessage(
    '<div class="triage-result ' + result.level + '">' +
      '<div class="triage-badge">' + result.badge + '</div>' +
      '<strong>' + result.title + '</strong>' +
      '<ul>' + result.points.map(point => '<li>' + point + '</li>').join('') + '</ul>' +
      '<div class="triage-actions">' +
        (result.level === 'emergency'
          ? '<button type="button" class="triage-btn triage-danger" onclick="callEmergency(\'108\')">Call 108</button><button type="button" class="triage-btn triage-lite" onclick="startSymptomChecker()">Start Again</button>'
          : '<button type="button" class="triage-btn triage-start" onclick="openBooking()">Book Appointment</button><button type="button" class="triage-btn triage-lite" onclick="askCareTips()">Get Care Tips</button>') +
      '</div>' +
    '</div>'
  );
}

function askCareTips() {
  const prompt = TRIAGE_PROMPTS[checkerState?.symptom] || 'symptoms';
  handleUserText(prompt);
}

function handleUserText(text) {
  if (!text) return;
  appendUserMessage(text);

  if (/^(start )?symptom checker$/i.test(text.trim())) {
    setTimeout(startSymptomChecker, 250);
    return;
  }

  setTimeout(() => {
    const data = findMatch(text);
    const response = data ? formatResponse(data) : '<span style="color:#666;">I could not find information about "' + escapeHtml(text) + '".</span><br><br>Try: bleeding, fever, fracture, asthma, dengue, cough, headache, burns, emergency, or tap <b>Start Symptom Checker</b>.';
    const cls = (data && data.emergency) ? 'emergency' : (data && data.steps && data.steps.length) ? 'warning' : '';
    appendBotMessage(response + (!data ? renderAssistantTools() : ''), cls);
  }, 300);
}

initChat = function () {
  const msgs = document.getElementById('messages');
  msgs.innerHTML = '';
  appendBotMessage(
    'Hello! I am your Smart Health Assistant.<br><br>' +
    'You can type any symptom for first-aid tips, or use the symptom checker to understand urgency before booking an appointment.<br><br>' +
    'Examples: bleeding, chest pain, fever, fracture, cough, headache, diarrhea.<br><br>' +
    'For life-threatening emergencies, call <b>108</b> immediately.' +
    renderAssistantTools()
  );
};

sendMessage = function () {
  const input = document.getElementById('userInput');
  const text = input.value.trim();
  if (!text) return;
  input.value = '';
  handleUserText(text);
};

window.addEventListener('load', initChat);
</script>
</body>
</html>
