<?php

// Image verification helper
function imageExists($path) {
    return !empty($path) && file_exists($path);
}

// Get emoji for time period
function getTimeEmoji($time_str) {
    if (empty($time_str)) return '🌙';
    // Handle HH:MM format from HTML5 time input
    if (preg_match('/^(\d{2}):(\d{2})/', $time_str, $matches)) {
        $hour = intval($matches[1]);
    } else {
        $time = strtotime($time_str);
        $hour = intval(date('H', $time));
    }
    if ($hour >= 6 && $hour < 12) return '🌅';
    if ($hour >= 12 && $hour < 17) return '☀️';
    if ($hour >= 17 && $hour < 21) return '🌆';
    return '🌙';
}

// Get time of day category
function getTimeOfDayCategory($time_str) {
    if (empty($time_str)) return 'night';
    // Handle HH:MM format from HTML5 time input
    if (preg_match('/^(\d{2}):(\d{2})/', $time_str, $matches)) {
        $hour = intval($matches[1]);
    } else {
        $time = strtotime($time_str);
        $hour = intval(date('H', $time));
    }
    if ($hour >= 6 && $hour < 12) return 'morning';
    if ($hour >= 12 && $hour < 17) return 'afternoon';
    if ($hour >= 17 && $hour < 21) return 'evening';
    return 'night';
}

// Convert time to word format with emojis
function getTimeInWords($time_str) {
    if (empty($time_str)) return 'Not set';
    $times = explode(',', $time_str);
    $emojis = array();
    foreach ($times as $t) {
        $t = trim($t);
        switch(getTimeOfDayCategory($t)) {
            case 'morning': $emojis[] = '🌅 Morning'; break;
            case 'afternoon': $emojis[] = '☀️ Afternoon'; break;
            case 'evening': $emojis[] = '🌆 Evening'; break;
            case 'night': $emojis[] = '🌙 Night'; break;
        }
    }
    return implode(' & ', array_unique($emojis));
}
?>
