<?php
// Firebase Database URLs
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/";

// Fetch user logs
$logs_json = file_get_contents($firebase_url . "user_logs.json");
$logs_data = json_decode($logs_json, true) ?? [];

// Fetch users
$users_json = file_get_contents($firebase_url . "users.json");
$users_data = json_decode($users_json, true) ?? [];

// Get selected user ID from dropdown
$selected_user_id = $_GET['user'] ?? (key($users_data) ?? '');

// Function to convert time to 12-hour format
function format_time($time) {
    return ($time && $time !== '---' && $time !== 'ABSENT') ? date("g:i A", strtotime($time)) : $time;
}

// Function to calculate total rendered hours
function calculate_hours($log) {
    if (isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure'])) {
        $morning_hours = (strtotime($log['am_departure']) - strtotime($log['am_arrival'])) / 3600;
        $afternoon_hours = (strtotime($log['pm_departure']) - strtotime($log['pm_arrival'])) / 3600;
        return number_format($morning_hours + $afternoon_hours, 2) . ' hrs';
    }
    return '---';
}

// Handle Log Edit
if (isset($_POST['edit_log'])) {
    $edit_date = $_POST['edit_date'];
    $log_type = $_POST['log_type'];
    $new_time = $_POST['new_time'];

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

    echo "<script>alert('Log updated successfully!'); window.location.href='admin.php?user={$selected_user_id}';</script>";
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin Panel - DTR System</title>
</head>
<body>
    <div class="container mt-4">
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
                                <td><?php echo format_time($log['am_arrival'] ?? '---'); ?></td>
                                <td><?php echo format_time($log['am_departure'] ?? '---'); ?></td>
                                <td><?php echo format_time($log['pm_arrival'] ?? '---'); ?></td>
                                <td><?php echo format_time($log['pm_departure'] ?? '---'); ?></td>
                                <td><?php echo calculate_hours($log); ?></td>
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

        <!-- Add User Button -->
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
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

    <script>
        function setEditData(date) {
            document.getElementById("edit_date").value = date;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
