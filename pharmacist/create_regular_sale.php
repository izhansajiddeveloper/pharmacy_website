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

    // Atomically increment the counter for the current month
    $query = "INSERT INTO invoice_sequences (seq_key, last_value) 
              VALUES ('$seqKey', 1) 
              ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)";

    if (!mysqli_query($conn, $query)) {
        throw new Exception("Sequence error: " . mysqli_error($conn));
    }

    // Retrieve the value generated
    $newID = mysqli_insert_id($conn);

    // Format the final string
    return $seqKey . str_pad($newID, 4, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $invoice_no = generateUniqueInvoiceNo($conn);
        // Regular customer - no name needed, use default
        $customer_name = "Regular Customer";
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

        // Create invoice with "Regular Customer" as default
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

        // Redirect to view sale or print invoice
        header("Location: view_sale.php?id=$sale_id&type=regular&success=1");
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
    <title>Quick Sale - Regular Customer | MediCare Pharma</title>
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

        .quick-actions {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 100;
        }

        .barcode-scanner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            display: none;
        }

        .scanner-active {
            display: flex;
            align-items: center;
            justify-content: center;
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
                            <i class="fas fa-bolt text-blue-500"></i>
                            <span class="gradient-text">Quick Sale</span>
                            <span class="text-gray-600 text-lg md:text-xl">(Regular Customer)</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-store text-blue-500"></i>
                            <span>Fast checkout for walk-in customers</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-2">
                        <a href="create_sale.php"
                            class="px-4 md:px-6 py-2 md:py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm text-sm md:text-base">
                            <i class="fas fa-users text-blue-500"></i>
                            <span class="hidden md:inline">Wholesale</span>
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
                <!-- Quick Actions Bar -->
                <div class="glass-card rounded-2xl p-4">
                    <div class="flex flex-wrap gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-credit-card"></i> Payment Method
                            </label>
                            <select name="payment_method" required
                                class="w-full form-input px-4 py-2 md:py-3 rounded-lg">
                                <option value="Cash" selected>Cash</option>
                                <option value="Online">Online</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-percentage"></i> Discount (Rs)
                            </label>
                            <input type="number" name="discount" id="discount" min="0" step="0.01" value="0"
                                class="w-full form-input px-4 py-2 md:py-3 rounded-lg"
                                placeholder="0.00">
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="startBarcodeScanner()"
                                class="px-4 py-2 md:py-3 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition flex items-center space-x-2">
                                <i class="fas fa-barcode"></i>
                                <span>Scan Barcode</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sale Items - Optimized for speed -->
                <div class="glass-card rounded-2xl p-4 md:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-pills text-green-500"></i>
                            <span>Items</span>
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

    <!-- Barcode Scanner Modal -->
    <div id="barcodeScanner" class="barcode-scanner">
        <div class="bg-white rounded-2xl p-6 m-4 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Scan Barcode</h3>
                <button onclick="stopBarcodeScanner()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="scannerArea" class="border-2 border-dashed border-gray-300 rounded-lg h-64 flex items-center justify-center">
                <div class="text-center">
                    <i class="fas fa-barcode text-4xl text-gray-400 mb-2"></i>
                    <p class="text-gray-600">Scanner will be activated here</p>
                    <p class="text-sm text-gray-500 mt-2">(Simulation mode)</p>
                </div>
            </div>
            <div class="mt-4">
                <input type="text" id="manualBarcode"
                    placeholder="Or enter barcode manually..."
                    class="w-full form-input px-4 py-3 rounded-lg mb-2"
                    onkeypress="if(event.key === 'Enter') addByBarcode()">
                <button onclick="addByBarcode()" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Add Item
                </button>
            </div>
        </div>
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
                    barcode: '<?php echo addslashes($medicine['id'] . "000"); ?>' // Simulated barcode
                },
            <?php endwhile; ?>
        ];

        let itemCount = 0;
        let scannerActive = false;

        function addItemRow(medicineId = '', quantity = 1, price = 0) {
            const container = document.getElementById('itemsContainer');
            const itemId = itemCount++;

            const row = document.createElement('div');
            row.className = 'item-row bg-gray-50 p-4 rounded-lg';
            row.id = `itemRow${itemId}`;
            row.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medicine</label>
                        <select name="medicine_id[]" class="medicine-select w-full form-input px-3 py-2 rounded-lg text-sm"
                                onchange="updatePrice(${itemId})" required>
                            <option value="">Select Medicine</option>
                            ${medicines.map(med => `
                                <option value="${med.id}" 
                                        data-stock="${med.stock}" 
                                        data-price="${med.price}"
                                        ${medicineId == med.id ? 'selected' : ''}>
                                    ${med.name} ${med.generic ? `(${med.generic})` : ''} 
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                        <input type="number" name="quantity[]" min="1" value="${quantity}"
                               class="quantity-input w-full form-input px-3 py-2 rounded-lg text-sm"
                               onchange="updateTotal()" oninput="updateTotal()" required>
                        <div class="text-xs text-gray-500 mt-1">
                            Stock: <span class="available-stock" id="availableStock${itemId}">0</span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" name="price[]" step="0.01" min="0" value="${price}"
                               class="price-input w-full form-input px-3 py-2 rounded-lg text-sm"
                               onchange="updateTotal()" oninput="updateTotal()" required>
                    </div>
                    <div class="md:col-span-2">
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
                updatePrice(itemId);
            }
            updateTotal();
            updateItemCount();
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

        function updatePrice(itemId) {
            const select = document.querySelector(`#itemRow${itemId} .medicine-select`);
            const selectedOption = select.options[select.selectedIndex];
            const priceInput = document.querySelector(`#itemRow${itemId} .price-input`);
            const availableStockSpan = document.getElementById(`availableStock${itemId}`);

            if (selectedOption.value) {
                const price = selectedOption.getAttribute('data-price');
                const stock = selectedOption.getAttribute('data-stock');

                priceInput.value = price;
                availableStockSpan.textContent = stock;

                // Auto-focus quantity and select all text
                const quantityInput = document.querySelector(`#itemRow${itemId} .quantity-input`);
                quantityInput.focus();
                quantityInput.select();
            } else {
                priceInput.value = 0;
                availableStockSpan.textContent = 0;
            }

            updateTotal();
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

            // Calculate total
            const total = Math.max(0, subtotal - discount);

            // Update display
            document.getElementById('subtotal').textContent = 'Rs' + subtotal.toFixed(2);
            document.getElementById('discountDisplay').textContent = 'Rs' + discount.toFixed(2);
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

        // Barcode scanner functions
        function startBarcodeScanner() {
            document.getElementById('barcodeScanner').classList.add('scanner-active');
            scannerActive = true;
            document.getElementById('manualBarcode').focus();

            // In a real implementation, you would initialize the barcode scanner here
            console.log('Barcode scanner activated');
        }

        function stopBarcodeScanner() {
            document.getElementById('barcodeScanner').classList.remove('scanner-active');
            scannerActive = false;
        }

        function addByBarcode() {
            const barcode = document.getElementById('manualBarcode').value;
            if (!barcode) return;

            // Simulate barcode lookup
            const medicine = medicines.find(m => m.barcode === barcode);
            if (medicine) {
                addItemRow(medicine.id, 1, medicine.price);
                document.getElementById('manualBarcode').value = '';
                document.getElementById('manualBarcode').focus();
            } else {
                alert('Medicine not found with this barcode');
            }
        }

        // Initialize with one empty item row
        document.addEventListener('DOMContentLoaded', function() {
            addItemRow();
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

            // Keyboard shortcuts for quick operation
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

                // Escape to close search results
                if (e.key === 'Escape') {
                    document.getElementById('searchResults').classList.add('hidden');
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