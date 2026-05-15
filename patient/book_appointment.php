<?php
date_default_timezone_set('Asia/Kolkata');
session_start(); require_once("../config/db.php");
require_once("monitor_core.php");
require_once("../includes/admin_core.php");
require_once("../includes/specializations.php");
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') { header("Location: ../login.php"); exit; }
$patient_id = $_SESSION['user_id'];
ensureAdminSchema($conn);
$doctor_result = $conn->query("
    SELECT * FROM doctors
    WHERE verification_status = 'verified'
    ORDER BY
        CASE WHEN availability_status = 'available' THEN 0 ELSE 1 END ASC,
        specialization ASC,
        name ASC
");
$doctor_list = [];
$doctors_by_id = [];
$specializations = array_combine(getDoctorSpecializations(), getDoctorSpecializations());

if ($doctor_result) {
    while ($doctor = $doctor_result->fetch_assoc()) {
        $doctor['specialization'] = trim($doctor['specialization'] ?? '') ?: 'General Practice';
        $doctor_list[] = $doctor;
        $doctors_by_id[(int) $doctor['id']] = $doctor;
        $specializations[$doctor['specialization']] = $doctor['specialization'];
    }
}

ksort($specializations, SORT_NATURAL | SORT_FLAG_CASE);
$specializations = array_values($specializations);
$msg = ""; $msg_type = "";

// Function to get valid appointment slots for a given date
function getValidAppointmentSlots(string $date): array {
    $slots = [];
    $start_time = strtotime($date . ' 09:30:00');
    $end_time = strtotime($date . ' 16:30:00'); // 4:30 PM
    
    // Special breaks (times when no appointments can be booked)
    $special_breaks = [
        [strtotime($date . ' 10:40:00'), strtotime($date . ' 10:50:00')], // 10:40-10:50
        [strtotime($date . ' 12:30:00'), strtotime($date . ' 13:30:00')]  // 12:30-1:30
    ];
    
    $current_time = $start_time;
    
    while ($current_time < $end_time) {
        $slot_end = $current_time + (30 * 60); // 30 minutes
        
        // Check if this slot overlaps with any special break
        $is_valid = true;
        foreach ($special_breaks as $break) {
            if (($current_time < $break[1] && $slot_end > $break[0])) {
                $is_valid = false;
                break;
            }
        }
        
        if ($is_valid && $slot_end <= $end_time) {
            $slots[] = date('Y-m-d H:i:s', $current_time);
        }
        
        // Move to next slot (30 min call + 5 min break = 35 min)
        $current_time += (35 * 60);
    }
    
    return $slots;
}

// Function to check if a slot is already booked
function isSlotBooked(int $doctor_id, string $slot_datetime): bool {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
    $stmt->bind_param("is", $doctor_id, $slot_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to check if the patient already has an appointment at the exact same time
function isPatientBusy(int $patient_id, string $slot_datetime): bool {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ? AND appointment_date = ?");
    $stmt->bind_param("is", $patient_id, $slot_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function isSlotStillBookable(string $slot_datetime): bool {
    $slot_timestamp = strtotime($slot_datetime);
    return $slot_timestamp > (time() + 3600);
}

// Get selected date (default to today if not set)
$today_date = date('Y-m-d');
$selected_date = isset($_GET['date']) ? $_GET['date'] : $today_date;
$selected_doctor = isset($_GET['doctor']) ? intval($_GET['doctor']) : null;
$selected_specialization = trim($_GET['specialization'] ?? '');
$is_selected_date_past = ($selected_date < $today_date);

if ($selected_doctor && isset($doctors_by_id[$selected_doctor])) {
    $selected_specialization = $doctors_by_id[$selected_doctor]['specialization'];
}

if ($selected_specialization !== '' && !in_array($selected_specialization, $specializations, true)) {
    $selected_specialization = '';
}

if ($selected_doctor && !isset($doctors_by_id[$selected_doctor])) {
    $selected_doctor = null;
}

// Get available slots for selected date and doctor
$available_slots = [];
if ($selected_doctor && isset($doctors_by_id[$selected_doctor]) && (($doctors_by_id[$selected_doctor]['availability_status'] ?? 'available') === 'available') && !$is_selected_date_past) {
    $all_slots = getValidAppointmentSlots($selected_date);
    foreach ($all_slots as $slot) {
        if (isSlotStillBookable($slot) && !isSlotBooked($selected_doctor, $slot)) {
            $available_slots[] = $slot;
        }
    }
} elseif ($selected_doctor && isset($doctors_by_id[$selected_doctor]) && (($doctors_by_id[$selected_doctor]['availability_status'] ?? 'available') !== 'available') && $msg === "") {
    $msg = "This doctor is currently marked as not available for appointments.";
    $msg_type = "warning";
} elseif ($selected_doctor && $is_selected_date_past && $msg === "") {
    $msg = "Past dates are not available for booking.";
    $msg_type = "warning";
}

if (isset($_POST['book'])) {
    $doctor_id = intval($_POST['doctor_id']);
    $slot_datetime = $_POST['slot_datetime'];
    
    // Validate that the appointment date is at least 1 hour in the future
    $appointment_datetime = new DateTime($slot_datetime);
    $current_datetime = new DateTime();
    $min_datetime = clone $current_datetime;
    $min_datetime->modify('+1 hour');
    
    if ($appointment_datetime->format('Y-m-d') < $current_datetime->format('Y-m-d')) {
        $msg = "You cannot book an appointment for a past date.";
        $msg_type = "error";
    } elseif ($appointment_datetime <= $min_datetime) {
        $msg = "Please select a date and time at least 1 hour from now.";
        $msg_type = "error";
    } else {
        // Check if slot is still available
        if (!isset($doctors_by_id[$doctor_id])) {
            $msg = "This doctor is not available for booking.";
            $msg_type = "error";
        } elseif (($doctors_by_id[$doctor_id]['availability_status'] ?? 'available') !== 'available') {
            $msg = "This doctor is currently marked as not available for appointments.";
            $msg_type = "error";
        } elseif (isSlotBooked($doctor_id, $slot_datetime)) {
            $msg = "This time slot is no longer available. Please select a different time.";
            $msg_type = "error";
        } elseif (isPatientBusy($patient_id, $slot_datetime)) {
            $msg = "You already have another appointment at this time. Please select a different slot.";
            $msg_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, 'booked')");
            if ($stmt !== false) {
                $stmt->bind_param("iis", $patient_id, $doctor_id, $slot_datetime);
                if ($stmt->execute()) { 
                    $doctor_name = $doctors_by_id[$doctor_id]['name'] ?? 'Doctor';
                    $formatted_time = date('D, d M Y · h:i A', strtotime($slot_datetime));
                    
                    // Notify patient and doctor about the confirmed appointment.
                    addUserNotification($conn, $patient_id, "Appointment Booked", "Your appointment with Dr. $doctor_name on $formatted_time has been booked successfully.");
                    addUserNotification($conn, $doctor_id, "New Appointment", "A new appointment has been booked by " . ($_SESSION['name'] ?? 'a patient') . " for $formatted_time.");

                    header("Location: dashboard.php?success=Appointment booked successfully!");
                    exit; 
                }
                else { $msg = "Could not book appointment. Please try again."; $msg_type = "error"; }
            } else {
                $msg = "Database error. Please try again."; $msg_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Book Appointment — TELEMEDICINE</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');
 
:root {
    --teal:       #0EB8A0;
    --teal-dark:  #0A8A78;
    --teal-glow:  rgba(14,184,160,0.15);
    --navy:       #f8fafc;
    --navy-mid:   #ffffff;
    --navy-light: #f1f5f9;
    --navy-card:  #ffffff;
    --white:      #1e293b;
    --cream:      #F5F0E8;
    --muted:      #64748b;
    --muted-dim:  #94a3b8;
    --accent:     #FF6B4A;
    --success:    #22C55E;
    --warning:    #F59E0B;
    --danger:     #EF4444;
    --border:     rgba(0,0,0,0.08);
    --radius:     14px;
    --radius-lg:  22px;
    --shadow:     0 4px 20px rgba(0,0,0,0.05);
}

body.dark-mode {
    --navy:       #0B1526;
    --navy-mid:   #112035;
    --navy-light: #1A3050;
    --navy-card:  #0F1E36;
    --white:      #FFFFFF;
    --muted:      #7A8EA8;
    --muted-dim:  #4A5E78;
    --border:     rgba(255,255,255,0.07);
    --shadow:     0 8px 32px rgba(0,0,0,0.25);
}
 
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
 
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--navy);
    color: var(--white);
    min-height: 100vh;
    line-height: 1.6;
}
 
/* BG */
.page-bg {
    position: fixed; inset: 0; z-index: -1;
    background:
        radial-gradient(ellipse 60% 50% at 15% 0%, rgba(14,184,160,0.1) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 85% 90%, rgba(14,184,160,0.07) 0%, transparent 50%),
        var(--navy);
}
 
/* SIDEBAR NAV */
.layout { display: flex; min-height: 100vh; }
 
.sidebar {
    width: 240px;
    background: var(--navy-card);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 50;
    padding: 24px 0;
}
.sidebar-logo {
    display: flex; align-items: center; gap: 10px;
    padding: 0 24px 28px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
    text-decoration: none;
}
.logo-dot {
    width: 9px; height: 9px;
    background: var(--teal);
    border-radius: 50%;
    box-shadow: 0 0 10px var(--teal);
    animation: blink 2s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }
.logo-text {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.15rem; font-weight: 700;
    color: var(--white);
}
 
.nav-section { padding: 0 12px; margin-bottom: 8px; }
.nav-section-label {
    font-size: 0.65rem; text-transform: uppercase;
    letter-spacing: 0.1em; color: var(--muted-dim);
    font-weight: 600; padding: 0 12px 8px;
}
.nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    color: var(--muted); text-decoration: none;
    font-size: 0.875rem; font-weight: 500;
    transition: all 0.2s; margin-bottom: 2px;
}
.nav-link:hover { background: var(--teal-glow); color: var(--white); }
.nav-link.active { background: var(--teal-glow); color: var(--teal); }
.nav-icon { font-size: 1rem; width: 20px; text-align: center; }
 
.sidebar-bottom {
    margin-top: auto;
    padding: 16px 12px 0;
    border-top: 1px solid var(--border);
}
 
/* MAIN CONTENT */
.main {
    margin-left: 240px;
    flex: 1;
    padding: 36px 40px;
    max-width: calc(100% - 240px);
}
 
/* PAGE HEADER */
.page-header { margin-bottom: 32px; }
.page-header h1 {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.8rem; font-weight: 600;
    margin-bottom: 4px;
}
.page-header p { color: var(--muted); font-size: 0.9rem; }

.booking-shell {
    width: min(1180px, 100%);
}

.booking-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 24px;
    align-items: start;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--white);
}

.section-subtitle {
    color: var(--muted);
    font-size: 0.88rem;
    margin-bottom: 18px;
}
 
/* CARDS */
.card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow);
}
.card-sm { padding: 20px; border-radius: var(--radius); }
 
/* GRID */
.grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
 
/* STAT CARD */
.stat-card {
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 24px;
}
.stat-card-icon {
    width: 40px; height: 40px;
    background: var(--teal-glow);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin-bottom: 14px;
}
.stat-card-value {
    font-family: 'Clash Display', sans-serif;
    font-size: 1.8rem; font-weight: 700;
    color: var(--white); line-height: 1;
    margin-bottom: 4px;
}
.stat-card-label { font-size: 0.78rem; color: var(--muted); font-weight: 500; }
 
/* FORMS */
.form-group { margin-bottom: 20px; }
.form-label {
    display: block; font-size: 0.8rem;
    font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.form-input, .form-select {
    width: 100%; padding: 12px 16px;
    background: var(--navy-light);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--white); font-size: 0.9rem;
    font-family: 'DM Sans', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.form-input:focus, .form-select:focus {
    border-color: rgba(14,184,160,0.5);
    box-shadow: 0 0 0 3px rgba(14,184,160,0.1);
}
.form-input::placeholder { color: var(--muted-dim); }
.form-select option { background: var(--navy-mid); }

.helper-text {
    color: var(--muted-dim);
    font-size: 0.8rem;
    margin-top: 4px;
    display: block;
}
 
/* BUTTONS */
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px; border-radius: 50px;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; border: none;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none; transition: all 0.2s;
}
.btn-primary {
    background: var(--teal); color: var(--navy);
    box-shadow: 0 0 20px rgba(14,184,160,0.25);
}
.btn-primary:hover {
    background: var(--teal-dark); color: var(--white);
    transform: translateY(-1px);
    box-shadow: 0 0 30px rgba(14,184,160,0.35);
}
.btn-secondary {
    background: var(--navy-light); color: var(--white);
    border: 1px solid var(--border);
}
.btn-secondary:hover { background: var(--navy-mid); transform: translateY(-1px); }
.btn-danger { background: rgba(239,68,68,0.15); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }
.btn-danger:hover { background: rgba(239,68,68,0.25); }
.btn-sm { padding: 7px 16px; font-size: 0.8rem; }
.btn-full { width: 100%; justify-content: center; }

/* SLOT BUTTONS */
.slot-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 8px;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    border: 2px solid var(--border);
    background: var(--navy-light);
    color: var(--white);
    text-align: center;
    transition: all 0.2s;
    min-height: 48px;
}
.slot-btn:hover:not(.disabled):not(.selected) {
    background: var(--teal-glow);
    border-color: rgba(14,184,160,0.5);
    color: var(--teal);
    transform: translateY(-1px);
}
.slot-btn.selected {
    background: var(--teal);
    border-color: var(--teal);
    color: var(--navy);
    box-shadow: 0 0 15px rgba(14,184,160,0.4);
}
.slot-btn.disabled {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.1);
    color: var(--muted-dim);
    cursor: not-allowed;
    opacity: 0.6;
}

