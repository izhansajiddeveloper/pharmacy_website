<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    header("Location: ../index.php");
    exit;
}

// Handle return amount update
if (isset($_POST['update_return_amount'])) {
    $return_id = mysqli_real_escape_string($conn, $_POST['return_id']);
    $return_amount = mysqli_real_escape_string($conn, $_POST['return_amount']);

    $update_query = "UPDATE returns_to_company SET return_amount = '$return_amount' WHERE id = '$return_id'";

    if (mysqli_query($conn, $update_query)) {
        // After successful return creation
        require_once "./ajax/auto_payment_generator.php";
        if (createAutoPaymentForReturn($return_id, $conn)) {
            // Payment created successfully

        }
        $_SESSION['success'] = "Return amount updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating return amount: " . mysqli_error($conn);
    }

    header("Location: return_to_company.php");
    exit;
}

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    exportToExcel($conn);
    exit;
}

// Handle print receipt request
if (isset($_GET['print_receipt']) && isset($_GET['return_id'])) {
    printReturnReceipt($conn, $_GET['return_id']);
    exit;
}

// Fetch all returns to company - UPDATED QUERY
$query = "
    SELECT 
        rt.id AS return_id,
        rt.batch_id,
        rt.medicine_id,
        rt.batch_no,
        rt.quantity,
        rt.purchase_price,
        COALESCE(rt.return_amount, 0) AS return_amount,
        rt.return_reason,
        rt.return_notes,
        rt.returned_by,
        rt.returned_at,
        m.name AS medicine_name,
        mg.name AS generic_name,  -- updated to join medicine_generics
        u.username AS returned_by_name,
        s.name AS supplier_name,
        s.phone AS supplier_phone,
        s.email AS supplier_email,
        sb.expiry_date,
        sb.received_date,
        sb.location,
        DATEDIFF(rt.returned_at, sb.expiry_date) AS days_after_expiry
    FROM returns_to_company rt
    JOIN medicines m ON rt.medicine_id = m.id
    LEFT JOIN medicine_generics mg ON m.generic_id = mg.id  -- join generic table
    JOIN users u ON rt.returned_by = u.id
    JOIN stock_batches sb ON rt.batch_id = sb.id
    LEFT JOIN suppliers s ON sb.supplier_id = s.id
    ORDER BY rt.returned_at DESC
";


$result = mysqli_query($conn, $query);
$returns = [];
$total_returned_value = 0;
$total_returned_quantity = 0;
$total_return_amount_received = 0;

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate purchase value (total)
    $row['total_value'] = $row['purchase_price'] * $row['quantity'];

    // return_amount is now TOTAL amount (not multiplied by quantity)
    $row['total_return_amount'] = $row['return_amount'];

    // Calculate per unit return amount for display
    $row['per_unit_return_amount'] = $row['quantity'] > 0 ? ($row['return_amount'] / $row['quantity']) : 0;

    $returns[] = $row;
    $total_returned_value += $row['total_value'];
    $total_returned_quantity += $row['quantity'];
    $total_return_amount_received += $row['total_return_amount'];
}

// Get statistics
$unique_medicines = array_unique(array_column($returns, 'medicine_name'));
$unique_suppliers = array_unique(array_column($returns, 'supplier_name'));
$unique_suppliers = array_filter($unique_suppliers);

// Group by return reason
$return_reasons = [];
foreach ($returns as $return) {
    $reason = $return['return_reason'];
    if (!isset($return_reasons[$reason])) {
        $return_reasons[$reason] = 0;
    }
    $return_reasons[$reason] += $return['quantity'];
}

// Get monthly statistics
$monthly_stats = [];
foreach ($returns as $return) {
    $month = date('Y-m', strtotime($return['returned_at']));
    if (!isset($monthly_stats[$month])) {
        $monthly_stats[$month] = [
            'count' => 0,
            'value' => 0,
            'quantity' => 0,
            'return_amount' => 0
        ];
    }
    $monthly_stats[$month]['count']++;
    $monthly_stats[$month]['value'] += $return['total_value'];
    $monthly_stats[$month]['quantity'] += $return['quantity'];
    $monthly_stats[$month]['return_amount'] += $return['total_return_amount'];
}

// Store return data for JavaScript
$return_data_json = [];
foreach ($returns as $ret) {
    $return_data_json[$ret['return_id']] = [
        'return_id' => $ret['return_id'],
        'medicine_name' => $ret['medicine_name'],
        'batch_no' => $ret['batch_no'],
        'quantity' => $ret['quantity'],
        'purchase_price' => $ret['purchase_price'],
        'return_amount' => $ret['return_amount'], // TOTAL amount
        'total_value' => $ret['total_value'],
        'total_return_amount' => $ret['total_return_amount'], // Same as return_amount
        'per_unit_return_amount' => $ret['per_unit_return_amount'], // Calculated
        'return_reason' => $ret['return_reason'],
        'return_notes' => $ret['return_notes'],
        'returned_by_name' => $ret['returned_by_name'],
        'returned_at' => $ret['returned_at'],
        'supplier_name' => $ret['supplier_name'],
        'supplier_phone' => $ret['supplier_phone'],
        'supplier_email' => $ret['supplier_email'],
        'expiry_date' => $ret['expiry_date'],
        'received_date' => $ret['received_date'],
        'location' => $ret['location'],
        'days_after_expiry' => $ret['days_after_expiry'],
        'generic_name' => $ret['generic_name'],
    ];
}

// Function to export to Excel - UPDATED
function exportToExcel($conn)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="returns_to_company_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    $export_query = "
        SELECT 
            rt.returned_at AS 'Return Date',
            m.name AS 'Medicine Name',
            m.generic_name AS 'Generic Name',
            rt.batch_no AS 'Batch No',
            rt.quantity AS 'Quantity',
            rt.purchase_price AS 'Purchase Price',
            COALESCE(rt.return_amount, 0) AS 'Return Amount Received',
            (rt.purchase_price * rt.quantity) AS 'Purchase Value',
            (COALESCE(rt.return_amount, 0) / NULLIF(rt.quantity, 0)) AS 'Return Amount Per Unit',
            rt.return_reason AS 'Return Reason',
            rt.return_notes AS 'Return Notes',
            u.username AS 'Returned By',
            s.name AS 'Supplier',
            sb.expiry_date AS 'Expiry Date',
            DATEDIFF(rt.returned_at, sb.expiry_date) AS 'Days After Expiry'
        FROM returns_to_company rt
        JOIN medicines m ON rt.medicine_id = m.id
        JOIN users u ON rt.returned_by = u.id
        JOIN stock_batches sb ON rt.batch_id = sb.id
        LEFT JOIN suppliers s ON sb.supplier_id = s.id
        ORDER BY rt.returned_at DESC
    ";

    $export_result = mysqli_query($conn, $export_query);

    $output = "<table border='1'>";
    $output .= "<tr>";

    // Headers
    if ($row = mysqli_fetch_assoc($export_result)) {
        foreach (array_keys($row) as $column) {
            $output .= "<th><b>" . $column . "</b></th>";
        }
        $output .= "</tr>";

        // Add first row
        $output .= "<tr>";
        foreach ($row as $value) {
            $output .= "<td>" . $value . "</td>";
        }
        $output .= "</tr>";

        // Add remaining rows
        while ($row = mysqli_fetch_assoc($export_result)) {
            $output .= "<tr>";
            foreach ($row as $value) {
                $output .= "<td>" . $value . "</td>";
            }
            $output .= "</tr>";
        }
    }

    $output .= "</table>";
    echo $output;
    exit;
}

