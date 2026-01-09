<?php
require_once "../../config/db.php";
require_once "../../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Function to generate batch number
function generateBatchNumber($conn, $medicine_id, $medicine_name)
{
    // Get first 3 letters of medicine name (uppercase)
    $prefix = strtoupper(substr($medicine_name, 0, 3));

    // Get current month and year (MMYY format)
    $month = date('m');
    $year = date('y');
    $date_part = $month . $year;

    // Get the last batch sequence for this medicine for current month
    $query = "SELECT batch_no FROM stock_batches 
              WHERE medicine_id = $medicine_id 
              AND batch_no LIKE '$prefix-$date_part-%'
              ORDER BY batch_no DESC LIMIT 1";

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_batch = $row['batch_no'];

        // Extract the sequence number and increment
        $parts = explode('-', $last_batch);
        $sequence = intval($parts[2]);
        $sequence++;
    } else {
        // First batch for this medicine this month
        $sequence = 1;
    }

    // Format sequence as 3 digits (001, 002, etc.)
    $sequence_formatted = str_pad($sequence, 3, '0', STR_PAD_LEFT);

    // Return generated batch number
    return "$prefix-$date_part-$sequence_formatted";
}

// Set header for JSON response
header('Content-Type: application/json');

// Check if it's an AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == 'generate_batch_no') {
    $medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;
    $medicine_name = isset($_GET['medicine_name']) ? $_GET['medicine_name'] : '';

    if ($medicine_id > 0 && !empty($medicine_name)) {
        $batch_no = generateBatchNumber($conn, $medicine_id, $medicine_name);

        // Extract parts for explanation
        $prefix = substr($batch_no, 0, 3);
        $date_part = substr($batch_no, 4, 4);
        $sequence = substr($batch_no, 9);

        // Get month name
        $month_num = substr($date_part, 0, 2);
        $months = [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December'
        ];
        $month_name = $months[$month_num] ?? 'Invalid Month';
        $year = '20' . substr($date_part, 2, 2);

        echo json_encode([
            'success' => true,
            'batch_no' => $batch_no,
            'explanation' => "$prefix = First 3 letters of medicine<br>$date_part = $month_name $year<br>$sequence = Sequence " . intval($sequence)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit;