/* TABLE */
.table-wrap { overflow-x: auto; border-radius: var(--radius); }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
    text-align: left; padding: 12px 16px;
    font-size: 0.72rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--muted); border-bottom: 1px solid var(--border);
}
tbody td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }
 
/* BADGES */
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 50px;
    font-size: 0.72rem; font-weight: 600;
}
.badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
.badge-success { background: rgba(34,197,94,0.12); color: var(--success); }
.badge-warning { background: rgba(245,158,11,0.12); color: var(--warning); }
.badge-danger  { background: rgba(239,68,68,0.12);  color: var(--danger); }
.badge-info    { background: var(--teal-glow); color: var(--teal); }
 
/* ALERT */
.alert {
    padding: 14px 18px; border-radius: var(--radius);
    font-size: 0.875rem; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.2);  color: var(--danger); }
.alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: var(--warning); }
 
/* DIVIDER */
.divider { height: 1px; background: var(--border); margin: 24px 0; }
 
/* EMPTY STATE */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--muted);
}
.empty-state .empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 1rem; font-weight: 600; margin-bottom: 8px; color: var(--white); }
.empty-state p { font-size: 0.85rem; }

.doctor-list {
    display: grid;
    gap: 14px;
    max-height: 460px;
    overflow-y: auto;
    padding-right: 4px;
}

