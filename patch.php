<?php
$files = [
    'patient/view_patient_reports.php',
    'patient/share_reports.php',
    'patient/friends.php',
    'patient/dashboard.php',
    'doctor/dashboard.php',
    'doctor/monitor_patients.php',
    'doctor/friends.php',
    'doctor/view_patient_reports.php',
    'doctor/add_patient_monitor.php',
    'patient/add_monitor.php',
    'patient/monitor_view.php',
    'doctor/monitor_requests.php'
];

$count = 0;
foreach ($files as $file) {
    $path = 'c:/xampp/htdocs/telemedicine_system/' . $file;
    if (!file_exists($path)) {
        continue;
    }
    
    $content = file_get_contents($path);
    
    if (strpos($content, 'id="accountBtn"') !== false) {
        continue; // already patched
    }
    
    if (strpos($content, '<div class="notif-container">') === false) {
        continue;
    }

    // 1. Replace <div class="notif-container">
    $html_start = <<<EOT
    <?php 
    \$acc_user_id = isset(\$patient_id) ? \$patient_id : (isset(\$doctor_id) ? \$doctor_id : \$_SESSION['user_id']);
    \$user_q_acc = \$conn->query("SELECT email, address FROM users WHERE id = '\$acc_user_id'");
    \$user_data_acc = \$user_q_acc ? \$user_q_acc->fetch_assoc() : null;
    \$user_email_acc = \$user_data_acc ? \$user_data_acc['email'] : 'N/A';
    \$user_address_acc = (\$user_data_acc && !empty(\$user_data_acc['address'])) ? \$user_data_acc['address'] : 'Not provided';
    ?>
    <div class="notif-container" style="display:flex; gap:15px; align-items:center;">
        <div style="position:relative; display:inline-block;">
EOT;
    $content = preg_replace('/<div class="notif-container">/', $html_start, $content, 1);
    
    $html_end = <<<EOT
        </div> <!-- end relative wrapper -->
        
        <!-- Account Dropdown -->
        <div style="position:relative; display:inline-block;">
            <div class="notif-btn" id="accountBtn" style="border-radius:50%; width:44px; height:44px; justify-content:center; padding:0; background:var(--teal-glow); color:var(--teal); border:1px solid rgba(14,184,160,0.3);">👤</div>
            <div class="notif-dropdown" id="accountDropdown" style="right:0; width:280px; padding:16px;">
                <div style="text-align:center; margin-bottom:16px;">
                    <div style="width:60px; height:60px; border-radius:50%; background:var(--teal); color:var(--navy); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 12px auto; font-weight:bold;">
                        <?php echo strtoupper(substr(\$_SESSION['name'], 0, 1)); ?>
                    </div>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--white); margin-bottom:4px;"><?php echo htmlspecialchars(\$_SESSION['name']); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted); margin-bottom:4px;">📧 <?php echo htmlspecialchars(\$user_email_acc); ?></div>
                    <div style="font-size:0.85rem; color:var(--muted);">📍 <?php echo htmlspecialchars(\$user_address_acc); ?></div>
                </div>
                <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
                    <a href="../logout.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:10px; background:rgba(239,68,68,0.1); color:var(--danger); text-decoration:none; border-radius:12px; font-weight:600; transition:0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">🚪 Logout</a>
                </div>
            </div>
        </div>
EOT;
    
    $js_replace = <<<EOT
document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
    if(document.getElementById('accountDropdown')) document.getElementById('accountDropdown').classList.remove('show');
});
if(document.getElementById('accountBtn')) {
    document.getElementById('accountBtn').addEventListener('click', function(e){
        document.getElementById('accountDropdown').classList.toggle('show');
        if(document.getElementById('notifDropdown')) document.getElementById('notifDropdown').classList.remove('show');
    });
}
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){
        if(document.getElementById('notifDropdown')) document.getElementById('notifDropdown').classList.remove('show');
        if(document.getElementById('accountDropdown')) document.getElementById('accountDropdown').classList.remove('show');
    }
});
EOT;

    // 2. Replace JS
    $content = preg_replace("/document\.getElementById\('notifBtn'\)\.addEventListener.*?classList\.remove\('show'\);\}\n\}\);/is", $js_replace, $content);
    
    // 3. Inject html_end
    // Match the exact ending of notif-container
    $content = preg_replace('/(<\?php endif; \?>\s*<\/div>\s*<\/div>)(\s*<\/div>)/', "$1\n" . $html_end . "$2", $content, 1);

    file_put_contents($path, $content);
    $count++;
    echo "Patched $file\n";
}
echo "Total patched: $count\n";
?>
