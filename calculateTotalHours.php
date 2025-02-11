<?php
// Firebase Database URLs
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/";

// Fetch user logs
$logs_json = file_get_contents($firebase_url . "user_logs.json");
$logs_data = json_decode($logs_json, true) ?? [];

// Fetch schedules
$schedules_json = file_get_contents($firebase_url . "user_schedules.json");
$schedules_data = json_decode($schedules_json, true) ?? [];

// Function to calculate total rendered hours for a single log entry based on schedule
function calculate_hours($log, $schedule) {
    if (
        isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure']) &&
        !empty($log['am_arrival']) && !empty($log['am_departure']) && 
        !empty($log['pm_arrival']) && !empty($log['pm_departure'])
    ) {
        $am_time_in = isset($schedule['am_time_in']) ? strtotime($schedule['am_time_in']) : 0;
        $pm_time_out = isset($schedule['pm_time_out']) ? strtotime($schedule['pm_time_out']) : 0;
        $am_arrival = max(strtotime($log['am_arrival']), $am_time_in);
        $am_departure = strtotime($log['am_departure']);
        $pm_arrival = strtotime($log['pm_arrival']);
        $pm_departure = min(strtotime($log['pm_departure']), $pm_time_out);

        $morning_seconds = max(0, $am_departure - $am_arrival);
        $afternoon_seconds = max(0, $pm_departure - $pm_arrival);
        $total_seconds = $morning_seconds + $afternoon_seconds;

        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);

        return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
               ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
    }
    return '---';
}

// Function to calculate total hours for all logs of a user based on schedule
function calculate_total_hours($logs, $schedules) {
    $total_seconds = 0;
    foreach ($logs as $log_date => $log) {
        if (
            isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure']) &&
            !empty($log['am_arrival']) && !empty($log['am_departure']) && 
            !empty($log['pm_arrival']) && !empty($log['pm_departure'])
        ) {
            $am_time_in = isset($schedules[$log_date]['am_time_in']) ? strtotime($schedules[$log_date]['am_time_in']) : 0;
            $pm_time_out = isset($schedules[$log_date]['pm_time_out']) ? strtotime($schedules[$log_date]['pm_time_out']) : 0;
            $am_arrival = max(strtotime($log['am_arrival']), $am_time_in);
            $am_departure = strtotime($log['am_departure']);
            $pm_arrival = strtotime($log['pm_arrival']);
            $pm_departure = min(strtotime($log['pm_departure']), $pm_time_out);

            $morning_seconds = max(0, $am_departure - $am_arrival);
            $afternoon_seconds = max(0, $pm_departure - $pm_arrival);
            $total_seconds += $morning_seconds + $afternoon_seconds;
        }
    }
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);

    return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
           ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
}

// Example usage
$user_id = 'user1'; // Replace with the actual user ID
$user_logs = $logs_data[$user_id] ?? [];
$user_schedules = $schedules_data[$user_id] ?? [];

$total_hours = calculate_total_hours($user_logs, $user_schedules);
echo "Total hours for user $user_id: $total_hours";
?>