.doctor-search {
    margin-bottom: 16px;
}

.doctor-option {
    position: relative;
}

.doctor-radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.doctor-card {
    display: block;
    padding: 18px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: linear-gradient(180deg, rgba(14,184,160,0.05), rgba(14,184,160,0.01));
    cursor: pointer;
    transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.doctor-card:hover {
    transform: translateY(-2px);
    border-color: rgba(14,184,160,0.35);
    box-shadow: 0 12px 24px rgba(0,0,0,0.06);
}

.doctor-option.is-unavailable .doctor-card {
    background: linear-gradient(180deg, rgba(239,68,68,0.07), rgba(239,68,68,0.02));
    border-color: rgba(239,68,68,0.18);
}

.doctor-option.is-unavailable .doctor-card:hover {
    border-color: rgba(239,68,68,0.3);
}

.doctor-option.is-unavailable .doctor-radio:disabled + .doctor-card {
    cursor: not-allowed;
    opacity: 0.82;
}

.doctor-radio:checked + .doctor-card {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(14,184,160,0.14);
    background: linear-gradient(180deg, rgba(14,184,160,0.14), rgba(14,184,160,0.04));
}

.doctor-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.doctor-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--white);
    margin-bottom: 4px;
}

.doctor-specialization {
    color: var(--teal);
    font-size: 0.82rem;
    font-weight: 600;
}

