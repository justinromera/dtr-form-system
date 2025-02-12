<?php
require('fpdf/fpdf.php');
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Ensure required GET parameters exist
$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];
$selected_month = $_GET['month'] ?? date('Y-m');
$preview = $_GET['preview'] ?? null;

if (!$user_id || !$selected_month) {
    die("Error: Missing required parameters.");
}

// Firebase Database URLs
$firebase_users_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/users.json";
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs.json";

// Fetch users and logs from Firebase
$users_json = file_get_contents($firebase_users_url);
$logs_json = file_get_contents($firebase_logs_url);

$users_data = json_decode($users_json, true) ?? [];
$logs_data = json_decode($logs_json, true) ?? [];

// Get user details
$user = $users_data[$user_id] ?? [];
$user_logs = $logs_data[$user_id] ?? [];

// Function to filter logs by month
function filter_logs_by_month($logs, $month) {
    $filtered = [];
    foreach ($logs as $date => $log) {
        if (strpos($date, $month) === 0) {
            $filtered[$date] = $log;
        }
    }
    return $filtered;
}

$logs_month = filter_logs_by_month($user_logs, $selected_month);

// Function to format time
function format_time($time) {
    return ($time && $time !== '---' && $time !== 'ABSENT') ? date("g:i A", strtotime($time)) : "";
}

// Create PDF
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'DAILY TIME RECORD', 0, 1, 'C');
        $this->Ln(5);
    }
}

$pdf = new PDF('P', 'mm', 'Letter'); // Letter size (8.5" x 11")
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8); // Reduced font size to fit content

// User Info
$pdf->Cell(95, 8, 'Name: ' . ($user['name'] ?? 'Unknown User'), 0, 0);
$pdf->Cell(95, 8, 'For the month of: ' . date('F Y', strtotime($selected_month)), 0, 1);
$pdf->Ln(3);

// Table Header
$pdf->SetFont('Arial', 'B', 8);
$headers = ['Day', 'AM Arrival', 'AM Departure', 'PM Arrival', 'PM Departure', 'Rendered Hours'];
$widths = [10, 20, 20, 20, 20, 30]; // Adjusted widths to fit the page
foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 6, $header, 1);
}
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);

// Fill Table
for ($day = 1; $day <= 31; $day++) {
    $date = $selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    $log = $logs_month[$date] ?? [];
    
    $pdf->Cell($widths[0], 6, $day, 1);
    $pdf->Cell($widths[1], 6, format_time($log['am_arrival'] ?? ''), 1);
    $pdf->Cell($widths[2], 6, format_time($log['am_departure'] ?? ''), 1);
    $pdf->Cell($widths[3], 6, format_time($log['pm_arrival'] ?? ''), 1);
    $pdf->Cell($widths[4], 6, format_time($log['pm_departure'] ?? ''), 1);
    $pdf->Cell($widths[5], 6, calculate_rendered_hours($log), 1);
    $pdf->Ln();
}

$pdf->Ln(5);
$pdf->Cell(0, 8, 'I certify on my honor that the above is a true and correct report of the hours of work performed.', 0, 1, 'C');
$pdf->Cell(0, 8, 'Verified as to the prescribed office hours:', 0, 1, 'C');
$pdf->Ln(10);
$pdf->Cell(0, 8, '______________________________', 0, 1, 'C');
$pdf->Cell(0, 8, 'In-Charge', 0, 1, 'C');
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'CIVIL SERVICE FORM No. 48', 0, 1, 'C');

// Output PDF
if ($preview) {
    $pdf->Output('I', 'DTR_Form_Preview.pdf'); // Preview in browser
} else {
    $pdf->Output('D', 'DTR_Form_' . $user_id . '_' . $selected_month . '.pdf'); // Download
}

// Function to calculate rendered hours
function calculate_rendered_hours($log) {
    if (!isset($log['am_arrival'], $log['am_departure'], $log['pm_arrival'], $log['pm_departure'])) {
        return '---';
    }
    $am_in = strtotime($log['am_arrival']);
    $am_out = strtotime($log['am_departure']);
    $pm_in = strtotime($log['pm_arrival']);
    $pm_out = strtotime($log['pm_departure']);
    $total_seconds = ($am_out - $am_in) + ($pm_out - $pm_in);
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    return "{$hours}h {$minutes}m";
}
?>
