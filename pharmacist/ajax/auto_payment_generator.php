<?php
require_once "../config/db.php";

/**
 * Automatically create payment record for a sale
 */
function createAutoPaymentForSale($sale_id, $conn)
{
    // Get sale details
    $query = "SELECT s.*, u.username 
              FROM sales s 
              JOIN users u ON s.pharmacist_id = u.id 
              WHERE s.id = $sale_id";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 0) {
        return false;
    }

    $sale = mysqli_fetch_assoc($result);

    // Calculate net amount
    $net_amount = $sale['total_amount'] - $sale['discount'];

    // Check if auto payment already exists
    $check_query = "SELECT id FROM payments WHERE payment_type = 'sale' AND reference_id = $sale_id AND is_auto_generated = 1";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        return true; // Already exists
    }

    // Create payment record
    $invoice_no = $sale['invoice_no'];
    $payment_date = $sale['sale_date'];
    $created_by = $sale['pharmacist_id'];

    $insert_query = "INSERT INTO payments (
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
                        created_at,
                        updated_at
                    ) VALUES (
                        'sale',
                        $sale_id,
                        '$invoice_no',
                        $net_amount,
                        '" . $sale['payment_method'] . "',
                        'completed',
                        '$payment_date',
                        'Auto-generated payment for sale',
                        1,
                        'SALES_SYSTEM',
                        $net_amount,
                        " . $sale['discount'] . ",
                        $created_by,
                        NOW(),
                        NOW()
                    )";

    return mysqli_query($conn, $insert_query);
}

/**
 * Automatically create payment record for a return to company
 */
function createAutoPaymentForReturn($return_id, $conn)
{
    // Get return details
    $query = "SELECT r.*, u.username 
              FROM returns_to_company r 
              JOIN users u ON r.returned_by = u.id 
              WHERE r.id = $return_id";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 0) {
        return false;
    }

    $return = mysqli_fetch_assoc($result);

    // Check if auto payment already exists
    $check_query = "SELECT id FROM payments WHERE payment_type = 'return_to_company' AND reference_id = $return_id AND is_auto_generated = 1";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        return true; // Already exists
    }

    // Create payment record
    $invoice_no = 'RET-PAY-' . str_pad($return_id, 6, '0', STR_PAD_LEFT);
    $payment_date = $return['returned_at'];
    $created_by = $return['returned_by'];
    $amount = $return['total_price'];

    $insert_query = "INSERT INTO payments (
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
                        created_at,
                        updated_at
                    ) VALUES (
                        'return_to_company',
                        $return_id,
                        '$invoice_no',
                        $amount,
                        'Cash',
                        'completed',
                        '$payment_date',
                        'Auto-generated payment for return to company',
                        1,
                        'RETURNS_SYSTEM',
                        $amount,
                        0.00,
                        $created_by,
                        NOW(),
                        NOW()
                    )";

    return mysqli_query($conn, $insert_query);
}

/**
 * Sync all existing sales and returns to create auto payments
 */
function syncAllAutoPayments($conn)
{
    $total_created = 0;

    // Sync sales
    $sales_query = "SELECT id FROM sales WHERE id NOT IN (
                        SELECT reference_id FROM payments WHERE payment_type = 'sale' AND is_auto_generated = 1
                    )";
    $sales_result = mysqli_query($conn, $sales_query);

    while ($sale = mysqli_fetch_assoc($sales_result)) {
        if (createAutoPaymentForSale($sale['id'], $conn)) {
            $total_created++;
        }
    }

    // Sync returns
    $returns_query = "SELECT id FROM returns_to_company WHERE id NOT IN (
                        SELECT reference_id FROM payments WHERE payment_type = 'return_to_company' AND is_auto_generated = 1
                    )";
    $returns_result = mysqli_query($conn, $returns_query);

    while ($return = mysqli_fetch_assoc($returns_result)) {
        if (createAutoPaymentForReturn($return['id'], $conn)) {
            $total_created++;
        }
    }

    return $total_created;
}