.doctor-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.doctor-status-badge.available {
    background: rgba(34,197,94,0.14);
    color: var(--success);
    border: 1px solid rgba(34,197,94,0.22);
}

.doctor-status-badge.unavailable {
    background: rgba(239,68,68,0.14);
    color: var(--danger);
    border: 1px solid rgba(239,68,68,0.22);
}

.doctor-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--teal-glow);
    color: var(--teal);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.doctor-meta {
    display: grid;
    gap: 8px;
}

.doctor-meta-item {
    color: var(--muted);
    font-size: 0.84rem;
    line-height: 1.5;
}

.doctor-option.is-hidden,
.doctor-empty.is-hidden {
    display: none;
}

.doctor-empty {
    border: 1px dashed var(--border);
    border-radius: var(--radius);
    padding: 28px 20px;
    text-align: center;
    color: var(--muted);
    background: rgba(255,255,255,0.02);
}

.doctor-empty strong {
    display: block;
    color: var(--white);
    margin-bottom: 8px;
}

.selection-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
}

.summary-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    background: var(--navy-light);
    border: 1px solid var(--border);
    color: var(--muted);
    font-size: 0.82rem;
}

.summary-pill strong {
    color: var(--white);
}

.slots-card {
    margin-top: 24px;
}
 
/* RESPONSIVE */
@media (max-width: 980px) {
    .booking-grid {
        grid-template-columns: 1fr;
    }

    .doctor-list {
        max-height: none;
    }
}

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main { margin-left: 0; max-width: 100%; padding: 20px; }
    .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
    .card { padding: 22px; }
}
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
<script src="../js/page-transition.js"></script>
</head><body><div class="page-bg"></div><div class="layout">
<aside class="sidebar">
    <div class="sidebar-logo" style="display:flex; align-items:center; justify-content:space-between; padding-right:15px;">
        <a href="../index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div class="logo-dot"></div>
            <span class="logo-text">TELEMEDICINE</span>
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.1rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="book_appointment.php" class="nav-link active"><span class="nav-icon">📅</span> Book Appointment</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Health</div>
        <a href="checklist.php" class="nav-link"><span class="nav-icon">💊</span> My Medicines</a>
        <a href="upload_report.php" class="nav-link"><span class="nav-icon">📄</span> Upload Report</a>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Monitoring</div>
        <a href="add_monitor.php" class="nav-link"><span class="nav-icon">👁️</span> Add Monitor</a>
        <a href="monitor_view.php" class="nav-link"><span class="nav-icon">👥</span> Monitored Patients</a>
    </div>
