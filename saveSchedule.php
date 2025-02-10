<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user.php");
    exit();
}

// Firebase Database URL
$firebase_schedules_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_schedules";

// Get the input data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user'];
$date = $input['date'];
$am_time_in = $input['am_time_in'];
$pm_time_out = $input['pm_time_out'];

// Prepare schedule data
$schedule_data = [
    'am_time_in' => $am_time_in,
    'pm_time_out' => $pm_time_out
];

// Update Firebase
$options = [
    "http" => [
        "header"  => "Content-type: application/json",
        "method"  => "PATCH",
        "content" => json_encode($schedule_data)
    ]
];
$context  = stream_context_create($options);
$response = file_get_contents("$firebase_schedules_url/$user_id/$date.json", false, $context);

// Check if the update was successful
if ($response !== false) {
    // Return success response
    echo json_encode(['status' => 'success']);
} else {
    // Return failure response
    echo json_encode(['status' => 'failure']);
}
?>
