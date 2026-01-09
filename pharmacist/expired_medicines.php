<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

// Handle return to company request
if (isset($_POST['return_to_company'])) {
    $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id']);
    $return_reason = mysqli_real_escape_string($conn, $_POST['return_reason']);
    $return_notes = mysqli_real_escape_string($conn, $_POST['return_notes']);

    // Get batch details
    $batch_query = "SELECT * FROM stock_batches WHERE id = '$batch_id'";
    $batch_result = mysqli_query($conn, $batch_query);

    if ($batch_result && mysqli_num_rows($batch_result) > 0) {
        $batch_data = mysqli_fetch_assoc($batch_result);

        // Insert into return history
        $return_query = "INSERT INTO returns_to_company 
                        (batch_id, medicine_id, batch_no, quantity, purchase_price, 
                         return_reason, return_notes, returned_by, returned_at) 
                        VALUES 
                        ('{$batch_data['id']}', '{$batch_data['medicine_id']}', '{$batch_data['batch_no']}', 
                         '{$batch_data['quantity']}', '{$batch_data['purchase_price']}', 
                         '$return_reason', '$return_notes', 
                         '{$_SESSION['user_id']}', NOW())";

        if (mysqli_query($conn, $return_query)) {
            // Update stock batch as returned
            $update_query = "UPDATE stock_batches SET 
                            is_returned = 1, 
                            returned_at = NOW(),
                            is_expired = 1 
                            WHERE id = '$batch_id'";
            mysqli_query($conn, $update_query);

            $_SESSION['success'] = "Batch returned to company successfully! The batch will no longer appear in expired medicines list.";
        } else {
            $_SESSION['error'] = "Error returning batch: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Batch not found!";
    }

    header("Location: expired_medicines.php");
    exit;
}

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    exportToExcel($conn);
    exit;
}

// Handle dispose batch request
if (isset($_POST['dispose_batch'])) {
    $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id']);
    $disposal_reason = mysqli_real_escape_string($conn, $_POST['disposal_reason']);
    $disposal_method = mysqli_real_escape_string($conn, $_POST['disposal_method']);
    $disposal_notes = mysqli_real_escape_string($conn, $_POST['disposal_notes']);

    // Get batch details before disposal
    $batch_query = "SELECT * FROM stock_batches WHERE id = '$batch_id'";
    $batch_result = mysqli_query($conn, $batch_query);
    $batch_data = mysqli_fetch_assoc($batch_result);

    // Insert into disposal history
    $disposal_query = "INSERT INTO disposal_history 
                      (batch_id, medicine_id, batch_no, quantity, purchase_price, 
                       disposal_reason, disposal_method, disposal_notes, disposed_by, disposed_at) 
                      VALUES 
                      ('{$batch_data['id']}', '{$batch_data['medicine_id']}', '{$batch_data['batch_no']}', 
                       '{$batch_data['quantity']}', '{$batch_data['purchase_price']}', 
                       '$disposal_reason', '$disposal_method', '$disposal_notes', 
                       '{$_SESSION['user_id']}', NOW())";

    if (mysqli_query($conn, $disposal_query)) {
        // Update stock batch as disposed
        $update_query = "UPDATE stock_batches SET is_disposed = 1, disposed_at = NOW() WHERE id = '$batch_id'";
        mysqli_query($conn, $update_query);

        $_SESSION['success'] = "Batch disposed successfully! The batch will no longer appear in expired medicines list.";
    } else {
        $_SESSION['error'] = "Error disposing batch: " . mysqli_error($conn);
    }

    header("Location: expired_medicines.php");
    exit;
}

// Handle dispose all request
if (isset($_POST['dispose_all'])) {
    $disposal_reason = mysqli_real_escape_string($conn, $_POST['disposal_reason_all']);
    $disposal_method = mysqli_real_escape_string($conn, $_POST['disposal_method_all']);

    // Get all expired batches that are NOT returned or disposed
    $expired_query = "SELECT * FROM stock_batches WHERE is_expired = 1 AND is_disposed = 0 AND is_returned = 0";
    $expired_result = mysqli_query($conn, $expired_query);

    $disposed_count = 0;
    while ($batch = mysqli_fetch_assoc($expired_result)) {
        // Insert into disposal history
        $disposal_query = "INSERT INTO disposal_history 
                          (batch_id, medicine_id, batch_no, quantity, purchase_price, 
                           disposal_reason, disposal_method, disposed_by, disposed_at) 
                          VALUES 
                          ('{$batch['id']}', '{$batch['medicine_id']}', '{$batch['batch_no']}', 
                           '{$batch['quantity']}', '{$batch['purchase_price']}', 
                           '$disposal_reason', '$disposal_method', 
                           '{$_SESSION['user_id']}', NOW())";

        if (mysqli_query($conn, $disposal_query)) {
            // Update stock batch as disposed
            $update_query = "UPDATE stock_batches SET is_disposed = 1, disposed_at = NOW() WHERE id = '{$batch['id']}'";
            mysqli_query($conn, $update_query);
            $disposed_count++;
        }
    }

    $_SESSION['success'] = "Disposed $disposed_count batches successfully! Disposed batches will no longer appear in expired medicines list.";
    header("Location: expired_medicines.php");
    exit;
}

// Auto-mark expired batches (only those not returned or disposed)
mysqli_query($conn, "
    UPDATE stock_batches
    SET is_expired = 1
    WHERE expiry_date < CURDATE()
    AND is_returned = 0
    AND is_disposed = 0
");

// Fetch expired medicines with detailed information - EXCLUDING RETURNED AND DISPOSED BATCHES
$query = "
    SELECT 
        m.id AS medicine_id,
        m.name AS medicine_name,
        mg.name AS generic_name,
        m.description,
        mc.name AS category_name,
        mt.name AS type_name,
        sb.id AS batch_id,
        sb.batch_no,
        sb.quantity,
        sb.purchase_price,
        sb.selling_price,
        sb.mrp,
        sb.received_date,
        sb.expiry_date,
        sb.location,
        sb.is_expired,
        sb.is_returned,
        sb.is_disposed,
        sb.added_at,
        s.name AS supplier_name,
        s.phone,
        s.email,
        DATEDIFF(CURDATE(), sb.expiry_date) AS days_expired
    FROM stock_batches sb
    JOIN medicines m ON sb.medicine_id = m.id
    LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
    LEFT JOIN medicine_categories mc ON m.category_id = mc.id
    LEFT JOIN medicine_types mt ON m.type_id = mt.id
    LEFT JOIN suppliers s ON sb.supplier_id = s.id
    WHERE sb.is_expired = 1 
      AND sb.is_returned = 0 
      AND sb.is_disposed = 0
    ORDER BY sb.expiry_date ASC, m.name ASC
";


$result = mysqli_query($conn, $query);
$expired_medicines = [];
$total_expired_value = 0;
$total_expired_quantity = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $expired_medicines[] = $row;
    $total_expired_quantity += $row['quantity'];
    $total_expired_value += ($row['purchase_price'] * $row['quantity']);
}

// Get statistics
$unique_medicines = array_unique(array_column($expired_medicines, 'medicine_name'));
$unique_suppliers = array_unique(array_column($expired_medicines, 'supplier_name'));
$unique_suppliers = array_filter($unique_suppliers);

