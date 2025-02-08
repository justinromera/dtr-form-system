<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $time = $_POST['time'];
    $time_type = $_POST['time_type'];

    $firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs.json";

    $logData = json_encode([
        "user_id" => $user_id,
        $time_type => $time
    ]);

    // Send data to Firebase
    $options = [
        "http" => [
            "header" => "Content-type: application/json",
            "method" => "POST",
            "content" => $logData
        ]
    ];

    $context = stream_context_create($options);
    file_get_contents($firebase_url, false, $context);

    header("Location: index.php");
}
?>
