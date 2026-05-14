<?php
session_start();
require_once("../config/db.php");
require_once("../patient/monitor_core.php");
require_once("../includes/prescription_pdf.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit;
}

$doctor_id = intval($_SESSION['user_id']);
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

function convertDurationToDays($duration_value, $duration_unit) {
    $duration_value = max(1, (int) $duration_value);
    if ($duration_unit === 'weeks') {
        return $duration_value * 7;
    }
    if ($duration_unit === 'months') {
        $start = new DateTime();
        $end = (clone $start)->modify('+' . $duration_value . ' month');
        return max(1, (int) $start->diff($end)->days);
    }
    return $duration_value;
}

// Verify doctor has authority (appointments or an active call)
$check = $conn->prepare("SELECT p.name, p.gender FROM patients p 
                                                  LEFT JOIN appointments a ON p.id = a.patient_id AND a.doctor_id = ?
                         LEFT JOIN video_calls vc ON p.id = vc.patient_id AND vc.doctor_id = ? AND vc.status = 'active'
                         WHERE p.id = ? AND (a.id IS NOT NULL OR vc.id IS NOT NULL) LIMIT 1");
$check->bind_param("iii", $doctor_id, $doctor_id, $patient_id);
$check->execute();
$patient_data = $check->get_result()->fetch_assoc();

if (!$patient_data) {
    die("Patient not found or unauthorized access.");
}

$msg = ""; $msg_type = "";
if (isset($_POST['issue_prescription'])) {
    $medicine_names = $_POST['medicine_name'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $all_times = $_POST['medicine_time'] ?? [];
    $duration_values = $_POST['duration_value'] ?? [];
    $duration_units = $_POST['duration_unit'] ?? [];

    $success_count = 0;
    $error_occurred = false;

    if (empty($medicine_names) || empty($medicine_names[0])) { // Check if at least one entry exists
        $msg = "Please add at least one medicine entry.";
        $msg_type = "error";
    } else {
        // Ensure checklist exists for patient
        $stmt_checklist = $conn->prepare("SELECT id FROM checklists WHERE patient_id = ? LIMIT 1");
        $stmt_checklist->bind_param("i", $patient_id);
        $stmt_checklist->execute();
        $res_checklist = $stmt_checklist->get_result();
        
        if ($res_checklist->num_rows > 0) {
            $checklist_id = $res_checklist->fetch_assoc()['id'];
        } else {
            $cs = $conn->prepare("INSERT INTO checklists (patient_id, created_by, title) VALUES (?, ?, 'Daily Medicines')");
            $cs->bind_param("ii", $patient_id, $doctor_id);
            $cs->execute();
            $checklist_id = $cs->insert_id;
        }
        
        foreach ($medicine_names as $index => $medicine_name) {
            $medicine_name = trim($medicine_name);
            $dosage = trim($dosages[$index]);
            $times_for_medicine = isset($all_times[$index]) ? $all_times[$index] : [];
            $duration_value = isset($duration_values[$index]) ? (int) $duration_values[$index] : 1;
            $duration_unit = $duration_units[$index] ?? 'days';
            $duration_days = convertDurationToDays($duration_value, $duration_unit);
            $time_str = implode(",", $times_for_medicine);

            if (!empty($medicine_name) && !empty($dosage) && !empty($time_str)) {
                $prescriptionPdf = createPrescriptionPdf(
                    $_SESSION['name'] ?? 'Doctor',
                    $patient_data['name'] ?? 'Patient',
                    $medicine_name,
                    $dosage,
                    $times_for_medicine,
                    $duration_days
                );
                $prescriptionDbPath = $prescriptionPdf ? $prescriptionPdf['db_path'] : null;

                $is = $conn->prepare("INSERT INTO checklist_items (checklist_id, medicine_name, dosage, times_of_day, status, prescribed_by, duration_days, prescription_file) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)");
                $is->bind_param("isssiis", $checklist_id, $medicine_name, $dosage, $time_str, $doctor_id, $duration_days, $prescriptionDbPath);
                
                if ($is->execute()) {
                    $success_count++;
                } else {
                    error_log("Error inserting medicine: " . $is->error);
                    $error_occurred = true;
                }
            }
        }

        if ($success_count > 0) {
            $msg = "$success_count prescription(s) issued successfully! They have been added to the patient's checklist.";
            $msg_type = "success";
            addUserNotification($conn, $patient_id, "New Prescription Issued", "Dr. " . $_SESSION['name'] . " has issued new prescription(s).");
        } elseif (!$error_occurred) {
            $msg = "No valid medicine entries were provided.";
            $msg_type = "error";
        } else {
            $msg = "An error occurred while issuing some prescriptions.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Issue Prescription — TELEMEDICINE</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root { --teal: #0EB8A0; --navy: #f8fafc; --navy-mid: #ffffff; --white: #1e293b; --muted: #64748b; --border: rgba(0,0,0,0.08); --radius: 14px; }
    body.dark-mode { --navy: #0B1526; --navy-mid: #112035; --white: #fff; --muted: #7A8EA8; --border: rgba(255,255,255,0.07); }
    body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: var(--white); margin: 0; padding: 40px; }
    .card { background: var(--navy-mid); border: 1px solid var(--border); border-radius: 20px; padding: 32px; max-width: 550px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    h1 { font-family: 'Clash Display', sans-serif; font-size: 1.6rem; margin-bottom: 8px; }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px; }
    .form-input { width: 100%; padding: 12px 16px; background: var(--navy); border: 1px solid var(--border); border-radius: 12px; color: var(--white); outline: none; box-sizing: border-box; }
    .btn { padding: 12px 24px; border-radius: 50px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; }
    .btn-primary { background: var(--teal); color: #0B1526; }
    .btn-secondary { background: var(--navy-light); color: var(--white); border: 1px solid var(--border); }
    .btn-danger { background: rgba(239,68,68,0.1); color: #EF4444; border: 1px solid rgba(239,68,68,0.2); }
    .alert { padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; }
    .alert-success { background: rgba(34,197,94,0.1); color: #22C55E; border: 1px solid rgba(34,197,94,0.2); }
    .alert-error { background: rgba(239,68,68,0.1); color: #EF4444; border: 1px solid rgba(239,68,68,0.2); }
    .time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: var(--navy); padding: 16px; border-radius: 12px; border: 1px solid var(--border); }
    .checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; }
    .checkbox-item input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--teal); }
    .medicine-entry { border: 1px solid var(--border); padding: 20px; border-radius: 16px; margin-bottom: 20px; background: var(--navy-light); position: relative; }
    .medicine-entry:not(:first-child) { margin-top: 20px; }
    .remove-medicine-btn { position: absolute; top: 10px; right: 10px; padding: 5px 10px; font-size: 0.75rem; }
</style>
<link rel="stylesheet" href="../css/ui-refresh.css">
<script src="../js/page-transition.js"></script>
</head><body>
    <div class="card">
        <a href="patients.php" class="btn btn-secondary btn-sm" style="margin-bottom: 20px;">← Back</a>
        <h1>💊 Issue Prescription</h1>
        <p style="color:var(--muted); margin-bottom: 24px; font-size: 0.9rem;">Issuing for: <strong><?php echo htmlspecialchars($patient_data['name']); ?></strong></p>
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo ($msg_type == 'success' ? '✅ ' : '❌ ') . $msg; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div id="medicine-entries-container">
                <div class="medicine-entry" id="medicine-entry-0">
                    <h3 style="font-family:'Clash Display',sans-serif;font-size:1.1rem;margin-bottom:15px;color:var(--white);">Medicine #1</h3>
                    <div class="form-group">
                        <label class="form-label">Medicine Name</label>
                        <input type="text" name="medicine_name[]" class="form-input" placeholder="e.g. Amoxicillin 500mg" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dosage Instructions</label>
                        <input type="text" name="dosage[]" class="form-input" placeholder="e.g. 1 capsule after meals" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Daily Reminders</label>
                        <div class="time-grid">
                            <label class="checkbox-item">
                                <input type="checkbox" name="medicine_time[0][]" value="morning"> 🌅 Morning
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="medicine_time[0][]" value="afternoon"> ☀️ Afternoon
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="medicine_time[0][]" value="evening"> 🌆 Evening
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="medicine_time[0][]" value="night"> 🌙 Night
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration</label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <input type="number" name="duration_value[]" class="form-input" min="1" value="1" required>
                            <select name="duration_unit[]" class="form-input">
                                <option value="days">Days</option>
                                <option value="weeks">Weeks</option>
                                <option value="months">Months</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm remove-medicine-btn" style="display:none;">Remove</button>
                </div>
            </div>
            <button type="button" id="add-more-medicine" class="btn btn-secondary" style="width:100%; justify-content:center; margin-top:10px;">+ Add Another Medicine</button>
            <button type="submit" name="issue_prescription" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;">
                🚀 Issue Digital Prescription
            </button>
        </form>
    </div>
    <script>
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');

        let medicineCount = 1;
        document.getElementById('add-more-medicine').addEventListener('click', function() {
            const container = document.getElementById('medicine-entries-container');
            const template = document.getElementById('medicine-entry-0');
            const newEntry = template.cloneNode(true);
            
            newEntry.id = 'medicine-entry-' + medicineCount;
            newEntry.querySelector('h3').textContent = 'Medicine #' + (medicineCount + 1);
            newEntry.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
            newEntry.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.name = `medicine_time[${medicineCount}][]`;
            });
            newEntry.querySelector('.remove-medicine-btn').style.display = 'block';
            newEntry.querySelector('.remove-medicine-btn').onclick = function() {
                newEntry.remove();
                // Re-index titles if needed (optional, but good for UX)
                document.querySelectorAll('.medicine-entry').forEach((entry, idx) => {
                    entry.querySelector('h3').textContent = 'Medicine #' + (idx + 1);
                });
            };
            container.appendChild(newEntry);
            medicineCount++;
        });
    </script>
</body></html>

