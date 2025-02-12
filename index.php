<?php
session_start();

// Firebase Database URLs
$firebase_users_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";
$firebase_admins_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/admins.json";

// Fetch users and admins from Firebase
$users_json = file_get_contents($firebase_users_url);
$admins_json = file_get_contents($firebase_admins_url);
$users_data = json_decode($users_json, true) ?? [];
$admins_data = json_decode($admins_json, true) ?? [];

// Handle login
$loginError = "";
if (isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $user_found = false;

    // Check admin credentials
    foreach ($admins_data as $admin_id => $admin) {
        if ($admin['email'] === $email && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin_id;
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['user_email'] = $admin['email'];

            echo "<script>alert('Login successful!'); window.location.href='admin.php';</script>";
            exit();
        }
    }

    // Check user credentials
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
    <style>
        /* Add responsive styles */
        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background-color: #fff;
            box-sizing: border-box;
        }

        .login-header h1 {
            font-size: 24px;
        }

        .login-header p {
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-size: 14px;
        }

        .form-group .input-group {
            display: flex;
            align-items: center;
        }

        .form-group .input-group input {
            flex: 1;
            padding: 10px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group .input-group .eye-icon {
            margin-left: -30px;
            cursor: pointer;
        }

        .form-group button {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-group button:hover {
            background-color: #0056b3;
        }

        .error-message {
            font-size: 14px;
        }

        @media (max-width: 400px) {
            .login-container {
                padding: 10px;
                margin: 50px;
            }

            .form-group .input-group input {
                padding: 8px;
            }

            .form-group button {
                padding: 8px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
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

