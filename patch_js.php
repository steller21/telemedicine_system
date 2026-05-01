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
    'doctor/monitor_requests.php',
    'patient/view_monitor_reports.php',
    'patient/view_patient_checklist.php',
    'patient/chat.php',
    'doctor/chat.php'
];

foreach ($files as $file) {
    $path = 'c:/xampp/htdocs/telemedicine_system/' . $file;
    if (!file_exists($path)) {
        continue;
    }
    
    $content = file_get_contents($path);
    
    if (strpos($content, "document.getElementById('accountBtn')") !== false) {
        continue;
    }

    $js_search1 = <<<EOT
document.getElementById('notifBtn').addEventListener('click', function(e){
    document.getElementById('notifDropdown').classList.toggle('show');
});
window.addEventListener('click', function(e){
    if(!e.target.closest('.notif-container')){document.getElementById('notifDropdown').classList.remove('show');}
});
EOT;

    $js_replace1 = <<<EOT
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

    $content = str_replace($js_search1, $js_replace1, $content);

    // Some files might have missing newlines or different spacing.
    $js_search2 = "document.getElementById('notifBtn').addEventListener('click', function(e){\r\n    document.getElementById('notifDropdown').classList.toggle('show');\r\n});\r\nwindow.addEventListener('click', function(e){\r\n    if(!e.target.closest('.notif-container')){document.getElementById('notifDropdown').classList.remove('show');}\r\n});";
    $content = str_replace($js_search2, $js_replace1, $content);
    
    // Fallback regex
    $content = preg_replace('/document\.getElementById\(\'notifBtn\'\)\.addEventListener\(\'click\', function\(e\)\{\s*document\.getElementById\(\'notifDropdown\'\)\.classList\.toggle\(\'show\'\);\s*\}\);\s*window\.addEventListener\(\'click\', function\(e\)\{\s*if\(!e\.target\.closest\(\'\.notif-container\'\)\)\{\s*document\.getElementById\(\'notifDropdown\'\)\.classList\.remove\(\'show\'\);\s*\}\s*\}\);/s', $js_replace1, $content);

    file_put_contents($path, $content);
    echo "Patched JS in $file\n";
}
?>