$total_days = 0;
foreach ($expired_medicines as $med) {
    $total_days += ($med['days_expired'] ?? 0);
}
$average_days_expired = count($expired_medicines) > 0 ? round($total_days / count($expired_medicines)) : 0;

// Store batch data for JavaScript
$batch_data_json = [];
foreach ($expired_medicines as $med) {
    $batch_data_json[$med['batch_id']] = [
        'batch_id' => $med['batch_id'],
        'medicine_name' => $med['medicine_name'],
        'batch_no' => $med['batch_no'],
        'quantity' => $med['quantity'],
        'purchase_price' => $med['purchase_price'],
        'selling_price' => $med['selling_price'],
        'mrp' => $med['mrp'],
        'expiry_date' => $med['expiry_date'],
        'received_date' => $med['received_date'],
        'location' => $med['location'],
        'supplier_name' => $med['supplier_name'],
        'phone' => $med['phone'],
        'email' => $med['email'],
        'generic_name' => $med['generic_name'],
        'category_name' => $med['category_name'],
        'type_name' => $med['type_name'],
        'manufacturer' => $med['manufacturer'] ?? '',
        'description' => $med['description'],
        'days_expired' => $med['days_expired'],
        'is_returned' => $med['is_returned'],
        'is_disposed' => $med['is_disposed']
    ];
}

