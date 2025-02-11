<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Firebase Database URL
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs";

// Get current user details
$user_id = $_SESSION['user_id'];

// Handle time log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_type = $_POST['logType'];
    $log_time = $_POST['logTime'];
    $current_date = date('Y-m-d');
    $log_key = '';

    // Determine the log key based on log type
    switch ($log_type) {
        case 'am_arrival':
            $log_key = 'am_arrival';
            break;
        case 'am_departure':
            $log_key = 'am_departure';
            break;
        case 'pm_arrival':
            $log_key = 'pm_arrival';
            break;
        case 'pm_departure':
            $log_key = 'pm_departure';
            break;
    }

    // Prepare log data
    $log_data = [
        $log_key => $log_time
    ];

    // Fetch existing logs for the user
    $logs_json = file_get_contents("$firebase_logs_url/$user_id/$current_date.json");
    $existing_logs = json_decode($logs_json, true) ?? [];

    // Merge existing logs with new log data
    $updated_logs = array_merge($existing_logs, $log_data);

    // Update Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "PATCH",
            "content" => json_encode($updated_logs)
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents("$firebase_logs_url/$user_id/$current_date.json", false, $context);

    // Redirect back to the dashboard
    header("Location: userDashboard.php");
    exit();
}
?>
