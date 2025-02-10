<?php
// Firebase Database URLs
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/";

// Fetch user logs
$logs_json = file_get_contents($firebase_url . "user_logs.json");
$logs_data = json_decode($logs_json, true) ?? [];

// Fetch users
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
<div 
    class="fixed top-1/2 left-2.5 transform -translate-y-1/2 flex flex-col items-center bg-white shadow-md w-16 pt-4 pb-4 rounded-[20px] border border-gray-300">
    <!-- Toggle Button (Top Icon) -->
    <button id="menuToggle" class="p-3 mb-2 bg-gray-100 rounded-full shadow">
      <div class="w-6 h-6 grid grid-cols-2 gap-1">
        <div class="bg-black w-full h-full"></div>
        <div class="bg-black w-full h-full"></div>
        <div class="bg-black w-full h-full"></div>
        <div class="bg-black w-full h-full"></div>
      </div>
    </button>

    <!-- Collapsible Navigation Items -->
    <div id="navBar" class="flex flex-col items-center nav-collapsed">
        <button class="p-4 icon" style="transition-delay: 0.1s;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A4.992 4.992 0 0112 15a4.992 4.992 0 016.879 2.804M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
        </button>
      <button class="p-4 icon" style="transition-delay: 0.2s;">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4"></path>
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
      </button>
      <button class="p-4 icon" style="transition-delay: 0.3s;">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"></path>
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 5.523-4.477 10-10 10S1 17.523 1 12 5.477 2 11 2c4.025 0 7.429 2.228 9 5.5"></path>
        </svg>
      </button>
      <button class="p-4 icon" style="transition-delay: 0.4s;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-6"><g stroke-width="0" id="SVGRepo_bgCarrier"></g><g stroke-linejoin="round" stroke-linecap="round" id="SVGRepo_tracerCarrier"></g><g id="SVGRepo_iconCarrier">
            <path class="group-focus:fill-white" fill="#000000" d="M17.2929 14.2929C16.9024 14.6834 16.9024 15.3166 17.2929 15.7071C17.6834 16.0976 18.3166 16.0976 18.7071 15.7071L21.6201 12.7941C21.6351 12.7791 21.6497 12.7637 21.6637 12.748C21.87 12.5648 22 12.2976 22 12C22 11.7024 21.87 11.4352 21.6637 11.252C21.6497 11.2363 21.6351 11.2209 21.6201 11.2059L18.7071 8.29289C18.3166 7.90237 17.6834 7.90237 17.2929 8.29289C16.9024 8.68342 16.9024 9.31658 17.2929 9.70711L18.5858 11H13C12.4477 11 12 11.4477 12 12C12 12.5523 12.4477 13 13 13H18.5858L17.2929 14.2929Z"></path>
            <path class="group-focus:fill-white" fill="#000" d="M5 2C3.34315 2 2 3.34315 2 5V19C2 20.6569 3.34315 22 5 22H14.5C15.8807 22 17 20.8807 17 19.5V16.7326C16.8519 16.647 16.7125 16.5409 16.5858 16.4142C15.9314 15.7598 15.8253 14.7649 16.2674 14H13C11.8954 14 11 13.1046 11 12C11 10.8954 11.8954 10 13 10H16.2674C15.8253 9.23514 15.9314 8.24015 16.5858 7.58579C16.7125 7.4591 16.8519 7.35296 17 7.26738V4.5C17 3.11929 15.8807 2 14.5 2H5Z"></path></g>
        </svg>
      </button>
    </div>
  </div>

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
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
        <!-- Remove User Button -->
        <button class="btn btn-danger mb-3" data-bs-toggle="modal" data-bs-target="#removeUserModal">Remove User</button>
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

    <script>
        function setEditData(date) {
            document.getElementById("edit_date").value = date;
            document.getElementById("log_date").value = date;
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