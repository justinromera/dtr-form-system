<?php
include 'auth.php';
// Firebase Database URLs
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/";

// Fetch user logs
$logs_json = file_get_contents($firebase_url . "user_logs.json");
$logs_data = json_decode($logs_json, true) ?? [];

// Fetch userss
$users_json = file_get_contents($firebase_url . "users.json");
$users_data = json_decode($users_json, true) ?? [];

// Fetch schedules
$schedules_json = file_get_contents($firebase_url . "user_schedules.json");
$schedules_data = json_decode($schedules_json, true) ?? [];

// Get selected user ID from dropdown
$selected_user_id = $_GET['user'] ?? (key($users_data) ?? '');

// Function to convert time to 12-hour format
function format_time($time) {
    return ($time && $time !== '---' && $time !== 'ABSENT') ? date("g:i A", strtotime($time)) : $time;
}

// Function to calculate rendered hours based on user log and schedule
function calculate_rendered_hours($log, $schedule) {
    if (
        isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure']) &&
        !empty($log['am_arrival']) && !empty($log['am_departure']) && 
        !empty($log['pm_arrival']) && !empty($log['pm_departure'])
    ) {
        $am_time_in = isset($schedule['am_time_in']) ? strtotime($schedule['am_time_in']) : strtotime($log['am_arrival']);
        $pm_time_out = isset($schedule['pm_time_out']) ? strtotime($schedule['pm_time_out']) : strtotime($log['pm_departure']);
        $am_arrival = max(strtotime($log['am_arrival']), $am_time_in);
        $am_departure = strtotime($log['am_departure']);
        $pm_arrival = strtotime($log['pm_arrival']);
        $pm_departure = min(strtotime($log['pm_departure']), $pm_time_out);

        $morning_seconds = max(0, $am_departure - $am_arrival);
        $afternoon_seconds = max(0, $pm_departure - $pm_arrival);
        $total_seconds = $morning_seconds + $afternoon_seconds;

        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);

        return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
               ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
    }
    return '---';
}

// Function to calculate total rendered hours for all logs of a user
function calculate_total_rendered_hours($logs, $schedules) {
    $total_seconds = 0;
    foreach ($logs as $log_date => $log) {
        if (
            isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure']) &&
            !empty($log['am_arrival']) && !empty($log['am_departure']) && 
            !empty($log['pm_arrival']) && !empty($log['pm_departure'])
        ) {
            $am_time_in = isset($schedules[$log_date]['am_time_in']) ? strtotime($schedules[$log_date]['am_time_in']) : strtotime($log['am_arrival']);
            $pm_time_out = isset($schedules[$log_date]['pm_time_out']) ? strtotime($schedules[$log_date]['pm_time_out']) : strtotime($log['pm_departure']);
            $am_arrival = max(strtotime($log['am_arrival']), $am_time_in);
            $am_departure = strtotime($log['am_departure']);
            $pm_arrival = strtotime($log['pm_arrival']);
            $pm_departure = min(strtotime($log['pm_departure']), $pm_time_out);

            $morning_seconds = max(0, $am_departure - $am_arrival);
            $afternoon_seconds = max(0, $pm_departure - $pm_arrival);
            $total_seconds += $morning_seconds + $afternoon_seconds;
        }
    }
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);

    return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
           ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
}

// Handle Log Edit
if (isset($_POST['edit_log'])) {
    $edit_date = $_POST['log_date']; // Use the date from the form
    $log_type = $_POST['log_type'];
    $new_time = $_POST['new_time'];

    // Ensure AM arrival time is not earlier than 9:00 AM
    if ($log_type == 'am_arrival' && strtotime($new_time) < strtotime('09:00')) {
        $new_time = '09:00';
    }

    $logs_data[$selected_user_id][$edit_date][$log_type] = $new_time;

    // Update Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "PATCH",
            "content" => json_encode($logs_data[$selected_user_id][$edit_date])
        ]   
    ];
    $context = stream_context_create($options);
    file_get_contents("{$firebase_url}user_logs/{$selected_user_id}/{$edit_date}.json", false, $context);

    // echo "<script>Swal.fire('Success', 'Log updated successfully!', 'success').then(() => { window.location.href='admin.php?user={$selected_user_id}'; });</script>";
    // exit();
    header("Location: admin.php?user={$selected_user_id}");