// Function to export to Excel - UPDATED TO EXCLUDE RETURNED/DISPOSED
function exportToExcel($conn)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="expired_medicines_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    // Fetch data for export - EXCLUDING RETURNED AND DISPOSED
    $export_query = "
        SELECT 
            m.name AS 'Medicine Name',
            m.generic_name AS 'Generic Name',
            sb.batch_no AS 'Batch No',
            sb.quantity AS 'Quantity',
            sb.purchase_price AS 'Purchase Price',
            sb.selling_price AS 'Selling Price',
            sb.mrp AS 'MRP',
            sb.received_date AS 'Received Date',
            sb.expiry_date AS 'Expiry Date',
            sb.location AS 'Location',
            DATEDIFF(CURDATE(), sb.expiry_date) AS 'Days Expired',
            (sb.purchase_price * sb.quantity) AS 'Loss Value',
            s.name AS 'Supplier',
            mc.name AS 'Category',
            mt.name AS 'Type'
        FROM stock_batches sb
        JOIN medicines m ON sb.medicine_id = m.id
        LEFT JOIN medicine_categories mc ON m.category_id = mc.id
        LEFT JOIN medicine_types mt ON m.type_id = mt.id
        LEFT JOIN suppliers s ON sb.supplier_id = s.id
        WHERE sb.is_expired = 1 
          AND sb.is_returned = 0 
          AND sb.is_disposed = 0
        ORDER BY sb.expiry_date ASC
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Medicines - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 50%, #fef3c7 100%);
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
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(239, 68, 68, 0.2);
        }

        .gradient-red {
            background: linear-gradient(135deg, var(--primary-red), var(--primary-red-dark));
        }

        .gradient-text {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(239, 68, 68, 0.1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: var(--primary-red);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: var(--primary-red-dark);
        }

        .table-row:hover {
            background-color: rgba(254, 242, 242, 0.3);
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

        .red-blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--primary-red), var(--primary-red-dark));
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.2;
            z-index: -1;
        }

        .orange-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-orange), #ea580c);
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

        .badge-expired {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-critical {
            background: linear-gradient(135deg, #7f1d1d, #991b1b);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-warning {
            background: linear-gradient(135deg, var(--accent-yellow), #d97706);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .days-indicator {
            width: 80px;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .days-fill {
            height: 100%;
            border-radius: 4px;
        }

        .status-active {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .status-expired {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .status-warning {
            background: linear-gradient(135deg, var(--accent-yellow), #d97706);
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

        .value-indicator {
            width: 100px;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .value-fill {
            height: 100%;
            border-radius: 4px;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="red-blob top-20 right-10 animate-float"></div>
    <div class="orange-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            <span class="gradient-text">Active Expired Medicines</span> ⚠️
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-skull-crossbones text-red-500"></i>
                            <span>Track and manage expired stock batches that need action</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span>Pharmacist Access</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Only shows batches that need action
                            </span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="stock.php"
                            class="px-6 py-3 border border-red-200 text-gray-700 rounded-xl hover:bg-red-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-red-500"></i>
                            <span>Back to Stock</span>
                        </a>
                        <a href="search_brand.php"
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-search"></i>
                            <span>Search Medicines</span>
                        </a>
                        <a href="medicines.php"
                            class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-plus"></i>
                            <span>Add Medicine</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Display Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="floating-alert bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-xl shadow-2xl flex items-center space-x-3 mx-6 mb-6">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['success'];
                            unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="floating-alert bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl shadow-2xl flex items-center space-x-3 mx-6 mb-6">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $_SESSION['error'];
                            unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-red flex items-center justify-center shadow-lg">
                            <i class="fas fa-skull-crossbones text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-red-600 bg-red-50 px-3 py-1 rounded-full">Active</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format(count($expired_medicines)); ?></h3>
                    <p class="text-gray-600 mb-3">Expired Batches</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-red h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-boxes text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-orange-600 bg-orange-50 px-3 py-1 rounded-full">Units</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($total_expired_quantity); ?></h3>
                    <p class="text-gray-600 mb-3">Expired Units</p>
                    <div class="flex items-center text-sm text-orange-500">
                        <i class="fas fa-weight-hanging mr-1"></i>
                        <span>Total expired stock</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-gray-700 to-gray-800 flex items-center justify-center shadow-lg">
                            <i class="fas fa-money-bill-wave text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-700 bg-gray-100 px-3 py-1 rounded-full">Loss</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($total_expired_value, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Financial Loss</p>
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>Based on purchase price</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-pills text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1">
                            <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-full text-center"><?php echo count($unique_medicines); ?> Medicines</span>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full text-center"><?php echo count($unique_suppliers); ?> Suppliers</span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($average_days_expired); ?></h3>
                    <p class="text-gray-600 mb-3">Avg Days Expired</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>Average expiration duration</span>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="glass-card mx-6 rounded-2xl p-6 mb-6 animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div class="flex items-center space-x-4 mb-4 lg:mb-0">
                        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center shadow">
                            <i class="fas fa-trash-alt text-red-600 text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Expired Stock Management</h3>
                            <p class="text-sm text-gray-600">Review and take action on expired medicines that need attention</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="printExpiredReport()"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm">
                            <i class="fas fa-print text-gray-600"></i>
                            <span class="text-gray-700">Print Report</span>
                        </button>
                        <a href="?export=excel"
                            class="px-4 py-2 border border-blue-300 rounded-lg hover:bg-blue-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm">
                            <i class="fas fa-file-excel text-blue-600"></i>
                            <span class="text-blue-700">Export Excel</span>
                        </a>
                        <a href="return_to_company.php"
                            class="px-4 py-2 border border-indigo-300 rounded-lg hover:bg-indigo-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm">
                            <i class="fas fa-undo-alt text-indigo-600"></i>
                            <span class="text-indigo-700">View Returns</span>
                        </a>
                        <!-- <a href="disposal_history.php"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium flex items-center space-x-2 bg-white/80 shadow-sm">
                            <i class="fas fa-history text-gray-600"></i>
                            <span class="text-gray-700">Disposal History</span>
                        </a> -->
                        <button onclick="showDisposeAllModal()"
                            class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:shadow-lg transition font-medium flex items-center space-x-2 shadow">
                            <i class="fas fa-trash"></i>
                            <span>Dispose All</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Expired Medicines Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.6s">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-red-100 bg-gradient-to-r from-red-50 to-red-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">Active Expired Stock Details</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo count($expired_medicines); ?> expired batch<?php echo count($expired_medicines) !== 1 ? 'es' : ''; ?> that need action (excludes returned/disposed)</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search expired medicines..."
                                class="pl-10 pr-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-red-400"></i>
                        </div>

                        <!-- Filter by Status -->
                        <select id="statusFilter" class="px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="">All Status</option>
                            <option value="critical">Critical (>90 days)</option>
                            <option value="expired">Expired (30-90 days)</option>
                            <option value="recent">Recent (<30 days)</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-red-50 to-red-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-2">
                                        <span>Medicine Details</span>
                                        <i class="fas fa-sort text-red-400 cursor-pointer hover:text-red-600" onclick="sortTable(0)"></i>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Batch Information
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Stock Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Expiry Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Financial Impact
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-red-50" id="expiredTableBody">
                            <?php if (count($expired_medicines) > 0): ?>
                                <?php foreach ($expired_medicines as $medicine):
                                    $days_expired = $medicine['days_expired'] ?? 0;
                                    $loss_value = $medicine['purchase_price'] * $medicine['quantity'];
                                    $formatted_expiry = date('d M Y', strtotime($medicine['expiry_date']));
                                    $received_date = date('d M Y', strtotime($medicine['received_date']));

                                    // Determine status based on days expired
                                    $status_class = 'status-expired';
                                    $status_text = 'Expired';
                                    $days_badge_class = 'badge-warning';

                                    if ($days_expired > 180) {
                                        $status_class = 'status-expired';
                                        $status_text = 'Critical';
                                        $days_badge_class = 'badge-critical';
                                    } elseif ($days_expired > 90) {
                                        $status_class = 'status-expired';
                                        $status_text = 'Critical';
                                        $days_badge_class = 'badge-critical';
                                    } elseif ($days_expired > 30) {
                                        $days_badge_class = 'badge-warning';
                                    } else {
                                        $days_badge_class = 'badge-expired';
                                    }

                                    // Determine value percentage for indicator
                                    $value_percentage = min(100, ($loss_value / 10000) * 100);
                                ?>
                                    <tr class="table-row hover:bg-red-25 transition-colors" data-days-expired="<?php echo $days_expired; ?>" data-batch-id="<?php echo $medicine['batch_id']; ?>" id="row-<?php echo $medicine['batch_id']; ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-red-500 to-red-600 flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas fa-pills text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($medicine['medicine_name']); ?></h4>
                                                    <p class="text-sm text-gray-500 mb-2">
                                                        ID: <span class="font-mono"><?php echo str_pad($medicine['medicine_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                        <?php if (!empty($medicine['generic_name'])): ?>
                                                            • <span class="font-medium"><?php echo htmlspecialchars($medicine['generic_name']); ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <div class="flex flex-wrap gap-2">
                                                        <?php if (!empty($medicine['category_name'])): ?>
                                                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                                <i class="fas fa-tag mr-1 text-xs"></i>
                                                                <?php echo htmlspecialchars($medicine['category_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($medicine['type_name'])): ?>
                                                            <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">
                                                                <i class="fas fa-prescription-bottle mr-1 text-xs"></i>
                                                                <?php echo htmlspecialchars($medicine['type_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div>
                                                    <div class="font-mono text-gray-800 text-sm mb-1"><?php echo htmlspecialchars($medicine['batch_no']); ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        <i class="fas fa-calendar-plus mr-1"></i>
                                                        Received: <?php echo $received_date; ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($medicine['supplier_name'])): ?>
                                                    <div class="flex items-center space-x-2 text-sm text-gray-700">
                                                        <i class="fas fa-truck text-gray-400 text-xs"></i>
                                                        <span class="font-medium"><?php echo htmlspecialchars($medicine['supplier_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($medicine['location'])): ?>
                                                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                                                        <i class="fas fa-map-marker-alt text-gray-400 text-xs"></i>
                                                        <span><?php echo htmlspecialchars($medicine['location']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div>
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Quantity</span>
                                                        <span class="text-lg font-bold text-red-600">
                                                            <?php echo number_format($medicine['quantity']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="value-indicator">
                                                        <div class="value-fill bg-gradient-to-r from-red-500 to-red-600" style="width: <?php echo min(100, ($medicine['quantity'] / 500) * 100); ?>%"></div>
                                                    </div>
                                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                                        <span>0</span>
                                                        <span>units</span>
                                                        <span>500+</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div>
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Expiry Date</span>
                                                        <span class="text-sm font-bold text-red-600"><?php echo $formatted_expiry; ?></span>
                                                    </div>
                                                    <div class="days-indicator">
                                                        <div class="days-fill <?php echo $status_class; ?>" style="width: <?php echo min(100, ($days_expired / 365) * 100); ?>%"></div>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="<?php echo $days_badge_class; ?> text-xs">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?php echo $days_expired; ?> days
                                                    </span>
                                                    <span class="text-xs <?php echo $status_class; ?> text-white px-2 py-1 rounded">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Loss Value</span>
                                                    <span class="text-sm font-bold text-red-600">
                                                        Rs <?php echo number_format($loss_value, 2); ?>
                                                    </span>
                                                </div>
                                                <div class="value-indicator">
                                                    <div class="value-fill bg-gradient-to-r from-red-700 to-red-800" style="width: <?php echo $value_percentage; ?>%"></div>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    @ Rs <?php echo number_format($medicine['purchase_price'], 2); ?>/unit
                                                </div>
                                                <?php if ($medicine['selling_price'] > 0): ?>
                                                    <div class="text-xs text-gray-600">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        Could've sold for: Rs <?php echo number_format($medicine['selling_price'] * $medicine['quantity'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col space-y-2">
                                                <!-- View Details Button -->
                                                <button onclick="viewBatchDetails(<?php echo $medicine['batch_id']; ?>)"
                                                    class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-eye text-sm"></i>
                                                    <span class="text-sm font-medium">View Details</span>
                                                </button>

                                                <!-- Action Buttons Row -->
                                                <div class="flex space-x-2">
                                                    <!-- Return Button -->
                                                    <button onclick="showReturnModal(<?php echo $medicine['batch_id']; ?>, '<?php echo addslashes($medicine['batch_no']); ?>')"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-undo-alt text-xs"></i>
                                                        <span class="text-xs font-medium">Return</span>
                                                    </button>

                                                    <!-- Dispose Button -->
                                                    <button onclick="showDisposeModal(<?php echo $medicine['batch_id']; ?>, '<?php echo addslashes($medicine['batch_no']); ?>')"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-trash-alt text-xs"></i>
                                                        <span class="text-xs font-medium">Dispose</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Active Expired Medicines Found! 🎉</h4>
                                            <p class="text-gray-600 mb-6">Great job! All expired medicines have been either returned to suppliers or properly disposed.</p>
                                            <div class="flex flex-col space-y-3">
                                                <a href="return_to_company.php"
                                                    class="gradient-red text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center justify-center space-x-2 shadow">
                                                    <i class="fas fa-undo-alt"></i>
                                                    <span>View Return History</span>
                                                </a>
                                                <a href="disposal_history.php"
                                                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-bold hover:bg-gray-50 transition-all duration-300 inline-flex items-center justify-center space-x-2 shadow-sm">
                                                    <i class="fas fa-history"></i>
                                                    <span>View Disposal History</span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 border-t border-red-100 bg-gradient-to-r from-red-50 to-red-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo count($expired_medicines); ?> active expired batches •
                                <span class="font-medium text-red-600">
                                    Total Loss: Rs <?php echo number_format($total_expired_value, 2); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-4 py-2 border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center space-x-2 bg-white/80 shadow-sm" onclick="exportAllToPDF()">
                                <i class="fas fa-file-export text-red-500"></i>
                                <span class="text-sm text-gray-700">Export PDF</span>
                            </button>
                            <button class="px-4 py-2 border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center space-x-2 bg-white/80 shadow-sm" onclick="printAllExpired()">
                                <i class="fas fa-print text-red-500"></i>
                                <span class="text-sm text-gray-700">Print Report</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mx-6 my-8">
                <!-- Expiry Management Tips -->
                <div class="glass-card rounded-2xl p-6 lg:col-span-2 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-lightbulb text-yellow-500"></i>
                        <span>Expiry Management Tips</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-start space-x-3 p-3 bg-red-50 rounded-lg border border-red-100">
                            <i class="fas fa-trash-alt text-red-600 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-red-800">Proper Disposal</p>
                                <p class="text-xs text-red-600">Dispose expired medicines as per pharmaceutical regulations</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                            <i class="fas fa-calendar-alt text-yellow-600 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-yellow-800">Regular Audits</p>
                                <p class="text-xs text-yellow-600">Conduct monthly expiry date checks and stock rotation</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-indigo-50 rounded-lg border border-indigo-100">
                            <i class="fas fa-undo-alt text-indigo-600 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-indigo-800">Return Policy</p>
                                <p class="text-xs text-indigo-600">Check supplier return policies before expiry</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg border border-green-100">
                            <i class="fas fa-chart-line text-green-600 mt-1"></i>
                            <div>
                                <p class="text-sm font-medium text-green-800">Inventory Analysis</p>
                                <p class="text-xs text-green-600">Analyze expiry patterns to optimize purchase quantities</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-6">
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <a href="stock.php?filter=expiring_soon"
                                class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                <span class="text-gray-700">View Expiring Soon</span>
                                <i class="fas fa-clock text-yellow-500"></i>
                            </a>
                            <a href="suppliers.php"
                                class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition shadow-sm">
                                <span class="text-gray-700">Contact Suppliers</span>
                                <i class="fas fa-phone text-blue-500"></i>
                            </a>
                            <a href="reports.php?type=expiry"
                                class="flex items-center justify-between p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition shadow-sm">
                                <span class="text-gray-700">Expiry Reports</span>
                                <i class="fas fa-chart-bar text-purple-500"></i>
                            </a>
                            <button onclick="showBulkActionsModal()"
                                class="w-full flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition shadow-sm">
                                <span class="text-gray-700">Bulk Actions</span>
                                <i class="fas fa-tasks text-red-500"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Batch Details Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6 border-b border-red-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-file-medical-alt text-blue-500 mr-2"></i>
                        <span id="modalBatchTitle">Batch Details</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="exportBatchToPDF()"
                            class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2">
                            <i class="fas fa-file-pdf"></i>
                            <span>Export PDF</span>
                        </button>
                        <button onclick="printBatchDetails()"
                            class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </button>
                        <button onclick="closeModal('viewModal')"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                            <i class="fas fa-times text-gray-600"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] custom-scrollbar" id="batchDetailsContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-red-500 text-3xl mb-4"></i>
                    <p class="text-gray-600">Loading batch details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Return to Company Modal -->
    <div id="returnModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-undo-alt text-indigo-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Return to Company</h3>
                <p class="text-gray-600 text-center mb-6" id="returnBatchInfo">
                    This batch will be marked as returned to the company/supplier and removed from this list.
                </p>
                <form id="returnForm" method="POST" action="">
                    <input type="hidden" name="batch_id" id="returnBatchId">

                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Return Reason *</label>
                            <select name="return_reason" required class="w-full px-4 py-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none transition">
                                <option value="">Select reason</option>
                                <option value="Expired">Expired Stock</option>
                                <option value="Damaged">Damaged Goods</option>
                                <option value="Wrong Delivery">Wrong Delivery</option>
                                <option value="Quality Issue">Quality Issue</option>
                                <option value="Overstock">Overstock Return</option>
                                <option value="Supplier Recall">Supplier Recall</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Return Notes</label>
                            <textarea name="return_notes" rows="3" class="w-full px-4 py-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none transition" placeholder="Additional details about the return..."></textarea>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <button type="button" onclick="closeModal('returnModal')" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" name="return_to_company" class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            <i class="fas fa-undo-alt mr-1"></i> Confirm Return
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dispose Batch Modal -->
    <div id="disposeModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-trash-alt text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Dispose Batch</h3>
                <p class="text-gray-600 text-center mb-6" id="disposeBatchInfo">
                    This batch will be disposed and removed from this list. This action cannot be undone!
                </p>
                <form id="disposeForm" method="POST" action="">
                    <input type="hidden" name="batch_id" id="disposeBatchId">

                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Reason *</label>
                            <select name="disposal_reason" required class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="">Select reason</option>
                                <option value="Expired">Expired</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Recall">Product Recall</option>
                                <option value="Quality Issue">Quality Issue</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Method *</label>
                            <select name="disposal_method" required class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="">Select method</option>
                                <option value="Incinerated">Incinerated</option>
                                <option value="Returned to Supplier">Returned to Supplier</option>
                                <option value="Destroyed">Physically Destroyed</option>
                                <option value="Hazardous Waste">Hazardous Waste Disposal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Notes</label>
                            <textarea name="disposal_notes" rows="3" class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition" placeholder="Additional details about the disposal..."></textarea>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <button type="button" onclick="closeModal('disposeModal')" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" name="dispose_batch" class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            <i class="fas fa-trash mr-1"></i> Confirm Disposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dispose All Modal -->
    <div id="disposeAllModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-skull-crossbones text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Dispose All Expired</h3>
                <div class="mb-6 p-4 bg-red-50 rounded-lg border-2 border-red-200">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        <div>
                            <h4 class="font-bold text-red-800">⚠️ CRITICAL ACTION</h4>
                            <p class="text-sm text-red-600 mt-1">You are about to dispose ALL expired batches that are not yet returned!</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600"><?php echo count($expired_medicines); ?></div>
                        <div class="text-xs text-gray-600">Active Batches</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">Rs <?php echo number_format($total_expired_value, 2); ?></div>
                        <div class="text-xs text-gray-600">Total Value</div>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Reason *</label>
                            <select name="disposal_reason_all" required class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="Expired">Expired</option>
                                <option value="Quarterly Clearance">Quarterly Clearance</option>
                                <option value="Regulatory Requirement">Regulatory Requirement</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Method *</label>
                            <select name="disposal_method_all" required class="w-full px-4 py-3 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="Incinerated">Incinerated</option>
                                <option value="Hazardous Waste">Hazardous Waste Disposal</option>
                                <option value="Authorized Disposal">Authorized Disposal Service</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-6 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-sm text-yellow-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Disposed batches will be removed from this list. Individual disposal records will be created for each batch.
                        </p>
                    </div>

                    <div class="flex space-x-3">
                        <button type="button" onclick="closeModal('disposeAllModal')" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" name="dispose_all" class="flex-1 px-4 py-3 bg-gradient-to-r from-red-700 to-red-800 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            <i class="fas fa-skull-crossbones mr-1"></i> Dispose All
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div id="bulkActionsModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-tasks text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-4">Bulk Actions</h3>
                <div class="space-y-3">
                    <button onclick="performBulkReturn()" class="w-full flex items-center justify-between p-3 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition shadow-sm">
                        <span class="text-gray-700">Return All to Suppliers</span>
                        <i class="fas fa-undo-alt text-indigo-500"></i>
                    </button>
                    <button onclick="performBulkDispose()" class="w-full flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition shadow-sm">
                        <span class="text-gray-700">Dispose All Expired</span>
                        <i class="fas fa-trash-alt text-red-500"></i>
                    </button>
                    <button onclick="generateBulkReport()" class="w-full flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition shadow-sm">
                        <span class="text-gray-700">Generate Expiry Report</span>
                        <i class="fas fa-file-excel text-green-500"></i>
                    </button>
                    <button onclick="notifySuppliers()" class="w-full flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                        <span class="text-gray-700">Notify Suppliers</span>
                        <i class="fas fa-envelope text-yellow-500"></i>
                    </button>
                </div>
                <button onclick="closeModal('bulkActionsModal')" class="w-full mt-6 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium shadow-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Printable Content (hidden by default) -->
    <div id="printable-content" class="hidden"></div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Store batch data from PHP
        const batchData = <?php echo json_encode($batch_data_json); ?>;

        // Global variables
        let currentBatchData = null;
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

        // View batch details
        function viewBatchDetails(batchId) {
            openModal('viewModal');

            // Check if we have the batch data
            if (batchData[batchId]) {
                currentBatchData = batchData[batchId];
                updateBatchModal(currentBatchData);
            } else {
                // Fallback - try to find the batch in the table rows
                const row = document.querySelector(`tr[data-batch-id="${batchId}"]`);
                if (row) {
                    const batchDetails = {
                        batch_id: batchId,
                        medicine_name: row.querySelector('h4').textContent,
                        batch_no: row.cells[1].querySelector('.font-mono').textContent,
                        quantity: parseInt(row.cells[2].querySelector('.text-lg').textContent.replace(/,/g, '')),
                        expiry_date: row.cells[3].querySelector('.text-sm.font-bold').textContent,
                        // Add more fields as needed from the row
                    };
                    updateBatchModal(batchDetails);
                } else {
                    document.getElementById('batchDetailsContent').innerHTML = `
                        <div class="text-center py-8 text-red-500">
                            <i class="fas fa-exclamation-circle text-3xl"></i>
                            <p class="mt-4">Batch data not found. Please refresh the page and try again.</p>
                        </div>
                    `;
                }
            }
        }

        // Update modal content with batch data
        function updateBatchModal(batch) {
            const expiryDate = new Date(batch.expiry_date).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
            const receivedDate = batch.received_date ? new Date(batch.received_date).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            }) : 'Not specified';

            // Calculate values with proper error handling
            const purchasePrice = parseFloat(batch.purchase_price) || 0;
            const sellingPrice = parseFloat(batch.selling_price) || 0;
            const mrpPrice = parseFloat(batch.mrp) || 0;
            const quantity = parseInt(batch.quantity) || 0;

            const lossValue = purchasePrice * quantity;
            const potentialRevenue = sellingPrice * quantity;
            const daysExpired = batch.days_expired || Math.floor((new Date() - new Date(batch.expiry_date)) / (1000 * 60 * 60 * 24));

            document.getElementById('modalBatchTitle').textContent = `Batch: ${batch.batch_no}`;

            let html = `
        <div class="space-y-8">
            <!-- Basic Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        Basic Information
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Batch ID:</span>
                            <span class="font-semibold">BATCH-${String(batch.batch_id).padStart(6, '0')}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Medicine:</span>
                            <span class="font-semibold text-gray-800">${batch.medicine_name}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Batch Number:</span>
                            <span class="font-mono">${batch.batch_no}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Quantity:</span>
                            <span class="font-semibold text-lg ${batch.quantity <= 10 ? 'text-red-600' : 'text-gray-800'}">${batch.quantity} units</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Expiry Date:</span>
                            <span class="font-semibold text-red-600">${expiryDate}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Received Date:</span>
                            <span class="text-gray-800">${receivedDate}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Location:</span>
                            <span class="text-gray-800">${batch.location || 'Not specified'}</span>
                        </div>
                        ${batch.supplier_name ? `
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Supplier:</span>
                            <span class="text-gray-800">${batch.supplier_name}</span>
                        </div>
                        ` : ''}
                        ${batch.phone ? `
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Phone:</span>
                            <span class="text-gray-800">${batch.phone}</span>
                        </div>
                        ` : ''}
                        ${batch.email ? `
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Email:</span>
                            <span class="text-gray-800">${batch.email}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <!-- Price Information -->
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-tag text-green-500 mr-2"></i>
                        Price Information
                    </h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Purchase Price:</span>
                            <span class="font-semibold text-blue-600">
                                Rs ${purchasePrice.toFixed(2)}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Selling Price:</span>
                            <span class="font-semibold text-green-600">
                                Rs ${sellingPrice.toFixed(2)}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">MRP:</span>
                            <span class="font-semibold text-purple-600">
                                Rs ${mrpPrice.toFixed(2)}
                            </span>
                        </div>
                        <div class="pt-3 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 font-medium">Total Loss Value:</span>
                                <span class="font-semibold text-red-600 text-lg">
                                    Rs ${lossValue.toFixed(2)}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                (Purchase Price × Quantity)
                            </p>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Potential Revenue Loss:</span>
                            <span class="font-semibold text-orange-600">
                                Rs ${potentialRevenue.toFixed(2)}
                            </span>
                        </div>
                        ${sellingPrice > 0 && purchasePrice > 0 ? `
                        <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-yellow-800">Profit Margin Lost:</span>
                                <span class="font-semibold text-yellow-700">
                                    ${((sellingPrice - purchasePrice) / purchasePrice * 100).toFixed(1)}%
                                </span>
                            </div>
                            <p class="text-xs text-yellow-600 mt-1">
                                Potential profit per unit: Rs ${(sellingPrice - purchasePrice).toFixed(2)}
                            </p>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            
            <!-- Days Expired Information -->
            <div class="space-y-4">
                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-clock text-red-500 mr-2"></i>
                    Expiry Status
                </h4>
                <div class="bg-red-50 p-4 rounded-xl border border-red-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Days Expired</p>
                            <p class="text-2xl font-bold text-red-600">
                                ${daysExpired} days
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Status</p>
                            <span class="inline-block px-3 py-1 rounded-full text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700">
                                ${daysExpired > 0 ? 'EXPIRED' : 'EXPIRING SOON'}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-gray-600">
                        <i class="fas fa-calendar mr-1"></i>
                        Expired on: ${expiryDate}
                    </div>
                </div>
            </div>
            
            <!-- Medicine Details -->
            <div class="space-y-4">
                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-pills text-purple-500 mr-2"></i>
                    Medicine Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <p class="text-sm text-gray-600">Generic Name</p>
                        <p class="font-medium">${batch.generic_name || 'N/A'}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <p class="text-sm text-gray-600">Category</p>
                        <p class="font-medium">${batch.category_name || 'N/A'}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <p class="text-sm text-gray-600">Type</p>
                        <p class="font-medium">${batch.type_name || 'N/A'}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl">
                        <p class="text-sm text-gray-600">Manufacturer</p>
                        <p class="font-medium">${batch.manufacturer || 'N/A'}</p>
                    </div>
                </div>
                ${batch.description ? `
                <div class="bg-gray-50 p-4 rounded-xl">
                    <p class="text-sm text-gray-600">Description</p>
                    <p class="text-gray-800">${batch.description}</p>
                </div>
                ` : ''}
            </div>
            
            <!-- Recommended Actions -->
            <div class="space-y-4">
                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                    Recommended Actions
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-yellow-50 p-4 rounded-xl border border-yellow-200">
                        <h5 class="font-semibold text-yellow-800 mb-2">Return to Supplier</h5>
                        <p class="text-sm text-yellow-700 mb-3">If supplier accepts returns for expired stock</p>
                        <button onclick="showReturnModal(${batch.batch_id}, '${batch.batch_no}')" class="w-full px-4 py-2 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg hover:shadow-lg transition">
                            Initiate Return
                        </button>
                    </div>
                    <div class="bg-red-50 p-4 rounded-xl border border-red-200">
                        <h5 class="font-semibold text-red-800 mb-2">Proper Disposal</h5>
                        <p class="text-sm text-red-700 mb-3">Dispose as per pharmaceutical regulations</p>
                        <button onclick="showDisposeModal(${batch.batch_id}, '${batch.batch_no}')" class="w-full px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:shadow-lg transition">
                            Dispose Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.getElementById('batchDetailsContent').innerHTML = html;
        }

        // Show return modal
        function showReturnModal(batchId, batchNo) {
            document.getElementById('returnBatchId').value = batchId;
            document.getElementById('returnBatchInfo').textContent = `Return Batch: ${batchNo} to company/supplier. This batch will be removed from the list.`;
            closeModal('viewModal');
            setTimeout(() => openModal('returnModal'), 300);
        }

        // Show dispose modal
        function showDisposeModal(batchId, batchNo) {
            document.getElementById('disposeBatchId').value = batchId;
            document.getElementById('disposeBatchInfo').textContent = `Dispose Batch: ${batchNo}. This batch will be removed from the list. This action cannot be undone!`;
            closeModal('viewModal');
            setTimeout(() => openModal('disposeModal'), 300);
        }

        // Show dispose all modal
        function showDisposeAllModal() {
            openModal('disposeAllModal');
        }

        // Show bulk actions modal
        function showBulkActionsModal() {
            openModal('bulkActionsModal');
        }

        // Export batch to PDF
        async function exportBatchToPDF() {
            if (!currentBatchData) {
                showNotification('No batch data available to export', 'error');
                return;
            }

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                // Add header
                doc.setFontSize(20);
                doc.setTextColor(239, 68, 68);
                doc.text('MediCare Pharma - Expired Batch Report', 105, 20, null, null, 'center');

                doc.setFontSize(11);
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`, 105, 30, null, null, 'center');

                const batch = currentBatchData;
                const expiryDate = new Date(batch.expiry_date).toLocaleDateString();
                const lossValue = batch.purchase_price * batch.quantity;
                const daysExpired = batch.days_expired || Math.floor((new Date() - new Date(batch.expiry_date)) / (1000 * 60 * 60 * 24));

                // Add batch details
                doc.setFontSize(16);
                doc.setTextColor(0, 0, 0);
                doc.text(`Batch: ${batch.batch_no}`, 20, 45);

                doc.setFontSize(12);
                doc.text(`Medicine: ${batch.medicine_name}`, 20, 55);
                doc.text(`Generic: ${batch.generic_name || 'N/A'}`, 20, 60);
                doc.text(`Category: ${batch.category_name || 'N/A'}`, 20, 65);
                doc.text(`Type: ${batch.type_name || 'N/A'}`, 20, 70);
                doc.text(`Quantity: ${batch.quantity} units`, 20, 75);
                doc.text(`Expiry Date: ${expiryDate}`, 20, 80);
                doc.text(`Days Expired: ${daysExpired} days`, 20, 85);
                doc.text(`Supplier: ${batch.supplier_name || 'N/A'}`, 20, 90);

                // Add price information
                doc.setFontSize(14);
                doc.text('Financial Impact', 20, 105);
                doc.setFontSize(12);
                doc.text(`Purchase Price: Rs ${parseFloat(batch.purchase_price || 0).toFixed(2)}`, 20, 115);
                doc.text(`Selling Price: Rs ${parseFloat(batch.selling_price || 0).toFixed(2)}`, 20, 120);
                doc.text(`MRP: Rs ${parseFloat(batch.mrp || 0).toFixed(2)}`, 20, 125);
                doc.text(`Total Loss Value: Rs ${lossValue.toFixed(2)}`, 20, 135);
                doc.text(`Potential Revenue Loss: Rs ${((batch.selling_price || 0) * batch.quantity).toFixed(2)}`, 20, 140);

                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text('Confidential - For Internal Use Only', 105, 280, null, null, 'center');

                // Save the PDF
                doc.save(`Expired_Batch_${batch.batch_no}_${new Date().toISOString().slice(0,10)}.pdf`);

                showNotification('PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Print batch details
        function printBatchDetails() {
            if (!currentBatchData) {
                showNotification('No batch data available to print', 'error');
                return;
            }

            const batch = currentBatchData;
            const expiryDate = new Date(batch.expiry_date).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
            const lossValue = batch.purchase_price * batch.quantity;
            const daysExpired = batch.days_expired || Math.floor((new Date() - new Date(batch.expiry_date)) / (1000 * 60 * 60 * 24));

            let printContent = `
                <div style="padding: 20px; font-family: Arial, sans-serif;">
                    <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #ef4444; padding-bottom: 15px;">
                        <h1 style="color: #ef4444; margin: 0;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 10px 0 5px 0;">Expired Batch Details Report</h2>
                        <p style="color: #666; margin: 0;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                    </div>
                    
                    <!-- Basic Info -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #3b82f6; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Basic Information</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Batch Number:</td>
                                <td style="padding: 8px 0; font-weight: bold;">${batch.batch_no}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Medicine:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #333;">${batch.medicine_name}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Generic Name:</td>
                                <td style="padding: 8px 0;">${batch.generic_name || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Quantity:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #ef4444;">${batch.quantity} units</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Expiry Date:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #ef4444;">${expiryDate}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Days Expired:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #ef4444;">${daysExpired} days</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Supplier:</td>
                                <td style="padding: 8px 0;">${batch.supplier_name || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Location:</td>
                                <td style="padding: 8px 0;">${batch.location || 'Not specified'}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Price Info -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #ef4444; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Financial Impact</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Purchase Price:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #3b82f6;">Rs ${parseFloat(batch.purchase_price || 0).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Selling Price:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #10b981;">Rs ${parseFloat(batch.selling_price || 0).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">MRP:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #8b5cf6;">Rs ${parseFloat(batch.mrp || 0).toFixed(2)}</td>
                            </tr>
                            <tr style="border-top: 2px solid #ddd;">
                                <td style="padding: 8px 0; color: #666; font-weight: bold;">Total Loss Value:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #ef4444; font-size: 18px;">Rs ${lossValue.toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Potential Revenue Loss:</td>
                                <td style="padding: 8px 0; color: #f97316;">Rs ${((batch.selling_price || 0) * batch.quantity).toFixed(2)}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Medicine Details -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #8b5cf6; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Medicine Details</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Category:</td>
                                <td style="padding: 8px 0;">${batch.category_name || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Type:</td>
                                <td style="padding: 8px 0;">${batch.type_name || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Manufacturer:</td>
                                <td style="padding: 8px 0;">${batch.manufacturer || 'N/A'}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Footer -->
                    <div style="margin-top: 40px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px;">
                        <p style="margin: 5px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 5px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 5px 0;">Page 1 of 1</p>
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
                    <title>Print Expired Batch - ${batch.batch_no}</title>
                    <style>
                        @media print {
                            @page { margin: 20mm; }
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

        // Export all expired medicines to PDF
        async function exportAllToPDF() {
            try {
                showNotification('Generating PDF report for all expired medicines...', 'info');

                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                // Add header
                doc.setFontSize(20);
                doc.setTextColor(239, 68, 68);
                doc.text('MediCare Pharma - All Active Expired Medicines Report', 105, 20, null, null, 'center');

                doc.setFontSize(11);
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 105, 30, null, null, 'center');
                doc.text(`Total Active Expired Batches: ${<?php echo count($expired_medicines); ?>}`, 105, 35, null, null, 'center');
                doc.text(`Total Loss Value: Rs <?php echo number_format($total_expired_value, 2); ?>`, 105, 40, null, null, 'center');

                // Add table headers
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                let y = 55;

                // Table headers
                doc.setFillColor(239, 68, 68);
                doc.rect(10, y, 190, 10, 'F');
                doc.setTextColor(255, 255, 255);
                doc.text('Medicine', 15, y + 7);
                doc.text('Batch No', 60, y + 7);
                doc.text('Quantity', 90, y + 7);
                doc.text('Expiry Date', 110, y + 7);
                doc.text('Days Expired', 140, y + 7);
                doc.text('Loss Value', 165, y + 7);

                y += 15;
                doc.setTextColor(0, 0, 0);

                // Add medicine rows
                <?php foreach ($expired_medicines as $index => $med): ?>
                    <?php
                    $days_expired = $med['days_expired'] ?? 0;
                    $loss_value = $med['purchase_price'] * $med['quantity'];
                    $expiry_date = date('d M Y', strtotime($med['expiry_date']));
                    ?>

                    <?php echo "
                    if (y > 270) {
                        doc.addPage();
                        y = 20;
                    }

                    doc.text('" . addslashes(substr($med['medicine_name'], 0, 25)) . "', 15, y);
                    doc.text('" . $med['batch_no'] . "', 60, y);
                    doc.text('" . $med['quantity'] . "', 90, y);
                    doc.text('" . $expiry_date . "', 110, y);
                    doc.text('" . $days_expired . "', 140, y);
                    doc.text('Rs " . number_format($loss_value, 2) . "', 165, y);

                    y = y + 7;
                "; ?>

                <?php endforeach; ?>

                // Add summary
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }

                doc.setFontSize(14);
                doc.setTextColor(239, 68, 68);
                doc.text('Summary', 20, y);
                y += 10;

                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text(`Total Batches: <?php echo count($expired_medicines); ?>`, 20, y);
                y += 7;
                doc.text(`Total Units: <?php echo $total_expired_quantity; ?>`, 20, y);
                y += 7;
                doc.text(`Total Loss Value: Rs <?php echo number_format($total_expired_value, 2); ?>`, 20, y);

                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text('Page 1', 105, 285, null, null, 'center');
                doc.text('Confidential - For Internal Use Only', 105, 290, null, null, 'center');

                // Save the PDF
                doc.save(`Active_Expired_Medicines_${new Date().toISOString().slice(0,10)}.pdf`);

                showNotification('All expired medicines PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Print all expired
        function printAllExpired() {
            let printContent = `
                <div style="padding: 20px; font-family: Arial, sans-serif;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ef4444; padding-bottom: 10px;">
                        <h1 style="color: #ef4444; margin: 0;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 10px 0 5px 0;">Active Expired Medicines Report</h2>
                        <p style="color: #666; margin: 0 0 5px 0;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                        <p style="color: #666; margin: 0;">Total Active Batches: <?php echo count($expired_medicines); ?> • Total Loss: Rs <?php echo number_format($total_expired_value, 2); ?></p>
                        <p style="color: #666; margin: 5px 0 0 0; font-size: 11px;">(Excludes returned/disposed batches)</p>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 11px; margin-top: 20px;">
                        <thead>
                            <tr style="background: #fef2f2;">
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Medicine</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Batch No</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Generic</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Quantity</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Expiry Date</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Days Expired</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Loss Value</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            <?php foreach ($expired_medicines as $med): ?>
                <?php
                $days_expired = $med['days_expired'] ?? 0;
                $loss_value = $med['purchase_price'] * $med['quantity'];
                $expiry_date = date('d M Y', strtotime($med['expiry_date']));
                ?>

                printContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($med['medicine_name']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($med['batch_no']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($med['generic_name'] ?? 'N/A'); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; ${<?php echo $med['quantity']; ?> <= 10 ? 'color: #ef4444; font-weight: bold;' : ''}"><?php echo $med['quantity']; ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo $expiry_date; ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; ${<?php echo $days_expired; ?> > 90 ? 'color: #ef4444; font-weight: bold;' : <?php echo $days_expired; ?> > 30 ? 'color: #f59e0b;' : 'color: #666;'}"><?php echo $days_expired; ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; color: #ef4444; font-weight: bold;">Rs <?php echo number_format($loss_value, 2); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($med['supplier_name'] ?? 'N/A'); ?></td>
                    </tr>
                `;
            <?php endforeach; ?>

            printContent += `
                        </tbody>
                        <tfoot style="background: #fef2f2; font-weight: bold;">
                            <tr>
                                <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right;">Total:</td>
                                <td style="border: 1px solid #ddd; padding: 10px;"><?php echo $total_expired_quantity; ?></td>
                                <td colspan="2" style="border: 1px solid #ddd; padding: 10px;"></td>
                                <td style="border: 1px solid #ddd; padding: 10px; color: #ef4444;">Rs <?php echo number_format($total_expired_value, 2); ?></td>
                                <td style="border: 1px solid #ddd; padding: 10px;"></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px;">
                        <p style="margin: 5px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 5px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 5px 0;">Page 1 of 1</p>
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
                    <title>Print Active Expired Medicines</title>
                    <style>
                        @media print {
                            @page { margin: 15mm; }
                            body { margin: 0; }
                            table { font-size: 10px; }
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

        // Print expired report (stats)
        function printExpiredReport() {
            let printContent = `
                <div style="padding: 20px; font-family: Arial, sans-serif;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ef4444; padding-bottom: 10px;">
                        <h1 style="color: #ef4444; margin: 0;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 10px 0 5px 0;">Active Expired Medicines Summary Report</h2>
                        <p style="color: #666; margin: 0 0 5px 0;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                        <p style="color: #666; margin: 5px 0 0 0; font-size: 11px;">(Only batches that need action - excludes returned/disposed)</p>
                    </div>
                    
                    <!-- Statistics -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
                        <div style="border: 1px solid #ef4444; padding: 15px; text-align: center; border-radius: 5px;">
                            <h3 style="color: #ef4444; margin: 0 0 5px 0;">Active Expired Batches</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo count($expired_medicines); ?></p>
                        </div>
                        <div style="border: 1px solid #f97316; padding: 15px; text-align: center; border-radius: 5px;">
                            <h3 style="color: #f97316; margin: 0 0 5px 0;">Expired Units</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo $total_expired_quantity; ?></p>
                        </div>
                        <div style="border: 1px solid #dc2626; padding: 15px; text-align: center; border-radius: 5px;">
                            <h3 style="color: #dc2626; margin: 0 0 5px 0;">Financial Loss</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0; color: #dc2626;">Rs <?php echo number_format($total_expired_value, 2); ?></p>
                        </div>
                        <div style="border: 1px solid #7c3aed; padding: 15px; text-align: center; border-radius: 5px;">
                            <h3 style="color: #7c3aed; margin: 0 0 5px 0;">Avg Days Expired</h3>
                            <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo $average_days_expired; ?></p>
                        </div>
                    </div>
                    
                    <!-- Quick Summary -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Quick Summary</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Unique Medicines:</td>
                                <td style="padding: 8px 0;"><?php echo count($unique_medicines); ?> different medicines</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Suppliers Involved:</td>
                                <td style="padding: 8px 0;"><?php echo count($unique_suppliers); ?> suppliers</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Average Loss per Batch:</td>
                                <td style="padding: 8px 0; color: #dc2626; font-weight: bold;">Rs <?php echo count($expired_medicines) > 0 ? number_format($total_expired_value / count($expired_medicines), 2) : '0.00'; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Report Generated By:</td>
                                <td style="padding: 8px 0;"><?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Recommended Actions -->
                    <div style="margin-bottom: 30px; padding: 15px; background: #fef2f2; border-left: 4px solid #ef4444;">
                        <h3 style="color: #dc2626; margin: 0 0 10px 0;">⚠️ Recommended Actions</h3>
                        <p style="margin: 5px 0;">1. Review supplier return policies for expired stock</p>
                        <p style="margin: 5px 0;">2. Implement FIFO (First-In, First-Out) inventory system</p>
                        <p style="margin: 5px 0;">3. Conduct monthly expiry date audits</p>
                        <p style="margin: 5px 0;">4. Optimize purchase quantities based on sales patterns</p>
                    </div>
                    
                    <!-- Footer -->
                    <div style="margin-top: 40px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px;">
                        <p style="margin: 5px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 5px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 5px 0;">Page 1 of 1</p>
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
                    <title>Print Active Expired Report</title>
                    <style>
                        @media print {
                            @page { margin: 20mm; }
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

        // Export to Excel (using XLSX library)
        function exportToExcelXLSX() {
            try {
                // Create worksheet from table
                const table = document.querySelector('.glass-card table');
                const ws = XLSX.utils.table_to_sheet(table);

                // Create workbook
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Active Expired Medicines");

                // Generate file
                XLSX.writeFile(wb, `active_expired_medicines_${new Date().toISOString().slice(0,10)}.xlsx`);

                showNotification('Excel file exported successfully!', 'success');
            } catch (error) {
                console.error('Excel export error:', error);
                showNotification('Error exporting Excel file', 'error');
            }
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // Sort table functionality
        function sortTable(columnIndex) {
            const table = document.querySelector('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            if (!sortDirection[columnIndex]) {
                sortDirection[columnIndex] = 'asc';
            } else {
                sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            }

            rows.sort((a, b) => {
                let aValue, bValue;

                if (columnIndex === 0) {
                    // Medicine name
                    aValue = a.querySelector('h4').textContent.toLowerCase();
                    bValue = b.querySelector('h4').textContent.toLowerCase();
                } else if (columnIndex === 2) {
                    // Quantity
                    aValue = parseInt(a.querySelector('.text-lg').textContent.replace(/,/g, ''));
                    bValue = parseInt(b.querySelector('.text-lg').textContent.replace(/,/g, ''));
                } else if (columnIndex === 4) {
                    // Loss value
                    aValue = parseFloat(a.querySelector('.font-bold.text-red-600').textContent.replace('Rs ', '').replace(/,/g, ''));
                    bValue = parseFloat(b.querySelector('.font-bold.text-red-600').textContent.replace('Rs ', '').replace(/,/g, ''));
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

            // Clear and re-add sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));

            // Update sort indicator
            updateSortIndicator(columnIndex);
        }

        function updateSortIndicator(columnIndex) {
            // Remove all sort indicators
            document.querySelectorAll('.fa-sort').forEach(icon => {
                icon.className = 'fas fa-sort text-red-400 cursor-pointer hover:text-red-600';
            });

            // Add indicator to current column
            const currentIcon = document.querySelectorAll('.fa-sort')[columnIndex];
            if (sortDirection[columnIndex] === 'asc') {
                currentIcon.className = 'fas fa-sort-up text-red-600 cursor-pointer';
            } else {
                currentIcon.className = 'fas fa-sort-down text-red-600 cursor-pointer';
            }
        }

        // Status filter functionality
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function(e) {
                const status = e.target.value;
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const daysExpired = parseInt(row.getAttribute('data-days-expired')) || 0;

                    let showRow = true;
                    if (status === 'critical' && daysExpired <= 90) {
                        showRow = false;
                    } else if (status === 'expired' && (daysExpired <= 30 || daysExpired > 90)) {
                        showRow = false;
                    } else if (status === 'recent' && daysExpired > 30) {
                        showRow = false;
                    }

                    row.style.display = showRow ? '' : 'none';
                });
            });
        }

        // Bulk action functions
        function performBulkReturn() {
            closeModal('bulkActionsModal');
            showNotification('Bulk return feature coming soon!', 'info');
        }

        function performBulkDispose() {
            closeModal('bulkActionsModal');
            showDisposeAllModal();
        }

        function generateBulkReport() {
            closeModal('bulkActionsModal');
            window.location.href = '?export=excel';
        }

        function notifySuppliers() {
            closeModal('bulkActionsModal');
            showNotification('Supplier notification feature coming soon!', 'info');
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

            const notification = document.createElement('div');
            notification.className = `floating-alert ${colors[type]} text-white px-6 py-3 rounded-xl shadow-2xl flex items-center space-x-3`;
            notification.innerHTML = `
                <i class="fas ${icons[type]} text-lg"></i>
                <span class="font-medium">${message}</span>
            `;
            document.body.appendChild(notification);

            setTimeout(() => notification.remove(), 3000);
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Auto-refresh data every 30 seconds
            setInterval(() => {
                console.log('Auto-refreshing expired medicines data...');
                // Here you would typically make an AJAX call to refresh data
            }, 30000);
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
                const modals = ['viewModal', 'returnModal', 'disposeModal', 'disposeAllModal', 'bulkActionsModal'];
                modals.forEach(modal => {
                    if (document.getElementById(modal).style.display === 'flex') {
                        closeModal(modal);
                    }
                });
            }

            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printExpiredReport();
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

            // Ctrl/Cmd + D for dispose all
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                showDisposeAllModal();
            }
        });
    </script>
</body>

</html>