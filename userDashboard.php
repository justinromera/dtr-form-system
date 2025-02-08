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

// Determine default log type based on current logs
$default_log_type = 'AM Arrival';
$current_hour = date('H');
$available_log_types = [];
if (!empty($user_logs)) {
    $latest_log = end($user_logs);
    if (isset($latest_log['am_arrival']) && !isset($latest_log['am_departure'])) {
        $default_log_type = 'AM Departure';
        $available_log_types = ['AM Departure'];
    } elseif (isset($latest_log['am_departure']) && !isset($latest_log['pm_arrival'])) {
        $default_log_type = 'PM Arrival';
        $available_log_types = ['PM Arrival'];
    } elseif (isset($latest_log['pm_arrival']) && !isset($latest_log['pm_departure'])) {
        $default_log_type = 'PM Departure';
        $available_log_types = ['PM Departure'];
    } elseif ($current_hour >= 12 && !isset($latest_log['am_arrival'])) {
        // Mark as absent for AM and only show PM options
        $default_log_type = 'PM Arrival';
        $available_log_types = ['PM Arrival', 'PM Departure'];
    }
} else {
    if ($current_hour < 12) {
        $available_log_types = ['AM Arrival'];
    } else {
        $available_log_types = ['PM Arrival', 'PM Departure'];
    }
}

// Determine if the user has already logged all required times for the day
$already_logged_for_day = false;
if (!empty($user_logs)) {
    $latest_log = end($user_logs);
    if (isset($latest_log['am_arrival']) && isset($latest_log['am_departure']) && isset($latest_log['pm_arrival']) && isset($latest_log['pm_departure'])) {
        $already_logged_for_day = true;
    }
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

// Get current time for default log time
$current_time = date('H:i');

// Function to calculate total hours rendered in hours and minutes based on schedule
function calculate_hours_rendered($log, $schedule) {
    $total_seconds = 0;

    if (isset($schedule['am_time_in']) && isset($schedule['pm_time_out'])) {
        $am_time_in = strtotime($schedule['am_time_in']);
        $pm_time_out = strtotime($schedule['pm_time_out']);
        $total_seconds += ($pm_time_out - $am_time_in);
    }

    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);

    return sprintf('%02d hours and %02d minutes', $hours, $minutes);
}

// Function to convert time to 12-hour format
function convert_to_12hr($time) {
    return date('h:i A', strtotime($time));
}

// Function to calculate total hours for a given period based on schedule
function calculate_total_hours($logs, $schedules) {
    $total_seconds = 0;
    foreach ($logs as $log_date => $log) {
        if (isset($schedules[$log_date]['am_time_in']) && isset($schedules[$log_date]['pm_time_out'])) {
            $am_time_in = strtotime($schedules[$log_date]['am_time_in']);
            $pm_time_out = strtotime($schedules[$log_date]['pm_time_out']);
            $total_seconds += ($pm_time_out - $am_time_in);
        }
    }
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    return sprintf('%02d hours and %02d minutes', $hours, $minutes);
}

// Calculate total hours for the selected month based on schedule
$total_hours_month = calculate_total_hours($filtered_logs, $user_schedules);

// Calculate total hours for the entire time based on schedule
$total_hours_all_time = calculate_total_hours($user_logs, $user_schedules);
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
        .container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-3 text-center">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        
        <div class="mb-4 d-flex justify-content-between flex-wrap filters-container">
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#summaryModal">View Summary</button>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</button>
        </div>

        <div class="d-flex justify-content-between mb-3 flex-wrap filters-container">
            <form method="GET" class="d-flex flex-wrap">
                <label class="me-2 align-self-center"><b>Filter by Month:</b></label>
                <input type="month" name="month" class="form-control me-2 mb-2" value="<?php echo $selected_month; ?>">
                <button type="submit" class="btn btn-primary me-2 mb-2">Apply</button>
            </form>
            <form method="GET" class="d-flex flex-wrap">
                <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                <input type="date" name="search" class="form-control me-2 mb-2" value="<?php echo $search_date; ?>">
                <button type="submit" class="btn btn-primary me-2 mb-2">Search</button>
            </form>
            <button class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="<?php echo $already_logged_for_day ? '#alreadyLoggedModal' : '#timeLogModal'; ?>">
                Log Time
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>AM Arrival</th>
                        <th>PM Arrival</th>
                        <th>AM Departure</th>
                        <th>PM Departure</th>
                        <th>Total Hours Rendered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filtered_logs)) : ?>
                        <?php foreach ($filtered_logs as $log_date => $log): ?>
                            <tr>
                                <td data-label="Date"><?php echo htmlspecialchars($log_date); ?></td>
                                <td data-label="AM Arrival"><?php echo isset($log['am_arrival']) ? convert_to_12hr($log['am_arrival']) : '---'; ?></td>
                                <td data-label="PM Arrival"><?php echo isset($log['pm_arrival']) ? convert_to_12hr($log['pm_arrival']) : '---'; ?></td>
                                <td data-label="AM Departure"><?php echo isset($log['am_departure']) ? convert_to_12hr($log['am_departure']) : '---'; ?></td>
                                <td data-label="PM Departure"><?php echo isset($log['pm_departure']) ? convert_to_12hr($log['pm_departure']) : '---'; ?></td>
                                <td data-label="Total Hours Rendered"><?php echo calculate_hours_rendered($log, $user_schedules[$log_date] ?? []); ?></td>
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

        <a href="logout.php" class="btn btn-danger w-100">Logout</a>
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
                                    <option value="<?php echo $log_type; ?>"><?php echo $log_type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="logTime" class="form-label">Select Time</label>
                            <div class="input-group">
                                <input type="time" class="form-control" id="logTime" name="logTime" value="<?php echo $current_time; ?>" required>
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
