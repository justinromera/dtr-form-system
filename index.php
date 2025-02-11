<?php
session_start();

// Firebase Database URL
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";

// Fetch users from Firebase
$users_json = file_get_contents($firebase_url);
$users_data = json_decode($users_json, true) ?? [];

// Handle login
$loginError = "";
if (isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $user_found = false;

    // Loop through users and check credentials
    foreach ($users_data as $user_id => $user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            echo "<script>alert('Login successful!'); window.location.href='userDashboard.php';</script>";
            exit();
        }
    }

    $loginError = "Invalid email or password!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - DTR System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="main-container">
        <div class="image-section">

        <img src="cpcpa.jpg" alt="DTR System Logo">
        </div>

        <div class="login-container">
            <div class="login-header">
                <!-- <img src="../img/bfp2.jpg" alt="DTR System Logo"> -->
                <h1>DTR System</h1>
                <p>Welcome! Please log in to your account.</p>
            </div>
            <form action="" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <input type="email" id="email" name="email" placeholder="Email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <i class="fas fa-eye eye-icon" onclick="togglePasswordVisibility('password')"></i>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" name="login">Login</button>
                </div>
                <p style="color: red;" class="error-message"><?php echo htmlspecialchars($loginError); ?></p>
            </form>
            <!-- <div class="login-footer">
                <p>Don't have an account? <a href="signup.php">Signup here</a></p>
                <p><a href="../forgot_password.php" id="forgotPasswordLink">Forgot your password?</a></p>
            </div> -->
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
        }
    </script>
</body>
</html>

