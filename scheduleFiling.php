<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Firebase Database URLs
$firebase_users_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users";
$firebase_schedules_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_schedules";
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs";

// Fetch users from Firebase
$users_json = file_get_contents("$firebase_users_url.json");
$users_data = json_decode($users_json, true) ?? [];

// Get selected user ID from dropdown
$selected_user_id = $_GET['user'] ?? (key($users_data) ?? '');

// Fetch schedules and logs for the selected user
$schedules_json = file_get_contents("$firebase_schedules_url.json");
$schedules_data = json_decode($schedules_json, true) ?? [];
$user_schedules = $schedules_data[$selected_user_id] ?? [];

$logs_json = file_get_contents("$firebase_logs_url.json");
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
        $schedules_json = file_get_contents("$firebase_schedules_url.json");
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
        file_get_contents("$firebase_schedules_url/$selected_user_id/$date.json", false, $context);
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

// Handle User Deletion
if (isset($_POST['delete_user'])) {
    $delete_user_id = $_POST['delete_user_id'];
    
    // Remove user entry
    unset($users_data[$delete_user_id]);
    unset($schedules_data[$delete_user_id]);
    unset($logs_data[$delete_user_id]);

    // Update Firebase (set entry to null to delete)
    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "DELETE"
        ]
    ];
    $context = stream_context_create($options);
    file_get_contents("$firebase_users_url/$delete_user_id.json", false, $context);
    file_get_contents("$firebase_schedules_url/$delete_user_id.json", false, $context);
    file_get_contents("$firebase_logs_url/$delete_user_id.json", false, $context);

    echo "<script>alert('User deleted successfully!'); window.location.href='scheduleFiling.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Filing - DTR System</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <button onclick="window.location.href='admin.php'" class="p-4 icon" style="transition-delay: 0.1s;" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-10 0a1 1 0 001 1h3m10-11h-3m-4 0h-3m-4 0h-3"></path>
          </svg>
        </button>
        <button onclick="window.location.href='scheduleFiling.php'" class="p-4 icon" style="transition-delay: 0.2s;" data-bs-toggle="tooltip" data-bs-placement="right" title="Schedule">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 4h10a2 2 0 012 2v10a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2zm3 4h4m-4 4h4"></path>
          </svg>
        </button>
        <button class="p-4 icon" data-bs-toggle="modal" data-bs-target="#addUserModal" style="transition-delay: 0.3s;" data-bs-toggle="tooltip" data-bs-placement="right" title="Add User">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        <button class="p-4 icon" data-bs-toggle="modal" data-bs-target="#removeUserModal" style="transition-delay: 0.4s;" data-bs-toggle="tooltip" data-bs-placement="right" title="Remove User">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
        <button onclick="window.location.href='logout.php'" class="p-4 icon" style="transition-delay: 0.5s;" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1"></path>
          </svg>
        </button>
    </div>
</div>

    <div class="container mt-4" style="margin-left: 70px;">
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

            <!-- <button type="button" class="btn btn-secondary" onclick="addRow()">Add Row</button> -->
            <!-- <button type="button" class="btn btn-info" onclick="applyToAll()">Apply to All</button> -->
            <!-- <button type="submit" name="submit_schedule" class="btn btn-success w-100">Submit Schedule</button> -->
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
            const amTimeIn = firstRow.cells[1].querySelector('div').innerText.split(' - ')[0];
            const pmTimeOut = firstRow.cells[1].querySelector('div').innerText.split(' - ')[1];

            for (let i = 1; i < table.rows.length; i++) {
                table.rows[i].cells[1].querySelector('div').innerText = `${amTimeIn} - ${pmTimeOut}`;
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

        // Initialize tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
