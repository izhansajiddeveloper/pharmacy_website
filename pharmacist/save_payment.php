<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacists can add/edit payments
if ($_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access!']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
$payment_type = mysqli_real_escape_string($conn, $_POST['payment_type']);
$reference_id = intval($_POST['reference_id']);
$invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
$amount = floatval($_POST['amount']);
$payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
$payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
$payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
$notes = mysqli_real_escape_string($conn, $_POST['notes']);

// Validate reference exists based on type
if ($payment_type === 'sale') {
    // UPDATED QUERY: Join with invoices to get customer name for sale type detection
    $check_query = "SELECT s.id, s.total_amount, s.discount, (s.total_amount - s.discount) as net_amount, 
                           s.pharmacist_id, s.invoice_no, i.customer_name
                    FROM sales s 
                    JOIN invoices i ON s.id = i.sale_id 
                    WHERE s.id = $reference_id";
} else {
    $check_query = "SELECT id, total_price as total_amount, 0 as discount, total_price as net_amount, 
                           returned_by as pharmacist_id, CONCAT('RET-', id) as invoice_no, 
                           'return' as customer_name 
                    FROM returns_to_company 
                    WHERE id = $reference_id";
}

$check_result = mysqli_query($conn, $check_query);
if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Reference ID does not exist!']);
    exit;
}

$reference_data = mysqli_fetch_assoc($check_result);

// Check if user has permission for this reference
if ($reference_data['pharmacist_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to create payment for this transaction!']);
    exit;
}

// Check if auto-generated payment already exists for this reference
$existing_payment_query = "SELECT id FROM payments WHERE payment_type = '$payment_type' AND reference_id = $reference_id AND is_auto_generated = 1";
$existing_payment_result = mysqli_query($conn, $existing_payment_query);

if (mysqli_num_rows($existing_payment_result) > 0 && $payment_id == 0) {
    // Auto-generated payment already exists, suggest viewing it
    $existing = mysqli_fetch_assoc($existing_payment_result);
    echo json_encode(['success' => false, 'message' => 'An auto-generated payment already exists for this transaction. You can view it in the payments list.']);
    exit;
}

// Validate amount is positive
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0!']);
    exit;
}

// Check invoice number uniqueness (for manual payments only)
$invoice_check = "SELECT id FROM payments WHERE invoice_no = '$invoice_no' AND is_auto_generated = 0";
if ($payment_id > 0) {
    $invoice_check .= " AND id != $payment_id";
}
$invoice_result = mysqli_query($conn, $invoice_check);
if (mysqli_num_rows($invoice_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Invoice number already exists for another manual payment!']);
    exit;
}

// Determine sale type for auto-generated_from field
$auto_generated_from = 'MANUAL';
$sale_type = '';

if ($payment_type === 'sale') {
    // Check if it's regular or wholesale based on customer name
    if (isset($reference_data['customer_name'])) {
        if ($reference_data['customer_name'] === 'Regular Customer') {
            $sale_type = 'REGULAR_SALES';
        } else {
            $sale_type = 'WHOLESALE_SALES';
        }
    }
}

if ($payment_id > 0) {
    // Update existing payment
    // Verify ownership
    $verify_query = "SELECT created_by, is_auto_generated FROM payments WHERE id = $payment_id";
    $verify_result = mysqli_query($conn, $verify_query);
    $verify = mysqli_fetch_assoc($verify_result);

    if ($verify['created_by'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You can only edit your own payments!']);
        exit;
    }

    if ($verify['is_auto_generated'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Auto-generated payments cannot be edited!']);
        exit;
    }

    $query = "UPDATE payments SET 
                payment_type = '$payment_type',
                reference_id = $reference_id,
                invoice_no = '$invoice_no',
                amount = $amount,
                payment_method = '$payment_method',
                payment_status = '$payment_status',
                payment_date = '$payment_date',
                notes = '$notes',
                transaction_net_amount = " . $reference_data['net_amount'] . ",
                transaction_discount = " . $reference_data['discount'] . ",
                updated_at = NOW()
              WHERE id = $payment_id";
} else {
    // Insert new manual payment
    // UPDATED: Include sale type in auto_generated_from field for manual payments too
    $auto_generated_from = $payment_type === 'sale' && !empty($sale_type) ? $sale_type : 'MANUAL';

    $query = "INSERT INTO payments (
                payment_type,
                reference_id,
                invoice_no,
                amount,
                payment_method,
                payment_status,
                payment_date,
                notes,
                is_auto_generated,
                auto_generated_from,
                transaction_net_amount,
                transaction_discount,
                created_by,
                pharmacist_id,
                created_at,
                updated_at
              ) VALUES (
                '$payment_type',
                $reference_id,
                '$invoice_no',
                $amount,
                '$payment_method',
                '$payment_status',
                '$payment_date',
                '$notes',
                0,
                '$auto_generated_from',
                " . $reference_data['net_amount'] . ",
                " . $reference_data['discount'] . ",
                $user_id,
                $user_id,
                NOW(),
                NOW()
              )";
}

if (mysqli_query($conn, $query)) {
    echo json_encode(['success' => true, 'message' => $payment_id > 0 ? 'Payment updated successfully!' : 'Payment added successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
