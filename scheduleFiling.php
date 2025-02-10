<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user.php");
    exit();
}

// Firebase Database URLs
$firebase_users_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";
$firebase_schedules_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_schedules.json";
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs.json";

// Fetch users from Firebase
$users_json = file_get_contents($firebase_users_url);
$users_data = json_decode($users_json, true) ?? [];

// Get selected user ID from dropdown
$selected_user_id = $_GET['user'] ?? (key($users_data) ?? '');

// Fetch schedules and logs for the selected user
$schedules_json = file_get_contents($firebase_schedules_url);
$schedules_data = json_decode($schedules_json, true) ?? [];
$user_schedules = $schedules_data[$selected_user_id] ?? [];

$logs_json = file_get_contents($firebase_logs_url);
$logs_data = json_decode($logs_json, true) ?? [];
$user_logs = $logs_data[$selected_user_id] ?? [];

// Handle schedule filing
if (isset($_POST['submit_schedule'])) {
    $selected_user_id = $_POST['user'];
    $schedule = $_POST['schedule'];

    foreach ($schedule as $date => $times) {
        $am_time_in = $times['am_time_in'];
        $pm_time_out = $times['pm_time_out'];

        // Fetch existing schedules for the user
        $schedules_json = file_get_contents($firebase_schedules_url);
        $schedules_data = json_decode($schedules_json, true) ?? [];

        // Update schedules with the new schedule
        if (!isset($schedules_data[$selected_user_id][$date])) {
            $schedules_data[$selected_user_id][$date] = [];
        }

        $schedules_data[$selected_user_id][$date]['am_time_in'] = $am_time_in;
        $schedules_data[$selected_user_id][$date]['pm_time_out'] = $pm_time_out;

        // Update Firebase
        $options = [
            "http" => [
                "header"  => "Content-type: application/json",
                "method"  => "PATCH",
                "content" => json_encode($schedules_data[$selected_user_id][$date])
            ]
        ];
        $context  = stream_context_create($options);
        file_get_contents("https://dtr-system-a192a-default-rtdb.firebaseio.com/user_schedules/$selected_user_id/$date.json", false, $context);
    }

    echo "<script>
            alert('Schedule filed successfully!');
            window.location.href='scheduleFiling.php?user=$selected_user_id';
          </script>";
    exit();
}