</aside>

<!-- Floating Chatbot Widget -->
<div class="chatbot-fab" id="chatbotFab">
    <span>🤖</span>
</div>

<div class="chatbot-modal" id="chatbotModal">
    <div class="chatbot-header">
        <span>AI Health Assistant</span>
        <button class="chatbot-close" id="chatbotClose">✕</button>
    </div>
    <iframe id="chatbotIframe" src="../chatbot_widget_content.php" frameborder="0"></iframe>
</div>

<style>
/* Floating Action Button for Chatbot */
.chatbot-fab {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: var(--teal);
    color: var(--navy);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 1000;
    transition: all 0.3s ease;
}
.chatbot-fab:hover { background: var(--teal-dark); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }

/* Chatbot Modal/Panel */
.chatbot-modal {
    position: fixed;
    bottom: 90px; /* Above the FAB */
    right: 20px;
    width: 350px;
    height: 500px;
    background: var(--navy-card);
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    z-index: 999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(0.95);
    opacity: 0;
    pointer-events: none;
}
.chatbot-modal.show { display: flex; transform: translateY(0) scale(1); opacity: 1; pointer-events: auto; }
.chatbot-header { background: var(--teal); color: var(--navy); padding: 12px 15px; font-weight: 600; font-size: 1.1rem; display: flex; justify-content: space-between; align-items: center; }
.chatbot-close { background: none; border: none; color: var(--navy); font-size: 1.2rem; cursor: pointer; opacity: 0.8; }
.chatbot-close:hover { opacity: 1; }
.chatbot-modal iframe { flex: 1; width: 100%; height: 100%; border: none; }

