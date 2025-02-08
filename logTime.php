<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user.php");
    exit();
}

// Firebase Database URL
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs.json";

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
        case 'AM Arrival':
            $log_key = 'am_arrival';
            break;
        case 'AM Departure':
            $log_key = 'am_departure';
            break;
        case 'PM Arrival':
            $log_key = 'pm_arrival';
            break;
        case 'PM Departure':
            $log_key = 'pm_departure';
            break;
    }

    // Prepare log data
    $log_data = [
        $log_key => $log_time
    ];

    // Update Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "PATCH",
            "content" => json_encode($log_data)
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents("https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs/$user_id/$current_date.json", false, $context);

    // Redirect back to the dashboard
    header("Location: userDashboard.php");
    exit();
}
?>
