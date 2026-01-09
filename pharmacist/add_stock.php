<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

// Fetch all medicines with their generic names from the generics table
$medicines = mysqli_query($conn, "
    SELECT m.id, m.name, g.name as generic_name, c.name as category_name, t.name as type_name
    FROM medicines m
    LEFT JOIN medicine_generics g ON m.generic_id = g.id
    LEFT JOIN medicine_categories c ON m.category_id = c.id
    LEFT JOIN medicine_types t ON m.type_id = t.id
    ORDER BY m.name
");

// Fetch suppliers
$suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name");

$success = false;
$error = '';

if (isset($_POST['submit'])) {
    // Debug: Check what's being submitted
    error_log("POST data: " . print_r($_POST, true));

    $medicine_id = isset($_POST['medicine_id']) ? intval($_POST['medicine_id']) : 0;
    $batch_no = isset($_POST['batch_no']) ? mysqli_real_escape_string($conn, $_POST['batch_no']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $units_per_packet = isset($_POST['units_per_packet']) ? intval($_POST['units_per_packet']) : 1;
    $packets_per_box = isset($_POST['packets_per_box']) ? intval($_POST['packets_per_box']) : 1;

    // Get prices from POST data
    $purchase_price = 0;
    $selling_price = 0;
    $mrp = 0;

    // Debug log price values
    error_log("Purchase price raw: " . ($_POST['purchase_price'] ?? ''));
    error_log("Selling price raw: " . ($_POST['selling_price'] ?? ''));
    error_log("MRP raw: " . ($_POST['mrp'] ?? ''));

    // Validate and convert prices
    if (isset($_POST['purchase_price']) && trim($_POST['purchase_price']) !== '') {
        $purchase_price = floatval($_POST['purchase_price']);
    }

    if (isset($_POST['selling_price']) && trim($_POST['selling_price']) !== '') {
        $selling_price = floatval($_POST['selling_price']);
    }

    if (isset($_POST['mrp']) && trim($_POST['mrp']) !== '') {
        $mrp = floatval($_POST['mrp']);
    }

    // Debug log converted prices
    error_log("Purchase price converted: " . $purchase_price);
    error_log("Selling price converted: " . $selling_price);
    error_log("MRP converted: " . $mrp);

    $supplier_id = (!empty($_POST['supplier_id']) && $_POST['supplier_id'] != '')
        ? intval($_POST['supplier_id'])
        : "NULL";

    $received_date = isset($_POST['received_date']) ? $_POST['received_date'] : NULL;
    $expiry_date   = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    $location      = isset($_POST['location']) ? mysqli_real_escape_string($conn, $_POST['location']) : '';

    // Validate required fields
    if ($medicine_id <= 0) {
        $error = "Please select a medicine";
    } elseif (empty($batch_no)) {
        $error = "Batch number is required";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than 0";
    } elseif ($units_per_packet <= 0) {
        $error = "Units per packet must be greater than 0";
    } elseif ($packets_per_box <= 0) {
        $error = "Packets per box must be greater than 0";
    } elseif ($purchase_price <= 0) {
        $error = "Purchase price must be greater than 0";
    } elseif ($selling_price <= 0) {
        $error = "Selling price must be greater than 0";
    } elseif ($mrp <= 0) {
        $error = "MRP must be greater than 0";
    } else {
        // Check if batch number already exists
        $check_query = "SELECT id FROM stock_batches WHERE batch_no = '$batch_no'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Batch number '$batch_no' already exists. Please use a different batch number or generate a new one.";
        } else {
            // Build query with proper NULL handling
            $query = "
                INSERT INTO stock_batches 
                (medicine_id, batch_no, quantity, units_per_packet, packets_per_box, purchase_price, selling_price, mrp, supplier_id, received_date, expiry_date, location, is_expired, added_at, is_returned) 
                VALUES 
                (
                    $medicine_id,
                    '$batch_no',
                    $quantity,
                    $units_per_packet,
                    $packets_per_box,
                    $purchase_price,
                    $selling_price,
                    $mrp,
                    $supplier_id,
                    " . ($received_date ? "'$received_date'" : "NULL") . ",
                    " . ($expiry_date ? "'$expiry_date'" : "NULL") . ",
                    '$location',
                    0,
                    NOW(),
                    0
                )
            ";

            error_log("SQL Query: " . $query); // Debug SQL

            if (mysqli_query($conn, $query)) {
                $success = true;

                // Clear POST data on success to show empty form
                $_POST = array();
            } else {
                $error = "Database error: " . mysqli_error($conn);
                error_log("Database error: " . mysqli_error($conn));
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Stock Batch - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            --accent-green: #10b981;
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

        .glass-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
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

        .yellow-blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.2;
            z-index: -1;
        }

        .green-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-green), #059669);
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
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

        .price-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(16, 185, 129, 0.2);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.1);
        }

        .required::after {
            content: " *";
            color: #ef4444;
        }

        .batch-generator {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        .searchable-dropdown {
            position: relative;
        }

        .searchable-dropdown input {
            cursor: pointer;
        }

        .dropdown-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }

        .dropdown-options.active {
            display: block;
        }

        .dropdown-option {
            padding: 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f3f4f6;
        }

        .dropdown-option:last-child {
            border-bottom: none;
        }

        .dropdown-option:hover {
            background: #f3f4f6;
        }

        .dropdown-option.selected {
            background: #fef3c7;
        }

        .medicine-info {
            display: flex;
            flex-direction: column;
        }

        .medicine-name {
            font-weight: 600;
            color: #374151;
        }

        .medicine-details {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .dropdown-search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="green-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Add <span class="gradient-text">Stock Batch</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-plus-circle text-green-500"></i>
                            <span>Add a new stock batch to the inventory</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span><?php echo ucfirst($_SESSION['role']); ?> Access</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0">
                        <a href="stock.php"
                            class="px-6 py-3 border border-green-200 text-gray-700 rounded-xl hover:bg-green-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-green-500"></i>
                            <span>Back to Stock</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <?php if ($success): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-green-50 to-green-25 border border-green-200 animate-fade-in-up">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-green-800">Success!</h3>
                            <p class="text-green-600">Stock batch added successfully. <a href="add_stock.php" class="font-medium underline">Add another batch</a> or <a href="stock.php" class="font-medium underline">view all stock</a>.</p>
                        </div>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-red-50 to-red-25 border border-red-200 animate-fade-in-up">
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

            <!-- Main Form -->
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-pills text-blue-500"></i>
                                <span>Medicine Information</span>
                            </h3>
                            <div class="space-y-4">
                                <div class="searchable-dropdown">
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-capsules text-gray-400 mr-1"></i>
                                        Select Medicine
                                    </label>
                                    <input type="hidden" name="medicine_id" id="medicine_id" required
                                        value="<?php echo isset($_POST['medicine_id']) ? htmlspecialchars($_POST['medicine_id']) : ''; ?>">
                                    <input type="text" id="medicine_search"
                                        placeholder="Search medicine by name, generic, or category..."
                                        value="<?php echo isset($_POST['medicine_search']) ? htmlspecialchars($_POST['medicine_search']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 pr-10 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500"
                                        autocomplete="off">
                                    <div class="dropdown-search-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <div class="dropdown-options" id="medicine_options"></div>
                                    <div id="selected_medicine_info" class="mt-2 hidden">
                                        <div class="bg-blue-50 p-3 rounded-lg">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-semibold text-gray-800" id="selected_medicine_name"></h4>
                                                    <p class="text-sm text-gray-600" id="selected_medicine_details"></p>
                                                </div>
                                                <button type="button" onclick="clearMedicineSelection()"
                                                    class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-box text-purple-500"></i>
                                <span>Batch Details</span>
                            </h3>
                            <div class="space-y-4">
                                <div class="batch-generator rounded-xl p-4 mb-4">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-3">
                                        <div>
                                            <h4 class="font-semibold text-blue-700 flex items-center space-x-2">
                                                <i class="fas fa-magic text-blue-500"></i>
                                                <span>Auto Generate Batch Number</span>
                                            </h4>
                                            <p class="text-sm text-blue-600">Generate unique batch number based on medicine and date</p>
                                        </div>
                                        <button type="button" id="generate-batch-btn"
                                            class="mt-2 md:mt-0 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition flex items-center space-x-2">
                                            <i class="fas fa-bolt"></i>
                                            <span>Generate Batch No</span>
                                        </button>
                                    </div>

                                    <div id="batch-explanation" class="text-sm text-gray-600 bg-blue-50 p-3 rounded-lg hidden">
                                        <div class="font-medium text-blue-700 mb-1">Batch Number Format:</div>
                                        <div id="explanation-details"></div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-hashtag text-gray-400 mr-1"></i>
                                        Batch Number
                                    </label>
                                    <div class="flex space-x-2">
                                        <input type="text" name="batch_no" id="batch_no" required
                                            placeholder="Click 'Generate Batch No' or enter manually"
                                            value="<?php echo isset($_POST['batch_no']) ? htmlspecialchars($_POST['batch_no']) : ''; ?>"
                                            class="flex-1 form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                        <button type="button" onclick="clearBatchNumber()"
                                            class="px-4 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium flex items-center space-x-2"
                                            title="Clear batch number">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Format: MED-0126-001 (Medicine code + MonthYear + Sequence)</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                            <i class="fas fa-boxes text-gray-400 mr-1"></i>
                                            Total Quantity (Units)
                                        </label>
                                        <input type="number" name="quantity" min="1" required
                                            placeholder="Total units"
                                            value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                            <i class="fas fa-box text-gray-400 mr-1"></i>
                                            Units per Packet
                                        </label>
                                        <input type="number" name="units_per_packet" min="1" required
                                            placeholder="e.g., 10"
                                            value="<?php echo isset($_POST['units_per_packet']) ? htmlspecialchars($_POST['units_per_packet']) : '1'; ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                            <i class="fas fa-layer-group text-gray-400 mr-1"></i>
                                            Packets per Box
                                        </label>
                                        <input type="number" name="packets_per_box" min="1" required
                                            placeholder="e.g., 12"
                                            value="<?php echo isset($_POST['packets_per_box']) ? htmlspecialchars($_POST['packets_per_box']) : '1'; ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-calculator text-gray-400 mr-1"></i>
                                            Total Packets (Auto)
                                        </label>
                                        <input type="text" id="total_packets" readonly
                                            class="w-full form-input px-4 py-3 rounded-lg bg-gray-50">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                            Storage Location
                                        </label>
                                        <input type="text" name="location"
                                            placeholder="e.g., Shelf A1, Refrigerator"
                                            value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-calendar-alt text-yellow-500"></i>
                                <span>Date Information</span>
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-calendar-plus text-gray-400 mr-1"></i>
                                        Received Date
                                    </label>
                                    <input type="date" name="received_date" required
                                        value="<?php echo isset($_POST['received_date']) ? htmlspecialchars($_POST['received_date']) : date('Y-m-d'); ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-calendar-times text-gray-400 mr-1"></i>
                                        Expiry Date
                                    </label>
                                    <input type="date" name="expiry_date" required
                                        value="<?php echo isset($_POST['expiry_date']) ? htmlspecialchars($_POST['expiry_date']) : date('Y-m-d', strtotime('+1 year')); ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-truck text-teal-500"></i>
                                <span>Supplier Information</span>
                            </h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-building text-gray-400 mr-1"></i>
                                    Select Supplier (Optional)
                                </label>
                                <select name="supplier_id"
                                    class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                    <option value="">No supplier selected</option>
                                    <?php
                                    $suppliers_result = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name");
                                    while ($supp = mysqli_fetch_assoc($suppliers_result)): ?>
                                        <option value="<?php echo $supp['id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supp['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Price Information & Preview -->
                    <div class="space-y-6">
                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-tag text-green-500"></i>
                                <span>Price Information</span>
                            </h3>
                            <div class="space-y-4">
                                <div class="price-card rounded-xl p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-shopping-cart text-blue-500 mr-1"></i>
                                        Purchase Price (Per Unit) (Rs)
                                    </label>
                                    <input type="number" name="purchase_price" step="0.01" min="0" required
                                        placeholder="0.00"
                                        value="<?php echo isset($_POST['purchase_price']) ? htmlspecialchars($_POST['purchase_price']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500"
                                        id="purchase_price">
                                    <p class="text-xs text-gray-500 mt-1">Cost price per unit of medicine</p>
                                </div>

                                <div class="price-card rounded-xl p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-cash-register text-green-500 mr-1"></i>
                                        Selling Price (Per Unit) (Rs)
                                    </label>
                                    <input type="number" name="selling_price" step="0.01" min="0" required
                                        placeholder="0.00"
                                        value="<?php echo isset($_POST['selling_price']) ? htmlspecialchars($_POST['selling_price']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500"
                                        id="selling_price">
                                    <p class="text-xs text-gray-500 mt-1">Selling price per unit to customers</p>
                                </div>

                                <div class="price-card rounded-xl p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-tags text-purple-500 mr-1"></i>
                                        MRP (Per Unit) (Rs)
                                    </label>
                                    <input type="number" name="mrp" step="0.01" min="0" required
                                        placeholder="0.00"
                                        value="<?php echo isset($_POST['mrp']) ? htmlspecialchars($_POST['mrp']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500"
                                        id="mrp">
                                    <p class="text-xs text-gray-500 mt-1">Maximum Retail Price per unit printed on package</p>
                                </div>

                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-700 mb-2">Price Summary (Per Unit)</h4>
                                    <div class="grid grid-cols-3 gap-2 text-sm">
                                        <div class="text-center p-2 bg-blue-50 rounded">
                                            <div class="font-medium text-blue-700">Purchase</div>
                                            <div class="text-blue-600" id="purchase_summary">Rs 0.00</div>
                                        </div>
                                        <div class="text-center p-2 bg-green-50 rounded">
                                            <div class="font-medium text-green-700">Selling</div>
                                            <div class="text-green-600" id="selling_summary">Rs 0.00</div>
                                        </div>
                                        <div class="text-center p-2 bg-purple-50 rounded">
                                            <div class="font-medium text-purple-700">MRP</div>
                                            <div class="text-purple-600" id="mrp_summary">Rs 0.00</div>
                                        </div>
                                    </div>
                                    <div class="mt-3 pt-3 border-t border-gray-200">
                                        <div class="text-sm text-gray-600">
                                            <div class="flex justify-between mb-1">
                                                <span>Profit Margin:</span>
                                                <span id="profit_margin" class="font-medium text-green-600">0%</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Discount on MRP:</span>
                                                <span id="mrp_discount" class="font-medium text-blue-600">0%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-lightbulb text-yellow-500"></i>
                                <span>Quick Tips</span>
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                                    <i class="fas fa-info-circle text-yellow-600 mt-1"></i>
                                    <div>
                                        <p class="text-sm font-medium text-yellow-800">Batch Number</p>
                                        <p class="text-xs text-yellow-600">Use a unique identifier for tracking</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                                    <i class="fas fa-calculator text-blue-600 mt-1"></i>
                                    <div>
                                        <p class="text-sm font-medium text-blue-800">Pricing Strategy</p>
                                        <p class="text-xs text-blue-600">Selling price should be higher than purchase price</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                                    <i class="fas fa-clock text-green-600 mt-1"></i>
                                    <div>
                                        <p class="text-sm font-medium text-green-800">Expiry Dates</p>
                                        <p class="text-xs text-green-600">Regularly check and update expiry information</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex space-x-4 mt-6">
                    <button type="submit" name="submit"
                        class="gradient-green text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                        <i class="fas fa-plus"></i>
                        <span>Add Stock Batch</span>
                        <i class="fas fa-arrow-right text-green-100 text-sm"></i>
                    </button>

                    <a href="stock.php"
                        class="px-8 py-4 border border-green-200 text-gray-700 rounded-xl hover:bg-green-50 transition font-bold flex items-center space-x-2 shadow-sm">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </a>

                    <button type="button" onclick="clearForm()"
                        class="px-8 py-4 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2 shadow-sm">
                        <i class="fas fa-redo"></i>
                        <span>Clear Form</span>
                    </button>
                </div>
            </form>
        </main>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Store medicine data from PHP
        const medicinesData = [
            <?php
            mysqli_data_seek($medicines, 0);
            while ($med = mysqli_fetch_assoc($medicines)):
                $displayName = htmlspecialchars($med['name']) .
                    (!empty($med['generic_name']) ? " (" . htmlspecialchars($med['generic_name']) . ")" : "") .
                    (!empty($med['category_name']) ? " - " . htmlspecialchars($med['category_name']) : "");
            ?> {
                    id: <?php echo $med['id']; ?>,
                    name: "<?php echo htmlspecialchars($med['name']); ?>",
                    generic_name: "<?php echo htmlspecialchars($med['generic_name'] ?? ''); ?>",
                    category_name: "<?php echo htmlspecialchars($med['category_name'] ?? ''); ?>",
                    type_name: "<?php echo htmlspecialchars($med['type_name'] ?? ''); ?>",
                    display_name: "<?php echo $displayName; ?>"
                },
            <?php endwhile; ?>
        ];

        // Medicine search functionality
        const medicineSearchInput = document.getElementById('medicine_search');
        const medicineIdInput = document.getElementById('medicine_id');
        const medicineOptionsDiv = document.getElementById('medicine_options');
        const selectedMedicineInfo = document.getElementById('selected_medicine_info');
        const selectedMedicineName = document.getElementById('selected_medicine_name');
        const selectedMedicineDetails = document.getElementById('selected_medicine_details');
        const generateBatchBtn = document.getElementById('generate-batch-btn');

        let selectedMedicine = null;

        // Populate medicine dropdown on focus
        medicineSearchInput.addEventListener('focus', function() {
            populateMedicineOptions('');
            medicineOptionsDiv.classList.add('active');
        });

        // Filter medicines on input
        medicineSearchInput.addEventListener('input', function() {
            populateMedicineOptions(this.value);
        });

        // Select medicine option
        function selectMedicine(medicine) {
            selectedMedicine = medicine;
            medicineIdInput.value = medicine.id;
            medicineSearchInput.value = medicine.display_name;
            medicineOptionsDiv.classList.remove('active');

            // Show selected medicine info
            selectedMedicineName.textContent = medicine.name;
            let details = [];
            if (medicine.generic_name) details.push(`Generic: ${medicine.generic_name}`);
            if (medicine.category_name) details.push(`Category: ${medicine.category_name}`);
            if (medicine.type_name) details.push(`Type: ${medicine.type_name}`);
            selectedMedicineDetails.textContent = details.join(' | ');
            selectedMedicineInfo.classList.remove('hidden');

            // Enable batch generation
            generateBatchBtn.disabled = false;

            // Auto-generate batch number if field is empty
            const batchNoInput = document.getElementById('batch_no');
            if (!batchNoInput.value) {
                setTimeout(generateBatchNumber, 300);
            }
        }

        // Populate medicine options
        function populateMedicineOptions(searchTerm) {
            medicineOptionsDiv.innerHTML = '';

            const filtered = medicinesData.filter(medicine =>
                medicine.display_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                medicine.generic_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                medicine.category_name.toLowerCase().includes(searchTerm.toLowerCase())
            );

            if (filtered.length === 0) {
                medicineOptionsDiv.innerHTML = `
                    <div class="dropdown-option p-3 text-gray-500 text-center">
                        No medicines found
                    </div>`;
                return;
            }

            filtered.forEach(medicine => {
                const div = document.createElement('div');
                div.className = 'dropdown-option';
                div.dataset.id = medicine.id;

                const medicineDiv = document.createElement('div');
                medicineDiv.className = 'medicine-info';

                const nameSpan = document.createElement('span');
                nameSpan.className = 'medicine-name';
                nameSpan.textContent = medicine.name;

                const detailsSpan = document.createElement('span');
                detailsSpan.className = 'medicine-details';
                detailsSpan.textContent =
                    (medicine.generic_name ? medicine.generic_name + ' • ' : '') +
                    (medicine.category_name ? medicine.category_name + ' • ' : '') +
                    (medicine.type_name || '');

                medicineDiv.appendChild(nameSpan);
                medicineDiv.appendChild(detailsSpan);
                div.appendChild(medicineDiv);

                div.addEventListener('click', () => selectMedicine(medicine));

                // Mark as selected if already chosen
                if (parseInt(medicineIdInput.value) === medicine.id) {
                    div.classList.add('selected');
                    medicineSearchInput.value = medicine.display_name;
                    selectMedicine(medicine);
                }

                medicineOptionsDiv.appendChild(div);
            });
        }

        // Clear medicine selection
        function clearMedicineSelection() {
            selectedMedicine = null;
            medicineIdInput.value = '';
            medicineSearchInput.value = '';
            selectedMedicineInfo.classList.add('hidden');
            generateBatchBtn.disabled = true;
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!medicineSearchInput.contains(event.target) && !medicineOptionsDiv.contains(event.target)) {
                medicineOptionsDiv.classList.remove('active');
                if (selectedMedicine) {
                    medicineSearchInput.value = selectedMedicine.display_name;
                }
            }
        });

        // Load initial medicine if exists
        const initialMedicineId = medicineIdInput.value;
        if (initialMedicineId) {
            const medicine = medicinesData.find(m => m.id == initialMedicineId);
            if (medicine) {
                selectMedicine(medicine);
            }
        }

        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            });

            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
        }

        // Batch number generation
        const batchNumberInput = document.getElementById('batch_no');
        const batchExplanation = document.getElementById('batch-explanation');
        const explanationDetails = document.getElementById('explanation-details');

        // Generate batch number function
        function generateBatchNumber() {
            if (!selectedMedicine) {
                alert('Please select a medicine first');
                medicineSearchInput.focus();
                return;
            }

            const medicineName = selectedMedicine.name;
            const medicineCode = medicineName.substring(0, 3).toUpperCase().replace(/\s/g, 'X');
            const now = new Date();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = String(now.getFullYear()).slice(-2);

            // Generate a random sequence number (001-999)
            const sequence = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');

            const batchNo = `BATCH-${medicineCode}-${month}${year}-${sequence}`;

            batchNumberInput.value = batchNo;

            // Show explanation
            explanationDetails.innerHTML = `
                <div><strong>${batchNo}</strong></div>
                <div class="mt-2 grid grid-cols-2 gap-1 text-xs">
                    <div class="text-blue-600">BATCH</div>
                    <div>Batch identifier</div>
                    <div class="text-blue-600">${medicineCode}</div>
                    <div>Medicine code (${medicineName.substring(0, 3).toUpperCase()})</div>
                    <div class="text-blue-600">${month}${year}</div>
                    <div>Month (${month}) and Year (${year})</div>
                    <div class="text-blue-600">${sequence}</div>
                    <div>Sequence number</div>
                </div>
            `;
            batchExplanation.classList.remove('hidden');

            // Show success notification
            showNotification('Batch number generated successfully!', 'success');
        }

        // Clear batch number function
        function clearBatchNumber() {
            batchNumberInput.value = '';
            batchExplanation.classList.add('hidden');
            batchNumberInput.focus();
        }

        // Event listeners
        generateBatchBtn.addEventListener('click', generateBatchNumber);

        // Calculate total packets
        const quantityInput = document.querySelector('input[name="quantity"]');
        const unitsPerPacketInput = document.querySelector('input[name="units_per_packet"]');
        const packetsPerBoxInput = document.querySelector('input[name="packets_per_box"]');
        const totalPacketsInput = document.getElementById('total_packets');

        function calculateTotalPackets() {
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitsPerPacket = parseFloat(unitsPerPacketInput.value) || 1;

            if (quantity > 0 && unitsPerPacket > 0) {
                const totalPackets = Math.ceil(quantity / unitsPerPacket);
                totalPacketsInput.value = `${totalPackets} packets`;
            } else {
                totalPacketsInput.value = '';
            }
        }

        if (quantityInput && unitsPerPacketInput && totalPacketsInput) {
            quantityInput.addEventListener('input', calculateTotalPackets);
            unitsPerPacketInput.addEventListener('input', calculateTotalPackets);
            // Initial calculation
            calculateTotalPackets();
        }

        // Price calculation and validation
        const purchasePriceInput = document.getElementById('purchase_price');
        const sellingPriceInput = document.getElementById('selling_price');
        const mrpInput = document.getElementById('mrp');

        const purchaseSummary = document.getElementById('purchase_summary');
        const sellingSummary = document.getElementById('selling_summary');
        const mrpSummary = document.getElementById('mrp_summary');
        const profitMarginSpan = document.getElementById('profit_margin');
        const mrpDiscountSpan = document.getElementById('mrp_discount');

        // Function to update price summaries
        function updatePriceSummaries() {
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;
            const mrp = parseFloat(mrpInput.value) || 0;

            purchaseSummary.textContent = 'Rs ' + purchasePrice.toFixed(2);
            sellingSummary.textContent = 'Rs ' + sellingPrice.toFixed(2);
            mrpSummary.textContent = 'Rs ' + mrp.toFixed(2);

            // Calculate profit margin
            if (purchasePrice > 0 && sellingPrice > 0) {
                const profitMargin = ((sellingPrice - purchasePrice) / purchasePrice * 100).toFixed(1);
                profitMarginSpan.textContent = profitMargin + '%';
                profitMarginSpan.className = profitMargin >= 0 ? 'font-medium text-green-600' : 'font-medium text-red-600';
            } else {
                profitMarginSpan.textContent = '0%';
            }

            // Calculate MRP discount
            if (mrp > 0 && sellingPrice > 0) {
                const discount = ((mrp - sellingPrice) / mrp * 100).toFixed(1);
                mrpDiscountSpan.textContent = discount + '%';
                mrpDiscountSpan.className = discount >= 0 ? 'font-medium text-blue-600' : 'font-medium text-red-600';
            } else {
                mrpDiscountSpan.textContent = '0%';
            }

            // Validate pricing logic
            if (purchasePrice > 0 && sellingPrice > 0) {
                if (sellingPrice < purchasePrice) {
                    sellingPriceInput.style.borderColor = '#ef4444';
                    sellingPriceInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                } else {
                    sellingPriceInput.style.borderColor = '';
                    sellingPriceInput.style.boxShadow = '';
                }
            }

            if (sellingPrice > 0 && mrp > 0 && mrp < sellingPrice) {
                mrpInput.style.borderColor = '#ef4444';
                mrpInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                mrpInput.style.borderColor = '';
                mrpInput.style.boxShadow = '';
            }
        }

        // Auto-calculate selling price and MRP based on purchase price
        if (purchasePriceInput && sellingPriceInput && mrpInput) {
            purchasePriceInput.addEventListener('input', function() {
                const purchasePrice = parseFloat(this.value);
                if (!isNaN(purchasePrice) && purchasePrice > 0) {
                    // Auto-set selling price as 120% of purchase price if empty or too low
                    const currentSelling = parseFloat(sellingPriceInput.value) || 0;
                    const suggestedSelling = purchasePrice * 1.2;
                    if (currentSelling === 0 || currentSelling < suggestedSelling) {
                        sellingPriceInput.value = suggestedSelling.toFixed(2);
                    }

                    // Auto-set MRP as 130% of purchase price if empty or too low
                    const currentMRP = parseFloat(mrpInput.value) || 0;
                    const suggestedMRP = purchasePrice * 1.3;
                    if (currentMRP === 0 || currentMRP < suggestedMRP) {
                        mrpInput.value = suggestedMRP.toFixed(2);
                    }

                    updatePriceSummaries();
                }
            });

            sellingPriceInput.addEventListener('input', updatePriceSummaries);
            mrpInput.addEventListener('input', updatePriceSummaries);

            // Initial update
            updatePriceSummaries();
        }

        // Validate expiry date is after received date
        const receivedDateInput = document.querySelector('input[name="received_date"]');
        const expiryDateInput = document.querySelector('input[name="expiry_date"]');

        if (receivedDateInput && expiryDateInput) {
            expiryDateInput.addEventListener('change', function() {
                const receivedDate = new Date(receivedDateInput.value);
                const expiryDate = new Date(this.value);

                if (expiryDate <= receivedDate) {
                    alert('Expiry date must be after the received date!');
                    this.value = '';
                    this.focus();
                }
            });
        }

        // Clear form function
        function clearForm() {
            if (confirm('Are you sure you want to clear all form fields?')) {
                document.querySelector('form').reset();
                clearMedicineSelection();
                batchExplanation.classList.add('hidden');
                updatePriceSummaries();
                calculateTotalPackets();
            }
        }

        // Form validation before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const medicineId = medicineIdInput.value;
            const batchNo = batchNumberInput.value.trim();
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitsPerPacket = parseFloat(unitsPerPacketInput.value) || 0;
            const packetsPerBox = parseFloat(packetsPerBoxInput.value) || 0;
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;
            const mrp = parseFloat(mrpInput.value) || 0;

            if (!medicineId) {
                e.preventDefault();
                alert('Please select a medicine');
                medicineSearchInput.focus();
                return false;
            }

            if (!batchNo) {
                e.preventDefault();
                alert('Please enter or generate a batch number');
                batchNumberInput.focus();
                return false;
            }

            if (quantity <= 0) {
                e.preventDefault();
                alert('Please enter a valid quantity (greater than 0)');
                quantityInput.focus();
                return false;
            }

            if (unitsPerPacket <= 0) {
                e.preventDefault();
                alert('Please enter valid units per packet (greater than 0)');
                unitsPerPacketInput.focus();
                return false;
            }

            if (packetsPerBox <= 0) {
                e.preventDefault();
                alert('Please enter valid packets per box (greater than 0)');
                packetsPerBoxInput.focus();
                return false;
            }

            if (purchasePrice <= 0) {
                e.preventDefault();
                alert('Please enter a valid purchase price (greater than 0)');
                purchasePriceInput.focus();
                return false;
            }

            if (sellingPrice <= 0) {
                e.preventDefault();
                alert('Please enter a valid selling price (greater than 0)');
                sellingPriceInput.focus();
                return false;
            }

            if (mrp <= 0) {
                e.preventDefault();
                alert('Please enter a valid MRP (greater than 0)');
                mrpInput.focus();
                return false;
            }

            if (sellingPrice < purchasePrice) {
                e.preventDefault();
                if (!confirm('Warning: Selling price is lower than purchase price. This will result in a loss. Continue anyway?')) {
                    sellingPriceInput.focus();
                    return false;
                }
            }

            if (mrp < sellingPrice) {
                e.preventDefault();
                if (!confirm('Warning: MRP is lower than selling price. This is unusual. Continue anyway?')) {
                    mrpInput.focus();
                    return false;
                }
            }

            return true;
        });

        // Show notification on successful submission
        <?php if ($success): ?>
            setTimeout(() => {
                showNotification('Stock batch added successfully!', 'success');
            }, 100);
        <?php endif; ?>

        // Show notification function
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
            notification.className = `fixed top-6 right-6 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-2xl transform translate-x-full transition-transform duration-300 z-50`;
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="fas ${icons[type]} text-lg"></i>
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

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Auto-generate batch number if medicine is already selected (from previous submission error)
            const medicineId = medicineIdInput.value;
            if (medicineId && !batchNumberInput.value) {
                setTimeout(() => {
                    const medicine = medicinesData.find(m => m.id == medicineId);
                    if (medicine) {
                        selectMedicine(medicine);
                        setTimeout(generateBatchNumber, 500);
                    }
                }, 500);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + G to generate batch number
            if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                e.preventDefault();
                generateBatchNumber();
            }

            // Ctrl/Cmd + S to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[type="submit"]').click();
            }

            // Escape to go back
            if (e.key === 'Escape') {
                window.location.href = 'stock.php';
            }

            // Alt + C to clear form
            if (e.altKey && e.key === 'c') {
                e.preventDefault();
                clearForm();
            }

            // Alt + R to clear batch number
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                clearBatchNumber();
            }
        });
    </script>
</body>

</html>