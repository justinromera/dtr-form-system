<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user.php");
    exit();
}

// Firebase Database URLs
$firebase_users_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs.json";

// Fetch users and logs from Firebase
$users_json = file_get_contents($firebase_users_url);
$logs_json = file_get_contents($firebase_logs_url);
$users_data = json_decode($users_json, true) ?? [];
$logs_data = json_decode($logs_json, true) ?? [];

// Get current user details
$user_id = $_SESSION['user_id'];
$user = $users_data[$user_id] ?? [];
$user_logs = $logs_data[$user_id] ?? [];

// Check if user has changed their password
$has_changed_password = $user['password_updated'] ?? false;

// Handle password change
if (isset($_POST['change_password'])) {
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $update_data = [
        "password" => $new_password,
        "password_updated" => true // Mark as changed
    ];

    // Update Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "PATCH",
            "content" => json_encode($update_data)
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents("https://dtr-system-a192a-default-rtdb.firebaseio.com/users/$user_id.json", false, $context);

    echo "<script>alert('Password changed successfully!'); window.location.href='userDashboard.php';</script>";
    exit();
}

// Get month filter (default: current month)
$selected_month = $_GET['month'] ?? date('Y-m');

// Get search date
$search_date = $_GET['search'] ?? '';

// Filter logs by month & search date
$filtered_logs = [];
foreach ($user_logs as $log_date => $log) {
    if (strpos($log_date, $selected_month) === 0) { // Match YYYY-MM format
        if ($search_date === '' || $log_date === $search_date) {
            $filtered_logs[$log_date] = $log;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>

        <!-- Filters: Month & Search -->
        <div class="d-flex justify-content-between mb-3">
            <form method="GET" class="d-flex">
                <label class="me-2 align-self-center"><b>Filter by Month:</b></label>
                <input type="month" name="month" class="form-control me-2" value="<?php echo $selected_month; ?>">
                <button type="submit" class="btn btn-primary">Apply</button>
                
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#timeLogModal">
    Log Time
</button>
            <form method="GET" class="d-flex">
                <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                <input type="date" name="search" class="form-control me-2" value="<?php echo $search_date; ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>

        <!-- User Logs Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>AM Arrival</th>
                        <th>PM Arrival</th>
                        <th>AM Departure</th>
                        <th>PM Departure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filtered_logs)) : ?>
                        <?php foreach ($filtered_logs as $log_date => $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log_date); ?></td>
                                <td><?php echo $log['am_arrival'] ?? '---'; ?></td>
                                <td><?php echo $log['pm_arrival'] ?? '---'; ?></td>
                                <td><?php echo $log['am_departure'] ?? '---'; ?></td>
                                <td><?php echo $log['pm_departure'] ?? '---'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" class="text-center">No records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password:</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<!-- Time Log Modal -->
<div class="modal fade" id="timeLogModal" tabindex="-1" aria-labelledby="timeLogModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="timeLogModalLabel">Log Your Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="timeLogForm" method="POST" action="log_time.php">
                    <div class="mb-3">
                        <label for="logType" class="form-label">Action</label>
                        <input type="text" class="form-control" id="logType" name="logType" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="logTime" class="form-label">Select Time</label>
                        <input type="datetime-local" class="form-control" id="logTime" name="logTime" required>
                    </div>
                    <button type="submit" class="btn btn-success">Log</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('logTime').addEventListener('input', function(event) {
    const selectedTime = new Date(event.target.value);
    const now = new Date();
    if (selectedTime < now) {
        alert('You cannot select a past time!');
        event.target.value = '';
    }
});
</script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Show the password modal only if the user hasn't changed their password
        <?php if (!$has_changed_password): ?>
            var changePasswordModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
            changePasswordModal.show();
        <?php endif; ?>
    </script>

</body>
</html>
