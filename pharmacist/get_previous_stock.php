<?php
require_once "../config/db.php";

header('Content-Type: application/json');

if (!isset($_GET['medicine_id']) || empty($_GET['medicine_id'])) {
    echo json_encode(['success' => false, 'message' => 'Medicine ID required']);
    exit;
}

$medicine_id = intval($_GET['medicine_id']);

// Get previous stock information for this medicine
$query = "SELECT batch_no, location, received_date 
          FROM stock_batches 
          WHERE medicine_id = $medicine_id 
          ORDER BY received_date DESC 
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $previous_stock = mysqli_fetch_assoc($conn);

    // Extract batch number and increment it
    $previous_batch = $previous_stock['batch_no'];
    $previous_location = $previous_stock['location'];

    // Generate next batch number
    preg_match('/(\D+)(\d+)$/', $previous_batch, $matches);
    if (count($matches) >= 3) {
        $prefix = $matches[1];
        $number = intval($matches[2]);
        $next_batch = $prefix . str_pad($number + 1, strlen($matches[2]), '0', STR_PAD_LEFT);
    } else {
        // If pattern doesn't match, create new batch number
        $med_query = mysqli_query($conn, "SELECT name FROM medicines WHERE id = $medicine_id");
        $medicine = mysqli_fetch_assoc($med_query);
        $abbreviation = substr(strtoupper(preg_replace('/[^A-Z]/', '', $medicine['name'])), 0, 3) ?: 'MED';
        $year = date('y');
        $month = date('m');
        $next_batch = $abbreviation . '-' . $year . $month . '-001';
    }

    echo json_encode([
        'success' => true,
        'previous_batch' => $previous_batch,
        'suggested_batch' => $next_batch,
        'previous_location' => $previous_location,
        'message' => 'Previous stock information loaded'
    ]);
} else {
    // No previous stock found
    $med_query = mysqli_query($conn, "SELECT name FROM medicines WHERE id = $medicine_id");
    $medicine = mysqli_fetch_assoc($med_query);
    $abbreviation = substr(strtoupper(preg_replace('/[^A-Z]/', '', $medicine['name'])), 0, 3) ?: 'MED';
    $year = date('y');
    $month = date('m');
    $initial_batch = $abbreviation . '-' . $year . $month . '-001';

    echo json_encode([
        'success' => true,
        'previous_batch' => null,
        'suggested_batch' => $initial_batch,
        'previous_location' => null,
        'message' => 'No previous stock found. Initial batch created.'
    ]);
}
