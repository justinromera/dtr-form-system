<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require 'PHPMailermaster/src/Exception.php';
require 'PHPMailermaster/src/PHPMailer.php';
require 'PHPMailermaster/src/SMTP.php';

// Firebase Database URL
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";

// Function to send email
function sendEmail($recipient_email, $generated_password) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sumtingwong010@gmail.com';  // Change this
        $mail->Password   = 'wyud ofas gxua dhph';    // Change this (Use App Password, not your actual password)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Disable SSL certificate verification
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Email Content
        $mail->setFrom('sumtingwong010@gmail.com', 'DTR System');
        $mail->addAddress($recipient_email);
        $mail->isHTML(true);
        $mail->Subject = 'Your DTR System Login Credentials';
        $mail->Body    = "<p>Dear User,</p>
                          <p>Your account has been created in the DTR System.</p>
                          <p><b>Email:</b> $recipient_email</p>
                          <p><b>Password:</b> $generated_password</p>
                          <p>Please change your password upon first login.</p>";

        // Send Email
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Handle Form Submission
if (isset($_POST['submit'])) {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // Generate a random password
    $generated_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*'), 0, 10);

    // Prepare user data
    $new_user = [
        "name" => $fullname,
        "email" => $email,
        "phone" => $phone,
        "password" => password_hash($generated_password, PASSWORD_DEFAULT) // Hash password for security
    ];

    // Send data to Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "POST",
            "content" => json_encode($new_user)
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($firebase_url, false, $context);

    if ($result && sendEmail($email, $generated_password)) {
        echo "<script>alert('User added successfully! Email with login credentials has been sent.'); window.location.href='admin.php';</script>";
    } else {
        echo "<script>alert('Failed to add user or send email.'); window.location.href='admin.php';</script>";
    }
}
?>
