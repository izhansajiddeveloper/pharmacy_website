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

// Check if coming from view stock page with selected medicine
$selected_medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);

    try {
        $invoice_no = generateUniqueInvoiceNo($conn);
        $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $discount = floatval($_POST['discount']);
        $total_amount_before_discount = 0;

        // Process sale items first to calculate total
        $medicine_ids = $_POST['medicine_id'];
        $quantities = $_POST['quantity'];
        $prices = $_POST['price'];

        $items_data = [];

        for ($i = 0; $i < count($medicine_ids); $i++) {
            $medicine_id = intval($medicine_ids[$i]);
            $quantity = intval($quantities[$i]);
            $price = floatval($prices[$i]);

            if ($medicine_id > 0 && $quantity > 0 && $price > 0) {
                $items_data[] = [
                    'medicine_id' => $medicine_id,
                    'quantity' => $quantity,
                    'price' => $price
                ];
                $total_amount_before_discount += ($quantity * $price);
            }
        }

        // Calculate discount amount based on percentage
        $discount_amount = ($total_amount_before_discount * $discount) / 100;
        $total_amount = max(0, $total_amount_before_discount - $discount_amount);

        // Insert sale
        $sale_query = "INSERT INTO sales (invoice_no, pharmacist_id, total_amount, discount, payment_method, sale_date) 
                       VALUES ('$invoice_no', $pharmacist_id, $total_amount, $discount_amount, '$payment_method', NOW())";

        if (!mysqli_query($conn, $sale_query)) {
            throw new Exception("Error creating sale: " . mysqli_error($conn));
        }

        $sale_id = mysqli_insert_id($conn);

        // Process sale items - auto-select batch with FIFO (First In First Out)
        foreach ($items_data as $item) {
            $medicine_id = $item['medicine_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];

            // Find available batches with FIFO (earliest expiry first)
            $batch_query = "SELECT sb.id, sb.quantity, sb.batch_no, sb.selling_price
                           FROM stock_batches sb 
                           WHERE sb.medicine_id = $medicine_id
                           AND sb.quantity > 0 
                           AND sb.expiry_date > CURDATE() 
                           AND sb.is_expired = 0
                           ORDER BY sb.expiry_date ASC, sb.received_date ASC";

            $batch_result = mysqli_query($conn, $batch_query);

            if (mysqli_num_rows($batch_result) > 0) {
                $remaining_quantity = $quantity;

                while ($remaining_quantity > 0 && ($batch = mysqli_fetch_assoc($batch_result))) {
                    $batch_id = $batch['id'];
                    $batch_stock = $batch['quantity'];
                    $batch_no = $batch['batch_no'];

                    // Calculate how much to take from this batch
                    $take_quantity = min($remaining_quantity, $batch_stock);

                    if ($take_quantity > 0) {
                        // Insert sale item with this batch
                        $item_query = "INSERT INTO sale_items (sale_id, medicine_id, batch_id, quantity, price) 
                                       VALUES ($sale_id, $medicine_id, $batch_id, $take_quantity, $price)";

                        if (!mysqli_query($conn, $item_query)) {
                            throw new Exception("Error adding sale item: " . mysqli_error($conn));
                        }

                        // Update stock for this batch
                        $update_stock = "UPDATE stock_batches SET quantity = quantity - $take_quantity WHERE id = $batch_id";
                        if (!mysqli_query($conn, $update_stock)) {
                            throw new Exception("Error updating stock: " . mysqli_error($conn));
                        }

                        $remaining_quantity -= $take_quantity;
                    }
                }

                // If we still have quantity left after checking all batches
                if ($remaining_quantity > 0) {
                    throw new Exception("Insufficient stock for medicine ID: $medicine_id. Only " . ($quantity - $remaining_quantity) . " available.");
                }
            } else {
                throw new Exception("No stock available for medicine ID: $medicine_id");
            }
        }

        // Create invoice with customer name
        $invoice_query = "INSERT INTO invoices (invoice_no, sale_id, pharmacist_id, customer_name, total_amount, discount, payment_method) 
                          VALUES ('$invoice_no', $sale_id, $pharmacist_id, '$customer_name', $total_amount, $discount_amount, '$payment_method')";

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

        mysqli_commit($conn);
        header("Location: view_sale.php?id=$sale_id&type=wholesale&success=1");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

// Fetch medicines with stock and their selling price
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

// Get selected medicine details if any
$selected_medicine_data = null;
if ($selected_medicine_id > 0) {
    $medicine_query = "SELECT m.*, mg.name as generic_name FROM medicines m
                       LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
                       WHERE m.id = $selected_medicine_id";
    $medicine_result = mysqli_query($conn, $medicine_query);
    if (mysqli_num_rows($medicine_result) > 0) {
        $selected_medicine_data = mysqli_fetch_assoc($medicine_result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wholesale Sale | MediCare Pharma</title>
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

        .gradient-blue {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
        }

        .gradient-text {
            background: linear-gradient(45deg, #3b82f6, #1d4ed8);
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
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .item-row {
            transition: all 0.3s ease;
        }

        .item-row:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }

        .price-readonly {
            background-color: #f3f4f6;
            color: #374151;
            cursor: not-allowed;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <main class="flex-1 p-4 md:p-6">
            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-4 md:p-6 mb-4 md:mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            <i class="fas fa-users text-blue-500"></i>
                            <span class="gradient-text">Wholesale Sale</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-store text-blue-500"></i>
                            <span>Bulk sales for wholesale customers</span>
                            <?php if ($selected_medicine_data): ?>
                                <span class="text-green-600 font-medium">
                                    <i class="fas fa-check-circle"></i>
                                    Pre-filled: <?php echo htmlspecialchars($selected_medicine_data['name']); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-2">
                        <a href="create_regular_sale.php"
                            class="px-4 md:px-6 py-2 md:py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm text-sm md:text-base">
                            <i class="fas fa-user text-blue-500"></i>
                            <span class="hidden md:inline">Regular Sale</span>
                        </a>
                        <a href="sales.php"
                            class="px-4 md:px-6 py-2 md:py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm text-sm md:text-base">
                            <i class="fas fa-arrow-left text-blue-500"></i>
                            <span class="hidden md:inline">Back</span>
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

            <form method="POST" id="saleForm" class="space-y-4 md:space-y-6">
                <!-- Customer & Payment Info -->
                <div class="glass-card rounded-2xl p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user"></i> Customer Name
                            </label>
                            <input type="text" name="customer_name" required
                                class="w-full form-input px-4 py-2 md:py-3 rounded-lg"
                                placeholder="Enter customer name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-credit-card"></i> Payment Method
                            </label>
                            <select name="payment_method" required
                                class="w-full form-input px-4 py-2 md:py-3 rounded-lg">
                                <option value="Cash" selected>Cash</option>
                                <option value="Online">Online</option>
                                <option value="Card">Card</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-percentage"></i> Discount %
                            </label>
                            <input type="number" name="discount" id="discount" min="0" step="0.01" value="0"
                                class="w-full form-input px-4 py-2 md:py-3 rounded-lg"
                                placeholder="0.00">
                        </div>
                    </div>
                </div>

                <!-- Sale Items Section -->
                <div class="glass-card rounded-2xl p-4 md:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-pills text-green-500"></i>
                            <span>Sale Items</span>
                            <?php if ($selected_medicine_data): ?>
                                <span class="text-sm font-normal text-green-600 bg-green-50 px-3 py-1 rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    1 medicine pre-selected
                                </span>
                            <?php endif; ?>
                        </h3>
                        <div class="flex space-x-2">
                            <button type="button" onclick="addItemRow()"
                                class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition flex items-center space-x-1">
                                <i class="fas fa-plus"></i>
                                <span class="hidden md:inline">Add Item</span>
                            </button>
                            <button type="button" onclick="clearAllItems()"
                                class="px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition flex items-center space-x-1">
                                <i class="fas fa-trash"></i>
                                <span class="hidden md:inline">Clear All</span>
                            </button>
                        </div>
                    </div>

                    <div id="itemsContainer" class="space-y-3">
                        <!-- Items will be added here dynamically -->
                    </div>

                    <!-- Quick Medicine Search -->
                    <div class="mt-4">
                        <input type="text" id="quickSearch"
                            placeholder="Quick search medicine by name or generic..."
                            class="w-full form-input px-4 py-3 rounded-lg mb-2">
                        <div id="searchResults" class="hidden bg-white border border-gray-200 rounded-lg max-h-60 overflow-y-auto"></div>
                    </div>
                </div>

                <!-- Summary Panel -->
                <div class="glass-card rounded-2xl p-4 md:p-6 sticky bottom-0 bg-white/95">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Items:</span>
                                <span id="itemCount" class="font-bold">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal:</span>
                                <span id="subtotal" class="font-bold text-gray-800">Rs0.00</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Discount:</span>
                                <span id="discountDisplay" class="font-bold text-red-600">Rs0.00</span>
                            </div>
                            <div class="border-t border-gray-200 pt-2">
                                <div class="flex justify-between">
                                    <span class="text-lg font-bold text-gray-800">Total:</span>
                                    <span id="totalAmount" class="text-2xl font-bold text-green-600">Rs0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit"
                                class="gradient-blue text-white px-6 md:px-8 py-3 md:py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow w-full md:w-auto justify-center">
                                <i class="fas fa-check-circle"></i>
                                <span>Complete Sale</span>
                                <i class="fas fa-print text-blue-100"></i>
                            </button>
                        </div>
                    </div>
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
                    price: <?php echo $medicine['min_price'] ?: 0; ?>,
                    barcode: '<?php echo addslashes($medicine['id'] . "000"); ?>'
                },
            <?php endwhile; ?>
        ];

        // Selected medicine data from PHP (if any)
        const selectedMedicine = <?php echo $selected_medicine_data ? json_encode($selected_medicine_data) : 'null'; ?>;

        let itemCount = 0;

        function addItemRow(medicineId = '', quantity = 1, price = 0) {
            const container = document.getElementById('itemsContainer');
            const itemId = itemCount++;

            const row = document.createElement('div');
            row.className = 'item-row bg-gray-50 p-4 rounded-lg';
            row.id = `itemRow${itemId}`;

            row.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medicine</label>
                        <select name="medicine_id[]" class="medicine-select w-full form-input px-3 py-2 rounded-lg text-sm"
                                onchange="updateMedicinePrice(${itemId})" required>
                            <option value="">Select Medicine</option>
                            ${medicines.map(med => `
                                <option value="${med.id}" 
                                        data-stock="${med.stock}" 
                                        data-price="${med.price}"
                                        ${medicineId == med.id ? 'selected' : ''}>
                                    ${med.name} ${med.generic ? `(${med.generic})` : ''} 
                                    <span class="text-xs text-gray-500">Stock: ${med.stock}</span>
                                </option>
                            `).join('')}
                        </select>
                        <div class="mt-1 text-xs text-gray-500" id="stockInfo${itemId}"></div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                        <input type="number" name="quantity[]" min="1" value="${quantity}"
                               class="quantity-input w-full form-input px-3 py-2 rounded-lg text-sm"
                               onchange="updateStockInfo(${itemId}); updateTotal()" 
                               oninput="updateStockInfo(${itemId}); updateTotal()" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" name="price[]" step="0.01" min="0" value="${price}"
                               class="price-input w-full form-input px-3 py-2 rounded-lg text-sm price-readonly"
                               readonly required>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                        <div class="font-bold text-green-600 text-lg" id="itemTotal${itemId}">Rs0.00</div>
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button" onclick="removeItemRow(${itemId})"
                                class="w-full px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;

            container.appendChild(row);

            // Initialize values if provided
            if (medicineId) {
                updateMedicinePrice(itemId);
                updateStockInfo(itemId);
            }
            updateTotal();
            updateItemCount();
        }

        function updateMedicinePrice(itemId) {
            const select = document.querySelector(`#itemRow${itemId} .medicine-select`);
            const selectedOption = select.options[select.selectedIndex];
            const priceInput = document.querySelector(`#itemRow${itemId} .price-input`);

            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                const stock = selectedOption.getAttribute('data-stock');

                if (price > 0) {
                    priceInput.value = price;
                } else {
                    priceInput.value = 0;
                }

                // Auto-focus quantity field
                const quantityInput = document.querySelector(`#itemRow${itemId} .quantity-input`);
                quantityInput.focus();
                quantityInput.select();

                // Update stock info
                updateStockInfo(itemId);
            } else {
                priceInput.value = 0;
            }

            updateTotal();
        }

        function updateStockInfo(itemId) {
            const select = document.querySelector(`#itemRow${itemId} .medicine-select`);
            const selectedOption = select.options[select.selectedIndex];
            const quantityInput = document.querySelector(`#itemRow${itemId} .quantity-input`);
            const stockInfo = document.getElementById(`stockInfo${itemId}`);

            if (selectedOption.value) {
                const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                const quantity = parseInt(quantityInput.value) || 0;

                if (stock > 0) {
                    if (quantity > stock) {
                        stockInfo.innerHTML = `<span class="text-red-500"><i class="fas fa-exclamation-triangle"></i> Only ${stock} available</span>`;
                        quantityInput.style.borderColor = '#ef4444';
                    } else {
                        stockInfo.innerHTML = `<span class="text-green-600"><i class="fas fa-check-circle"></i> ${stock} available</span>`;
                        quantityInput.style.borderColor = '';
                    }
                } else {
                    stockInfo.innerHTML = `<span class="text-red-500"><i class="fas fa-exclamation-triangle"></i> Out of stock</span>`;
                    quantityInput.style.borderColor = '#ef4444';
                }
            } else {
                stockInfo.innerHTML = '';
                quantityInput.style.borderColor = '';
            }
        }

        function removeItemRow(itemId) {
            const row = document.getElementById(`itemRow${itemId}`);
            if (row) row.remove();
            updateTotal();
            updateItemCount();
        }

        function clearAllItems() {
            if (confirm('Remove all items?')) {
                document.getElementById('itemsContainer').innerHTML = '';
                itemCount = 0;
                updateTotal();
                updateItemCount();
            }
        }

        function updateTotal() {
            let subtotal = 0;
            let totalItems = 0;

            document.querySelectorAll('.item-row').forEach((row, index) => {
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const itemTotal = quantity * price;

                // Update item total display
                const itemTotalSpan = row.querySelector(`#itemTotal${index}`);
                if (itemTotalSpan) {
                    itemTotalSpan.textContent = 'Rs' + itemTotal.toFixed(2);
                }

                subtotal += itemTotal;
                totalItems += quantity;
            });

            // Get discount
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const discountAmount = (subtotal * discount) / 100;
            const total = Math.max(0, subtotal - discountAmount);

            // Update display
            document.getElementById('subtotal').textContent = 'Rs' + subtotal.toFixed(2);
            document.getElementById('discountDisplay').textContent = 'Rs' + discountAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = 'Rs' + total.toFixed(2);
            document.getElementById('itemCount').textContent = totalItems;
        }

        function updateItemCount() {
            const items = document.querySelectorAll('.item-row').length;
            document.getElementById('itemCount').textContent = items;
        }

        // Quick search functionality
        document.getElementById('quickSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const resultsDiv = document.getElementById('searchResults');

            if (searchTerm.length < 2) {
                resultsDiv.classList.add('hidden');
                return;
            }

            const filteredMedicines = medicines.filter(med =>
                med.name.toLowerCase().includes(searchTerm) ||
                (med.generic && med.generic.toLowerCase().includes(searchTerm))
            );

            if (filteredMedicines.length > 0) {
                resultsDiv.innerHTML = filteredMedicines.map(med => `
                    <div class="p-3 border-b border-gray-100 hover:bg-blue-50 cursor-pointer flex justify-between items-center"
                         onclick="addMedicine(${med.id})">
                        <div>
                            <div class="font-medium">${med.name}</div>
                            <div class="text-sm text-gray-500">${med.generic || ''}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-green-600">Rs${med.price}</div>
                            <div class="text-xs text-gray-500">Stock: ${med.stock}</div>
                        </div>
                    </div>
                `).join('');
                resultsDiv.classList.remove('hidden');
            } else {
                resultsDiv.innerHTML = '<div class="p-3 text-gray-500 text-center">No medicines found</div>';
                resultsDiv.classList.remove('hidden');
            }
        });

        // Click outside to close search results
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#quickSearch') && !e.target.closest('#searchResults')) {
                document.getElementById('searchResults').classList.add('hidden');
            }
        });

        function addMedicine(medicineId) {
            const medicine = medicines.find(m => m.id === medicineId);
            if (medicine) {
                addItemRow(medicineId, 1, medicine.price);
                document.getElementById('quickSearch').value = '';
                document.getElementById('searchResults').classList.add('hidden');
                document.getElementById('quickSearch').focus();
            }
        }

        // Initialize with one empty item row or pre-filled medicine
        document.addEventListener('DOMContentLoaded', function() {
            if (selectedMedicine) {
                // Pre-fill the selected medicine
                addItemRow(selectedMedicine.id, 1, selectedMedicine.price || 0);
            } else {
                addItemRow();
            }

            document.getElementById('quickSearch').focus();

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
                let stockError = false;

                items.forEach(row => {
                    const medicineSelect = row.querySelector('.medicine-select');
                    const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                    const price = parseFloat(row.querySelector('.price-input').value) || 0;

                    if (medicineSelect.value && quantity > 0 && price > 0) {
                        // Check if quantity exceeds available stock
                        const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
                        const availableStock = parseFloat(selectedOption.getAttribute('data-stock')) || 0;

                        if (quantity > availableStock) {
                            e.preventDefault();
                            alert(`Quantity (${quantity}) exceeds available stock (${availableStock}) for selected medicine.`);
                            stockError = true;
                            return;
                        }

                        hasValidItem = true;
                    }
                });

                if (stockError) return;

                if (!hasValidItem) {
                    e.preventDefault();
                    alert('Please add at least one valid item with quantity and price.');
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + Enter to submit
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    document.querySelector('button[type="submit"]').click();
                }

                // F2 to add new item
                if (e.key === 'F2') {
                    e.preventDefault();
                    addItemRow();
                }

                // F3 to focus search
                if (e.key === 'F3') {
                    e.preventDefault();
                    document.getElementById('quickSearch').focus();
                }
            });
        });

        // Auto-focus on quantity field when medicine is selected
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('medicine-select')) {
                const row = e.target.closest('.item-row');
                if (row) {
                    setTimeout(() => {
                        const quantityInput = row.querySelector('.quantity-input');
                        if (quantityInput) {
                            quantityInput.focus();
                            quantityInput.select();
                        }
                    }, 100);
                }
            }
        });
    </script>
</body>

</html>