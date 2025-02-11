<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if the logged-in user is an admin
$admin_email = "admin@example.com"; // Replace with the actual admin email
if ($_SESSION['user_email'] !== $admin_email) {
    header("Location: index.php");
    exit();
}
?>
