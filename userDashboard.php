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
$firebase_schedules_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_schedules.json";

// Fetch users, logs, and schedules from Firebase
$users_json = file_get_contents($firebase_users_url);
$logs_json = file_get_contents($firebase_logs_url);
$schedules_json = file_get_contents($firebase_schedules_url);
$users_data = json_decode($users_json, true) ?? [];
$logs_data = json_decode($logs_json, true) ?? [];
$schedules_data = json_decode($schedules_json, true) ?? [];

// Get current user details
$user_id = $_SESSION['user_id'];
$user = $users_data[$user_id] ?? [];
$user_logs = $logs_data[$user_id] ?? [];
$user_schedules = $schedules_data[$user_id] ?? [];

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

// Function to convert time to 12-hour format
function format_time($time) {
    return ($time && $time !== '---' && $time !== 'ABSENT') ? date("g:i A", strtotime($time)) : $time;
}

// Function to calculate total rendered hours for a single log entry
function calculate_hours($log) {
    if (
        isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure']) &&
        !empty($log['am_arrival']) && !empty($log['am_departure']) && 
        !empty($log['pm_arrival']) && !empty($log['pm_departure'])
    ) {
        $morning_seconds = max(0, strtotime($log['am_departure']) - strtotime($log['am_arrival']));
        $afternoon_seconds = max(0, strtotime($log['pm_departure']) - strtotime($log['pm_arrival']));
        $total_seconds = $morning_seconds + $afternoon_seconds;

        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);

        return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
               ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
    }
    return '---';
}

// Function to calculate total hours for all logs of a user
function calculate_total_hours($logs) {
    $total_seconds = 0;
    foreach ($logs as $log) {
        if (
            isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure']) &&
            !empty($log['am_arrival']) && !empty($log['am_departure']) && 
            !empty($log['pm_arrival']) && !empty($log['pm_departure'])
        ) {
            $morning_seconds = max(0, strtotime($log['am_departure']) - strtotime($log['am_arrival']));
            $afternoon_seconds = max(0, strtotime($log['pm_departure']) - strtotime($log['pm_arrival']));
            $total_seconds += $morning_seconds + $afternoon_seconds;
        }
    }
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);

    return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
           ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
}

// Calculate total hours for the selected month based on logs
$total_hours_month = calculate_total_hours($filtered_logs);

// Calculate total hours for the entire time based on logs
$total_hours_all_time = calculate_total_hours($user_logs);

// Determine if the user has already logged all required times for the day
$today_date = date('Y-m-d');
$already_logged_for_day = isset($user_logs[$today_date]) && 
                          isset($user_logs[$today_date]['am_arrival']) && 
                          isset($user_logs[$today_date]['am_departure']) && 
                          isset($user_logs[$today_date]['pm_arrival']) && 
                          isset($user_logs[$today_date]['pm_departure']);

