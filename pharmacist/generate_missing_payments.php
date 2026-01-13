<?php
// generate_missing_payments.php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    die("Admin access required");
}

echo "<h2>Generating Missing Payments</h2>";
echo "<pre>";

// Get all sales without payments
$sales_query = "SELECT s.*, i.customer_name, i.invoice_no, s.pharmacist_id 
                FROM sales s 
                JOIN invoices i ON s.id = i.sale_id 
                WHERE s.id NOT IN (SELECT reference_id FROM payments WHERE payment_type = 'sale') 
                ORDER BY s.sale_date";

$sales_result = mysqli_query($conn, $sales_query);
$total_sales = mysqli_num_rows($sales_result);
$generated_count = 0;

echo "Found $total_sales sales without payments\n\n";

while ($sale = mysqli_fetch_assoc($sales_result)) {
    $sale_id = $sale['id'];
    $pharmacist_id = $sale['pharmacist_id'];
    $invoice_no = $sale['invoice_no'];
    $total_amount = $sale['total_amount'];
    $discount = $sale['discount'];
    $net_amount = $total_amount - $discount;
    $payment_method = $sale['payment_method'];
    $sale_date = $sale['sale_date'];
    $customer_name = $sale['customer_name'];

    // Determine sale type
    if ($customer_name === 'Regular Customer') {
        $sale_type = 'REGULAR_SALES';
        $sale_type_display = 'Regular';
    } else {
        $sale_type = 'WHOLESALE_SALES';
        $sale_type_display = 'Wholesale';
    }

    // Check if payment already exists
    $check_payment = "SELECT id FROM payments WHERE payment_type = 'sale' AND reference_id = $sale_id";
    $check_result = mysqli_query($conn, $check_payment);

    if (mysqli_num_rows($check_result) == 0) {
        // Insert auto-generated payment
        $payment_query = "INSERT INTO payments (
            payment_date, payment_type, reference_id, invoice_no, amount,
            payment_method, payment_status, notes, created_by, pharmacist_id,
            is_auto_generated, auto_generated_from, transaction_net_amount, transaction_discount,
            created_at, updated_at
        ) VALUES (
            '$sale_date', 'sale', $sale_id, '$invoice_no', $net_amount,
            '$payment_method', 'completed', 'Auto-generated payment for existing $sale_type_display sale (Invoice: $invoice_no)', 
            $pharmacist_id, $pharmacist_id,
            1, '$sale_type', $net_amount, $discount,
            NOW(), NOW()
        )";

        if (mysqli_query($conn, $payment_query)) {
            echo "✓ Generated payment for $sale_type_display sale ID: $sale_id (Invoice: $invoice_no)\n";
            $generated_count++;
        } else {
            echo "✗ Error generating payment for sale ID: $sale_id - " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ Payment already exists for sale ID: $sale_id\n";
    }
}

// Get all returns without payments
$returns_query = "SELECT r.* 
                  FROM returns_to_company r 
                  WHERE r.id NOT IN (SELECT reference_id FROM payments WHERE payment_type = 'return_to_company') 
                  ORDER BY r.return_date";

$returns_result = mysqli_query($conn, $returns_query);
$total_returns = mysqli_num_rows($returns_result);

echo "\nFound $total_returns returns without payments\n\n";

while ($return = mysqli_fetch_assoc($returns_result)) {
    $return_id = $return['id'];
    $pharmacist_id = $return['returned_by'];
    $invoice_no = $return['invoice_no'];
    $total_price = $return['total_price'];
    $return_date = $return['return_date'];

    // Check if payment already exists
    $check_payment = "SELECT id FROM payments WHERE payment_type = 'return_to_company' AND reference_id = $return_id";
    $check_result = mysqli_query($conn, $check_payment);

    if (mysqli_num_rows($check_result) == 0) {
        // Insert auto-generated payment for return
        $payment_query = "INSERT INTO payments (
            payment_date, payment_type, reference_id, invoice_no, amount,
            payment_method, payment_status, notes, created_by, pharmacist_id,
            is_auto_generated, auto_generated_from, transaction_net_amount, transaction_discount,
            created_at, updated_at
        ) VALUES (
            '$return_date', 'return_to_company', $return_id, '$invoice_no', $total_price,
            'Cash', 'completed', 'Auto-generated payment for return to company (Invoice: $invoice_no)', 
            $pharmacist_id, $pharmacist_id,
            1, 'RETURNS_SYSTEM', $total_price, 0,
            NOW(), NOW()
        )";

        if (mysqli_query($conn, $payment_query)) {
            echo "✓ Generated payment for return ID: $return_id (Invoice: $invoice_no)\n";
            $generated_count++;
        } else {
            echo "✗ Error generating payment for return ID: $return_id - " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ Payment already exists for return ID: $return_id\n";
    }
}

echo "\n========================================\n";
echo "Summary:\n";
echo "- Total sales without payments: $total_sales\n";
echo "- Total returns without payments: $total_returns\n";
echo "- Total payments generated: $generated_count\n";
echo "========================================\n";

echo "</pre>";
