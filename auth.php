<?php
session_start();

// Prevent back page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if the logged-in user is an admin
$admin_email = "admin@admin.com"; // Default admin email
if ($_SESSION['user_email'] !== $admin_email) {
    header("Location: index.php");
    exit();
}
?>
