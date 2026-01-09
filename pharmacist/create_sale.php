<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$pharmacist_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Generate invoice number
function generateUniqueInvoiceNo($conn)
{
    $prefix = "INV-";
    $yearMonth = date('Ym'); // Format: 202601
    $seqKey = $prefix . $yearMonth;

    $query = "INSERT INTO invoice_sequences (seq_key, last_value) 
              VALUES ('$seqKey', 1) 
              ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)";

    if (!mysqli_query($conn, $query)) {
        throw new Exception("Sequence error: " . mysqli_error($conn));
    }

    $newID = mysqli_insert_id($conn);
    return $seqKey . str_pad($newID, 4, '0', STR_PAD_LEFT);
}

// Calculate discount percentage based on total amount
function calculateDiscount($total_amount)
{
    $discount_rules = [
        ['min' => 5000, 'discount' => 10],  // 10% discount for orders above 5000
        ['min' => 3000, 'discount' => 7],   // 7% discount for orders above 3000
        ['min' => 1000, 'discount' => 5],   // 5% discount for orders above 1000
        ['min' => 500, 'discount' => 3],    // 3% discount for orders above 500
        ['min' => 0, 'discount' => 0]       // No discount
    ];

    foreach ($discount_rules as $rule) {
        if ($total_amount >= $rule['min']) {
            $discount_amount = ($total_amount * $rule['discount']) / 100;
            return [
                'percentage' => $rule['discount'],
                'amount' => round($discount_amount, 2)
            ];
        }
    }

    return ['percentage' => 0, 'amount' => 0];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);

    try {
        $invoice_no = generateUniqueInvoiceNo($conn);
        $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $total_amount = 0;

        // Process sale items first to calculate total
        $medicine_ids = $_POST['medicine_id'] ?? [];
        $batch_ids = $_POST['batch_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['price'] ?? [];

        // Calculate subtotal
        for ($i = 0; $i < count($medicine_ids); $i++) {
            $quantity = intval($quantities[$i]);
            $price = floatval($prices[$i]);

            if ($quantity > 0 && $price > 0) {
                $total_amount += ($quantity * $price);
            }
        }

        // Calculate auto discount based on total
        $discount_info = calculateDiscount($total_amount);
        $discount_amount = $discount_info['amount'];
        $discount_percentage = $discount_info['percentage'];

        // Apply discount
        $final_total = $total_amount - $discount_amount;

        // Insert sale with auto-calculated discount
        $sale_query = "INSERT INTO sales (invoice_no, pharmacist_id, total_amount, discount, payment_method, sale_date) 
                       VALUES ('$invoice_no', $pharmacist_id, $final_total, $discount_amount, '$payment_method', NOW())";

        if (!mysqli_query($conn, $sale_query)) {
            throw new Exception("Error creating sale: " . mysqli_error($conn));
        }

        $sale_id = mysqli_insert_id($conn);

        // Process sale items with batch stock validation
        for ($i = 0; $i < count($medicine_ids); $i++) {
            $medicine_id = intval($medicine_ids[$i]);
            $batch_id = intval($batch_ids[$i]);
            $quantity = intval($quantities[$i]);
            $price = floatval($prices[$i]);

            if ($medicine_id > 0 && $batch_id > 0 && $quantity > 0 && $price > 0) {
                // Check if the selected batch has enough stock and is valid
                $batch_check = "SELECT sb.quantity, sb.selling_price, m.name, sb.batch_no
                               FROM stock_batches sb
                               JOIN medicines m ON sb.medicine_id = m.id
                               WHERE sb.id = $batch_id 
                               AND sb.medicine_id = $medicine_id
                               AND sb.expiry_date > CURDATE() 
                               AND sb.is_expired = 0
                               AND sb.quantity >= $quantity";

                $batch_result = mysqli_query($conn, $batch_check);

                if ($batch_result && mysqli_num_rows($batch_result) > 0) {
                    $batch = mysqli_fetch_assoc($batch_result); // FIXED: Changed $batch_batch to $batch

                    // Calculate item discount (proportionate to total discount)
                    $item_subtotal = $quantity * $price;
                    $item_discount = ($item_subtotal / $total_amount) * $discount_amount;
                    $item_discount = round($item_discount, 2);

                    // Insert sale item with batch_id and discount
                    $item_query = "INSERT INTO sale_items (sale_id, medicine_id, batch_id, quantity, price, discount) 
                                   VALUES ($sale_id, $medicine_id, $batch_id, $quantity, $price, $item_discount)";

                    if (!mysqli_query($conn, $item_query)) {
                        throw new Exception("Error adding sale item: " . mysqli_error($conn));
                    }

                    // Update specific batch stock
                    $update_stock = "UPDATE stock_batches SET quantity = quantity - $quantity WHERE id = $batch_id";
                    if (!mysqli_query($conn, $update_stock)) {
                        throw new Exception("Error updating stock for batch ID: $batch_id");
                    }
                } else {
                    throw new Exception("Selected batch is either expired or doesn't have enough stock for medicine ID: $medicine_id");
                }
            }
        }

        // Create invoice
        $invoice_query = "INSERT INTO invoices (invoice_no, sale_id, pharmacist_id, customer_name, total_amount, discount, payment_method) 
                          VALUES ('$invoice_no', $sale_id, $pharmacist_id, '$customer_name', $final_total, $discount_amount, '$payment_method')";

        if (!mysqli_query($conn, $invoice_query)) {
            throw new Exception("Error creating invoice: " . mysqli_error($conn));
        }

        $invoice_id = mysqli_insert_id($conn);

        // Insert invoice items with discount distribution
        $invoice_items_query = "INSERT INTO invoice_items (invoice_id, medicine_id, batch_id, quantity, price, discount, total_price)
                                SELECT $invoice_id, si.medicine_id, si.batch_id, si.quantity, si.price, 
                                       si.discount, (si.quantity * si.price - si.discount) as total_price
                                FROM sale_items si
                                WHERE si.sale_id = $sale_id";

        if (!mysqli_query($conn, $invoice_items_query)) {
            throw new Exception("Error creating invoice items: " . mysqli_error($conn));
        }

        // After successful sale creation - CREATE AUTO PAYMENT
        require_once "ajax/auto_payment_generator.php";
        if (createAutoPaymentForSale($sale_id, $conn)) {
            // Payment created successfully
            $payment_created = true;
        }

        // Commit transaction
        mysqli_commit($conn);

        // Redirect to view sale with success message
        header("Location: view_sale.php?id=$sale_id&success=1&discount_percentage=$discount_percentage&payment_created=" . ($payment_created ?? 0));
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// Fetch medicines with valid batch-wise stock
$medicines_query = "SELECT 
                        m.id,
                        m.name,
                        GROUP_CONCAT(
                            DISTINCT CONCAT(
                                sb.id, '|', 
                                sb.batch_no, '|',
                                sb.quantity, '|',
                                sb.selling_price, '|',
                                DATE_FORMAT(sb.expiry_date, '%d/%m/%Y')
                            ) SEPARATOR ';'
                        ) as batch_data
                    FROM medicines m
                    JOIN stock_batches sb ON m.id = sb.medicine_id
                    WHERE sb.expiry_date > CURDATE() 
                        AND sb.is_expired = 0
                        AND sb.quantity > 0
                    GROUP BY m.id
                    ORDER BY m.name";

$medicines_result = mysqli_query($conn, $medicines_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sale - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --primary-yellow-dark: #d97706;
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --accent-purple: #8b5cf6;
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

        .gradient-green {
            background: linear-gradient(135deg, var(--accent-green), #059669);
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-input {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(209, 213, 219, 0.5);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            background: white;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
        }

        .item-row {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .item-row:hover {
            background-color: rgba(16, 185, 129, 0.05);
            border-left-color: var(--accent-green);
        }

        .batch-info {
            font-size: 12px;
            color: #64748b;
        }

        .stock-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .stock-high {
            background-color: #10b981;
        }

        .stock-medium {
            background-color: #f59e0b;
        }

        .stock-low {
            background-color: #ef4444;
        }

        .expiry-near {
            color: #ea580c;
            font-weight: 600;
        }

        .custom-select {
            position: relative;
        }

        .custom-select .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 50;
            display: none;
        }

        .custom-select.open .dropdown-menu {
            display: block;
        }

        .batch-dropdown-item {
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .batch-dropdown-item:hover {
            background-color: #f1f5f9;
        }

        .batch-dropdown-item.selected {
            background-color: #e0f2fe;
        }

        .batch-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .batch-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
        }

        .discount-badge {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .auto-discount {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .payment-badge {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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
                            Create <span class="gradient-text">New Sale</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-cash-register text-green-500"></i>
                            <span>Process sale with batch-wise stock management</span>
                            <span class="discount-badge">
                                <i class="fas fa-percentage mr-1"></i>
                                Auto Discount System
                            </span>
                            <span class="payment-badge">
                                <i class="fas fa-robot mr-1"></i>
                                Auto Payment Generation
                            </span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="sales.php"
                            class="px-6 py-3 border border-green-200 text-gray-700 rounded-xl hover:bg-green-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-green-500"></i>
                            <span>Back to Sales</span>
                        </a>
                        <a href="view_stock.php"
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-boxes"></i>
                            <span>View Stock</span>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
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

            <form method="POST" id="saleForm" class="space-y-6">
                <!-- Customer Information -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-user text-blue-500"></i>
                        <span>Customer Information</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Customer Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="customer_name" required
                                placeholder="Enter customer name"
                                class="w-full form-input px-4 py-3 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select name="payment_method" required
                                class="w-full form-input px-4 py-3 rounded-lg">
                                <option value="Cash">Cash</option>
                                <option value="Online">Online</option>
                                <option value="Card">Card</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sale Items -->
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-shopping-cart text-purple-500"></i>
                            <span>Sale Items (Batch-wise)</span>
                        </h3>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            Select from valid batches only
                        </div>
                    </div>

                    <div id="itemsContainer">
                        <!-- Items will be added here dynamically -->
                    </div>

                    <div class="mt-4 flex space-x-3">
                        <button type="button" onclick="addItemRow()"
                            class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Item</span>
                        </button>
                        <button type="button" onclick="clearAllItems()"
                            class="px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition flex items-center space-x-2">
                            <i class="fas fa-trash"></i>
                            <span>Clear All</span>
                        </button>
                    </div>
                </div>

                <!-- Summary -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-calculator text-yellow-500"></i>
                        <span>Summary</span>
                    </h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Items Count
                                </label>
                                <div class="text-2xl font-bold text-purple-600" id="itemsCount">0</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Subtotal
                                </label>
                                <div id="subtotal" class="text-2xl font-bold text-gray-800">Rs 0.00</div>
                            </div>
                        </div>

                        <!-- Discount Information -->
                        <div class="bg-gradient-to-r from-yellow-50 to-yellow-25 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-percentage text-yellow-600"></i>
                                    <span class="font-medium text-gray-800">Auto Discount</span>
                                    <span class="auto-discount">
                                        <i class="fas fa-bolt mr-1"></i>
                                        Automatic Calculation
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600" id="discountRules">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Based on order value
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Discount Percentage
                                    </label>
                                    <div id="discountPercentage" class="text-lg font-bold text-yellow-600">0%</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Discount Amount
                                    </label>
                                    <div id="discountAmount" class="text-lg font-bold text-red-600">Rs 0.00</div>
                                    <input type="hidden" name="discount" id="discountValue" value="0">
                                </div>
                            </div>

                            <div class="mt-2 text-xs text-gray-500">
                                <i class="fas fa-lightbulb mr-1"></i>
                                Discount rules:
                                <span id="discountRulesText">Above Rs 5000: 10%, Rs 3000-4999: 7%, Rs 1000-2999: 5%, Rs 500-999: 3%</span>
                            </div>
                        </div>

                        <!-- Auto Payment Info -->
                        <div class="bg-gradient-to-r from-blue-50 to-blue-25 rounded-xl p-4">
                            <div class="flex items-center space-x-2 mb-2">
                                <i class="fas fa-credit-card text-blue-600"></i>
                                <span class="font-medium text-gray-800">Payment Automation</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                Payment record will be automatically created in the payments system
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Auto-generated payments cannot be edited or deleted
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-bold text-gray-800">Total Amount:</span>
                                <span id="totalAmount" class="text-3xl font-bold text-green-600">Rs 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button type="submit" id="submitBtn"
                        class="gradient-green text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                        <i class="fas fa-check"></i>
                        <span>Complete Sale</span>
                        <i class="fas fa-arrow-right text-green-100 text-sm"></i>
                    </button>

                    <button type="button" onclick="window.location.href='sales.php'"
                        class="px-8 py-4 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2 shadow-sm">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Available medicines with batch data from PHP
        const medicines = [
            <?php while ($medicine = mysqli_fetch_assoc($medicines_result)): ?> {
                    id: <?php echo $medicine['id']; ?>,
                    name: '<?php echo addslashes($medicine['name']); ?>',
                    generic: '<?php echo addslashes($medicine['generic_name'] ?: ''); ?>',
                    batches: '<?php echo $medicine['batch_data'] ? addslashes($medicine['batch_data']) : ''; ?>'
                },
            <?php endwhile; ?>
        ];

        // Discount rules (must match PHP function)
        const discountRules = [{
                min: 5000,
                discount: 10,
                label: 'Above Rs 5000: 10%'
            },
            {
                min: 3000,
                discount: 7,
                label: 'Rs 3000-4999: 7%'
            },
            {
                min: 1000,
                discount: 5,
                label: 'Rs 1000-2999: 5%'
            },
            {
                min: 500,
                discount: 3,
                label: 'Rs 500-999: 3%'
            },
            {
                min: 0,
                discount: 0,
                label: 'Below Rs 500: 0%'
            }
        ];

        let itemCount = 0;

        // Function to parse batch data
        function parseBatchData(batchString) {
            if (!batchString) return [];

            const batches = batchString.split(';');
            return batches.map(batch => {
                const [id, batchNo, quantity, price, expiry] = batch.split('|');
                return {
                    id: parseInt(id),
                    batchNo: batchNo,
                    quantity: parseInt(quantity),
                    price: parseFloat(price),
                    expiry: expiry,
                    isNearExpiry: checkIfNearExpiry(expiry)
                };
            });
        }

        // Check if expiry is within 30 days
        function checkIfNearExpiry(expiryDateStr) {
            const parts = expiryDateStr.split('/');
            if (parts.length !== 3) return false;

            const expiryDate = new Date(parts[2], parts[1] - 1, parts[0]);
            const today = new Date();
            const diffTime = expiryDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            return diffDays <= 30;
        }

        // Get stock indicator class
        function getStockClass(quantity) {
            if (quantity <= 10) return 'stock-low';
            if (quantity <= 50) return 'stock-medium';
            return 'stock-high';
        }

        // Calculate discount based on subtotal
        function calculateDiscount(subtotal) {
            for (const rule of discountRules) {
                if (subtotal >= rule.min) {
                    const discountAmount = (subtotal * rule.discount) / 100;
                    return {
                        percentage: rule.discount,
                        amount: Math.round(discountAmount * 100) / 100,
                        rule: rule.label
                    };
                }
            }
            return {
                percentage: 0,
                amount: 0,
                rule: 'No discount'
            };
        }

        // Update discount display
        function updateDiscount(subtotal) {
            const discountInfo = calculateDiscount(subtotal);

            document.getElementById('discountPercentage').textContent = `${discountInfo.percentage}%`;
            document.getElementById('discountAmount').textContent = `Rs ${discountInfo.amount.toFixed(2)}`;
            document.getElementById('discountValue').value = discountInfo.amount;
            document.getElementById('discountRulesText').textContent = discountInfo.rule;

            return discountInfo;
        }

        function addItemRow(medicineId = '', batchId = '', quantity = 1) {
            const container = document.getElementById('itemsContainer');
            const itemId = itemCount++;

            const row = document.createElement('div');
            row.className = 'item-row bg-gradient-to-r from-gray-50 to-white p-5 rounded-xl mb-4 border border-gray-100';
            row.innerHTML = `
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    <!-- Medicine Selection -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Medicine <span class="text-red-500">*</span>
                        </label>
                        <div class="custom-select">
                            <input type="hidden" name="medicine_id[]" class="medicine-id" value="${medicineId}">
                            <input type="text" 
                                   class="medicine-search w-full form-input px-4 py-3 rounded-lg"
                                   placeholder="Search medicine..."
                                   autocomplete="off"
                                   data-item="${itemId}"
                                   onfocus="showMedicineDropdown(${itemId})">
                            <div class="dropdown-menu" id="medicineDropdown${itemId}"></div>
                        </div>
                    </div>

                    <!-- Batch Selection -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Batch <span class="text-red-500">*</span>
                        </label>
                        <div class="custom-select">
                            <input type="hidden" name="batch_id[]" class="batch-id" value="${batchId}">
                            <input type="text" 
                                   class="batch-search w-full form-input px-4 py-3 rounded-lg"
                                   placeholder="Select batch..."
                                   readonly
                                   data-item="${itemId}"
                                   onfocus="showBatchDropdown(${itemId})">
                            <div class="dropdown-menu" id="batchDropdown${itemId}"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1" id="batchInfo${itemId}"></div>
                    </div>

                    <!-- Quantity & Price -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                            <input type="number" name="quantity[]" min="1" value="${quantity}"
                                   class="quantity-input w-full form-input px-3 py-2 rounded-lg text-center"
                                   onchange="updateItemTotal(${itemId})" oninput="updateItemTotal(${itemId})" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                            <input type="number" name="price[]" step="0.01" min="0.01" value="0"
                                   class="price-input w-full form-input px-3 py-2 rounded-lg"
                                   onchange="updateTotal()" oninput="updateTotal()" required>
                        </div>
                    </div>
                </div>

                <!-- Item Summary -->
                <div class="mt-4 flex justify-between items-center pt-3 border-t border-gray-200">
                    <div class="text-sm">
                        <span class="text-gray-600">Item Total: </span>
                        <span class="item-total font-bold text-green-600" id="itemTotal${itemId}">Rs 0.00</span>
                    </div>
                    <button type="button" onclick="removeItemRow(${itemId})"
                            class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition text-sm">
                        <i class="fas fa-trash mr-1"></i> Remove
                    </button>
                </div>
            `;

            container.appendChild(row);

            // Initialize medicine search functionality
            const medicineSearch = row.querySelector('.medicine-search');
            medicineSearch.addEventListener('input', function() {
                filterMedicineDropdown(itemId, this.value);
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!row.contains(e.target)) {
                    closeAllDropdowns();
                }
            });

            updateItemsCount();
            updateTotal(); // Update total with new item
        }

        function removeItemRow(itemId) {
            const row = document.querySelector(`[onclick="removeItemRow(${itemId})"]`).closest('.item-row');
            row.remove();
            updateTotal();
            updateItemsCount();
        }

        function clearAllItems() {
            if (confirm('Are you sure you want to clear all items?')) {
                document.getElementById('itemsContainer').innerHTML = '';
                itemCount = 0;
                updateTotal();
                updateItemsCount();
                addItemRow(); // Add one empty row
            }
        }

        function showMedicineDropdown(itemId) {
            closeAllDropdowns();
            const dropdown = document.getElementById(`medicineDropdown${itemId}`);
            const row = dropdown.closest('.item-row');
            row.querySelector('.custom-select').classList.add('open');

            let html = '';
            medicines.forEach(medicine => {
                html += `
                    <div class="batch-dropdown-item" onclick="selectMedicine(${itemId}, ${medicine.id})">
                        <div class="font-medium">${medicine.name}</div>
                        ${medicine.generic ? `<div class="text-xs text-gray-500">${medicine.generic}</div>` : ''}
                    </div>
                `;
            });

            dropdown.innerHTML = html || '<div class="p-4 text-gray-500 text-center">No medicines found</div>';
        }

        function filterMedicineDropdown(itemId, searchTerm) {
            const dropdown = document.getElementById(`medicineDropdown${itemId}`);
            const term = searchTerm.toLowerCase();

            let html = '';
            const filtered = medicines.filter(med =>
                med.name.toLowerCase().includes(term) ||
                (med.generic && med.generic.toLowerCase().includes(term))
            );

            filtered.forEach(medicine => {
                html += `
                    <div class="batch-dropdown-item" onclick="selectMedicine(${itemId}, ${medicine.id})">
                        <div class="font-medium">${medicine.name}</div>
                        ${medicine.generic ? `<div class="text-xs text-gray-500">${medicine.generic}</div>` : ''}
                    </div>
                `;
            });

            dropdown.innerHTML = html || '<div class="p-4 text-gray-500 text-center">No medicines found</div>';
        }

        function selectMedicine(itemId, medicineId) {
            const medicine = medicines.find(m => m.id === medicineId);
            if (!medicine) return;

            const row = document.querySelector(`[data-item="${itemId}"]`).closest('.item-row');
            row.querySelector('.medicine-id').value = medicineId;
            row.querySelector('.medicine-search').value = medicine.name + (medicine.generic ? ` (${medicine.generic})` : '');

            // Update batch selection
            const batchSearch = row.querySelector('.batch-search');
            const batchIdInput = row.querySelector('.batch-id');
            const batchInfo = document.getElementById(`batchInfo${itemId}`);

            batchSearch.value = '';
            batchIdInput.value = '';
            batchInfo.innerHTML = '';

            // Show batches for this medicine
            showBatchDropdown(itemId);
        }

        function showBatchDropdown(itemId) {
            const row = document.querySelector(`[data-item="${itemId}"]`).closest('.item-row');
            const medicineId = parseInt(row.querySelector('.medicine-id').value);

            if (!medicineId) {
                alert('Please select a medicine first');
                return;
            }

            closeAllDropdowns();
            const dropdown = document.getElementById(`batchDropdown${itemId}`);
            const customSelect = dropdown.closest('.custom-select');
            customSelect.classList.add('open');

            const medicine = medicines.find(m => m.id === medicineId);
            if (!medicine || !medicine.batches) {
                dropdown.innerHTML = '<div class="p-4 text-gray-500 text-center">No batches available</div>';
                return;
            }

            const batches = parseBatchData(medicine.batches);
            if (batches.length === 0) {
                dropdown.innerHTML = '<div class="p-4 text-gray-500 text-center">No batches available</div>';
                return;
            }

            let html = '';
            batches.forEach(batch => {
                const stockClass = getStockClass(batch.quantity);
                const expiryClass = batch.isNearExpiry ? 'expiry-near' : 'text-gray-500';

                html += `
                    <div class="batch-dropdown-item" onclick="selectBatch(${itemId}, ${batch.id})">
                        <div class="batch-details">
                            <div class="font-medium">Batch: ${batch.batchNo}</div>
                            <div class="batch-meta">
                                <span class="flex items-center">
                                    <span class="stock-indicator ${stockClass}"></span>
                                    Stock: ${batch.quantity}
                                </span>
                                <span>Price: Rs ${batch.price.toFixed(2)}</span>
                                <span class="${expiryClass}">Exp: ${batch.expiry}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            dropdown.innerHTML = html;
        }

        function selectBatch(itemId, batchId) {
            const row = document.querySelector(`[data-item="${itemId}"]`).closest('.item-row');
            const medicineId = parseInt(row.querySelector('.medicine-id').value);
            const medicine = medicines.find(m => m.id === medicineId);

            if (!medicine || !medicine.batches) return;

            const batches = parseBatchData(medicine.batches);
            const batch = batches.find(b => b.id === batchId);
            if (!batch) return;

            // Update batch input
            row.querySelector('.batch-id').value = batchId;
            const batchSearch = row.querySelector('.batch-search');
            batchSearch.value = `Batch ${batch.batchNo}`;

            // Update price input
            const priceInput = row.querySelector('.price-input');
            priceInput.value = batch.price.toFixed(2);

            // Update batch info
            const batchInfo = document.getElementById(`batchInfo${itemId}`);
            const stockClass = getStockClass(batch.quantity);
            const expiryClass = batch.isNearExpiry ? 'expiry-near' : '';

            batchInfo.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="flex items-center">
                        <span class="stock-indicator ${stockClass}"></span>
                        Available: ${batch.quantity} units
                    </span>
                    <span class="${expiryClass}">Expires: ${batch.expiry}</span>
                </div>
            `;

            // Set max quantity
            const quantityInput = row.querySelector('.quantity-input');
            quantityInput.max = batch.quantity;
            quantityInput.value = Math.min(parseInt(quantityInput.value) || 1, batch.quantity);

            // Close dropdown
            closeAllDropdowns();

            // Update totals
            updateItemTotal(itemId);
        }

        function updateItemTotal(itemId) {
            const row = document.querySelector(`[onclick="removeItemRow(${itemId})"]`).closest('.item-row');
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const itemTotal = quantity * price;

            // Update item total display
            const itemTotalSpan = document.getElementById(`itemTotal${itemId}`);
            if (itemTotalSpan) {
                itemTotalSpan.textContent = 'Rs ' + itemTotal.toFixed(2);
            }

            updateTotal();
        }

        function updateTotal() {
            let subtotal = 0;
            let items = 0;

            // Calculate subtotal from all items
            document.querySelectorAll('.item-row').forEach(row => {
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const itemTotal = quantity * price;

                if (quantity > 0 && price > 0) {
                    subtotal += itemTotal;
                    items++;
                }
            });

            // Calculate discount
            const discountInfo = updateDiscount(subtotal);
            const discountAmount = discountInfo.amount;

            // Calculate total
            const total = Math.max(0, subtotal - discountAmount);

            // Update display
            document.getElementById('subtotal').textContent = 'Rs ' + subtotal.toFixed(2);
            document.getElementById('totalAmount').textContent = 'Rs ' + total.toFixed(2);
            document.getElementById('itemsCount').textContent = items;
        }

        function updateItemsCount() {
            const items = document.querySelectorAll('.item-row').length;
            document.getElementById('itemsCount').textContent = items;
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.custom-select').forEach(el => {
                el.classList.remove('open');
            });
        }

        // Initialize with one empty item row
        document.addEventListener('DOMContentLoaded', function() {
            addItemRow();
            updateTotal();

            // Form validation
            document.getElementById('saleForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const items = document.querySelectorAll('.item-row');
                if (items.length === 0) {
                    alert('Please add at least one item to the sale.');
                    return;
                }

                let hasValidItem = false;
                let errorMessages = [];

                items.forEach((row, index) => {
                    const medicineId = row.querySelector('.medicine-id').value;
                    const batchId = row.querySelector('.batch-id').value;
                    const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                    const price = parseFloat(row.querySelector('.price-input').value) || 0;
                    const maxQuantity = parseInt(row.querySelector('.quantity-input').max) || 0;

                    if (!medicineId || !batchId) {
                        errorMessages.push(`Item ${index + 1}: Please select a medicine and batch.`);
                    } else if (quantity <= 0) {
                        errorMessages.push(`Item ${index + 1}: Quantity must be greater than 0.`);
                    } else if (price <= 0) {
                        errorMessages.push(`Item ${index + 1}: Price must be greater than 0.`);
                    } else if (quantity > maxQuantity) {
                        errorMessages.push(`Item ${index + 1}: Quantity exceeds available stock (${maxQuantity} units).`);
                    } else {
                        hasValidItem = true;
                    }
                });

                if (errorMessages.length > 0) {
                    alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                    return;
                }

                if (!hasValidItem) {
                    alert('Please add at least one valid item with quantity and price.');
                    return;
                }

                // Show loading
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;

                // Show discount confirmation
                const discountAmount = parseFloat(document.getElementById('discountAmount').textContent.replace('Rs ', ''));
                const discountPercentage = document.getElementById('discountPercentage').textContent;

                if (discountAmount > 0) {
                    if (!confirm(`Discount Applied: ${discountPercentage} (Rs ${discountAmount.toFixed(2)})\n\nProceed with sale?`)) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        return;
                    }
                }

                // Submit form
                this.submit();
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }

            // Escape to cancel
            if (e.key === 'Escape') {
                window.location.href = 'sales.php';
            }

            // Ctrl/Cmd + N to add new item
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                addItemRow();
            }
        });
    </script>
</body>

</html>