exit();
}

// Handle Log Deletion
if (isset($_POST['delete_log'])) {
    $delete_date = $_POST['delete_date'];
    
    // Remove log entry
    unset($logs_data[$selected_user_id][$delete_date]);

    // Update Firebase (set entry to null to delete)
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "DELETE"
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents("{$firebase_url}user_logs/{$selected_user_id}/{$delete_date}.json", false, $context);

    echo "<script>alert('Log deleted successfully!'); window.location.href='admin.php?user={$selected_user_id}';</script>";
    exit();
}

// Handle User Deletion
if (isset($_POST['delete_user'])) {
    $delete_user_id = $_POST['delete_user_id'];
    
    // Remove user entry
    unset($users_data[$delete_user_id]);
    unset($logs_data[$delete_user_id]);

    // Update Firebase (set entry to null to delete)
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "DELETE"
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents("{$firebase_url}users/{$delete_user_id}.json", false, $context);
    file_get_contents("{$firebase_url}user_logs/{$delete_user_id}.json", false, $context);

    echo "<script>alert('User deleted successfully!'); window.location.href='admin.php';</script>";
    exit();
}

function get_next_date($date) {
    $timestamp = strtotime($date);
    $next_date = date("Y-m-d", strtotime("+1 day", $timestamp));
    return $next_date;
}

