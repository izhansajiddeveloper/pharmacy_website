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

    // 1. Atomically increment the counter for the current month
    // LAST_INSERT_ID(last_value + 1) stores the new value in a connection-specific variable
    $query = "INSERT INTO invoice_sequences (seq_key, last_value) 
              VALUES ('$seqKey', 1) 
              ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)";

    if (!mysqli_query($conn, $query)) {
        throw new Exception("Sequence error: " . mysqli_error($conn));
    }

    // 2. Retrieve the value generated in the step above
    $newID = mysqli_insert_id($conn);

    // 3. Format the final string (e.g., INV-2026010005)
    return $seqKey . str_pad($newID, 4, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $invoice_no = generateUniqueInvoiceNo($conn);
        $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $discount = floatval($_POST['discount']);
        $total_amount = 0;

        // Insert sale
        $sale_query = "INSERT INTO sales (invoice_no, pharmacist_id, total_amount, discount, payment_method, sale_date) 
                       VALUES ('$invoice_no', $pharmacist_id, 0, $discount, '$payment_method', NOW())";

        if (!mysqli_query($conn, $sale_query)) {
            throw new Exception("Error creating sale: " . mysqli_error($conn));
        }

        $sale_id = mysqli_insert_id($conn);

        // Process sale items
        $medicine_ids = $_POST['medicine_id'];
        $quantities = $_POST['quantity'];
        $prices = $_POST['price'];

        for ($i = 0; $i < count($medicine_ids); $i++) {
            $medicine_id = intval($medicine_ids[$i]);
            $quantity = intval($quantities[$i]);
            $price = floatval($prices[$i]);

            if ($medicine_id > 0 && $quantity > 0 && $price > 0) {
                // Check stock availability
                $stock_query = "SELECT sb.id, sb.quantity 
                                FROM stock_batches sb 
                                WHERE sb.medicine_id = $medicine_id 
                                AND sb.quantity >= $quantity 
                                AND sb.expiry_date > CURDATE() 
                                AND sb.is_expired = 0
                                ORDER BY sb.expiry_date ASC 
                                LIMIT 1";
                $stock_result = mysqli_query($conn, $stock_query);

                if (mysqli_num_rows($stock_result) > 0) {
                    $stock = mysqli_fetch_assoc($stock_result);
                    $batch_id = $stock['id'];

                    // Insert sale item
                    $item_query = "INSERT INTO sale_items (sale_id, medicine_id, batch_id, quantity, price) 
                                   VALUES ($sale_id, $medicine_id, $batch_id, $quantity, $price)";

                    if (!mysqli_query($conn, $item_query)) {
                        throw new Exception("Error adding sale item: " . mysqli_error($conn));
                    }

                    // Update stock
                    $update_stock = "UPDATE stock_batches SET quantity = quantity - $quantity WHERE id = $batch_id";
                    if (!mysqli_query($conn, $update_stock)) {
                        throw new Exception("Error updating stock: " . mysqli_error($conn));
                    }

                    $total_amount += ($quantity * $price);
                } else {
                    throw new Exception("Insufficient stock for medicine ID: $medicine_id");
                }
            }
        }

        // Update sale total
        $update_sale = "UPDATE sales SET total_amount = $total_amount WHERE id = $sale_id";
        if (!mysqli_query($conn, $update_sale)) {
            throw new Exception("Error updating sale total: " . mysqli_error($conn));
        }

        // Create invoice
        $invoice_query = "INSERT INTO invoices (invoice_no, sale_id, pharmacist_id, customer_name, total_amount, discount, payment_method) 
                          VALUES ('$invoice_no', $sale_id, $pharmacist_id, '$customer_name', $total_amount, $discount, '$payment_method')";

        if (!mysqli_query($conn, $invoice_query)) {
            throw new Exception("Error creating invoice: " . mysqli_error($conn));
        }

        $invoice_id = mysqli_insert_id($conn);

        // Insert invoice items
        $invoice_items_query = "INSERT INTO invoice_items (invoice_id, medicine_id, batch_id, quantity, price, discount, total_price)
                                SELECT $invoice_id, si.medicine_id, si.batch_id, si.quantity, si.price, 
                                       0 as discount, (si.quantity * si.price) as total_price
                                FROM sale_items si
                                WHERE si.sale_id = $sale_id";

        if (!mysqli_query($conn, $invoice_items_query)) {
            throw new Exception("Error creating invoice items: " . mysqli_error($conn));
        }

        // Commit transaction
        mysqli_commit($conn);

        $success = "Sale created successfully! Invoice Number: $invoice_no";

        // Redirect to view sale
        header("Location: view_sale.php?id=$sale_id&success=1");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// Fetch medicines with stock
$medicines_query = "
    SELECT 
        m.id,
        m.name,
        mg.name AS generic_name,
        COALESCE(SUM(sb.quantity), 0) AS total_stock,
        MIN(sb.selling_price) AS min_price
    FROM medicines m
    LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
    LEFT JOIN stock_batches sb 
        ON m.id = sb.medicine_id
        AND sb.expiry_date > CURDATE()
        AND sb.is_expired = 0
        AND sb.quantity > 0
    GROUP BY m.id, m.name, mg.name
    HAVING total_stock > 0
    ORDER BY m.name
";

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
        }

        .item-row:hover {
            background-color: rgba(16, 185, 129, 0.05);
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
                            <span>Process a new sale transaction</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0">
                        <a href="sales.php"
                            class="px-6 py-3 border border-green-200 text-gray-700 rounded-xl hover:bg-green-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-green-500"></i>
                            <span>Back to Sales</span>
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
                                Customer Name
                            </label>
                            <input type="text" name="customer_name" required
                                placeholder="Enter customer name"
                                class="w-full form-input px-4 py-3 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Method
                            </label>
                            <select name="payment_method" required
                                class="w-full form-input px-4 py-3 rounded-lg">
                                <option value="Cash">Cash</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sale Items -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-shopping-cart text-purple-500"></i>
                        <span>Sale Items</span>
                    </h3>

                    <div id="itemsContainer">
                        <!-- Items will be added here dynamically -->
                    </div>

                    <div class="mt-4">
                        <button type="button" onclick="addItemRow()"
                            class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Item</span>
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
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Subtotal:</span>
                            <span id="subtotal" class="text-lg font-bold text-gray-800">Rs0.00</span>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Discount (Rs)
                            </label>
                            <input type="number" name="discount" id="discount" min="0" step="0.01" value="0"
                                class="w-full form-input px-4 py-3 rounded-lg">
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-bold text-gray-800">Total Amount:</span>
                                <span id="totalAmount" class="text-2xl font-bold text-green-600">Rs0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button type="submit"
                        class="gradient-green text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                        <i class="fas fa-check"></i>
                        <span>Complete Sale</span>
                        <i class="fas fa-arrow-right text-green-100 text-sm"></i>
                    </button>

                    <button type="button" onclick="window.location.href='sales.php'"
                        class="px-8 py-4 border border-green-200 text-gray-700 rounded-xl hover:bg-green-50 transition font-bold flex items-center space-x-2 shadow-sm">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Available medicines from PHP
        const medicines = [
            <?php while ($medicine = mysqli_fetch_assoc($medicines_result)): ?> {
                    id: <?php echo $medicine['id']; ?>,
                    name: '<?php echo addslashes($medicine['name']); ?>',
                    generic: '<?php echo addslashes($medicine['generic_name'] ?: ''); ?>',
                    stock: <?php echo $medicine['total_stock']; ?>,
                    price: <?php echo $medicine['min_price'] ?: 0; ?>
                },
            <?php endwhile; ?>
        ];

        let itemCount = 0;

        function addItemRow(medicineId = '', quantity = 1, price = 0) {
            const container = document.getElementById('itemsContainer');
            const itemId = itemCount++;

            const row = document.createElement('div');
            row.className = 'item-row bg-gray-50 p-4 rounded-lg mb-3';
            row.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medicine</label>
                        <select name="medicine_id[]" class="medicine-select w-full form-input px-3 py-2 rounded-lg"
                                onchange="updatePrice(${itemId})" required>
                            <option value="">Select Medicine</option>
                            ${medicines.map(med => `
                                <option value="${med.id}" data-stock="${med.stock}" data-price="${med.price}"
                                        ${medicineId == med.id ? 'selected' : ''}>
                                    ${med.name} ${med.generic ? `(${med.generic})` : ''} - Stock: ${med.stock}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input type="number" name="quantity[]" min="1" value="${quantity}"
                               class="quantity-input w-full form-input px-3 py-2 rounded-lg"
                               onchange="updateTotal(${itemId})" oninput="updateTotal(${itemId})" required>
                        <div class="text-xs text-gray-500 mt-1">
                            Available: <span class="available-stock" id="availableStock${itemId}">0</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price (Rs)</label>
                        <input type="number" name="price[]" step="0.01" min="0" value="${price}"
                               class="price-input w-full form-input px-3 py-2 rounded-lg"
                               onchange="updateTotal()" oninput="updateTotal()" required>
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="removeItemRow(${itemId})"
                                class="w-full px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
                <div class="mt-2 text-sm">
                    <span>Item Total: </span>
                    <span class="item-total font-bold text-green-600" id="itemTotal${itemId}">Rs0.00</span>
                </div>
            `;

            container.appendChild(row);

            // Initialize values if provided
            if (medicineId) {
                updatePrice(itemId);
            }
            updateTotal(itemId);
        }

        function removeItemRow(itemId) {
            const row = document.querySelector(`[onclick="removeItemRow(${itemId})"]`).closest('.item-row');
            row.remove();
            updateTotal();
        }

        function updatePrice(itemId) {
            const select = document.querySelector(`[onchange="updatePrice(${itemId})"]`);
            const selectedOption = select.options[select.selectedIndex];
            const priceInput = document.querySelector(`#itemTotal${itemId}`).closest('.item-row').querySelector('.price-input');
            const availableStockSpan = document.getElementById(`availableStock${itemId}`);

            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                const stock = selectedOption.getAttribute('data-stock');

                priceInput.value = price;
                availableStockSpan.textContent = stock;

                // Update quantity if exceeds available stock
                const quantityInput = priceInput.closest('.item-row').querySelector('.quantity-input');
                if (parseInt(quantityInput.value) > parseInt(stock)) {
                    quantityInput.value = stock;
                }
            } else {
                priceInput.value = 0;
                availableStockSpan.textContent = 0;
            }

            updateTotal(itemId);
        }

        function updateTotal(itemId = null) {
            let subtotal = 0;

            // Calculate subtotal from all items
            document.querySelectorAll('.item-row').forEach((row, index) => {
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const itemTotal = quantity * price;

                // Update item total display
                const itemTotalSpan = row.querySelector('.item-total');
                if (itemTotalSpan) {
                    itemTotalSpan.textContent = 'Rs' + itemTotal.toFixed(2);
                }

                subtotal += itemTotal;
            });

            // Get discount
            const discount = parseFloat(document.getElementById('discount').value) || 0;

            // Calculate total
            const total = Math.max(0, subtotal - discount);

            // Update display
            document.getElementById('subtotal').textContent = 'Rs' + subtotal.toFixed(2);
            document.getElementById('totalAmount').textContent = 'Rs' + total.toFixed(2);
        }

        // Initialize with one empty item row
        document.addEventListener('DOMContentLoaded', function() {
            addItemRow();

            // Update total when discount changes
            document.getElementById('discount').addEventListener('input', updateTotal);
            document.getElementById('discount').addEventListener('change', updateTotal);

            // Form validation
            document.getElementById('saleForm').addEventListener('submit', function(e) {
                const items = document.querySelectorAll('.item-row');
                if (items.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one item to the sale.');
                    return;
                }

                let hasValidItem = false;
                items.forEach(row => {
                    const medicineSelect = row.querySelector('.medicine-select');
                    const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                    const price = parseFloat(row.querySelector('.price-input').value) || 0;

                    if (medicineSelect.value && quantity > 0 && price > 0) {
                        hasValidItem = true;
                    }
                });

                if (!hasValidItem) {
                    e.preventDefault();
                    alert('Please add at least one valid item with quantity and price.');
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[type="submit"]').click();
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