// Determine available log types based on existing logs for the day
$available_log_types = [];
if (!isset($user_logs[$today_date]['am_arrival'])) {
    $available_log_types[] = 'AM Arrival';
}
if (!isset($user_logs[$today_date]['am_departure'])) {
    $available_log_types[] = 'AM Departure';
}
if (!isset($user_logs[$today_date]['pm_arrival'])) {
    $available_log_types[] = 'PM Arrival';
}
if (!isset($user_logs[$today_date]['pm_departure'])) {
    $available_log_types[] = 'PM Departure';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #198D5E;
            color: white;
        }
        .navbar-brand {
            font-weight: bold;
            color: white;
        }
        .navbar-nav .nav-link {
            color: white;
        }
        .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .container {
            padding: 20px;
        }
        .table th, .table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .btn {
            margin-bottom: 10px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            .d-flex {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                width: 100%;
            }
            .filters-container {
                flex-direction: column;
                gap: 10px;
            }
            .table thead {
                display: none;
            }
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            .table tr {
                margin-bottom: 15px;
                border-bottom: 2px solid #ddd;
                padding-bottom: 10px;
            }
            .table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                font-weight: bold;
                text-align: left;
            }
        }
        .input-button {
            display: flex;
            flex-direction: row;
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">DTR System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-warning text-white" href="#changePasswordModal" data-bs-toggle="modal">Change Password</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="mb-4 d-flex justify-content-between flex-wrap filters-container">
            <button class="btn" id="" data-bs-toggle="modal" data-bs-target="#summaryModal">View Summary</button>
        </div>

        <div class="d-flex justify-content-between mb-3 flex-wrap filters-container">
            <form method="GET" class="d-flex flex-wrap">
                <div class="input-button">
                    <input type="month" name="month" class="form-control me-2 mb-2" value="<?php echo $selected_month; ?>">
                    <button type="submit" class="btn  me-2 mb-2">Apply</button>
                </div>
            </form>
            <form method="GET" class="d-flex flex-wrap">
                <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                <div class="input-button">  
                    <input type="date" name="search" class="form-control me-2 mb-2" value="<?php echo $search_date; ?>">
                    <button type="submit" class="btn  me-2 mb-2">Search</button>
                </div>
            </form>
            <button class="btn mb-2" data-bs-toggle="modal" data-bs-target="<?php echo $already_logged_for_day ? '#alreadyLoggedModal' : '#timeLogModal'; ?>">
                Log Time
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>Time In (AM)</th>
                        <th>Time Out (AM)</th>
                        <th>Time In (PM)</th>
                        <th>Time Out (PM)</th>
                        <th>Total Hours Rendered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filtered_logs)) : ?>
                        <?php foreach ($filtered_logs as $log_date => $log): ?>
                            <tr>
                                <td data-label="Date"><?php echo htmlspecialchars($log_date); ?></td>
                                <td data-label="Time In (AM)"><?php echo isset($log['am_arrival']) ? format_time($log['am_arrival']) : '---'; ?></td>
                                <td data-label="Time Out (AM)"><?php echo isset($log['am_departure']) ? format_time($log['am_departure']) : '---'; ?></td>
                                <td data-label="Time In (PM)"><?php echo isset($log['pm_arrival']) ? format_time($log['pm_arrival']) : '---'; ?></td>
                                <td data-label="Time Out (PM)"><?php echo isset($log['pm_departure']) ? format_time($log['pm_departure']) : '---'; ?></td>
                                <td data-label="Total Hours Rendered"><?php echo calculate_hours($log); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" class="text-center">No records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
                        <?php if ($has_changed_password): ?>
                            <div class="mb-3">
                                <label for="previous_password" class="form-label">Previous Password:</label>
                                <input type="password" name="previous_password" id="previous_password" class="form-control" required>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password:</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password:</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary w-100">Update Password</button>
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
                    <form id="timeLogForm" method="POST" action="logTime.php">
                        <div class="mb-3">
                            <label for="logType" class="form-label">Action</label>
                            <select class="form-control" id="logType" name="logType">
                                <?php foreach ($available_log_types as $log_type): ?>
                                    <option value="<?php echo strtolower(str_replace(' ', '_', $log_type)); ?>"><?php echo $log_type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="logTime" class="form-label">Select Time</label>
                            <div class="input-group">
                                <input type="time" class="form-control" id="logTime" name="logTime" value="<?php echo date('H:i'); ?>" required>
                                <button type="button" class="btn btn-secondary" id="setNowButton">Now</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Log</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Already Logged Modal -->
    <div class="modal fade" id="alreadyLoggedModal" tabindex="-1" aria-labelledby="alreadyLoggedLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="alreadyLoggedLabel">Already Logged</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You have already logged all required times for today.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Modal -->
    <div class="modal fade" id="summaryModal" tabindex="-1" aria-labelledby="summaryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="summaryModalLabel">Summary of Rendered Hours</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Total Hours for <?php echo date('F Y', strtotime($selected_month)); ?>:</strong> <?php echo $total_hours_month; ?></p>
                    <p><strong>Total Hours for Entire Time:</strong> <?php echo $total_hours_all_time; ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

<script>
document.getElementById('setNowButton').addEventListener('click', function() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('logTime').value = `${hours}:${minutes}`;
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