// Handle Log Addition
if (isset($_POST['add_log'])) {
    $add_date = $_POST['log_date'];
    $log_type = $_POST['log_type'];
    $new_time = $_POST['new_time'];

    // Ensure AM arrival time is not earlier than 9:00 AM
    if ($log_type == 'am_arrival' && strtotime($new_time) < strtotime('09:00')) {
        $new_time = '09:00';
    }

    $logs_data[$selected_user_id][$add_date][$log_type] = $new_time;

    // Update Firebase
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "PATCH",
            "content" => json_encode($logs_data[$selected_user_id][$add_date])
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents("{$firebase_url}user_logs/{$selected_user_id}/{$add_date}.json", false, $context);

    header("Location: admin.php?user={$selected_user_id}");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin Panel - DTR System</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    .nav-collapsed {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.5s ease-in-out;
    }

    .nav-expanded {
      max-height: 400px; /* Adjust based on total height of icons */
      transition: max-height 0.5s ease-in-out;
    }

    .icon {
      opacity: 0;
      transform: translateY(-20px);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .icon-visible {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body class="bg-gray-100">
    <?php include 'nav.php'; ?>
    <div class="container mt-4" style="margin-left: 70px;">
        <h1 class="mb-4">Admin Panel - DTR System</h1>

        <!-- Select User Dropdown -->
        <form method="GET" class="mb-3">
            <label for="user" class="form-label"><b>Select User:</b></label>
            <select name="user" id="user" class="form-select" onchange="this.form.submit()">
                <?php foreach ($users_data as $user_id => $user): ?>
                    <option value="<?php echo $user_id; ?>" <?php echo ($selected_user_id == $user_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name'] ?? 'Unknown User'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
            <!-- Add Log Button -->
            <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addLogModal" onclick="setAddData()">Add Log</button>
        <!-- User Logs Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>AM Arrival</th>
                        <th>AM Departure</th>
                        <th>PM Arrival</th>
                        <th>PM Departure</th>
                        <th>Total Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs_data[$selected_user_id])): ?>
                        <?php foreach ($logs_data[$selected_user_id] as $logDate => $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($logDate); ?></td>
                                 <td><?php echo (strtotime($log['am_arrival']) < strtotime('09:00')) ? '09:00 AM' : format_time($log['am_arrival'] ?? '---'); ?></td>
                                <td><?php echo format_time($log['am_departure'] ?? '---'); ?></td>
                                <td><?php echo format_time($log['pm_arrival'] ?? '---'); ?></td>
                                <td><?php echo format_time($log['pm_departure'] ?? '---'); ?></td>
                                <td><?php echo calculate_rendered_hours($log, $schedules_data[$selected_user_id][$logDate] ?? []); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editLogModal" 
                                        onclick="setEditData('<?php echo $logDate; ?>')">Edit</button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="delete_date" value="<?php echo $logDate; ?>">
                                        <button type="submit" name="delete_log" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this log?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No logs available for this user</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Hours Table -->
        <div class="table-responsive mt-4">
            <table class="table table-bordered">
                <thead class="table-secondary">
                    <tr>
                        <th>User</th>
                        <th>Total Rendered Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_data as $user_id => $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name'] ?? 'Unknown User'); ?></td>
                            <td><?php echo calculate_total_rendered_hours($logs_data[$user_id] ?? [], $schedules_data[$user_id] ?? []); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add User Button -->
        <!-- <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button> -->
        <!-- Remove User Button -->
        <!-- <button class="btn btn-danger mb-3" data-bs-toggle="modal" data-bs-target="#removeUserModal">Remove User</button> -->
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="add_user.php" method="POST">
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name:</label>
                            <input type="text" name="fullname" id="fullname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Cellphone Number:</label>
                            <input type="text" name="phone" id="phone" class="form-control" required>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove User Modal -->
    <div class="modal fade" id="removeUserModal" tabindex="-1" aria-labelledby="removeUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeUserModalLabel">Remove User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="delete_user_id" class="form-label">Select User to Remove:</label>
                            <select name="delete_user_id" id="delete_user_id" class="form-select" required>
                                <?php foreach ($users_data as $user_id => $user): ?>
                                    <option value="<?php echo $user_id; ?>"><?php echo htmlspecialchars($user['name'] ?? 'Unknown User'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Remove User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Log Modal -->
    <div class="modal fade" id="editLogModal" tabindex="-1" aria-labelledby="editLogModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLogModalLabel">Edit Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="edit_date" id="edit_date">
                        <div class="mb-3">
                            <label for="log_date" class="form-label">Date:</label>
                            <input type="date" name="log_date" id="log_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="log_type" class="form-label">Log Type:</label>
                            <select name="log_type" id="log_type" class="form-select" required>
                                <option value="am_arrival">AM Arrival</option>
                                <option value="am_departure">AM Departure</option>
                                <option value="pm_arrival">PM Arrival</option>
                                <option value="pm_departure">PM Departure</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="new_time" class="form-label">New Time:</label>
                            <input type="time" name="new_time" id="new_time" class="form-control" required>
                        </div>
                        <button type="submit" name="edit_log" class="btn btn-primary">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Log Modal -->
    <div class="modal fade" id="addLogModal" tabindex="-1" aria-labelledby="addLogModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLogModalLabel">Add Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="log_date" class="form-label">Date:</label>
                            <input type="date" name="log_date" id="add_log_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="log_type" class="form-label">Log Type:</label>
                            <select name="log_type" id="add_log_type" class="form-select" required>
                                <option value="am_arrival">AM Arrival</option>
                                <option value="am_departure">AM Departure</option>
                                <option value="pm_arrival">PM Arrival</option>
                                <option value="pm_departure">PM Departure</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="new_time" class="form-label">New Time:</label>
                            <input type="time" name="new_time" id="add_new_time" class="form-control" required>
                        </div>
                        <button type="submit" name="add_log" class="btn btn-primary">Add Log</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setEditData(date) {
            document.getElementById("edit_date").value = date;
            document.getElementById("log_date").value = date;
        }

        function setAddData() {
            const lastLogDate = "<?php echo end(array_keys($logs_data[$selected_user_id] ?? [])); ?>";
            const nextDate = "<?php echo get_next_date(end(array_keys($logs_data[$selected_user_id] ?? []))); ?>";
            document.getElementById("add_log_date").value = nextDate;
        }

        const menuToggle = document.getElementById("menuToggle");
        const navBar = document.getElementById("navBar");
        const icons = document.querySelectorAll(".icon");

        menuToggle.addEventListener("click", () => {
        if (navBar.classList.contains("nav-collapsed")) {
            navBar.classList.remove("nav-collapsed");
            navBar.classList.add("nav-expanded");
            // Show icons with animation
            icons.forEach((icon, index) => {
            setTimeout(() => {
                icon.classList.add("icon-visible");
            }, index * 100);
            });
        } else {
            navBar.classList.remove("nav-expanded");
            navBar.classList.add("nav-collapsed");
            // Hide icons instantly
            icons.forEach(icon => {
            icon.classList.remove("icon-visible");
            });
        }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>