@media (max-width: 600px) {
    .chatbot-fab { bottom: 15px; right: 15px; width: 50px; height: 50px; font-size: 1.5rem; }
    .chatbot-modal { bottom: 75px; right: 15px; width: calc(100% - 30px); height: 70vh; max-height: 500px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chatbotFab = document.getElementById('chatbotFab');
    const chatbotModal = document.getElementById('chatbotModal');
    const chatbotClose = document.getElementById('chatbotClose');
    if (chatbotFab && chatbotModal && chatbotClose) {
        chatbotFab.addEventListener('click', () => chatbotModal.classList.toggle('show'));
        chatbotClose.addEventListener('click', () => chatbotModal.classList.remove('show'));
        document.addEventListener('click', (e) => {
            if (!chatbotModal.contains(e.target) && !chatbotFab.contains(e.target) && chatbotModal.classList.contains('show')) chatbotModal.classList.remove('show');
        });
    }
});
</script>
<main class="main">
    <div class="page-header"><h1>Book Appointment</h1><p>Select a doctor, choose your preferred date, and pick a time slot.</p></div>
    <div class="booking-shell">
        <?php if($msg): ?><div class="alert alert-<?php echo $msg_type; ?>"><?php echo $msg_type=='success'?'✅':'❌'; ?> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <div class="booking-grid">
        <div class="card">
            <!-- Step 1: Select Doctor and Date -->
            <div id="step1" style="display: block;">
                <h3 class="section-title">Step 1: Select Specialization & Date</h3>
                <p class="section-subtitle">Choose a specialization first, then pick a doctor from the matching list on the right.</p>
                <form method="GET" id="selectionForm">
                    <input type="hidden" name="doctor" id="doctorInput" value="<?php echo $selected_doctor ? $selected_doctor : ''; ?>">
                    <div class="form-group">
                        <label class="form-label">Select Specialization</label>
                        <select class="form-select" name="specialization" id="specializationSelect" required>
                            <option value="">— PLEASE SELECT A SPECIALIZATION —</option>
                            <?php foreach ($specializations as $specialization): ?>
                            <option value="<?php echo htmlspecialchars($specialization); ?>" <?php echo ($selected_specialization === $specialization) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($specialization); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="helper-text">Doctors will appear on the right after you choose a specialization.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Select Date</label>
                        <input class="form-input" type="date" name="date" id="dateSelect" 
                               value="<?php echo $selected_date; ?>" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        <small class="helper-text">Working hours: 9:30 AM - 4:30 PM (30-min appointments with breaks)</small>
                    </div>
                </form>
                <div class="selection-summary">
                    <div class="summary-pill">Specialization: <strong id="selectedSpecializationLabel"><?php echo $selected_specialization !== '' ? htmlspecialchars($selected_specialization) : 'Not selected'; ?></strong></div>
                    <div class="summary-pill">Doctor: <strong id="selectedDoctorLabel"><?php echo ($selected_doctor && isset($doctors_by_id[$selected_doctor])) ? htmlspecialchars('Dr. ' . $doctors_by_id[$selected_doctor]['name']) : 'Not selected'; ?></strong></div>
                </div>
            </div>
        </div>

            <div class="card">
                <h3 class="section-title">Doctors</h3>
                <p class="section-subtitle">Available doctors are listed first. Doctors marked not available still appear below so patients can search and view them.</p>
                <div class="doctor-search">
                    <input class="form-input" type="text" id="doctorSearchInput" placeholder="Search doctor by name">
                </div>
                <div class="doctor-list" id="doctorList">
                    <?php foreach ($doctor_list as $doctor): ?>
                    <?php
                        $doctor_id = (int) $doctor['id'];
                        $doctor_name = trim($doctor['name'] ?? 'Unnamed Doctor');
                        $doctor_specialization = $doctor['specialization'];
                        $doctor_availability = $doctor['availability_status'] ?? 'available';
                        $is_doctor_available = $doctor_availability === 'available';
                        $doctor_affiliations = trim($doctor['affiliations'] ?? '');
                        $doctor_bio = trim($doctor['bio'] ?? '');
                        $doctor_bio_preview = strlen($doctor_bio) > 110 ? substr($doctor_bio, 0, 107) . '...' : $doctor_bio;
                        $doctor_initials = strtoupper(substr($doctor_name, 0, 1));
                    ?>
                    <div class="doctor-option<?php echo ($selected_specialization !== '' && $doctor_specialization !== $selected_specialization) ? ' is-hidden' : ''; ?><?php echo $is_doctor_available ? '' : ' is-unavailable'; ?>" data-specialization="<?php echo htmlspecialchars($doctor_specialization); ?>" data-search="<?php echo htmlspecialchars(strtolower($doctor_name . ' ' . $doctor_specialization . ' ' . str_replace('_', ' ', $doctor_availability))); ?>" data-availability="<?php echo htmlspecialchars($doctor_availability); ?>">
                        <input class="doctor-radio" type="radio" name="doctor_option" id="doctor-option-<?php echo $doctor_id; ?>" value="<?php echo $doctor_id; ?>" data-name="<?php echo htmlspecialchars($doctor_name); ?>" data-specialization="<?php echo htmlspecialchars($doctor_specialization); ?>" <?php echo ($selected_doctor === $doctor_id && $is_doctor_available) ? 'checked' : ''; ?> <?php echo $is_doctor_available ? '' : 'disabled'; ?>>
                        <label class="doctor-card" for="doctor-option-<?php echo $doctor_id; ?>">
                            <div class="doctor-card-top">
                                <div>
                                    <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor_name); ?></div>
                                    <div class="doctor-specialization"><?php echo htmlspecialchars($doctor_specialization); ?></div>
                                    <div class="doctor-status-badge <?php echo $is_doctor_available ? 'available' : 'unavailable'; ?>">
                                        <?php echo $is_doctor_available ? 'Available' : 'Not Available'; ?>
                                    </div>
                                </div>
                                <div class="doctor-avatar"><?php echo htmlspecialchars($doctor_initials); ?></div>
                            </div>
                            <div class="doctor-meta">
                                <div class="doctor-meta-item"><?php echo $doctor_affiliations !== '' ? htmlspecialchars($doctor_affiliations) : ($is_doctor_available ? 'Available for online consultation and appointment booking.' : 'Currently unavailable for appointment booking.'); ?></div>
                                <div class="doctor-meta-item"><?php echo $doctor_bio !== '' ? htmlspecialchars($doctor_bio_preview) : ($is_doctor_available ? 'Choose this doctor to view open time slots for your selected date.' : 'You can still find this doctor here, but booking is disabled until availability is turned back on.'); ?></div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <div class="doctor-empty<?php echo $selected_specialization !== '' ? ' is-hidden' : ''; ?>" id="doctorPrompt">
                        <strong>Select a specialization</strong>
                        Pick a specialization on the left to see matching doctors here.
                    </div>
                    <div class="doctor-empty is-hidden" id="doctorEmptyState">
                        <strong>No doctors found</strong>
                        There are no doctors matching this specialization and search right now.
                    </div>
                </div>
            </div>
        </div>

        <div class="card slots-card" id="step2" style="display: <?php echo ($selected_doctor && isset($doctors_by_id[$selected_doctor]) && (($doctors_by_id[$selected_doctor]['availability_status'] ?? 'available') === 'available')) ? 'block' : 'none'; ?>;">
            <!-- Step 2: Select Time Slot -->
                <h3 class="section-title">Step 2: Select Time Slot</h3>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 20px;">
                    Available slots for <?php echo date('l, F j, Y', strtotime($selected_date)); ?> with<?php echo ($selected_doctor && isset($doctors_by_id[$selected_doctor])) ? ' Dr. ' . htmlspecialchars($doctors_by_id[$selected_doctor]['name']) : ''; ?>
                </p>

                <?php if (!empty($available_slots)): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px;">
                        <?php foreach ($available_slots as $slot): 
                            $slot_time = strtotime($slot);
                            $end_time = $slot_time + (30 * 60);
                            $time_display = date('g:i A', $slot_time) . ' - ' . date('g:i A', $end_time);
                            
                            // Check if slot is in the past (less than 1 hour from now)
                            $current_time = time();
                            $is_past = ($slot_time <= ($current_time + 3600));
                        ?>
                        <button class="slot-btn <?php echo $is_past ? 'disabled' : ''; ?>" 
                                type="button" 
                                data-slot="<?php echo $slot; ?>"
                                <?php echo $is_past ? 'disabled' : ''; ?>>
                            <?php echo $time_display; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="POST" id="bookingForm" style="display: none;">
                        <input type="hidden" name="doctor_id" value="<?php echo $selected_doctor; ?>">
                        <input type="hidden" name="slot_datetime" id="selectedSlot">
                        <button class="btn btn-primary btn-full" type="submit" name="book" id="confirmBtn">
                            📅 Confirm Appointment
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📅</div>
                        <h3>No Available Slots</h3>
                        <p>All appointment slots for this date are booked or unavailable.</p>
                        <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date . ' +1 day')); ?>&doctor=<?php echo $selected_doctor; ?>&specialization=<?php echo urlencode($selected_specialization); ?>" class="btn btn-secondary">Try Next Day</a>
                    </div>
                <?php endif; ?>
        </div>
    </div>
