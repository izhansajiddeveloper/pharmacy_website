<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$sale_id = intval($_GET['id']);
$pharmacist_id = $_SESSION['user_id'];

// Get sale details
$sale_query = "SELECT s.*, u.name AS pharmacist_name 
               FROM sales s
               LEFT JOIN users u ON s.pharmacist_id = u.id
               WHERE s.id = $sale_id AND s.pharmacist_id = $pharmacist_id";

$sale_result = mysqli_query($conn, $sale_query);
$sale = mysqli_fetch_assoc($sale_result);

if (!$sale) {
    header("Location: sales.php");
    exit;
}

// Get sale items
$items_query = "SELECT si.*, m.name AS medicine_name, m.generic_name, sb.batch_no
                FROM sale_items si
                LEFT JOIN medicines m ON si.medicine_id = m.id
                LEFT JOIN stock_batches sb ON si.batch_id = sb.id
                WHERE si.sale_id = $sale_id";

$items_result = mysqli_query($conn, $items_query);

// Store items in array for calculations
$items_array = [];
$item_count = 0;
$total_quantity = 0;
$subtotal = 0;
while ($item = mysqli_fetch_assoc($items_result)) {
    $item_count++;
    $total_quantity += $item['quantity'];
    $item_total = $item['quantity'] * $item['price'];
    $subtotal += $item_total;
    $items_array[] = $item;
}

// Reset pointer for later use
mysqli_data_seek($items_result, 0);

