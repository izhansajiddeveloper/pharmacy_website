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

    // Get sale items with proper joins
    $items_query = "
        SELECT 
            si.*,
            m.name AS medicine_name,
            mg.name AS generic_name,
            sb.batch_no,
            sb.quantity AS current_stock,
            sb.selling_price
        FROM sale_items si
        JOIN medicines m ON si.medicine_id = m.id
        LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
        LEFT JOIN stock_batches sb ON si.batch_id = sb.id
        WHERE si.sale_id = $sale_id
    ";
    $items_result = mysqli_query($conn, $items_query);

    $error = '';
    $success = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        mysqli_begin_transaction($conn);

        try {
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $discount = floatval($_POST['discount']);
            $total_amount = 0;

            // Check if medicine items are posted
            if (!isset($_POST['medicine_id']) || !is_array($_POST['medicine_id'])) {
                throw new Exception("No medicine items found in the form submission");
            }

            // First, restore original stock quantities
            $restore_query = "UPDATE stock_batches sb
                            JOIN sale_items si ON sb.id = si.batch_id
                            SET sb.quantity = sb.quantity + si.quantity
                            WHERE si.sale_id = $sale_id";

            if (!mysqli_query($conn, $restore_query)) {
                throw new Exception("Error restoring stock: " . mysqli_error($conn));
            }

            // Delete old sale items
            $delete_items = "DELETE FROM sale_items WHERE sale_id = $sale_id";
            if (!mysqli_query($conn, $delete_items)) {
                throw new Exception("Error deleting old items: " . mysqli_error($conn));
            }

            // Process new sale items
            $medicine_ids = $_POST['medicine_id'];
            $quantities = $_POST['quantity'];
            $prices = $_POST['price'];

            // Validate arrays have same length
            $item_count = count($medicine_ids);
            if (count($quantities) !== $item_count || count($prices) !== $item_count) {
                throw new Exception("Form data is inconsistent. Please try again.");
            }

            for ($i = 0; $i < $item_count; $i++) {
                $medicine_id = intval($medicine_ids[$i]);
                $quantity = intval($quantities[$i]);
                $price = floatval($prices[$i]);

                // Skip if medicine_id is 0 (empty selection)
                if ($medicine_id == 0 || $medicine_id < 1) {
                    continue;
                }

                if ($quantity > 0 && $price > 0) {
                    // Check stock availability
                    $stock_query = "SELECT sb.id, sb.quantity, sb.selling_price
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
                } else {
                    throw new Exception("Invalid quantity or price for medicine ID: $medicine_id");
                }
            }

            // Check if any valid items were added
            if ($total_amount <= 0) {
                throw new Exception("Please add at least one valid sale item with quantity and price");
            }

            // Update sale
            $update_sale = "UPDATE sales SET 
                            total_amount = $total_amount, 
                            discount = $discount, 
                            payment_method = '$payment_method',
                            sale_date = NOW()
                            WHERE id = $sale_id";

            if (!mysqli_query($conn, $update_sale)) {
                throw new Exception("Error updating sale: " . mysqli_error($conn));
            }

            // Update invoice
            $update_invoice = "UPDATE invoices SET 
                            total_amount = $total_amount, 
                            discount = $discount, 
                            payment_method = '$payment_method',
                            created_at = NOW()
                            WHERE sale_id = $sale_id";

            if (!mysqli_query($conn, $update_invoice)) {
                throw new Exception("Error updating invoice: " . mysqli_error($conn));
            }

            // Update invoice items
            $invoice_query = "SELECT id FROM invoices WHERE sale_id = $sale_id";
            $invoice_result = mysqli_query($conn, $invoice_query);
            $invoice = mysqli_fetch_assoc($invoice_result);
            $invoice_id = $invoice['id'];

            // Delete old invoice items
            $delete_invoice_items = "DELETE FROM invoice_items WHERE invoice_id = $invoice_id";
            if (!mysqli_query($conn, $delete_invoice_items)) {
                throw new Exception("Error deleting invoice items: " . mysqli_error($conn));
            }

            // Insert new invoice items
            $invoice_items_query = "INSERT INTO invoice_items (invoice_id, medicine_id, batch_id, quantity, price, total_price)
                                    SELECT $invoice_id, si.medicine_id, si.batch_id, si.quantity, si.price, 
                                        (si.quantity * si.price) as total_price
                                    FROM sale_items si
                                    WHERE si.sale_id = $sale_id";

            if (!mysqli_query($conn, $invoice_items_query)) {
                throw new Exception("Error creating invoice items: " . mysqli_error($conn));
            }

            mysqli_commit($conn);

            $success = "Sale updated successfully!";
            // Refresh data
            $sale_result = mysqli_query($conn, $sale_query);
            $sale = mysqli_fetch_assoc($sale_result);
            $items_result = mysqli_query($conn, $items_query);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }

    // Store existing items in array for JavaScript
    $existing_items_array = array();
    if ($items_result && mysqli_num_rows($items_result) > 0) {
        mysqli_data_seek($items_result, 0); // Reset pointer
        while ($item = mysqli_fetch_assoc($items_result)) {
            $existing_items_array[] = $item;
        }
    }

    // Fetch medicines with stock (excluding expired)
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
            AND sb.expiry_date >= CURDATE()
            AND sb.is_expired = 0
        GROUP BY m.id, m.name, mg.name
        HAVING total_stock > 0
        ORDER BY m.name ASC
    ";

    $medicines_result = mysqli_query($conn, $medicines_query);

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit Sale - MediCare Pharma</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

        <style>
            :root {
                --primary-yellow: #f59e0b;
                --primary-yellow-light: #fbbf24;
                --primary-yellow-dark: #d97706;
                --accent-blue: #3b82f6;
                --accent-green: #10b981;
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
                border-color: var(--accent-blue);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                outline: none;
            }

            .item-row {
                transition: all 0.3s ease;
                border-left: 4px solid transparent;
            }

            .item-row:hover {
                background-color: rgba(59, 130, 246, 0.05);
                border-left-color: var(--accent-blue);
            }

            .select2-container--default .select2-selection--single {
                height: 46px;
                border: 1px solid rgba(209, 213, 219, 0.5);
                border-radius: 0.5rem;
                background: rgba(255, 255, 255, 0.9);
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 46px;
                padding-left: 1rem;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 46px;
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
                                Edit <span class="gradient-text">Sale</span>
                            </h1>
                            <p class="text-gray-600 flex items-center space-x-2">
                                <i class="fas fa-edit text-blue-500"></i>
                                <span>Update sale transaction</span>
                                <span class="text-gray-400 mx-2">•</span>
                                <i class="fas fa-hashtag text-purple-500"></i>
                                <span class="font-medium">Invoice: <?php echo htmlspecialchars($sale['invoice_no']); ?></span>
                            </p>
                        </div>
                        <div class="mt-4 lg:mt-0 flex space-x-3">
                            <a href="view_sale.php?id=<?php echo $sale_id; ?>"
                                class="px-6 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm">
                                <i class="fas fa-eye text-blue-500"></i>
                                <span>View Details</span>
                            </a>
                            <a href="sales.php"
                                class="px-6 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm">
                                <i class="fas fa-arrow-left text-blue-500"></i>
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
                <?php elseif ($success): ?>
                    <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-green-50 to-green-25 border border-green-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-green-800">Success!</h3>
                                <p class="text-green-600"><?php echo htmlspecialchars($success); ?></p>
                                <p class="text-green-500 text-sm mt-1">Sale has been updated successfully!</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Current Sale Info -->
                <div class="glass-card rounded-2xl p-6 mb-6 bg-gradient-to-r from-blue-50 to-blue-25 border border-blue-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        <span>Current Sale Information</span>
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Invoice No</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($sale['invoice_no']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Original Total</p>
                            <p class="font-semibold text-gray-800">Rs <?php echo number_format($sale['total_amount'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Original Discount</p>
                            <p class="font-semibold text-gray-800">Rs <?php echo number_format($sale['discount'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Payment Method</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($sale['payment_method']); ?></p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="saleForm" class="space-y-6">
                    <!-- Sale Items -->
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                                <i class="fas fa-shopping-cart text-purple-500"></i>
                                <span>Sale Items</span>
                            </h3>
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-exclamation-circle text-yellow-500"></i>
                                Original stock has been restored for editing
                            </span>
                        </div>

                        <div id="itemsContainer">
                            <!-- Items will be loaded by JavaScript -->
                        </div>

                        <div class="mt-6">
                            <button type="button" onclick="addItemRow()"
                                class="px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2 font-semibold">
                                <i class="fas fa-plus-circle"></i>
                                <span>Add New Item</span>
                            </button>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-credit-card text-green-500"></i>
                            <span>Payment Information</span>
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Payment Method
                                </label>
                                <select name="payment_method" required
                                    class="w-full form-input px-4 py-3 rounded-lg">
                                    <option value="Cash" <?php echo $sale['payment_method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Card" <?php echo $sale['payment_method'] === 'Card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="Online" <?php echo $sale['payment_method'] === 'Online' ? 'selected' : ''; ?>>Online</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Discount (Rs )
                                </label>
                                <input type="number" name="discount" id="discount" min="0" step="0.01"
                                    value="<?php echo $sale['discount']; ?>"
                                    class="w-full form-input px-4 py-3 rounded-lg" oninput="updateTotal()">
                            </div>
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
                                <span id="subtotal" class="text-lg font-bold text-gray-800">Rs 0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Discount:</span>
                                <span id="discountDisplay" class="text-lg font-medium text-red-600">- Rs 0.00</span>
                            </div>
                            <div class="border-t border-gray-200 pt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-xl font-bold text-gray-800">Total Amount:</span>
                                    <span id="totalAmount" class="text-2xl font-bold text-green-600">Rs 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <button type="submit"
                            class="gradient-blue text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-save"></i>
                            <span>Update Sale</span>
                            <i class="fas fa-arrow-right text-blue-100 text-sm"></i>
                        </button>

                        <button type="button" onclick="resetForm()"
                            class="px-8 py-4 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-redo"></i>
                            <span>Reset Form</span>
                        </button>

                        <a href="sales.php"
                            class="px-8 py-4 border border-red-200 text-red-600 rounded-xl hover:bg-red-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>
                    </div>
                </form>
            </main>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            // Medicines data from PHP
            const medicines = [
                <?php
                if ($medicines_result) {
                    while ($medicine = mysqli_fetch_assoc($medicines_result)):
                ?> {
                            id: <?php echo $medicine['id']; ?>,
                            name: "<?php echo addslashes($medicine['name']); ?>",
                            generic: "<?php echo addslashes($medicine['generic_name']); ?>",
                            stock: <?php echo $medicine['total_stock']; ?>,
                            price: <?php echo $medicine['min_price'] ?: 0; ?>
                        },
                <?php
                    endwhile;
                }
                ?>
            ];

            // Existing items data from PHP
            const existingItems = <?php echo !empty($existing_items_array) ? json_encode($existing_items_array) : '[]'; ?>;

            let itemCount = 0;

            function initializeSelect2() {
                $('.medicine-select').select2({
                    placeholder: "Select Medicine",
                    allowClear: true,
                    width: '100%'
                });
            }

            function addItemRow(itemData = null) {
                const container = document.getElementById('itemsContainer');
                const itemId = itemCount++;

                const row = document.createElement('div');
                row.className = 'item-row bg-white p-4 rounded-lg mb-3 border border-gray-200';
                row.id = 'itemRow' + itemId;

                const isExistingItem = itemData !== null;
                const medicineId = isExistingItem ? itemData.medicine_id : '';
                const medicineName = isExistingItem ? itemData.medicine_name : '';
                const quantity = isExistingItem ? itemData.quantity : 1;
                const price = isExistingItem ? itemData.price : 0;
                const batchNo = isExistingItem ? itemData.batch_no : '';

                // Create hidden input fields for form submission
                const medicineIdInput = document.createElement('input');
                medicineIdInput.type = 'hidden';
                medicineIdInput.name = 'medicine_id[]';
                medicineIdInput.value = medicineId;

                const quantityInput = document.createElement('input');
                quantityInput.type = 'hidden';
                quantityInput.name = 'quantity[]';
                quantityInput.value = quantity;

                const priceInputField = document.createElement('input');
                priceInputField.type = 'hidden';
                priceInputField.name = 'price[]';
                priceInputField.value = price;

                row.innerHTML = `
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Medicine ${isExistingItem ? '<span class="text-xs text-green-600">(Original Item)</span>' : ''}
                            </label>
                            <select class="medicine-select w-full form-input px-3 py-2 rounded-lg"
                                    data-item-id="${itemId}"
                                    onchange="updateMedicineInfo(${itemId})" 
                                    required>
                                <option value="">Select Medicine</option>
                                ${medicines.map(med => `
                                    <option value="${med.id}" 
                                            data-stock="${med.stock}" 
                                            data-price="${med.price}"
                                            ${medicineId == med.id ? 'selected' : ''}>
                                        ${med.name} ${med.generic ? `(${med.generic})` : ''} 
                                        - Stock: ${med.stock} - Price: Rs  ${med.price.toFixed(2)}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                            <div class="flex items-center space-x-2">
                                <button type="button" onclick="changeQuantity(${itemId}, -1)" 
                                        class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200">
                                    <i class="fas fa-minus text-gray-600"></i>
                                </button>
                                <input type="number" 
                                    id="quantityDisplay${itemId}"
                                    min="1" 
                                    value="${quantity}"
                                    class="w-full form-input px-3 py-2 rounded-lg text-center"
                                    onchange="updateItemTotal(${itemId})" 
                                    oninput="updateItemTotal(${itemId})" 
                                    required>
                                <button type="button" onclick="changeQuantity(${itemId}, 1)" 
                                        class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Available: <span class="available-stock font-medium" id="availableStock${itemId}">0</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (Rs )</label>
                            <input type="number" 
                                id="priceDisplay${itemId}"
                                step="0.01" 
                                min="0" 
                                value="${price}"
                                class="w-full form-input px-3 py-2 rounded-lg"
                                onchange="updateItemTotal(${itemId})" 
                                oninput="updateItemTotal(${itemId})" 
                                required>
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="removeItemRow(${itemId})"
                                    class="w-full px-4 py-2.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition flex items-center justify-center space-x-2">
                                <i class="fas fa-trash"></i>
                                <span>Remove</span>
                            </button>
                        </div>
                    </div>
                    ${isExistingItem ? `
                    <div class="mt-2 text-xs text-blue-600 bg-blue-50 p-2 rounded border border-blue-100">
                        <i class="fas fa-info-circle mr-1"></i>
                        Original: ${medicineName} (Batch: ${batchNo}) - Quantity: ${quantity} @ Rs  ${price.toFixed(2)}
                    </div>
                    ` : ''}
                    <div class="mt-3 text-sm flex justify-between items-center">
                        <div>
                            <span class="text-gray-600">Item Total: </span>
                            <span class="item-total font-bold text-green-600 text-lg" id="itemTotal${itemId}">Rs  0.00</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Stock after sale: <span id="remainingStock${itemId}" class="font-medium">0</span>
                        </div>
                    </div>
                `;

                // Append hidden inputs
                row.appendChild(medicineIdInput);
                row.appendChild(quantityInput);
                row.appendChild(priceInputField);

                container.appendChild(row);

                // Initialize Select2 for this row
                initializeSelect2();

                // Update medicine info if existing item
                if (isExistingItem) {
                    updateMedicineInfo(itemId);
                }

                updateItemTotal(itemId);
            }

            function removeItemRow(itemId) {
                const row = document.getElementById('itemRow' + itemId);
                if (row) {
                    row.remove();
                    updateTotal();
                }
            }

            function changeQuantity(itemId, change) {
                const input = document.getElementById('quantityDisplay' + itemId);
                const hiddenInput = document.querySelector(`#itemRow${itemId} input[name="quantity[]"]`);
                const current = parseInt(input.value) || 0;
                const newValue = Math.max(1, current + change);
                input.value = newValue;
                hiddenInput.value = newValue;
                updateItemTotal(itemId);
            }

            function updateMedicineInfo(itemId) {
                const select = $(`select[data-item-id="${itemId}"]`);
                const selectedOption = select.find('option:selected');
                const priceDisplay = document.getElementById('priceDisplay' + itemId);
                const hiddenPriceInput = document.querySelector(`#itemRow${itemId} input[name="price[]"]`);
                const hiddenMedicineInput = document.querySelector(`#itemRow${itemId} input[name="medicine_id[]"]`);
                const availableStockSpan = document.getElementById('availableStock' + itemId);

                if (selectedOption.val()) {
                    const price = parseFloat(selectedOption.data('price')) || 0;
                    const stock = parseInt(selectedOption.data('stock')) || 0;

                    priceDisplay.value = price.toFixed(2);
                    hiddenPriceInput.value = price.toFixed(2);
                    hiddenMedicineInput.value = selectedOption.val();
                    availableStockSpan.textContent = stock;

                    // Update quantity if exceeds available stock
                    const quantityDisplay = document.getElementById('quantityDisplay' + itemId);
                    const hiddenQuantityInput = document.querySelector(`#itemRow${itemId} input[name="quantity[]"]`);
                    if (parseInt(quantityDisplay.value) > stock) {
                        quantityDisplay.value = Math.max(1, stock);
                        hiddenQuantityInput.value = Math.max(1, stock);
                    }
                } else {
                    priceDisplay.value = 0;
                    hiddenPriceInput.value = 0;
                    hiddenMedicineInput.value = 0;
                    availableStockSpan.textContent = 0;
                }

                updateItemTotal(itemId);
            }

            function updateItemTotal(itemId) {
                const quantityDisplay = document.getElementById('quantityDisplay' + itemId);
                const priceDisplay = document.getElementById('priceDisplay' + itemId);
                const quantity = parseFloat(quantityDisplay.value) || 0;
                const price = parseFloat(priceDisplay.value) || 0;
                const itemTotal = quantity * price;

                // Update hidden inputs
                const hiddenQuantityInput = document.querySelector(`#itemRow${itemId} input[name="quantity[]"]`);
                const hiddenPriceInput = document.querySelector(`#itemRow${itemId} input[name="price[]"]`);
                if (hiddenQuantityInput) hiddenQuantityInput.value = quantity;
                if (hiddenPriceInput) hiddenPriceInput.value = price;

                // Update item total display
                const itemTotalSpan = document.getElementById('itemTotal' + itemId);
                if (itemTotalSpan) {
                    itemTotalSpan.textContent = 'Rs  ' + itemTotal.toFixed(2);
                }

                // Update remaining stock
                const select = $(`select[data-item-id="${itemId}"]`);
                const selectedOption = select.find('option:selected');
                if (selectedOption.val()) {
                    const stock = parseInt(selectedOption.data('stock')) || 0;
                    const remainingStock = Math.max(0, stock - quantity);
                    const remainingStockSpan = document.getElementById('remainingStock' + itemId);
                    if (remainingStockSpan) {
                        remainingStockSpan.textContent = remainingStock;
                        remainingStockSpan.className = remainingStock < 0 ? 'font-medium text-red-600' : 'font-medium text-green-600';
                    }
                }

                updateTotal();
            }

            function updateTotal() {
                let subtotal = 0;

                // Calculate subtotal from all items
                document.querySelectorAll('.item-row').forEach((row) => {
                    const itemTotalSpan = row.querySelector('.item-total');
                    if (itemTotalSpan) {
                        const itemTotal = parseFloat(itemTotalSpan.textContent.replace('Rs  ', '')) || 0;
                        subtotal += itemTotal;
                    }
                });

                // Get discount
                const discount = parseFloat(document.getElementById('discount').value) || 0;

                // Calculate total
                const total = Math.max(0, subtotal - discount);

                // Update display
                document.getElementById('subtotal').textContent = 'Rs  ' + subtotal.toFixed(2);
                document.getElementById('discountDisplay').textContent = '- Rs  ' + discount.toFixed(2);
                document.getElementById('totalAmount').textContent = 'Rs  ' + total.toFixed(2);
            }

            function resetForm() {
                if (confirm('Are you sure you want to reset the form? All changes will be lost.')) {
                    document.getElementById('itemsContainer').innerHTML = '';
                    itemCount = 0;
                    existingItems.forEach(item => {
                        addItemRow(item);
                    });
                    document.getElementById('discount').value = <?php echo $sale['discount']; ?>;
                    updateTotal();
                }
            }

            // Form submission handler
            function validateForm() {
                const items = document.querySelectorAll('.item-row');
                if (items.length === 0) {
                    alert('Please add at least one item to the sale.');
                    return false;
                }

                let hasValidItem = false;
                let hasStockIssue = false;
                let hasEmptyMedicine = false;

                items.forEach(row => {
                    const select = row.querySelector('.medicine-select');
                    const quantity = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
                    const price = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;

                    if (select.value && quantity > 0 && price > 0) {
                        hasValidItem = true;

                        // Check stock
                        const selectedOption = select.options[select.selectedIndex];
                        const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                        if (quantity > stock) {
                            hasStockIssue = true;
                            alert(`Insufficient stock for ${selectedOption.text.split(' - ')[0]}. Available: ${stock}`);
                        }
                    } else if (select.value) {
                        hasEmptyMedicine = true;
                    }
                });

                if (hasEmptyMedicine) {
                    alert('Please ensure all medicine items have valid quantity and price.');
                    return false;
                }

                if (!hasValidItem) {
                    alert('Please add at least one valid item with quantity and price.');
                    return false;
                }

                if (hasStockIssue) {
                    return false;
                }

                return true;
            }

            // Initialize form with existing items
            document.addEventListener('DOMContentLoaded', function() {
                // Load existing items
                if (existingItems && existingItems.length > 0) {
                    existingItems.forEach(item => {
                        addItemRow(item);
                    });
                } else {
                    addItemRow();
                }

                // Update total when discount changes
                document.getElementById('discount').addEventListener('input', updateTotal);

                // Calculate initial total
                updateTotal();

                // Form validation on submit
                document.getElementById('saleForm').addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return false;
                    }
                    return true;
                });

                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    // Ctrl/Cmd + S to save
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                        e.preventDefault();
                        document.getElementById('saleForm').submit();
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
            });
        </script>
    </body>

    </html>