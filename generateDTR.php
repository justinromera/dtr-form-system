<?php
require('fpdf/fpdf.php');
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
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

// Get user ID and month from request
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$selected_month = $_GET['month'] ?? $_POST['month'] ?? null;

if (!$user_id || !$selected_month) {
    echo json_encode(['error' => 'Missing user_id or month']);
    exit();
}

// Get user details
$user = $users_data[$user_id] ?? [];
$user_logs = $logs_data[$user_id] ?? [];

// Filter logs by selected month
$filtered_logs = [];
foreach ($user_logs as $log_date => $log) {
    if (strpos($log_date, $selected_month) === 0) { // Match YYYY-MM format
        $filtered_logs[$log_date] = $log;
    }
}

// Function to convert time to 12-hour format
function format_time($time) {
    return ($time && $time !== '---' && $time !== 'ABSENT') ? date("g:i A", strtotime($time)) : $time;
}

// Create PDF
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Daily Time Record (Civil Service Form No. 48)', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// User Information
$pdf->Cell(0, 10, 'Name: ' . ($user['name'] ?? 'Unknown User'), 0, 1);
$pdf->Cell(0, 10, 'Month: ' . date('F Y', strtotime($selected_month)), 0, 1);
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(20, 10, 'Date', 1);
$pdf->Cell(30, 10, 'Time In (AM)', 1);
$pdf->Cell(30, 10, 'Time Out (AM)', 1);
$pdf->Cell(30, 10, 'Time In (PM)', 1);
$pdf->Cell(30, 10, 'Time Out (PM)', 1);
$pdf->Cell(30, 10, 'Total Hours', 1);
$pdf->Ln();

// Table Body
$pdf->SetFont('Arial', '', 10);
for ($day = 1; $day <= 31; $day++) {
    $date = $selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    $log = $filtered_logs[$date] ?? [];

    $pdf->Cell(20, 10, $day, 1);
    $pdf->Cell(30, 10, format_time($log['am_arrival'] ?? '---'), 1);
    $pdf->Cell(30, 10, format_time($log['am_departure'] ?? '---'), 1);
    $pdf->Cell(30, 10, format_time($log['pm_arrival'] ?? '---'), 1);
    $pdf->Cell(30, 10, format_time($log['pm_departure'] ?? '---'), 1);
    $pdf->Cell(30, 10, '', 1); // Placeholder for total hours
    $pdf->Ln();
}

// Save PDF to a temporary file
$temp_file = tempnam(sys_get_temp_dir(), 'DTR_Form_') . '.pdf';
$pdf->Output('F', $temp_file);

// Return the path to the temporary file
echo json_encode(['file' => $temp_file]);
?>
