<?php
// Firebase Realtime Database URL
$firebase_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/";

// Fetch user logs
$logs_json = file_get_contents($firebase_url . "user_logs.json");
$logs_data = json_decode($logs_json, true) ?? [];

// Fetch users
$users_json = file_get_contents($firebase_url . "users.json");
$users_data = json_decode($users_json, true) ?? [];

$logs = [];

if (!empty($logs_data)) {
    foreach ($logs_data as $logId => $log) {
        // Ensure 'login_time' and 'logout_time' exist before using them
        $login_time = isset($log['login_time']) ? $log['login_time'] : null;
        $logout_time = isset($log['logout_time']) ? $log['logout_time'] : null;

        if ($login_time) {
            $logDate = date('Y-m-d', strtotime($login_time));
            $logs[$logDate]['am_arrival'] = ($login_time >= '00:00:00' && $login_time <= '11:59:59') ? $login_time : '---';
            $logs[$logDate]['pm_arrival'] = ($login_time >= '12:00:00' && $login_time <= '23:59:59') ? $login_time : '---';
        } else {
            $logDate = date('Y-m-d');
            $logs[$logDate]['am_arrival'] = '---';
            $logs[$logDate]['pm_arrival'] = '---';
        }

        if ($logout_time) {
            $logs[$logDate]['am_departure'] = ($logout_time >= '00:00:00' && $logout_time <= '11:59:59') ? $logout_time : '---';
            $logs[$logDate]['pm_departure'] = ($logout_time >= '12:00:00' && $logout_time <= '23:59:59') ? $logout_time : '---';
        } else {
            $logs[$logDate]['am_departure'] = '---';
            $logs[$logDate]['pm_departure'] = '---';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>DTR System</title>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">DTR System</h1>

        <!-- Add User Button -->
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>

        <!-- DTR Logs Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>AM Arrival</th>
                        <th>AM Departure</th>
                        <th>PM Arrival</th>
                        <th>PM Departure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $date => $log): ?>
                        <tr>
                            <td><?php echo date('j', strtotime($date)); ?></td>
                            <td><?php echo $log['am_arrival'] ?? '---'; ?></td>
                            <td><?php echo $log['am_departure'] ?? '---'; ?></td>
                            <td><?php echo $log['pm_arrival'] ?? '---'; ?></td>
                            <td><?php echo $log['pm_departure'] ?? '---'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Time In/Out Form -->
        <form action="record.php" method="POST" class="mt-4">
            <div class="mb-3">
                <label for="user_id" class="form-label">Select User:</label>
                <select name="user_id" id="user_id" class="form-select" required>
                    <?php if (!empty($users_data)): ?>
                        <?php foreach ($users_data as $userId => $user): ?>
                            <option value="<?php echo htmlspecialchars($userId); ?>">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="time" class="form-label">Time:</label>
                <input type="time" name="time" id="time" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="time_type" class="form-label">Select Type:</label>
                <select name="time_type" id="time_type" class="form-select" required>
                    <option value="am_arrival">AM Arrival</option>
                    <option value="am_departure">AM Departure</option>
                    <option value="pm_arrival">PM Arrival</option>
                    <option value="pm_departure">PM Departure</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success">Submit</button>
        </form>
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
                            <label for="name" class="form-label">Name:</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
