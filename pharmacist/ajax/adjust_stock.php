<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Set header for JSON response
header('Content-Type: application/json');

// Check permissions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate data
$medicine_id = isset($_POST['medicine_id']) ? intval($_POST['medicine_id']) : 0;
$adjustment_type = isset($_POST['adjustment_type']) ? $_POST['adjustment_type'] : '';
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, trim($_POST['reason'])) : '';

// Validate required fields
if ($medicine_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
    exit;
}

if (!in_array($adjustment_type, ['add', 'remove', 'set'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid adjustment type']);
    exit;
}

if ($quantity <= 0 && $adjustment_type !== 'set') {
    echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
    exit;
}

if ($adjustment_type === 'set' && $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
    exit;
}

// Check if medicine exists
$check_query = "SELECT id, name FROM medicines WHERE id = $medicine_id";
$check_result = mysqli_query($conn, $check_query);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Medicine not found']);
    exit;
}

$medicine = mysqli_fetch_assoc($check_result);

// Get current total stock
$stock_query = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(quantity), 0) as total_stock 
     FROM stock_batches 
     WHERE medicine_id = $medicine_id 
     AND quantity > 0 
     AND is_expired = 0
     AND is_returned = 0
     AND is_disposed = 0"
);

$stock_data = mysqli_fetch_assoc($stock_query);
$current_stock = $stock_data['total_stock'];

// Calculate new stock based on adjustment type
$new_stock = $current_stock;
$adjustment_quantity = 0;

switch ($adjustment_type) {
    case 'add':
        $new_stock = $current_stock + $quantity;
        $adjustment_quantity = $quantity;
        break;
        
    case 'remove':
        if ($quantity > $current_stock) {
            echo json_encode(['success' => false, 'message' => 'Cannot remove more than current stock']);
            exit;
        }
        $new_stock = $current_stock - $quantity;
        $adjustment_quantity = -$quantity;
        break;
        
    case 'set':
        $adjustment_quantity = $quantity - $current_stock;
        $new_stock = $quantity;
        break;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Create a new batch for the adjustment
    $batch_no = 'ADJ-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $insert_query = "INSERT INTO stock_batches (
        medicine_id,
        batch_no,
        quantity,
        units_per_packet,
        packets_per_box,
        purchase_price,
        selling_price,
        mrp,
        supplier_id,
        received_date,
        expiry_date,
        location,
        is_expired,
        added_at
    ) VALUES (
        $medicine_id,
        '$batch_no',
        $adjustment_quantity,
        1,
        1,
        0.00,
        0.00,
        0.00,
        NULL,
        NOW(),
        DATE_ADD(NOW(), INTERVAL 365 DAY),
        'Adjustment',
        0,
        NOW()
    )";
    
    if (!mysqli_query($conn, $insert_query)) {
        throw new Exception('Failed to create adjustment batch: ' . mysqli_error($conn));
    }
    
    // Log the adjustment
    $log_query = "INSERT INTO stock_adjustments (
        medicine_id,
        adjustment_type,
        quantity,
        previous_stock,
        new_stock,
        reason,
        adjusted_by,
        adjusted_at
    ) VALUES (
        $medicine_id,
        '$adjustment_type',
        $adjustment_quantity,
        $current_stock,
        $new_stock,
        '$reason',
        '{$_SESSION['user_id']}',
        NOW()
    )";
    
    if (!mysqli_query($conn, $log_query)) {
        throw new Exception('Failed to log adjustment: ' . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => "Stock adjusted successfully. New total: $new_stock units",
        'previous_stock' => $current_stock,
        'new_stock' => $new_stock,
        'adjustment' => $adjustment_quantity
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;
?>