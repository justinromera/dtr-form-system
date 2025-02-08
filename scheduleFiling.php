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

// Fetch users from Firebase
$users_json = file_get_contents($firebase_users_url);
$users_data = json_decode($users_json, true) ?? [];


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

    echo "<script>alert('Schedule filed successfully!'); window.location.href='scheduleFiling.php';</script>";
    exit();
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-3 text-center">Schedule Filing</h2>

        <form method="POST" id="scheduleForm">
            <div class="mb-3">
                <label for="user" class="form-label">Select User:</label>
                <select name="user" id="user" class="form-select" required>
                    <?php foreach ($users_data as $user_id => $user): ?>
                        <option value="<?php echo $user_id; ?>"><?php echo htmlspecialchars($user['name'] ?? 'Unknown User'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="scheduleTable">
                    <thead class="table-primary">
                        <tr>
                            <th>Date</th>
                            <th>Time In (AM)</th>
                            <th>Time Out (PM)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $start_date = strtotime('monday this week');
                        for ($i = 0; $i < 7; $i++): 
                            $date = date('Y-m-d', strtotime("+$i days", $start_date));
                        ?>
                            <tr>
                                <td><input type="date" name="schedule[<?php echo $date; ?>][date]" class="form-control" value="<?php echo $date; ?>" required></td>
                                <td><input type="time" name="schedule[<?php echo $date; ?>][am_time_in]" class="form-control" required></td>
                                <td><input type="time" name="schedule[<?php echo $date; ?>][pm_time_out]" class="form-control" required></td>
                                <td><button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-secondary" onclick="addRow()">Add Row</button>
            <button type="button" class="btn btn-info" onclick="applyToAll()">Apply to All</button>
            <button type="submit" name="submit_schedule" class="btn btn-success w-100">Submit Schedule</button>
        </form>
    </div>

    <script>
        function addRow() {
            const table = document.getElementById('scheduleTable').getElementsByTagName('tbody')[0];
            const newRow = table.insertRow();
            const dateCell = newRow.insertCell(0);
            const amTimeInCell = newRow.insertCell(1);
            const pmTimeOutCell = newRow.insertCell(2);
            const actionsCell = newRow.insertCell(3);

            dateCell.innerHTML = '<input type="date" name="schedule[new][date]" class="form-control" required>';
            amTimeInCell.innerHTML = '<input type="time" name="schedule[new][am_time_in]" class="form-control" required>';
            pmTimeOutCell.innerHTML = '<input type="time" name="schedule[new][pm_time_out]" class="form-control" required>';
            actionsCell.innerHTML = '<button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button>';
        }

        function removeRow(button) {
            const row = button.parentNode.parentNode;
            row.parentNode.removeChild(row);
        }

        function applyToAll() {
            const table = document.getElementById('scheduleTable').getElementsByTagName('tbody')[0];
            const firstRow = table.rows[0];
            const amTimeIn = firstRow.cells[1].getElementsByTagName('input')[0].value;
            const pmTimeOut = firstRow.cells[2].getElementsByTagName('input')[0].value;

            for (let i = 1; i < table.rows.length; i++) {
                table.rows[i].cells[1].getElementsByTagName('input')[0].value = amTimeIn;
                table.rows[i].cells[2].getElementsByTagName('input')[0].value = pmTimeOut;
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