// Function to calculate supposed hours based on schedule
function calculate_supposed_hours($schedule) {
    if (isset($schedule['am_time_in'], $schedule['pm_time_out']) && !empty($schedule['am_time_in']) && !empty($schedule['pm_time_out'])) {
        $am_time_in = strtotime($schedule['am_time_in']);
        $pm_time_out = strtotime($schedule['pm_time_out']);
        $total_seconds = max(0, $pm_time_out - $am_time_in);

        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);

        return "{$hours} hour" . ($hours > 1 ? "s" : "") . 
               ($minutes > 0 ? " {$minutes} minute" . ($minutes > 1 ? "s" : "") : "");
    }
    return '---';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Filing - DTR System</title>
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
        }
        .btn {
            margin-bottom: 10px;
        }
        .schedule-cell {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-3 text-center">Schedule Filing</h2>

        <form method="GET" class="mb-3">
            <label for="user" class="form-label">Select User:</label>
            <select name="user" id="user" class="form-select" onchange="this.form.submit()">
                <?php foreach ($users_data as $user_id => $user): ?>
                    <option value="<?php echo $user_id; ?>" <?php echo ($selected_user_id == $user_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name'] ?? 'Unknown User'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <form method="POST" id="scheduleForm">
            <input type="hidden" name="user" value="<?php echo $selected_user_id; ?>">
            <div class="table-responsive">
                <table class="table table-bordered" id="scheduleTable">
                    <thead class="table-primary">
                        <tr>
                            <th>Date</th>
                            <th>Schedule</th>
                            <th>Actions</th>
                            <th>Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_logs as $log_date => $log): 
                            $am_time_in = $user_schedules[$log_date]['am_time_in'] ?? '';
                            $pm_time_out = $user_schedules[$log_date]['pm_time_out'] ?? '';
                            $real_am_time_in = $log['am_arrival'] ?? '---';
                            $real_pm_time_out = $log['pm_departure'] ?? '---';
                            $supposed_hours = calculate_supposed_hours($user_schedules[$log_date] ?? []);
                            $rendered_hours = calculate_rendered_hours($log, $user_schedules[$log_date] ?? []);
                        ?>
                            <tr>
                                <td><?php echo $log_date; ?></td>
                                <td class="schedule-cell" data-bs-toggle="modal" data-bs-target="#editScheduleModal" onclick="setEditScheduleData('<?php echo $log_date; ?>', '<?php echo $am_time_in; ?>', '<?php echo $pm_time_out; ?>')">
                                    <div><?php echo $am_time_in ? date("g:i A", strtotime($am_time_in)) : '---'; ?> - <?php echo $pm_time_out ? date("g:i A", strtotime($pm_time_out)) : '---'; ?></div>
                                    <small><?php echo $real_am_time_in ? date("g:i A", strtotime($real_am_time_in)) : '---'; ?> - <?php echo $real_pm_time_out ? date("g:i A", strtotime($real_pm_time_out)) : '---'; ?></small>
                                </td>
                                <td><button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button></td>
                                <td>
                                    <div>Supposed: <?php echo $supposed_hours; ?></div>
                                    <div>Rendered: <?php echo $rendered_hours; ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-secondary" onclick="addRow()">Add Row</button>
            <button type="button" class="btn btn-info" onclick="applyToAll()">Apply to All</button>
            <button type="submit" name="submit_schedule" class="btn btn-success w-100">Submit Schedule</button>
        </form>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editScheduleModalLabel">Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editScheduleForm">
                        <input type="hidden" name="edit_date" id="edit_date">
                        <div class="mb-3">
                            <label for="edit_am_time_in" class="form-label">Time In (AM):</label>
                            <input type="time" name="edit_am_time_in" id="edit_am_time_in" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_pm_time_out" class="form-label">Time Out (PM):</label>
                            <input type="time" name="edit_pm_time_out" id="edit_pm_time_out" class="form-control" required>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="saveSchedule()">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addRow() {
            const table = document.getElementById('scheduleTable').getElementsByTagName('tbody')[0];
            const newRow = table.insertRow();
            const dateCell = newRow.insertCell(0);
            const scheduleCell = newRow.insertCell(1);
            const actionsCell = newRow.insertCell(2);
            const totalHoursCell = newRow.insertCell(3);

            dateCell.innerHTML = '<input type="date" name="schedule[new][date]" class="form-control" required>';
            scheduleCell.innerHTML = '<div>--- - ---</div><small>--- - ---</small>';
            scheduleCell.classList.add('schedule-cell');
            scheduleCell.setAttribute('data-bs-toggle', 'modal');
            scheduleCell.setAttribute('data-bs-target', '#editScheduleModal');
            scheduleCell.setAttribute('onclick', 'setEditScheduleData("", "", "")');
            actionsCell.innerHTML = '<button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button>';
            totalHoursCell.innerHTML = '<div>Supposed: ---</div><div>Rendered: ---</div>';
        }

        function removeRow(button) {
            const row = button.parentNode.parentNode;
            row.parentNode.removeChild(row);
        }

        function applyToAll() {
            const table = document.getElementById('scheduleTable').getElementsByTagName('tbody')[0];
            const firstRow = table.rows[0];
            const amTimeIn = firstRow.cells[1].getElementsByTagName('input')[0].value;
            const pmTimeOut = firstRow.cells[1].getElementsByTagName('input')[1].value;

            for (let i = 1; i < table.rows.length; i++) {
                table.rows[i].cells[1].getElementsByTagName('input')[0].value = amTimeIn;
                table.rows[i].cells[1].getElementsByTagName('input')[1].value = pmTimeOut;
            }
        }

        function setEditScheduleData(date, amTimeIn, pmTimeOut) {
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_am_time_in').value = amTimeIn;
            document.getElementById('edit_pm_time_out').value = pmTimeOut;
        }

        function saveSchedule() {
            const date = document.getElementById('edit_date').value;
            const amTimeIn = document.getElementById('edit_am_time_in').value;
            const pmTimeOut = document.getElementById('edit_pm_time_out').value;

            const table = document.getElementById('scheduleTable').getElementsByTagName('tbody')[0];
            for (let i = 0; i < table.rows.length; i++) {
                if (table.rows[i].cells[0].innerText === date) {
                    table.rows[i].cells[1].innerHTML = `<div>${amTimeIn} - ${pmTimeOut}</div><small>--- - ---</small>`;
                    table.rows[i].cells[1].setAttribute('onclick', `setEditScheduleData('${date}', '${amTimeIn}', '${pmTimeOut}')`);
                    break;
                }
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('editScheduleModal'));
            modal.hide();

            // Save changes to Firebase
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "saveSchedule.php", true);
            xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    alert('Schedule updated successfully!');
                    location.reload();
                }
            };
            xhr.send(JSON.stringify({
                user: "<?php echo $selected_user_id; ?>",
                date: date,
                am_time_in: amTimeIn,
                pm_time_out: pmTimeOut
            }));
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
