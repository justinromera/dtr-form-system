<?php
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";

// Default admin credentials
$admin_email = "admin@admin.com";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);
$admin_name = "Admin User";

// Fetch existing users
$users_json = file_get_contents($firebase_url);
$users_data = json_decode($users_json, true) ?? [];

// Check if admin user already exists
$admin_exists = false;
foreach ($users_data as $user) {
    if ($user['email'] === $admin_email) {
        $admin_exists = true;
        break;
    }
}

// Add admin user if not exists
if (!$admin_exists) {
    $admin_id = uniqid();
    $users_data[$admin_id] = [
        'email' => $admin_email,
        'password' => $admin_password,
        'name' => $admin_name
    ];

    // Update Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "PATCH",
            "content" => json_encode($users_data)
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents($firebase_url, false, $context);

    echo "Admin user created successfully.";
} else {
    echo "Admin user already exists.";
}
?>