</main></div></body></html>
<script>
// Theme Toggle Logic
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
}
themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
});

// Auto-hide alerts after 7 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.5s ease';
        a.style.opacity = '0';
        setTimeout(() => a.style.display = 'none', 500);
    });
}, 7000);

// Clear URL parameters so they don't reappear on refresh
if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('success'); 
    url.searchParams.delete('error');
    window.history.replaceState({}, document.title, url);
}

// Slot selection functionality
document.addEventListener('DOMContentLoaded', function() {
    const slotButtons = document.querySelectorAll('.slot-btn:not(.disabled)');
    const bookingForm = document.getElementById('bookingForm');
    const selectedSlotInput = document.getElementById('selectedSlot');
    const confirmBtn = document.getElementById('confirmBtn');
    const selectionForm = document.getElementById('selectionForm');
    const specializationSelect = document.getElementById('specializationSelect');
    const doctorSearchInput = document.getElementById('doctorSearchInput');
    const doctorInput = document.getElementById('doctorInput');
    const doctorCards = document.querySelectorAll('.doctor-option');
    const doctorRadios = document.querySelectorAll('.doctor-radio');
    const doctorPrompt = document.getElementById('doctorPrompt');
    const doctorEmptyState = document.getElementById('doctorEmptyState');
    const selectedDoctorLabel = document.getElementById('selectedDoctorLabel');
    const selectedSpecializationLabel = document.getElementById('selectedSpecializationLabel');
    const dateSelect = document.getElementById('dateSelect');
    const step2 = document.getElementById('step2');
    let selectedSlot = null;

    slotButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove selected class from all buttons
            slotButtons.forEach(btn => btn.classList.remove('selected'));
            
            // Add selected class to clicked button
            this.classList.add('selected');
            
            // Store selected slot
            selectedSlot = this.dataset.slot;
            selectedSlotInput.value = selectedSlot;
            
            // Show booking form
            bookingForm.style.display = 'block';
            
            // Scroll to confirm button
            confirmBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    function updateDoctorSummary() {
        const checkedDoctor = document.querySelector('.doctor-radio:checked');
        selectedDoctorLabel.textContent = checkedDoctor ? `Dr. ${checkedDoctor.dataset.name}` : 'Not selected';
    }

    function filterDoctors() {
        const selectedSpecialization = specializationSelect.value;
        const searchTerm = (doctorSearchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;
        let selectedDoctorStillVisible = false;

        doctorCards.forEach(card => {
            const matchesSpecialization = selectedSpecialization !== '' && card.dataset.specialization === selectedSpecialization;
            const matchesSearch = searchTerm === '' || (card.dataset.search || '').includes(searchTerm);
            const matches = matchesSpecialization && matchesSearch;
            card.classList.toggle('is-hidden', !matches);
            if (matches) {
                visibleCount += 1;
                const radio = card.querySelector('.doctor-radio');
                if (radio && radio.checked && !radio.disabled) {
                    selectedDoctorStillVisible = true;
                }
            }
        });

        if (!selectedDoctorStillVisible) {
            doctorInput.value = '';
            doctorRadios.forEach(radio => {
                radio.checked = false;
            });
            selectedSlot = null;
            if (selectedSlotInput) {
                selectedSlotInput.value = '';
            }
            if (bookingForm) {
                bookingForm.style.display = 'none';
            }
            slotButtons.forEach(btn => btn.classList.remove('selected'));
            if (step2) {
                step2.style.display = 'none';
            }
        }

        doctorPrompt.classList.toggle('is-hidden', selectedSpecialization !== '');
        doctorEmptyState.classList.toggle('is-hidden', !(selectedSpecialization !== '' && visibleCount === 0));
        selectedSpecializationLabel.textContent = selectedSpecialization || 'Not selected';
        updateDoctorSummary();
    }

    doctorRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            doctorInput.value = this.value;
            selectedDoctorLabel.textContent = `Dr. ${this.dataset.name}`;
            // Automatically refresh slots when a doctor is picked
            selectionForm.submit();
        });
    });

    dateSelect.addEventListener('change', function() {
        // Automatically refresh slots if a doctor is already selected and date changes
        if (doctorInput.value !== '') {
            selectionForm.submit();
        }
    });

    specializationSelect.addEventListener('change', function() {
        doctorInput.value = '';
        if (doctorSearchInput) {
            doctorSearchInput.value = '';
        }
        filterDoctors();
    });

    if (doctorSearchInput) {
        doctorSearchInput.addEventListener('input', filterDoctors);
    }

    filterDoctors();
});
</script>