// Calculate net amount
$discount = isset($sale['discount']) ? $sale['discount'] : 0;
$net_amount = $sale['total_amount'] - $discount;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Sale - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --primary-yellow-dark: #d97706;
            --accent-purple: #8b5cf6;
            --accent-green: #10b981;
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
        }

        .gradient-purple {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.1);
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-section,
            .print-section * {
                visibility: visible;
            }

            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
            }
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
                            Sale <span class="gradient-text">Details</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-eye text-purple-500"></i>
                            <span>View sale transaction details</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-hashtag text-purple-500"></i>
                            <span class="font-medium">Invoice: <?php echo htmlspecialchars($sale['invoice_no']); ?></span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="sales.php"
                            class="px-6 py-3 border border-purple-200 text-gray-700 rounded-xl hover:bg-purple-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-purple-500"></i>
                            <span>Back to Sales</span>
                        </a>
                        <button onclick="printInvoice()"
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-print"></i>
                            <span>Print Invoice</span>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-green-50 to-green-25 border border-green-200">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-green-800">Success!</h3>
                            <p class="text-green-600">Sale created successfully. Invoice Number: <?php echo htmlspecialchars($sale['invoice_no']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sale Information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Left Column - Sale Info -->
                <div class="lg:col-span-2">
                    <div class="glass-card rounded-2xl p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-receipt text-purple-500"></i>
                            <span>Invoice Information</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Invoice Number</p>
                                    <p class="text-xl font-bold text-purple-600"><?php echo htmlspecialchars($sale['invoice_no']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Sale Date & Time</p>
                                    <p class="text-lg font-medium text-gray-800">
                                        <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Sale ID</p>
                                    <p class="font-medium text-gray-800">SALE-<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Processed By</p>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user-md text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($sale['pharmacist_name']); ?></p>
                                            <p class="text-sm text-gray-500">Pharmacist ID: <?php echo $sale['pharmacist_id']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sale Items -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-shopping-cart text-green-500"></i>
                            <span>Sale Items</span>
                        </h3>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-purple-50">
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">#</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Medicine Name</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Batch No</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Quantity</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Unit Price</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items_array as $index => $item): ?>
                                        <tr class="border-t border-purple-100 hover:bg-purple-50">
                                            <td class="px-4 py-3"><?php echo $index + 1; ?></td>
                                            <td class="px-4 py-3">
                                                <div>
                                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($item['medicine_name']); ?></span>
                                                    <?php if (!empty($item['generic_name'])): ?>
                                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($item['generic_name']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($item['batch_no']); ?></td>
                                            <td class="px-4 py-3"><?php echo $item['quantity']; ?></td>
                                            <td class="px-4 py-3">Rs <?php echo number_format($item['price'], 2); ?></td>
                                            <td class="px-4 py-3 font-semibold text-green-600">Rs <?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Summary & Actions -->
                <div class="space-y-6">
                    <!-- Payment Summary -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-calculator text-yellow-500"></i>
                            <span>Payment Summary</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="info-card rounded-xl p-4">
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-600">Items Count:</span>
                                    <span class="font-bold text-purple-600"><?php echo $item_count; ?></span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-600">Total Quantity:</span>
                                    <span class="font-bold text-green-600"><?php echo $total_quantity; ?> units</span>
                                </div>
                            </div>

                            <div class="info-card rounded-xl p-4">
                                <div class="flex justify-between mb-3">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-bold text-gray-800">Rs <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <?php if ($discount > 0): ?>
                                    <div class="flex justify-between mb-3">
                                        <span class="text-gray-600">Discount:</span>
                                        <span class="font-bold text-red-600">- Rs <?php echo number_format($discount, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="border-t border-gray-200 pt-3">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-gray-800">Total Amount:</span>
                                        <span class="text-xl font-bold text-green-600">Rs <?php echo number_format($net_amount, 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="info-card rounded-xl p-4 bg-gradient-to-r from-blue-50 to-blue-25">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-bold <?php echo $sale['payment_method'] === 'Cash' ? 'text-green-600' : 'text-blue-600'; ?>">
                                        <i class="fas <?php echo $sale['payment_method'] === 'Cash' ? 'fa-money-bill-wave' : 'fa-credit-card'; ?> mr-1"></i>
                                        <?php echo htmlspecialchars($sale['payment_method']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-bolt text-red-500"></i>
                            <span>Quick Actions</span>
                        </h3>
                        <div class="space-y-3">
                            <button onclick="printInvoice()"
                                class="w-full flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-print text-blue-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Print Invoice</span>
                                </div>
                                <i class="fas fa-chevron-right text-blue-400"></i>
                            </button>

                            <button onclick="exportToPDF()"
                                class="w-full flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-file-pdf text-red-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Export PDF</span>
                                </div>
                                <i class="fas fa-chevron-right text-red-400"></i>
                            </button>

                            <button onclick="downloadInvoice()"
                                class="w-full flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-download text-green-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Download Invoice</span>
                                </div>
                                <i class="fas fa-chevron-right text-green-400"></i>
                            </button>

                            <a href="delete_sale.php?id=<?php echo $sale_id; ?>"
                                class="w-full flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-trash-alt text-red-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Delete Sale</span>
                                </div>
                                <i class="fas fa-chevron-right text-red-400"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden Printable Invoice -->
    <div id="printable-invoice" style="display: none;">
        <!-- This will be populated by JavaScript -->
    </div>

    <script>
        // Get data from PHP
        const saleData = {
            invoiceNo: '<?php echo htmlspecialchars($sale['invoice_no']); ?>',
            saleId: 'SALE-<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?>',
            pharmacistName: '<?php echo htmlspecialchars($sale['pharmacist_name']); ?>',
            pharmacistId: '<?php echo $sale['pharmacist_id']; ?>',
            saleDate: '<?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?>',
            subtotal: <?php echo $subtotal; ?>,
            discount: <?php echo $discount; ?>,
            totalAmount: <?php echo $net_amount; ?>,
            paymentMethod: '<?php echo htmlspecialchars($sale['payment_method']); ?>'
        };

        const saleItems = <?php echo json_encode($items_array); ?>;

        // Function to generate invoice HTML
        function generateInvoiceHTML() {
            const itemsHTML = saleItems.map((item, index) => `
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">${index + 1}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                        <strong>${escapeHtml(item.medicine_name)}</strong>
                        ${item.generic_name ? `<br><small style="color: #666;">${escapeHtml(item.generic_name)}</small>` : ''}
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">${escapeHtml(item.batch_no)}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">${item.quantity}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">Rs  ${formatNumber(item.price)}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">Rs  ${formatNumber(item.quantity * item.price)}</td>
                </tr>
            `).join('');

            return `
                <div class="print-section" style="max-width: 800px; margin: 0 auto; padding: 30px; font-family: Arial, sans-serif;">
                    <!-- Store Header -->
                    <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #8b5cf6; padding-bottom: 20px;">
                        <h1 style="color: #8b5cf6; font-size: 28px; margin: 0 0 10px 0; font-weight: bold;">MediCare Pharma</h1>
                        <p style="color: #666; margin: 5px 0;">123 Health Street, Medical City</p>
                        <p style="color: #666; margin: 5px 0;">Phone: (123) 456-7890 | Email: info@medicarepharma.com</p>
                        <p style="color: #666; margin: 5px 0;">GSTIN: 27AABCU9603R1ZX</p>
                    </div>
                    
                    <!-- Invoice Title -->
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2 style="color: #333; font-size: 24px; margin: 0 0 20px 0; border-bottom: 1px solid #ddd; padding-bottom: 10px; font-weight: bold;">TAX INVOICE</h2>
                    </div>
                    
                    <!-- Invoice Details -->
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 300px; margin-bottom: 15px;">
                            <h3 style="color: #666; font-size: 14px; margin: 0 0 10px 0; font-weight: bold;">INVOICE DETAILS</h3>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Invoice No:</strong> ${saleData.invoiceNo}
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Date:</strong> ${saleData.saleDate}
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Sale ID:</strong> ${saleData.saleId}
                            </p>
                        </div>
                        <div style="flex: 1; min-width: 300px; text-align: right;">
                            <h3 style="color: #666; font-size: 14px; margin: 0 0 10px 0; font-weight: bold;">PROCESSED BY</h3>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>${saleData.pharmacistName}</strong>
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                Pharmacist ID: ${saleData.pharmacistId}
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Payment:</strong> ${saleData.paymentMethod}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Items Table -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; border: 1px solid #ddd;">
                        <thead>
                            <tr style="background: #8b5cf6; color: white;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold;">#</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold;">Medicine Name</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold;">Batch No</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold;">Quantity</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold;">Unit Price</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                    </table>
                    
                    <!-- Payment Summary -->
                    <div style="background: #f7f7f7; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                        <h3 style="color: #666; font-size: 16px; margin: 0 0 15px 0; font-weight: bold;">PAYMENT SUMMARY</h3>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Subtotal:</span>
                            <span style="font-weight: bold;">Rs  ${formatNumber(saleData.subtotal)}</span>
                        </div>
                        ${saleData.discount > 0 ? `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Discount:</span>
                            <span style="font-weight: bold; color: #ef4444;">- Rs  ${formatNumber(saleData.discount)}</span>
                        </div>
                        ` : ''}
                        <div style="display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                            <span style="font-size: 18px; font-weight: bold; color: #333;">Total Amount:</span>
                            <span style="font-size: 24px; font-weight: bold; color: #10b981;">Rs  ${formatNumber(saleData.totalAmount)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                            <span style="color: #666;">Payment Method:</span>
                            <span style="font-weight: bold; color: ${saleData.paymentMethod === 'Cash' ? '#10b981' : '#3b82f6'};">
                                ${saleData.paymentMethod}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="text-align: center; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
                        <p style="margin: 10px 0; font-size: 14px;">Thank you for shopping with MediCare Pharma!</p>
                        <p style="margin: 5px 0; font-size: 12px;">This is a computer-generated invoice. No signature required.</p>
                        <p style="margin: 5px 0; font-size: 12px;">For any queries, contact: support@medicarepharma.com | Phone: 1800-123-4567</p>
                    </div>
                </div>
            `;
        }

        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatNumber(num) {
            return parseFloat(num).toFixed(2);
        }

        // Print invoice function
        function printInvoice() {
            const invoiceHTML = generateInvoiceHTML();

            // Create a new window for printing
            const printWindow = window.open('', '_blank');

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Invoice - ${saleData.invoiceNo}</title>
                    <style>
                        @media print {
                            @page {
                                margin: 15mm;
                                size: A4;
                            }
                            body {
                                margin: 0;
                                padding: 0;
                                font-family: Arial, sans-serif;
                                background: white;
                            }
                            * {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                            .no-print {
                                display: none !important;
                            }
                        }
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            background: white;
                        }
                        .invoice-container {
                            max-width: 800px;
                            margin: 0 auto;
                        }
                        .print-btn {
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: #3b82f6;
                            color: white;
                            border: none;
                            padding: 10px 20px;
                            border-radius: 5px;
                            cursor: pointer;
                            font-size: 14px;
                            z-index: 1000;
                        }
                        .print-btn:hover {
                            background: #2563eb;
                        }
                    </style>
                </head>
                <body>
                    <button class="print-btn no-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                    ${invoiceHTML}
                    <script>
                        // Auto print after a short delay
                        setTimeout(() => {
                            window.print();
                            // Close window after print
                            setTimeout(() => {
                                if (!window.document.hasFocus()) {
                                    window.close();
                                }
                            }, 1000);
                        }, 1000);
                    <\/script>
                </body>
                </html>
            `);

            printWindow.document.close();
        }

        // Export to PDF function
        async function exportToPDF() {
            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                // Set up PDF
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();

                // Add header
                doc.setFontSize(20);
                doc.setTextColor(139, 92, 246);
                doc.text('MediCare Pharma', pageWidth / 2, 20, {
                    align: 'center'
                });

                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('123 Health Street, Medical City', pageWidth / 2, 28, {
                    align: 'center'
                });
                doc.text('Phone: (123) 456-7890 | Email: info@medicarepharma.com', pageWidth / 2, 33, {
                    align: 'center'
                });
                doc.text('GSTIN: 27AABCU9603R1ZX', pageWidth / 2, 38, {
                    align: 'center'
                });

                // Add invoice title
                doc.setFontSize(18);
                doc.setTextColor(0, 0, 0);
                doc.text('TAX INVOICE', pageWidth / 2, 50, {
                    align: 'center'
                });

                // Invoice details
                doc.setFontSize(10);
                doc.text(`Invoice No: ${saleData.invoiceNo}`, 20, 60);
                doc.text(`Date: ${saleData.saleDate}`, 20, 65);
                doc.text(`Sale ID: ${saleData.saleId}`, 20, 70);

                // Pharmacist info
                doc.text('Processed By:', pageWidth - 20, 60, {
                    align: 'right'
                });
                doc.text(saleData.pharmacistName, pageWidth - 20, 65, {
                    align: 'right'
                });
                doc.text(`ID: ${saleData.pharmacistId}`, pageWidth - 20, 70, {
                    align: 'right'
                });
                doc.text(`Payment: ${saleData.paymentMethod}`, pageWidth - 20, 75, {
                    align: 'right'
                });

                // Table data
                const tableData = saleItems.map((item, index) => [
                    index + 1,
                    item.medicine_name + (item.generic_name ? `\n(${item.generic_name})` : ''),
                    item.batch_no,
                    item.quantity,
                    `Rs  ${formatNumber(item.price)}`,
                    `Rs  ${formatNumber(item.quantity * item.price)}`
                ]);

                // Add table using autoTable plugin
                doc.autoTable({
                    startY: 85,
                    head: [
                        ['#', 'Medicine Name', 'Batch No', 'Qty', 'Unit Price', 'Total']
                    ],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [139, 92, 246],
                        textColor: [255, 255, 255],
                        fontSize: 10
                    },
                    bodyStyles: {
                        fontSize: 9
                    },
                    columnStyles: {
                        0: {
                            cellWidth: 10
                        },
                        1: {
                            cellWidth: 60
                        },
                        2: {
                            cellWidth: 30
                        },
                        3: {
                            cellWidth: 15
                        },
                        4: {
                            cellWidth: 25
                        },
                        5: {
                            cellWidth: 25
                        }
                    },
                    margin: {
                        left: 10,
                        right: 10
                    }
                });

                // Get the final Y position after table
                const finalY = doc.lastAutoTable.finalY + 10;

                // Add payment summary
                doc.setFontSize(11);
                doc.text('PAYMENT SUMMARY', 20, finalY);

                doc.text(`Subtotal: Rs  ${formatNumber(saleData.subtotal)}`, pageWidth - 20, finalY, {
                    align: 'right'
                });

                let yPos = finalY + 8;
                if (saleData.discount > 0) {
                    doc.text(`Discount: - Rs  ${formatNumber(saleData.discount)}`, pageWidth - 20, yPos, {
                        align: 'right'
                    });
                    yPos += 6;
                }

                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text(`Total Amount: Rs  ${formatNumber(saleData.totalAmount)}`, pageWidth - 20, yPos + 10, {
                    align: 'right'
                });

                // Add footer
                yPos = pageHeight - 30;
                doc.setFontSize(9);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(100, 100, 100);
                doc.text('Thank you for shopping with MediCare Pharma!', pageWidth / 2, yPos, {
                    align: 'center'
                });
                doc.text('This is a computer-generated invoice. No signature required.', pageWidth / 2, yPos + 5, {
                    align: 'center'
                });
                doc.text('For queries: support@medicarepharma.com | 1800-123-4567', pageWidth / 2, yPos + 10, {
                    align: 'center'
                });

                // Save the PDF
                const fileName = `Invoice_${saleData.invoiceNo}_${new Date().toISOString().split('T')[0]}.pdf`;
                doc.save(fileName);

                showNotification('PDF exported successfully!', 'success');

            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF. Please try again.', 'error');
            }
        }

        // Download invoice as text file
        function downloadInvoice() {
            const invoiceText = `
INVOICE: ${saleData.invoiceNo}
DATE: ${saleData.saleDate}
SALE ID: ${saleData.saleId}
PHARMACIST: ${saleData.pharmacistName} (ID: ${saleData.pharmacistId})

ITEMS:
${saleItems.map((item, index) => `${index + 1}. ${item.medicine_name} (Batch: ${item.batch_no}) - Qty: ${item.quantity} @ Rs  ${item.price} = Rs  ${item.quantity * item.price}`).join('\n')}

SUMMARY:
Subtotal: Rs  ${saleData.subtotal}
Discount: Rs  ${saleData.discount}
Total Amount: Rs  ${saleData.totalAmount}
Payment Method: ${saleData.paymentMethod}

Thank you for shopping with MediCare Pharma!
            `.trim();

            const blob = new Blob([invoiceText], {
                type: 'text/plain'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Invoice_${saleData.invoiceNo}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showNotification('Invoice downloaded as text file', 'success');
        }

        // Show notification function
        function showNotification(message, type = 'success') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };

            const notification = document.createElement('div');
            notification.className = `fixed top-6 right-6 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-2xl transform translate-x-full transition-transform duration-300 z-50`;
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} text-lg"></i>
                    <span class="font-medium">${message}</span>
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => notification.style.transform = 'translateX(0)', 10);

            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printInvoice();
            }

            // Ctrl/Cmd + E to export PDF
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportToPDF();
            }

            // Escape to go back
            if (e.key === 'Escape') {
                window.location.href = 'sales.php';
            }
        });
    </script>
</body>

</html>