// Function to print return receipt - UPDATED
function printReturnReceipt($conn, $return_id)
{
    $return_id = mysqli_real_escape_string($conn, $return_id);

    $receipt_query = "
    SELECT 
        rt.*,
        m.name AS medicine_name,
        mg.name AS generic_name,   -- replaced m.generic_name with mg.name
        u.username AS returned_by_name,
        s.name AS supplier_name,
        s.phone AS supplier_phone,
        s.email AS supplier_email,
        s.address AS supplier_address,
        sb.expiry_date,
        sb.received_date,
        sb.location,
        (rt.purchase_price * rt.quantity) AS total_value,
        COALESCE(rt.return_amount, 0) AS total_return_amount,
        (COALESCE(rt.return_amount, 0) / NULLIF(rt.quantity, 0)) AS return_amount_per_unit
    FROM returns_to_company rt
    JOIN medicines m ON rt.medicine_id = m.id
    LEFT JOIN medicine_generics mg ON m.generic_id = mg.id  -- join generic table
    JOIN users u ON rt.returned_by = u.id
    JOIN stock_batches sb ON rt.batch_id = sb.id
    LEFT JOIN suppliers s ON sb.supplier_id = s.id
    WHERE rt.id = '$return_id'
";


    $receipt_result = mysqli_query($conn, $receipt_query);
    $receipt_data = mysqli_fetch_assoc($receipt_result);

    if (!$receipt_data) {
        die("Return record not found!");
    }

    header('Content-Type: text/html; charset=utf-8');
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Return Receipt - <?php echo $receipt_data['batch_no']; ?></title>
        <style>
            @media print {
                @page {
                    margin: 0;
                }

                body {
                    margin: 1.5cm;
                }
            }

            body {
                font-family: 'Arial', sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background: white;
            }

            .receipt-header {
                text-align: center;
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }

            .receipt-title {
                font-size: 24px;
                font-weight: bold;
                color: #333;
            }

            .receipt-subtitle {
                font-size: 14px;
                color: #666;
            }

            .receipt-info {
                margin: 20px 0;
            }

            .info-row {
                display: flex;
                margin: 8px 0;
            }

            .info-label {
                width: 200px;
                font-weight: bold;
                color: #555;
            }

            .info-value {
                flex: 1;
                color: #333;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }

            .table th {
                background: #f5f5f5;
                padding: 10px;
                text-align: left;
                border: 1px solid #ddd;
                font-weight: bold;
            }

            .table td {
                padding: 10px;
                border: 1px solid #ddd;
            }

            .total-row {
                font-weight: bold;
                background: #f9f9f9;
            }

            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 12px;
                color: #666;
            }

            .signature {
                margin-top: 60px;
                display: flex;
                justify-content: space-between;
            }

            .signature-box {
                width: 45%;
                text-align: center;
                border-top: 1px solid #333;
                padding-top: 10px;
            }

            .stamp {
                text-align: center;
                margin-top: 30px;
                color: red;
                font-weight: bold;
            }
        </style>
    </head>

    <body onload="window.print();">
        <div class="receipt-header">
            <div class="receipt-title">MediCare Pharma</div>
            <div class="receipt-subtitle">Return to Company Receipt</div>
            <div class="receipt-subtitle">Receipt No: RTN-<?php echo str_pad($receipt_data['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <div class="receipt-subtitle">Generated on: <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>

        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Return ID:</div>
                <div class="info-value">RTN-<?php echo str_pad($receipt_data['id'], 6, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Return Date:</div>
                <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($receipt_data['returned_at'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Medicine:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['medicine_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Batch Number:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['batch_no']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Generic Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['generic_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Supplier:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['supplier_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Expiry Date:</div>
                <div class="info-value"><?php echo date('d M Y', strtotime($receipt_data['expiry_date'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Return Reason:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['return_reason']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Return Notes:</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($receipt_data['return_notes'])); ?></div>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Purchase Value</th>
                    <th>Return Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($receipt_data['medicine_name']); ?> (Batch: <?php echo htmlspecialchars($receipt_data['batch_no']); ?>)</td>
                    <td><?php echo $receipt_data['quantity']; ?> units</td>
                    <td>Rs <?php echo number_format($receipt_data['purchase_price'], 2); ?></td>
                    <td>Rs <?php echo number_format($receipt_data['total_value'], 2); ?></td>
                    <td>Rs <?php echo number_format($receipt_data['total_return_amount'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" align="right">Total Purchase Value:</td>
                    <td><strong>Rs <?php echo number_format($receipt_data['total_value'], 2); ?></strong></td>
                    <td></td>
                </tr>
                <?php if ($receipt_data['total_return_amount'] > 0): ?>
                    <tr class="total-row">
                        <td colspan="4" align="right">Total Return Amount Received:</td>
                        <td><strong>Rs <?php echo number_format($receipt_data['total_return_amount'], 2); ?></strong></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Returned By:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['returned_by_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Location:</div>
                <div class="info-value"><?php echo htmlspecialchars($receipt_data['location']); ?></div>
            </div>
            <?php if ($receipt_data['total_return_amount'] > 0): ?>
                <div class="info-row">
                    <div class="info-label">Amount Received Per Unit:</div>
                    <div class="info-value text-green-600">Rs <?php echo number_format($receipt_data['return_amount_per_unit'], 2); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Amount Received:</div>
                    <div class="info-value text-green-600 font-bold">Rs <?php echo number_format($receipt_data['total_return_amount'], 2); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="signature">
            <div class="signature-box">
                <div>_________________________</div>
                <div>Pharmacy Representative</div>
                <div>MediCare Pharma</div>
            </div>
            <div class="signature-box">
                <div>_________________________</div>
                <div>Supplier Representative</div>
                <div><?php echo htmlspecialchars($receipt_data['supplier_name']); ?></div>
            </div>
        </div>

        <div class="stamp">
            <div>RETURNED TO COMPANY</div>
            <div><?php echo date('d M Y', strtotime($receipt_data['returned_at'])); ?></div>
            <?php if ($receipt_data['total_return_amount'] > 0): ?>
                <div style="color: green; margin-top: 10px;">PAYMENT RECEIVED</div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>This is an official return receipt from MediCare Pharma</p>
            <p>For any queries, contact: pharmacy@medicare.com | Phone: +91 1234567890</p>
            <p>This document is computer generated and does not require signature</p>
        </div>

        <script>
            window.onload = function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 500);
            };
        </script>
    </body>

    </html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns to Company - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --primary-yellow-dark: #d97706;
            --primary-gray: #6b7280;
            --primary-gray-light: #9ca3af;
            --primary-gray-dark: #4b5563;
            --accent-teal: #14b8a6;
            --accent-purple: #8b5cf6;
            --accent-blue: #3b82f6;
        }

        body {
            background: linear-gradient(135deg, #f0f9ff 0%, #f5f3ff 50%, #f0f9ff 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(99, 102, 241, 0.3);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.2);
        }

        .gradient-indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }

        .gradient-text {
            background: linear-gradient(45deg, #6366f1, #4f46e5);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(99, 102, 241, 0.1);
            border-radius: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #6366f1;
            border-radius: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #4f46e5;
        }

        .table-row:hover {
            background-color: rgba(238, 242, 255, 0.3);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .indigo-blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.2;
            z-index: -1;
        }

        .purple-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }

            #printable-content,
            #printable-content * {
                visibility: visible;
            }

            #printable-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background: white;
            }

            .no-print {
                display: none !important;
            }
        }

        .floating-alert {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Compact table styles */
        .compact-table th,
        .compact-table td {
            padding: 0.5rem 0.75rem;
        }

        .compact-table th {
            font-size: 0.75rem;
        }

        .compact-table td {
            font-size: 0.875rem;
        }

        .compact-card {
            padding: 0.5rem;
        }

        .compact-text {
            font-size: 0.8125rem;
        }

        .truncate-cell {
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="indigo-blob top-20 right-10 animate-float"></div>
    <div class="purple-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "siderbar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-2">
                            <span class="gradient-text">Returns to Company</span> 📦
                        </h1>
                        <p class="text-gray-600 text-sm flex items-center space-x-2">
                            <i class="fas fa-undo-alt text-indigo-500"></i>
                            <span>Track and manage all returns to suppliers and companies</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-2">
                        <a href="expired_medicines.php"
                            class="px-4 py-2 border border-indigo-200 text-gray-700 rounded-lg hover:bg-indigo-50 transition font-medium flex items-center space-x-2 shadow-sm text-sm">
                            <i class="fas fa-arrow-left text-indigo-500"></i>
                            <span>Back to Expired</span>
                        </a>
                        <a href="disposal_history.php"
                            class="px-4 py-2 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-lg hover:shadow-lg transition font-medium flex items-center space-x-2 shadow text-sm">
                            <i class="fas fa-trash-alt"></i>
                            <span>Disposal</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mx-6 my-4">
                <div class="stat-card rounded-xl p-4 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 rounded-lg gradient-indigo flex items-center justify-center shadow">
                            <i class="fas fa-undo-alt text-white text-lg"></i>
                        </div>
                        <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-1"><?php echo number_format(count($returns)); ?></h3>
                    <p class="text-gray-600 text-sm mb-2">Total Returns</p>
                </div>

                <div class="stat-card rounded-xl p-4 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center shadow">
                            <i class="fas fa-boxes text-white text-lg"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Units</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-1"><?php echo number_format($total_returned_quantity); ?></h3>
                    <p class="text-gray-600 text-sm mb-2">Returned Units</p>
                </div>

                <div class="stat-card rounded-xl p-4 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center shadow">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Purchase</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-1">Rs <?php echo number_format($total_returned_value, 2); ?></h3>
                    <p class="text-gray-600 text-sm mb-2">Purchase Value</p>
                </div>

                <div class="stat-card rounded-xl p-4 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center shadow">
                            <i class="fas fa-hand-holding-usd text-white text-lg"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-full">Received</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-1">Rs <?php echo number_format($total_return_amount_received, 2); ?></h3>
                    <p class="text-gray-600 text-sm mb-2">Amount Received</p>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="glass-card mx-6 rounded-2xl p-4 mb-4 animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div class="flex items-center space-x-3 mb-3 lg:mb-0">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center shadow">
                            <i class="fas fa-chart-bar text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">Return Analytics & Management</h3>
                            <p class="text-xs text-gray-600">View analytics, generate reports, and manage return records</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="printReturnReport()"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm text-xs">
                            <i class="fas fa-print text-gray-600"></i>
                            <span class="text-gray-700">Print</span>
                        </button>
                        <button onclick="showAnalyticsModal()"
                            class="px-3 py-1.5 border border-purple-300 rounded-lg hover:bg-purple-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm text-xs">
                            <i class="fas fa-chart-pie text-purple-600"></i>
                            <span class="text-purple-700">Analytics</span>
                        </button>
                        <a href="?export=excel"
                            class="px-3 py-1.5 border border-green-300 rounded-lg hover:bg-green-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm text-xs">
                            <i class="fas fa-file-excel text-green-600"></i>
                            <span class="text-green-700">Excel</span>
                        </a>
                        <button onclick="exportAllToPDF()"
                            class="px-3 py-1.5 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg hover:shadow-lg transition font-medium flex items-center space-x-2 shadow text-xs">
                            <i class="fas fa-file-export"></i>
                            <span>Export All</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- COMPACT Returns Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.6s">
                <!-- Table Header -->
                <div class="px-4 py-3 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-indigo-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-2 md:mb-0">
                        <h3 class="text-sm font-semibold text-gray-800">Return History</h3>
                        <p class="text-xs text-gray-600">Showing <?php echo count($returns); ?> record<?php echo count($returns) !== 1 ? 's' : ''; ?> sorted by return date</p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search..."
                                class="pl-8 pr-3 py-1.5 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none transition bg-white/80 shadow-sm w-40 md:w-48 text-sm">
                            <i class="fas fa-search absolute left-2.5 top-2 text-indigo-400 text-xs"></i>
                        </div>

                        <!-- Filter by Reason -->
                        <select id="reasonFilter" class="px-3 py-1.5 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none transition bg-white/80 shadow-sm text-xs">
                            <option value="">All Reasons</option>
                            <option value="Expired">Expired</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Wrong Delivery">Wrong Delivery</option>
                            <option value="Quality Issue">Quality Issue</option>
                            <option value="Overstock">Overstock</option>
                            <option value="Supplier Recall">Supplier Recall</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Compact Table -->
                <div class="overflow-x-auto custom-scrollbar max-h-[500px]">
                    <table class="w-full min-w-[1000px] compact-table">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-indigo-50 to-indigo-25">
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Return</span>
                                        <i class="fas fa-sort text-indigo-400 cursor-pointer hover:text-indigo-600 text-xs" onclick="sortTable(0)"></i>
                                    </div>
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Medicine
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Qty
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Purchase
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Payment
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Supplier
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-50" id="returnsTableBody">
                            <?php if (count($returns) > 0): ?>
                                <?php foreach ($returns as $return):
                                    $return_date = date('d M Y, h:i A', strtotime($return['returned_at']));
                                    $expiry_date = date('d M Y', strtotime($return['expiry_date']));
                                    $total_value = $return['purchase_price'] * $return['quantity'];
                                    $total_return_amount = $return['return_amount']; // Already TOTAL amount
                                    $per_unit_return_amount = $return['quantity'] > 0 ? ($return['return_amount'] / $return['quantity']) : 0;
                                    $days_after_expiry = $return['days_after_expiry'] ?? 0;

                                    // Determine status based on days after expiry
                                    $status_class = 'bg-green-100 text-green-800';
                                    if ($days_after_expiry > 0) {
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                    }
                                    if ($days_after_expiry > 30) {
                                        $status_class = 'bg-red-100 text-red-800';
                                    }
                                ?>
                                    <tr class="table-row hover:bg-indigo-25 transition-colors" data-return-id="<?php echo $return['return_id']; ?>" data-reason="<?php echo $return['return_reason']; ?>">
                                        <!-- Compact Return Details Column -->
                                        <td class="px-3 py-2">
                                            <div class="space-y-1">
                                                <div class="flex items-center space-x-2">
                                                    <div class="w-8 h-8 rounded-md bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                                        <i class="fas fa-undo-alt text-indigo-600 text-xs"></i>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="font-semibold text-gray-800 text-xs truncate-cell" title="RTN-<?php echo str_pad($return['return_id'], 6, '0', STR_PAD_LEFT); ?>">
                                                            RTN-<?php echo str_pad($return['return_id'], 6, '0', STR_PAD_LEFT); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <i class="fas fa-calendar-alt mr-0.5 text-xs"></i>
                                                            <?php echo date('d M', strtotime($return['returned_at'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex flex-wrap gap-1">
                                                    <span class="text-xs <?php echo $status_class; ?> px-1.5 py-0.5 rounded-full">
                                                        <?php echo substr($return['return_reason'], 0, 10); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Compact Medicine Information Column -->
                                        <td class="px-3 py-2">
                                            <div class="space-y-1">
                                                <div class="font-medium text-gray-800 text-sm truncate-cell" title="<?php echo htmlspecialchars($return['medicine_name']); ?>">
                                                    <?php echo htmlspecialchars(substr($return['medicine_name'], 0, 20)); ?>
                                                </div>
                                                <div class="text-xs text-gray-600 flex items-center space-x-1">
                                                    <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-xs">
                                                        <?php echo htmlspecialchars($return['batch_no']); ?>
                                                    </span>
                                                    <?php if (!empty($return['generic_name'])): ?>
                                                        <span class="text-gray-500 truncate-cell" title="<?php echo htmlspecialchars($return['generic_name']); ?>">
                                                            <?php echo htmlspecialchars(substr($return['generic_name'], 0, 12)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <i class="fas fa-calendar-times mr-0.5 text-xs"></i>
                                                    <?php echo date('M Y', strtotime($expiry_date)); ?>
                                                    <?php if ($days_after_expiry > 0): ?>
                                                        <span class="text-yellow-600 ml-1">
                                                            <i class="fas fa-clock mr-0.5 text-xs"></i>
                                                            +<?php echo $days_after_expiry; ?>d
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Compact Quantity Column -->
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col items-center">
                                                <div class="text-lg font-bold text-indigo-600">
                                                    <?php echo number_format($return['quantity']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 bg-indigo-50 px-2 py-0.5 rounded-full">
                                                    units
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Compact Purchase Details Column -->
                                        <td class="px-3 py-2">
                                            <div class="space-y-1">
                                                <div class="bg-green-50 p-2 rounded border border-green-200">
                                                    <div class="text-xs text-gray-600">Unit</div>
                                                    <div class="text-sm font-bold text-green-600">
                                                        Rs <?php echo number_format($return['purchase_price'], 2); ?>
                                                    </div>
                                                </div>
                                                <div class="bg-indigo-50 p-2 rounded border border-indigo-200">
                                                    <div class="text-xs text-gray-600">Total</div>
                                                    <div class="text-sm font-bold text-indigo-600">
                                                        Rs <?php echo number_format($total_value, 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Compact Return Payment Column - UPDATED -->
                                        <td class="px-3 py-2">
                                            <div class="space-y-1">
                                                <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                    <div class="text-xs text-gray-600">Per Unit</div>
                                                    <div class="text-sm font-bold <?php echo $per_unit_return_amount > 0 ? 'text-blue-600' : 'text-gray-600'; ?>">
                                                        Rs <?php echo number_format($per_unit_return_amount, 2); ?>
                                                    </div>
                                                </div>
                                                <div class="bg-purple-50 p-2 rounded border border-purple-200">
                                                    <div class="text-xs text-gray-600">Total</div>
                                                    <div class="text-sm font-bold <?php echo $total_return_amount > 0 ? 'text-purple-600' : 'text-gray-600'; ?>">
                                                        Rs <?php echo number_format($total_return_amount, 2); ?>
                                                    </div>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium <?php echo $total_return_amount > 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                        <i class="fas <?php echo $total_return_amount > 0 ? 'fa-check-circle' : 'fa-clock'; ?> mr-0.5 text-xs"></i>
                                                        <?php echo $total_return_amount > 0 ? 'Paid' : 'Pending'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Compact Supplier Details Column -->
                                        <td class="px-3 py-2">
                                            <div class="space-y-1">
                                                <?php if (!empty($return['supplier_name'])): ?>
                                                    <div class="font-medium text-gray-800 text-sm truncate-cell" title="<?php echo htmlspecialchars($return['supplier_name']); ?>">
                                                        <?php echo htmlspecialchars(substr($return['supplier_name'], 0, 15)); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-600 space-y-0.5">
                                                        <?php if (!empty($return['supplier_phone'])): ?>
                                                            <div class="flex items-center">
                                                                <i class="fas fa-phone mr-1 text-xs text-gray-400"></i>
                                                                <span class="truncate-cell" title="<?php echo htmlspecialchars($return['supplier_phone']); ?>">
                                                                    <?php echo htmlspecialchars(substr($return['supplier_phone'], 0, 10)); ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-xs text-gray-500 italic">No supplier</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Compact Actions Column -->
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col space-y-1">
                                                <!-- View Button -->
                                                <button onclick="viewReturnDetails(<?php echo $return['return_id']; ?>)"
                                                    class="inline-flex items-center justify-center space-x-1 px-2 py-1.5 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition-colors text-xs shadow-sm"
                                                    title="View Details">
                                                    <i class="fas fa-eye text-xs"></i>
                                                    <span>View</span>
                                                </button>

                                                <!-- Update Payment Button -->
                                                <button onclick="updateReturnAmount(<?php echo $return['return_id']; ?>, <?php echo $total_return_amount; ?>)"
                                                    class="inline-flex items-center justify-center space-x-1 px-2 py-1.5 bg-green-50 text-green-600 rounded hover:bg-green-100 transition-colors text-xs shadow-sm"
                                                    title="Update Payment">
                                                    <i class="fas fa-money-bill-wave text-xs"></i>
                                                    <span>Payment</span>
                                                </button>

                                                <!-- Action Buttons Row -->
                                                <div class="flex space-x-1">
                                                    <!-- Print Button -->
                                                    <a href="?print_receipt&return_id=<?php echo $return['return_id']; ?>" target="_blank"
                                                        class="flex-1 inline-flex items-center justify-center px-2 py-1 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100 transition-colors text-xs shadow-sm"
                                                        title="Print Receipt">
                                                        <i class="fas fa-print text-xs"></i>
                                                    </a>

                                                    <!-- Export Button -->
                                                    <button onclick="exportReturnToPDF(<?php echo $return['return_id']; ?>)"
                                                        class="flex-1 inline-flex items-center justify-center px-2 py-1 bg-purple-50 text-purple-600 rounded hover:bg-purple-100 transition-colors text-xs shadow-sm"
                                                        title="Export PDF">
                                                        <i class="fas fa-download text-xs"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-inbox text-indigo-400 text-xl"></i>
                                            </div>
                                            <h4 class="text-base font-semibold text-gray-800 mb-2">No Return Records Found</h4>
                                            <p class="text-gray-600 mb-4 text-sm">No medicines have been returned to companies yet.</p>
                                            <a href="expired_medicines.php"
                                                class="gradient-indigo text-white px-4 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-300 inline-flex items-center justify-center space-x-2 shadow text-sm">
                                                <i class="fas fa-undo-alt"></i>
                                                <span>Go to Expired Medicines</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-4 py-3 border-t border-indigo-100 bg-gradient-to-r from-indigo-50 to-indigo-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-2 md:mb-0">
                            <div class="text-xs text-gray-500 flex flex-wrap items-center gap-2">
                                <span class="flex items-center">
                                    <i class="fas fa-layer-group text-indigo-500 mr-1 text-xs"></i>
                                    <?php echo count($returns); ?> returns
                                </span>
                                <span class="hidden md:inline">•</span>
                                <span class="flex items-center">
                                    <i class="fas fa-money-bill-wave text-green-500 mr-1 text-xs"></i>
                                    Value: Rs <?php echo number_format($total_returned_value, 2); ?>
                                </span>
                                <span class="hidden md:inline">•</span>
                                <span class="flex items-center">
                                    <i class="fas fa-hand-holding-usd text-blue-500 mr-1 text-xs"></i>
                                    Received: Rs <?php echo number_format($total_return_amount_received, 2); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-2 py-1 border border-indigo-200 rounded hover:bg-indigo-50 transition flex items-center space-x-1 bg-white/80 shadow-sm text-xs"
                                onclick="generateFullReport()">
                                <i class="fas fa-file-alt text-indigo-500 text-xs"></i>
                                <span class="text-gray-700">Report</span>
                            </button>
                            <button class="px-2 py-1 border border-indigo-200 rounded hover:bg-indigo-50 transition flex items-center space-x-1 bg-white/80 shadow-sm text-xs"
                                onclick="printReturnReport()">
                                <i class="fas fa-print text-indigo-500 text-xs"></i>
                                <span class="text-gray-700">Print</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mx-6 my-6">
                <!-- Return Reasons Chart -->
                <div class="glass-card rounded-xl p-4 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center space-x-2">
                        <i class="fas fa-chart-pie text-purple-500 text-sm"></i>
                        <span>Return Reasons</span>
                    </h3>
                    <div class="h-48">
                        <canvas id="reasonsChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Returns Chart -->
                <div class="glass-card rounded-xl p-4 animate-fade-in-up" style="animation-delay: 0.8s">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center space-x-2">
                        <i class="fas fa-chart-line text-blue-500 text-sm"></i>
                        <span>Monthly Trends</span>
                    </h3>
                    <div class="h-48">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Return Details Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-4 border-b border-indigo-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-file-invoice text-blue-500 mr-2"></i>
                        <span id="modalReturnTitle">Return Details</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="printReturnDetails()"
                            class="px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2 text-sm">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </button>
                        <button onclick="exportReturnDetailsToPDF()"
                            class="px-3 py-1.5 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2 text-sm">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </button>
                        <button onclick="closeModal('viewModal')"
                            class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 transition">
                            <i class="fas fa-times text-gray-600 text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 overflow-y-auto max-h-[calc(90vh-140px)] custom-scrollbar" id="returnDetailsContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-6">
                    <i class="fas fa-spinner fa-spin text-indigo-500 text-2xl mb-3"></i>
                    <p class="text-gray-600 text-sm">Loading return details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Return Amount Modal - UPDATED -->
    <div id="updateAmountModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 450px;">
            <form id="updateAmountForm" method="POST" action="">
                <div class="p-4 border-b border-indigo-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
                            <span>Update Return Amount</span>
                        </h3>
                        <button type="button" onclick="closeModal('updateAmountModal')"
                            class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 transition">
                            <i class="fas fa-times text-gray-600 text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="p-4">
                    <input type="hidden" name="update_return_amount" value="1">
                    <input type="hidden" id="return_id" name="return_id" value="">

                    <div class="space-y-4">
                        <div>
                            <h4 class="text-base font-semibold text-gray-800 mb-2" id="updateMedicineName"></h4>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div>Batch: <span id="updateBatchNo" class="font-mono"></span></div>
                                <div>Quantity: <span id="updateQuantity" class="font-medium"></span> units</div>
                                <div>Purchase Price: Rs <span id="updatePurchasePrice" class="font-medium"></span>/unit</div>
                                <div>Total Purchase Value: Rs <span id="updateTotalPurchase" class="font-medium text-green-600"></span></div>
                            </div>
                        </div>

                        <div>
                            <label for="return_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Total Return Amount <span class="text-red-500">*</span>
                                <span class="text-xs text-gray-500 ml-2">(Total amount for all units)</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-sm">Rs</span>
                                </div>
                                <input type="number"
                                    id="return_amount"
                                    name="return_amount"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:outline-none transition text-sm"
                                    placeholder="0.00">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Enter the <strong>total amount</strong> received for this return (not per unit)
                            </p>
                        </div>

                        <div class="bg-gray-50 p-3 rounded-lg">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Summary</h5>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Purchase Value:</span>
                                    <span class="text-sm font-medium text-gray-800" id="summaryPurchaseValue"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Total Return Amount:</span>
                                    <span class="text-sm font-medium text-green-600" id="summaryReturnAmount">Rs 0.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Per Unit Amount:</span>
                                    <span class="text-sm font-medium text-blue-600" id="summaryPerUnit">Rs 0.00/unit</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-indigo-100 flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('updateAmountModal')"
                        class="px-4 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium text-sm">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:shadow-lg transition font-medium text-sm">
                        <i class="fas fa-save mr-1"></i>
                        Save Amount
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Analytics Modal -->
    <div id="analyticsModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 800px;">
            <div class="p-4 border-b border-indigo-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-chart-bar text-purple-500 mr-2"></i>
                        <span>Returns Analytics Dashboard</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="printAnalyticsReport()"
                            class="px-3 py-1.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2 text-sm">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </button>
                        <button onclick="closeModal('analyticsModal')"
                            class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 transition">
                            <i class="fas fa-times text-gray-600 text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 overflow-y-auto max-h-[calc(90vh-140px)] custom-scrollbar">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Top Statistics -->
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800">Key Statistics</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                                <div class="text-xl font-bold text-indigo-600"><?php echo count($returns); ?></div>
                                <div class="text-xs text-gray-600">Total Returns</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                                <div class="text-xl font-bold text-green-600">Rs <?php echo number_format($total_returned_value, 2); ?></div>
                                <div class="text-xs text-gray-600">Purchase Value</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                                <div class="text-xl font-bold text-blue-600">Rs <?php echo number_format($total_return_amount_received, 2); ?></div>
                                <div class="text-xs text-gray-600">Amount Received</div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                                <div class="text-xl font-bold text-purple-600"><?php echo count($unique_suppliers); ?></div>
                                <div class="text-xs text-gray-600">Suppliers</div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Reasons -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Return Reasons</h4>
                        <div class="space-y-1.5">
                            <?php foreach ($return_reasons as $reason => $count): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700 truncate-cell" style="max-width: 120px;"><?php echo $reason; ?></span>
                                    <span class="text-sm font-semibold text-indigo-600"><?php echo $count; ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: <?php echo (max($return_reasons) > 0) ? ($count / max($return_reasons)) * 100 : 0; ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Payment Status</h4>
                        <div class="h-48">
                            <canvas id="paymentStatusChart"></canvas>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Monthly Returns vs Payments</h4>
                        <div class="h-48">
                            <canvas id="monthlyComparisonChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Breakdown -->
                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Monthly Breakdown</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Month</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Returns</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Quantity</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Purchase Value</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Amount Received</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_stats as $month => $stats): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-700"><?php echo date('M Y', strtotime($month . '-01')); ?></td>
                                        <td class="px-3 py-2 text-gray-700"><?php echo $stats['count']; ?></td>
                                        <td class="px-3 py-2 text-gray-700"><?php echo $stats['quantity']; ?></td>
                                        <td class="px-3 py-2 text-gray-700">Rs <?php echo number_format($stats['value'], 2); ?></td>
                                        <td class="px-3 py-2 text-gray-700">Rs <?php echo number_format($stats['return_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Printable Content (hidden by default) -->
    <div id="printable-content" class="hidden"></div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="floating-alert bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-lg shadow-xl flex items-center space-x-2 text-sm">
            <i class="fas fa-check-circle"></i>
            <span class="font-medium"><?php echo $_SESSION['success'];
                                        unset($_SESSION['success']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="floating-alert bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-lg shadow-xl flex items-center space-x-2 text-sm">
            <i class="fas fa-exclamation-circle"></i>
            <span class="font-medium"><?php echo $_SESSION['error'];
                                        unset($_SESSION['error']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Store return data from PHP
        const returnData = <?php echo json_encode($return_data_json); ?>;
        const monthlyStats = <?php echo json_encode($monthly_stats); ?>;
        const returnReasons = <?php echo json_encode($return_reasons); ?>;
        const totalReturnAmountReceived = <?php echo $total_return_amount_received; ?>;
        const totalReturnedValue = <?php echo $total_returned_value; ?>;

        // Global variables
        let currentReturnData = null;
        let sortDirection = {};

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Update return amount - UPDATED
        function updateReturnAmount(returnId, currentAmount) {
            if (!returnData[returnId]) {
                showNotification('Return data not found', 'error');
                return;
            }

            const returnItem = returnData[returnId];
            const quantity = returnItem.quantity;

            // currentAmount is now TOTAL amount
            document.getElementById('return_id').value = returnId;
            document.getElementById('return_amount').value = currentAmount || '';

            document.getElementById('updateMedicineName').textContent = returnItem.medicine_name;
            document.getElementById('updateBatchNo').textContent = returnItem.batch_no;
            document.getElementById('updateQuantity').textContent = quantity;
            document.getElementById('updatePurchasePrice').textContent = parseFloat(returnItem.purchase_price).toFixed(2);

            const totalPurchaseValue = returnItem.purchase_price * quantity;
            document.getElementById('updateTotalPurchase').textContent = totalPurchaseValue.toFixed(2);
            document.getElementById('summaryPurchaseValue').textContent = `Rs ${totalPurchaseValue.toFixed(2)}`;

            // Calculate and update return amount summary
            updateReturnAmountSummary();

            openModal('updateAmountModal');
        }

        // Update return amount summary in real-time - UPDATED
        function updateReturnAmountSummary() {
            const totalReturnAmount = parseFloat(document.getElementById('return_amount').value) || 0;
            const quantity = parseInt(document.getElementById('updateQuantity').textContent);

            // Calculate per unit amount
            const perUnitAmount = quantity > 0 ? (totalReturnAmount / quantity) : 0;

            // Update all displays
            document.getElementById('summaryReturnAmount').textContent = `Rs ${totalReturnAmount.toFixed(2)}`;
            document.getElementById('summaryPerUnit').textContent = `Rs ${perUnitAmount.toFixed(2)}/unit`;
        }

        // Add event listener for return amount input
        document.getElementById('return_amount').addEventListener('input', updateReturnAmountSummary);

        // View return details
        function viewReturnDetails(returnId) {
            openModal('viewModal');

            if (returnData[returnId]) {
                currentReturnData = returnData[returnId];
                updateReturnModal(currentReturnData);
            } else {
                document.getElementById('returnDetailsContent').innerHTML = `
                    <div class="text-center py-6 text-red-500">
                        <i class="fas fa-exclamation-circle text-2xl"></i>
                        <p class="mt-3 text-sm">Return data not found. Please refresh the page and try again.</p>
                    </div>
                `;
            }
        }

        // Update modal content with return data - UPDATED
        function updateReturnModal(returnData) {
            const returnDate = new Date(returnData.returned_at).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const expiryDate = returnData.expiry_date ? new Date(returnData.expiry_date).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            }) : 'Not specified';

            const totalValue = returnData.purchase_price * returnData.quantity;
            const totalReturnAmount = returnData.return_amount || 0; // Already TOTAL amount
            const perUnitReturnAmount = returnData.quantity > 0 ? (totalReturnAmount / returnData.quantity) : 0;
            const daysAfterExpiry = returnData.days_after_expiry || 0;

            document.getElementById('modalReturnTitle').textContent = `Return: RTN-${String(returnData.return_id).padStart(6, '0')}`;

            let html = `
                <div class="space-y-4">
                    <!-- Return Information -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <h4 class="text-base font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2 text-sm"></i>
                                Return Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Return ID:</span>
                                    <span class="text-sm font-semibold">RTN-${String(returnData.return_id).padStart(6, '0')}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Return Date:</span>
                                    <span class="text-sm font-semibold text-gray-800">${returnDate}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Batch Number:</span>
                                    <span class="text-sm font-mono">${returnData.batch_no}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Return Reason:</span>
                                    <span class="text-sm font-semibold ${returnData.return_reason === 'Expired' ? 'text-red-600' : 'text-indigo-600'}">
                                        ${returnData.return_reason}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Returned By:</span>
                                    <span class="text-sm text-gray-800">${returnData.returned_by_name}</span>
                                </div>
                                ${returnData.return_notes ? `
                                <div class="mt-2 p-2 bg-gray-50 rounded">
                                    <p class="text-xs font-medium text-gray-700 mb-1">Return Notes:</p>
                                    <p class="text-xs text-gray-600">${returnData.return_notes}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <!-- Medicine Information -->
                        <div class="space-y-3">
                            <h4 class="text-base font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-pills text-purple-500 mr-2 text-sm"></i>
                                Medicine Information
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Medicine:</span>
                                    <span class="text-sm font-semibold text-gray-800">${returnData.medicine_name}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Generic Name:</span>
                                    <span class="text-sm text-gray-800">${returnData.generic_name || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Expiry Date:</span>
                                    <span class="text-sm font-semibold ${daysAfterExpiry > 0 ? 'text-red-600' : 'text-green-600'}">
                                        ${expiryDate}
                                    </span>
                                </div>
                                ${daysAfterExpiry > 0 ? `
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Days After Expiry:</span>
                                    <span class="text-sm font-semibold text-red-600">${daysAfterExpiry} days</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Information - UPDATED -->
                    <div class="space-y-3">
                        <h4 class="text-base font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-money-bill-wave text-green-500 mr-2 text-sm"></i>
                            Financial Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="bg-green-50 p-3 rounded border border-green-200">
                                <p class="text-xs text-gray-600">Quantity Returned</p>
                                <p class="text-lg font-bold text-green-600">${returnData.quantity}</p>
                                <p class="text-xs text-gray-500">units</p>
                            </div>
                            <div class="bg-blue-50 p-3 rounded border border-blue-200">
                                <p class="text-xs text-gray-600">Purchase Price</p>
                                <p class="text-lg font-bold text-blue-600">Rs ${parseFloat(returnData.purchase_price).toFixed(2)}</p>
                                <p class="text-xs text-gray-500">per unit</p>
                            </div>
                            <div class="bg-indigo-50 p-3 rounded border border-indigo-200">
                                <p class="text-xs text-gray-600">Purchase Value</p>
                                <p class="text-lg font-bold text-indigo-600">Rs ${totalValue.toFixed(2)}</p>
                                <p class="text-xs text-gray-500">total</p>
                            </div>
                        </div>
                        
                        <!-- Return Amount Information - UPDATED -->
                        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="bg-yellow-50 p-3 rounded border border-yellow-200">
                                <p class="text-xs text-gray-600">Return Amount Per Unit</p>
                                <p class="text-lg font-bold ${perUnitReturnAmount > 0 ? 'text-yellow-600' : 'text-gray-600'}">
                                    Rs ${perUnitReturnAmount.toFixed(2)}
                                </p>
                                <p class="text-xs text-gray-500">per unit</p>
                            </div>
                            <div class="bg-purple-50 p-3 rounded border border-purple-200">
                                <p class="text-xs text-gray-600">Total Return Amount</p>
                                <p class="text-lg font-bold ${totalReturnAmount > 0 ? 'text-purple-600' : 'text-gray-600'}">
                                    Rs ${totalReturnAmount.toFixed(2)}
                                </p>
                                <p class="text-xs text-gray-500">total received</p>
                            </div>
                        </div>
                        
                        ${totalReturnAmount > 0 ? `
                        <div class="mt-2 text-center">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>
                                Payment received
                            </span>
                        </div>
                        ` : `
                        <div class="mt-2 text-center">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-clock mr-1"></i>
                                Payment pending
                            </span>
                        </div>
                        `}
                    </div>
                    
                    <!-- Supplier Information -->
                    <div class="space-y-3">
                        <h4 class="text-base font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-truck text-orange-500 mr-2 text-sm"></i>
                            Supplier Information
                        </h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-600">Supplier Name</p>
                                    <p class="text-sm font-medium text-gray-800">${returnData.supplier_name || 'N/A'}</p>
                                </div>
                                ${returnData.supplier_phone ? `
                                <div>
                                    <p class="text-xs text-gray-600">Phone</p>
                                    <p class="text-sm font-medium text-gray-800">${returnData.supplier_phone}</p>
                                </div>
                                ` : ''}
                                ${returnData.supplier_email ? `
                                <div>
                                    <p class="text-xs text-gray-600">Email</p>
                                    <p class="text-sm font-medium text-gray-800">${returnData.supplier_email}</p>
                                </div>
                                ` : ''}
                                ${returnData.location ? `
                                <div>
                                    <p class="text-xs text-gray-600">Location</p>
                                    <p class="text-sm font-medium text-gray-800">${returnData.location}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-2 pt-3 border-t border-gray-200">
                        <button onclick="updateReturnAmount(${returnData.return_id}, ${totalReturnAmount || 0})"
                            class="flex-1 px-3 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:shadow-lg transition font-medium flex items-center justify-center space-x-2 text-sm">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>${totalReturnAmount > 0 ? 'Update Payment' : 'Add Payment'}</span>
                        </button>
                        <a href="?print_receipt&return_id=${returnData.return_id}" target="_blank"
                            class="flex-1 px-3 py-2 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:shadow-lg transition font-medium flex items-center justify-center space-x-2 text-sm">
                            <i class="fas fa-print"></i>
                            <span>Print Receipt</span>
                        </a>
                        <button onclick="exportReturnDetailsToPDF()"
                            class="flex-1 px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:shadow-lg transition font-medium flex items-center justify-center space-x-2 text-sm">
                            <i class="fas fa-file-pdf"></i>
                            <span>Export PDF</span>
                        </button>
                    </div>
                </div>
            `;

            document.getElementById('returnDetailsContent').innerHTML = html;
        }

        // Show analytics modal
        function showAnalyticsModal() {
            openModal('analyticsModal');
            setTimeout(initializeAnalyticsCharts, 100);
        }

        // Print single receipt
        function printSingleReceipt(returnId) {
            window.open(`?print_receipt&return_id=${returnId}`, '_blank');
        }

        // Export return to PDF
        function exportReturnToPDF(returnId) {
            window.open(`?print_receipt&return_id=${returnId}&download=1`, '_blank');
        }

        // Export return details to PDF
        function exportReturnDetailsToPDF() {
            if (!currentReturnData) {
                showNotification('No return data available to export', 'error');
                return;
            }

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                // Add header
                doc.setFontSize(16);
                doc.setTextColor(99, 102, 241);
                doc.text('MediCare Pharma - Return Details', 105, 20, null, null, 'center');

                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 105, 30, null, null, 'center');
                doc.text(`Return ID: RTN-${String(currentReturnData.return_id).padStart(6, '0')}`, 105, 35, null, null, 'center');

                const returnData = currentReturnData;
                const totalValue = returnData.purchase_price * returnData.quantity;
                const totalReturnAmount = returnData.return_amount || 0;
                const perUnitAmount = returnData.quantity > 0 ? (totalReturnAmount / returnData.quantity) : 0;

                // Add return details
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text('Return Information', 20, 50);
                doc.setFontSize(10);
                doc.text(`Return Date: ${new Date(returnData.returned_at).toLocaleDateString()}`, 20, 60);
                doc.text(`Medicine: ${returnData.medicine_name}`, 20, 65);
                doc.text(`Batch No: ${returnData.batch_no}`, 20, 70);
                doc.text(`Return Reason: ${returnData.return_reason}`, 20, 75);
                doc.text(`Returned By: ${returnData.returned_by_name}`, 20, 80);

                // Add financial information - UPDATED
                doc.setFontSize(12);
                doc.text('Financial Information', 20, 95);
                doc.setFontSize(10);
                doc.text(`Quantity: ${returnData.quantity} units`, 20, 105);
                doc.text(`Purchase Price: Rs ${parseFloat(returnData.purchase_price).toFixed(2)}`, 20, 110);
                doc.text(`Purchase Value: Rs ${totalValue.toFixed(2)}`, 20, 115);
                doc.text(`Return Amount Per Unit: Rs ${perUnitAmount.toFixed(2)}`, 20, 120);
                doc.text(`Total Return Amount: Rs ${totalReturnAmount.toFixed(2)}`, 20, 125);

                // Add supplier information
                if (returnData.supplier_name) {
                    doc.setFontSize(12);
                    doc.text('Supplier Information', 20, 140);
                    doc.setFontSize(10);
                    doc.text(`Supplier: ${returnData.supplier_name}`, 20, 150);
                    if (returnData.supplier_phone) {
                        doc.text(`Phone: ${returnData.supplier_phone}`, 20, 155);
                    }
                    if (returnData.supplier_email) {
                        doc.text(`Email: ${returnData.supplier_email}`, 20, 160);
                    }
                }

                // Add footer
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text('Confidential - For Internal Use Only', 105, 280, null, null, 'center');
                doc.text('MediCare Pharmacy Management System', 105, 285, null, null, 'center');

                // Save the PDF
                doc.save(`Return_Details_RTN-${returnData.return_id}_${new Date().toISOString().slice(0,10)}.pdf`);

                showNotification('PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Print return details
        function printReturnDetails() {
            if (!currentReturnData) {
                showNotification('No return data available to print', 'error');
                return;
            }

            printSingleReceipt(currentReturnData.return_id);
        }

        // Export all returns to PDF
        async function exportAllToPDF() {
            window.location.href = '?export=excel';
        }

        // Generate full report
        function generateFullReport() {
            window.open('?export=excel', '_blank');
        }

        // Print return report - UPDATED
        function printReturnReport() {
            let printContent = `
                <div style="padding: 15px; font-family: Arial, sans-serif; font-size: 10px;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #6366f1; padding-bottom: 10px;">
                        <h1 style="color: #6366f1; margin: 0; font-size: 16px;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 8px 0 4px 0; font-size: 14px;">Returns to Company Report</h2>
                        <p style="color: #666; margin: 0; font-size: 9px;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                        <p style="color: #666; margin: 4px 0 0 0; font-size: 8px;">
                            Total Returns: <?php echo count($returns); ?> • 
                            Purchase Value: Rs <?php echo number_format($total_returned_value, 2); ?> • 
                            Amount Received: Rs <?php echo number_format($total_return_amount_received, 2); ?>
                        </p>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 8px; margin-top: 15px;">
                        <thead>
                            <tr style="background: #f0f9ff;">
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Return Date</th>
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Medicine</th>
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Batch No</th>
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Qty</th>
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Purchase Value</th>
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Return Amount</th>
                                <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            <?php foreach ($returns as $ret): ?>
                <?php
                $total_value = $ret['purchase_price'] * $ret['quantity'];
                $total_return_amount = $ret['return_amount']; // Already TOTAL amount
                $return_date = date('d M Y', strtotime($ret['returned_at']));
                ?>
                printContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo $return_date; ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo htmlspecialchars(substr($ret['medicine_name'], 0, 20)); ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo htmlspecialchars($ret['batch_no']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo $ret['quantity']; ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px; color: #059669;">Rs <?php echo number_format($total_value, 2); ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px; color: #3b82f6;">Rs <?php echo number_format($total_return_amount, 2); ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo htmlspecialchars($ret['return_reason']); ?></td>
                    </tr>
                `;
            <?php endforeach; ?>

            printContent += `
                        </tbody>
                        <tfoot style="background: #f0f9ff; font-weight: bold; font-size: 9px;">
                            <tr>
                                <td colspan="4" style="border: 1px solid #ddd; padding: 6px; text-align: right;">Total:</td>
                                <td style="border: 1px solid #ddd; padding: 6px; color: #059669;">Rs <?php echo number_format($total_returned_value, 2); ?></td>
                                <td style="border: 1px solid #ddd; padding: 6px; color: #3b82f6;">Rs <?php echo number_format($total_return_amount_received, 2); ?></td>
                                <td style="border: 1px solid #ddd; padding: 6px;"></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 8px;">
                        <p style="margin: 3px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 3px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 3px 0;">Page 1 of 1</p>
                    </div>
                </div>
            `;

            // Set printable content
            document.getElementById('printable-content').innerHTML = printContent;

            // Trigger print
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print Returns Report</title>
                    <style>
                        @media print {
                            @page { margin: 10mm; }
                            body { margin: 0; }
                            table { font-size: 8px; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            window.onafterprint = function() {
                                window.close();
                            };
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        // Print analytics report
        function printAnalyticsReport() {
            let printContent = `
                <div style="padding: 15px; font-family: Arial, sans-serif; font-size: 10px;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #8b5cf6; padding-bottom: 10px;">
                        <h1 style="color: #8b5cf6; margin: 0; font-size: 16px;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 8px 0 4px 0; font-size: 14px;">Returns Analytics Report</h2>
                        <p style="color: #666; margin: 0; font-size: 9px;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                    </div>
                    
                    <!-- Key Statistics -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">
                        <div style="border: 1px solid #6366f1; padding: 10px; text-align: center; border-radius: 4px;">
                            <h3 style="color: #6366f1; margin: 0 0 3px 0; font-size: 9px;">Total Returns</h3>
                            <p style="font-size: 18px; font-weight: bold; margin: 0;"><?php echo count($returns); ?></p>
                        </div>
                        <div style="border: 1px solid #059669; padding: 10px; text-align: center; border-radius: 4px;">
                            <h3 style="color: #059669; margin: 0 0 3px 0; font-size: 9px;">Purchase Value</h3>
                            <p style="font-size: 18px; font-weight: bold; margin: 0; color: #059669;">Rs <?php echo number_format($total_returned_value, 2); ?></p>
                        </div>
                        <div style="border: 1px solid #3b82f6; padding: 10px; text-align: center; border-radius: 4px;">
                            <h3 style="color: #3b82f6; margin: 0 0 3px 0; font-size: 9px;">Amount Received</h3>
                            <p style="font-size: 18px; font-weight: bold; margin: 0; color: #3b82f6;">Rs <?php echo number_format($total_return_amount_received, 2); ?></p>
                        </div>
                        <div style="border: 1px solid #8b5cf6; padding: 10px; text-align: center; border-radius: 4px;">
                            <h3 style="color: #8b5cf6; margin: 0 0 3px 0; font-size: 9px;">Suppliers</h3>
                            <p style="font-size: 18px; font-weight: bold; margin: 0;"><?php echo count($unique_suppliers); ?></p>
                        </div>
                    </div>
                    
                    <!-- Return Reasons -->
                    <div style="margin-bottom: 20px;">
                        <h3 style="color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px; font-size: 11px;">Return Reasons</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9px;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Reason</th>
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Count</th>
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            <?php
            $totalReasons = array_sum($return_reasons);
            foreach ($return_reasons as $reason => $count):
                $percentage = $totalReasons > 0 ? round(($count / $totalReasons) * 100, 1) : 0;
            ?>
                printContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo $reason; ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo $count; ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;">
                            <div style="background: #e0e7ff; height: 12px; border-radius: 6px; overflow: hidden; margin-bottom: 2px;">
                                <div style="background: #6366f1; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <span style="font-size: 8px;"><?php echo $percentage; ?>%</span>
                        </td>
                    </tr>
                `;
            <?php endforeach; ?>

            printContent += `
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Monthly Breakdown -->
                    <div>
                        <h3 style="color: #333; border-bottom: 1px solid #ddd; padding-bottom: 4px; font-size: 11px;">Monthly Breakdown</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9px;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Month</th>
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Returns</th>
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Quantity</th>
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Purchase Value</th>
                                    <th style="border: 1px solid #ddd; padding: 6px; text-align: left;">Amount Received</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            <?php foreach ($monthly_stats as $month => $stats): ?>
                printContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo date('M Y', strtotime($month . '-01')); ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo $stats['count']; ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px;"><?php echo $stats['quantity']; ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px; color: #059669;">Rs <?php echo number_format($stats['value'], 2); ?></td>
                        <td style="border: 1px solid #ddd; padding: 5px; color: #3b82f6;">Rs <?php echo number_format($stats['return_amount'], 2); ?></td>
                    </tr>
                `;
            <?php endforeach; ?>

            printContent += `
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Footer -->
                    <div style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 8px;">
                        <p style="margin: 3px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 3px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 3px 0;">Page 1 of 1</p>
                    </div>
                </div>
            `;

            // Set printable content
            document.getElementById('printable-content').innerHTML = printContent;

            // Trigger print
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print Analytics Report</title>
                    <style>
                        @media print {
                            @page { margin: 10mm; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            window.onafterprint = function() {
                                window.close();
                            };
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        // Initialize charts
        function initializeCharts() {
            // Reasons Chart
            const reasonsCtx = document.getElementById('reasonsChart').getContext('2d');
            const reasonsData = {
                labels: Object.keys(returnReasons),
                datasets: [{
                    data: Object.values(returnReasons),
                    backgroundColor: [
                        '#6366f1', '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b',
                        '#ef4444', '#ec4899'
                    ],
                    borderWidth: 1
                }]
            };

            if (Object.keys(returnReasons).length > 0) {
                new Chart(reasonsCtx, {
                    type: 'doughnut',
                    data: reasonsData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Monthly Chart
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            const months = Object.keys(monthlyStats).sort();
            const monthlyCounts = months.map(month => monthlyStats[month].count);

            if (months.length > 0) {
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: months.map(month => month.substring(5) + '/' + month.substring(2, 4)),
                        datasets: [{
                            label: 'Number of Returns',
                            data: monthlyCounts,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 9
                                    },
                                    precision: 0
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 9
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Initialize analytics charts
        function initializeAnalyticsCharts() {
            // Payment Status Chart
            const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');

            // Calculate payment status counts
            let paidCount = 0;
            let pendingCount = 0;
            Object.values(returnData).forEach(returnItem => {
                if (returnItem.return_amount > 0) {
                    paidCount++;
                } else {
                    pendingCount++;
                }
            });

            const paymentStatusData = {
                labels: ['Paid', 'Pending'],
                datasets: [{
                    data: [paidCount, pendingCount],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderColor: ['#059669', '#d97706'],
                    borderWidth: 1
                }]
            };

            new Chart(paymentStatusCtx, {
                type: 'doughnut',
                data: paymentStatusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });

            // Monthly Comparison Chart
            const monthlyComparisonCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
            const months = Object.keys(monthlyStats).sort();
            const monthlyPurchaseValues = months.map(month => monthlyStats[month].value);
            const monthlyReturnAmounts = months.map(month => monthlyStats[month].return_amount);

            if (months.length > 0) {
                new Chart(monthlyComparisonCtx, {
                    type: 'bar',
                    data: {
                        labels: months.map(month => month.substring(5) + '/' + month.substring(2, 4)),
                        datasets: [{
                                label: 'Purchase Value',
                                data: monthlyPurchaseValues,
                                backgroundColor: 'rgba(16, 185, 129, 0.6)',
                                borderColor: '#059669',
                                borderWidth: 1
                            },
                            {
                                label: 'Amount Received',
                                data: monthlyReturnAmounts,
                                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                borderColor: '#3b82f6',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 9
                                    },
                                    callback: function(value) {
                                        if (value >= 1000) {
                                            return 'Rs ' + (value / 1000).toFixed(0) + 'k';
                                        }
                                        return 'Rs ' + value;
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 9
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('#returnsTableBody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const rowReason = row.getAttribute('data-reason');
                    const reasonFilter = document.getElementById('reasonFilter').value;

                    let showRow = text.includes(searchTerm);

                    // Apply reason filter if set
                    if (reasonFilter && rowReason !== reasonFilter) {
                        showRow = false;
                    }

                    row.style.display = showRow ? '' : 'none';
                });
            });
        }

        // Filter by reason
        const reasonFilter = document.getElementById('reasonFilter');
        if (reasonFilter) {
            reasonFilter.addEventListener('change', function(e) {
                const reason = e.target.value;
                const rows = document.querySelectorAll('#returnsTableBody tr');
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();

                rows.forEach(row => {
                    const rowReason = row.getAttribute('data-reason');
                    const rowText = row.textContent.toLowerCase();

                    let showRow = true;

                    // Apply reason filter
                    if (reason && rowReason !== reason) {
                        showRow = false;
                    }

                    // Apply search filter
                    if (searchTerm && !rowText.includes(searchTerm)) {
                        showRow = false;
                    }

                    row.style.display = showRow ? '' : 'none';
                });
            });
        }

        // Sort table functionality
        function sortTable(columnIndex) {
            const table = document.querySelector('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not([style*="display: none"])'));

            if (!sortDirection[columnIndex]) {
                sortDirection[columnIndex] = 'asc';
            } else {
                sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            }

            rows.sort((a, b) => {
                let aValue, bValue;

                if (columnIndex === 0) {
                    // Return date
                    const aDateText = a.querySelector('.text-gray-500').textContent;
                    const bDateText = b.querySelector('.text-gray-500').textContent;

                    // Extract date from text
                    const aDate = new Date(aDateText);
                    const bDate = new Date(bDateText);

                    aValue = aDate.getTime();
                    bValue = bDate.getTime();
                } else if (columnIndex === 2) {
                    // Quantity
                    aValue = parseInt(a.querySelector('.text-lg.font-bold').textContent.replace(/,/g, ''));
                    bValue = parseInt(b.querySelector('.text-lg.font-bold').textContent.replace(/,/g, ''));
                } else {
                    aValue = a.cells[columnIndex].textContent.toLowerCase();
                    bValue = b.cells[columnIndex].textContent.toLowerCase();
                }

                if (sortDirection[columnIndex] === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });

            // Clear and re-add sorted rows (only visible ones)
            rows.forEach(row => tbody.appendChild(row));

            // Update sort indicator
            updateSortIndicator(columnIndex);
        }

        function updateSortIndicator(columnIndex) {
            // Remove all sort indicators
            document.querySelectorAll('.fa-sort').forEach(icon => {
                icon.className = 'fas fa-sort text-indigo-400 cursor-pointer hover:text-indigo-600 text-xs';
            });

            // Add indicator to current column
            const currentIcon = document.querySelectorAll('.fa-sort')[columnIndex];
            if (currentIcon) {
                if (sortDirection[columnIndex] === 'asc') {
                    currentIcon.className = 'fas fa-sort-up text-indigo-600 cursor-pointer text-xs';
                } else {
                    currentIcon.className = 'fas fa-sort-down text-indigo-600 cursor-pointer text-xs';
                }
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const colors = {
                success: 'bg-gradient-to-r from-green-500 to-green-600',
                error: 'bg-gradient-to-r from-red-500 to-red-600',
                warning: 'bg-gradient-to-r from-yellow-500 to-yellow-600',
                info: 'bg-gradient-to-r from-blue-500 to-blue-600'
            };

            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            // Remove existing notifications
            document.querySelectorAll('.floating-alert').forEach(el => el.remove());

            const notification = document.createElement('div');
            notification.className = `floating-alert ${colors[type]} text-white px-4 py-2 rounded-lg shadow-xl flex items-center space-x-2 text-sm`;
            notification.innerHTML = `
                <i class="fas ${icons[type]}"></i>
                <span class="font-medium">${message}</span>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 3000);
        }

        // Initialize animations and charts
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Initialize charts
            initializeCharts();

            // Auto-refresh data every 60 seconds
            setInterval(() => {
                console.log('Auto-refreshing returns data...');
                // You can add AJAX refresh here if needed
            }, 60000);
        });

        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                const modals = ['viewModal', 'analyticsModal', 'updateAmountModal'];
                modals.forEach(modal => {
                    if (document.getElementById(modal).style.display === 'flex') {
                        closeModal(modal);
                    }
                });
            }

            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printReturnReport();
            }

            // Ctrl/Cmd + E to export Excel
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = '?export=excel';
            }

            // Ctrl/Cmd + F for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Ctrl/Cmd + A for analytics
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                e.preventDefault();
                showAnalyticsModal();
            }
        });

        // Close notifications when clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.floating-alert')) {
                e.target.closest('.floating-alert').remove();
            }
        });
    </script>
</body>

</html>