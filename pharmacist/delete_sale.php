<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$sale_id = intval($_GET['id']);
$pharmacist_id = $_SESSION['user_id'];

// Check if sale exists and belongs to this pharmacist
$sale_query = "SELECT * FROM sales WHERE id = $sale_id AND pharmacist_id = $pharmacist_id";
$sale_result = mysqli_query($conn, $sale_query);
$sale = mysqli_fetch_assoc($sale_result);

if (!$sale) {
    header("Location: sales.php");
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);

    try {
        // Restore stock quantities first
        $restore_query = "UPDATE stock_batches sb
                          JOIN sale_items si ON sb.id = si.batch_id
                          SET sb.quantity = sb.quantity + si.quantity
                          WHERE si.sale_id = $sale_id";

        if (!mysqli_query($conn, $restore_query)) {
            throw new Exception("Error restoring stock: " . mysqli_error($conn));
        }

        // Get invoice id
        $invoice_query = "SELECT id FROM invoices WHERE sale_id = $sale_id";
        $invoice_result = mysqli_query($conn, $invoice_query);
        $invoice = mysqli_fetch_assoc($invoice_result);
        $invoice_id = $invoice['id'];

        // Delete invoice items
        $delete_invoice_items = "DELETE FROM invoice_items WHERE invoice_id = $invoice_id";
        if (!mysqli_query($conn, $delete_invoice_items)) {
            throw new Exception("Error deleting invoice items: " . mysqli_error($conn));
        }

        // Delete invoice
        $delete_invoice = "DELETE FROM invoices WHERE sale_id = $sale_id";
        if (!mysqli_query($conn, $delete_invoice)) {
            throw new Exception("Error deleting invoice: " . mysqli_error($conn));
        }

        // Delete sale items
        $delete_items = "DELETE FROM sale_items WHERE sale_id = $sale_id";
        if (!mysqli_query($conn, $delete_items)) {
            throw new Exception("Error deleting sale items: " . mysqli_error($conn));
        }

        // Delete sale
        $delete_sale = "DELETE FROM sales WHERE id = $sale_id";
        if (!mysqli_query($conn, $delete_sale)) {
            throw new Exception("Error deleting sale: " . mysqli_error($conn));
        }

        mysqli_commit($conn);

        header("Location: sales.php?deleted=1");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Sale - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --primary-yellow-dark: #d97706;
            --accent-red: #ef4444;
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
        }

        .gradient-red {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <main class="flex-1 p-6">
            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Delete <span class="gradient-text">Sale</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-trash-alt text-red-500"></i>
                            <span>Remove sale transaction</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0">
                        <a href="sales.php"
                            class="px-6 py-3 border border-red-200 text-gray-700 rounded-xl hover:bg-red-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-red-500"></i>
                            <span>Back to Sales</span>
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-red-50 to-red-25 border border-red-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-red-800">Error!</h3>
                            <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sale Information -->
            <div class="glass-card rounded-2xl p-6 mb-6">
                <div class="text-center mb-8">
                    <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <i class="fas fa-exclamation-triangle text-red-600 text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirm Deletion</h2>
                    <p class="text-gray-600 mb-6">
                        You are about to delete the following sale. This action will restore stock quantities and cannot be undone.
                    </p>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Invoice Number</p>
                            <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($sale['invoice_no']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Sale Date</p>
                            <p class="text-lg font-medium text-gray-800"><?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Amount</p>
                            <p class="text-xl font-bold text-red-600">Rs <?php echo number_format($sale['total_amount'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Payment Method</p>
                            <p class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($sale['payment_method']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Get sale items count -->
                <?php
                $items_query = "SELECT COUNT(*) as items_count, SUM(quantity) as total_quantity 
                                FROM sale_items WHERE sale_id = $sale_id";
                $items_result = mysqli_query($conn, $items_query);
                $items = mysqli_fetch_assoc($items_result);
                ?>

                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-6">
                    <h3 class="text-lg font-semibold text-yellow-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-exclamation-circle text-yellow-600"></i>
                        <span>Important Notes</span>
                    </h3>
                    <ul class="space-y-3 text-yellow-700">
                        <li class="flex items-start space-x-3">
                            <i class="fas fa-check mt-1 text-sm"></i>
                            <span><strong><?php echo $items['items_count']; ?> items</strong> (<?php echo $items['total_quantity']; ?> units) will be removed from this sale</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <i class="fas fa-check mt-1 text-sm"></i>
                            <span>Stock quantities will be restored for all medicines</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <i class="fas fa-check mt-1 text-sm"></i>
                            <span>The invoice and all related records will be permanently deleted</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <i class="fas fa-exclamation-triangle mt-1 text-sm"></i>
                            <span><strong>This action cannot be undone.</strong> Please verify this is correct before proceeding.</span>
                        </li>
                    </ul>
                </div>

                <!-- Confirmation Form -->
                <form method="POST">
                    <div class="flex space-x-4 justify-center">
                        <a href="sales.php"
                            class="px-8 py-4 border border-red-200 text-gray-700 rounded-xl hover:bg-red-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>

                        <button type="submit"
                            class="gradient-red text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-trash-alt"></i>
                            <span>Confirm Delete</span>
                            <i class="fas fa-arrow-right text-red-100 text-sm"></i>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to cancel
            if (e.key === 'Escape') {
                window.location.href = 'sales.php';
            }
        });
    </script>
</body>

</html>