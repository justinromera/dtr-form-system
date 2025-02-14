<?php
require('fpdf/fpdf.php');

// Fetch logs from Firebase
$firebase_logs_url = "https://dtr-system-a192a-default-rtdb.firebaseio.com/user_logs.json";
$logs_json = file_get_contents($firebase_logs_url);
$logs_data = json_decode($logs_json, true) ?? [];

// Get user ID from session or request
session_start();
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
$user_logs = $logs_data[$user_id] ?? [];

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','I', 8);
        $this->Cell(30, -1, 'Civil Service Form No. 48', 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(190, 12, 'DAILY TIME RECORD', 0, 1, 'C');
        $this->Ln(2);
        $this->Cell(190, -10, '_______', 0, 1, 'C');
        $this->Cell(190, 30, '---------------------------------------------------------', 0, 1, 'C');
        $this->SetFont('Arial','BI', 10);
        $this->Cell(190, -25, '(Name)', 0, 1, 'C');

        // Additional details
        $this->SetFont('Arial', 'I', 10);
        $this->Ln(12);
        $this->Cell(180, 10, 'For the month of ____________________, 20_______', 0, 1, 'C');
        $this->Cell(185, 3, 'Official hours of arrival                   Regular Days ________', 0, 1, 'C');
        $this->Cell(196, 7, 'and departure                      Saturdays ____________', 0, 1, 'C');
    }

    function Footer() {
        $this->SetY(-50);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(178 , 5, 'I CERTIFY on my honor that the above is a true and correct,', 0, 1, 'C');
        $this->Cell(177, 5, 'report of the hours of work performed record of which was made,', 0, 1, 'C');
        $this->Cell(160, 5, 'daily at the time of arrival and departure from office.', 0, 1, 'C');
        $this->Ln(2);
        $this->Cell(143, 4, '______________________________', 0, 1, 'R');
        $this->Cell(0, 6, 'In-Charge', 0, 1, 'C');
    }
}

$pdf = new PDF('P', 'mm', array(215.9, 279.4)); // Short bond paper in portrait mode
$pdf->AddPage();
$pdf->SetFont('Arial', '', 5);

// Table headers
$pdf->SetY(55);
$pdf->SetX(58);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(10, 10, 'Days', 1, 0, 'C');
$pdf->Cell(28, 5, 'A.M.', 1, 0, 'C');
$pdf->Cell(28, 5, 'P.M.', 1, 0, 'C');
$pdf->Cell(28, 5, 'TOTAL', 1, 1, 'C');

$pdf->SetX(68);
$pdf->Cell(14, 5, 'Arrival', 1, 0, 'C');
$pdf->Cell(14, 5, 'Departure', 1, 0, 'C');
$pdf->Cell(14, 5, 'Arrival', 1, 0, 'C');
$pdf->Cell(14, 5, 'Departure', 1, 0, 'C');
$pdf->Cell(14, 5, 'Hours', 1, 0, 'C');
$pdf->Cell(14, 5, 'Minutes', 1, 1, 'C');

$total_hours = 0;
$total_minutes = 0;

$pdf->SetFont('Arial', '', 8);
for ($day = 1; $day <= 31; $day++) {
    $pdf->SetX(58);
    $pdf->Cell(10, 5, $day, 1, 0, 'C');

    $date = sprintf('%s-%02d', date('Y-m'), $day);
    $log = $user_logs[$date] ?? [];

    // Ensure all keys exist
    $log += ['am_arrival' => '', 'am_departure' => '', 'pm_arrival' => '', 'pm_departure' => ''];

    // Check if the date is a Sunday
    $day_of_week = date('l', strtotime($date));
    $pdf->SetFont('Arial', 'I', 8);
    if ($day_of_week == 'Sunday') {
        $pdf->Cell(84, 5, 'Sunday', 1, 0, 'C');
    } else {
        // Convert time from 24-hour to 12-hour format
        foreach ($log as $key => $time) {
            if (!empty($time)) {
                $formatted_time = date('h:i A', strtotime($time));
                
                // If it's AM Arrival and earlier than 9:00 AM, set it to "9:00 AM"
                if ($key === 'am_arrival' && strtotime($time) < strtotime('09:00:00')) {
                    $formatted_time = '09:00 AM';
                }
        
                $log[$key] = $formatted_time;
            }
        }

        // Calculate total worked hours for the day
        $am_in  = !empty($log['am_arrival']) ? strtotime($log['am_arrival']) : 0;
        $am_out = !empty($log['am_departure']) ? strtotime($log['am_departure']) : 0;
        $pm_in  = !empty($log['pm_arrival']) ? strtotime($log['pm_arrival']) : 0;
        $pm_out = !empty($log['pm_departure']) ? strtotime($log['pm_departure']) : 0;

        // Compute rendered time
        $morning_hours = ($am_out > $am_in) ? ($am_out - $am_in) : 0;
        $afternoon_hours = ($pm_out > $pm_in) ? ($pm_out - $pm_in) : 0;

        // Sum total seconds
        $total_seconds = $morning_hours + $afternoon_hours;
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);

        // Accumulate total working hours
        $total_hours += $hours;
        $total_minutes += $minutes;

        // Adjust minutes into hours if over 60 minutes
        if ($total_minutes >= 60) {
            $total_hours += floor($total_minutes / 60);
            $total_minutes = $total_minutes % 60;
        }

        // Display work logs
        $pdf->Cell(14, 5, $log['am_arrival'], 1, 0, 'C');
        $pdf->Cell(14, 5, $log['am_departure'], 1, 0, 'C');
        $pdf->Cell(14, 5, $log['pm_arrival'], 1, 0, 'C');
        $pdf->Cell(14, 5, $log['pm_departure'], 1, 0, 'C');
        $pdf->Cell(14, 5, $hours, 1, 0, 'C'); // Under Time Hours
        $pdf->Cell(14, 5, $minutes, 1, 0, 'C'); // Under Time Minutes
    }
    $pdf->Ln();
}

// Adding the "Total" row with summed hours
$pdf->SetX(58);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(66, 8, 'TOTAL:', 1, 0, 'C'); // Merge "Days" to "P.M. Departure"
$pdf->Cell(14, 8, $total_hours . 'h', 1, 0, 'C'); // Total Hours
$pdf->Cell(14, 8, $total_minutes . 'm', 1, 0, 'C'); // Total Minutes

$pdf->Ln();
// Ensure no whitespace or errors before output
ob_clean();
$pdf->Output('I', 'DTR_Form.pdf');
?>
