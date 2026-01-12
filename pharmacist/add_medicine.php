<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacist can add medicines
if ($_SESSION['role'] !== 'pharmacist') {
    $_SESSION['error'] = "You don't have permission to add medicines";
    header("Location: medicines.php");
    exit;
}

$success = false;
$error = '';

// Handle modal form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which form was submitted
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name'] ?? '');
        $category_desc = trim($_POST['category_description'] ?? '');

        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO medicine_categories (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $category_name, $category_desc);
            if ($stmt->execute()) {
                $_SESSION['modal_success'] = "Category added successfully!";
            } else {
                $_SESSION['modal_error'] = "Failed to add category: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_type'])) {
        $type_name = trim($_POST['type_name'] ?? '');
        $type_desc = trim($_POST['type_description'] ?? '');

        if (!empty($type_name)) {
            $stmt = $conn->prepare("INSERT INTO medicine_types (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $type_name, $type_desc);
            if ($stmt->execute()) {
                $_SESSION['modal_success'] = "Type added successfully!";
            } else {
                $_SESSION['modal_error'] = "Failed to add type: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_generic'])) {
        $generic_name = trim($_POST['generic_name'] ?? '');

        if (!empty($generic_name)) {
            $stmt = $conn->prepare("INSERT INTO medicine_generics (name, created_at) VALUES (?, NOW())");
            $stmt->bind_param('s', $generic_name);
            if ($stmt->execute()) {
                $_SESSION['modal_success'] = "Generic added successfully!";
            } else {
                $_SESSION['modal_error'] = "Failed to add generic: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Handle main medicine form submission
    if (isset($_POST['submit'])) {
        $name = trim($_POST['name'] ?? '');
        $generic_id = intval($_POST['generic_id'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $type_id = intval($_POST['type_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        // Stock details
        $add_stock = isset($_POST['add_stock']) ? 1 : 0;
        $batch_no = trim($_POST['batch_no'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        $units_per_packet = intval($_POST['units_per_packet'] ?? 1);
        $packets_per_box = intval($_POST['packets_per_box'] ?? 1);
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $selling_price = floatval($_POST['selling_price'] ?? 0);
        $mrp = floatval($_POST['mrp'] ?? 0);
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $received_date = $_POST['received_date'] ?? date('Y-m-d');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $location = trim($_POST['location'] ?? '');

        // Validate required fields
        if (empty($name) || empty($generic_id) || empty($category_id) || empty($type_id)) {
            $error = "Please fill in all required fields.";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert medicine
                $stmt = $conn->prepare("
                    INSERT INTO medicines (name, generic_id, category_id, type_id, description, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                if ($stmt) {
                    $stmt->bind_param('siiis', $name, $generic_id, $category_id, $type_id, $description);

                    if ($stmt->execute()) {
                        $new_medicine_id = $stmt->insert_id;
                        $success = true;
                        
                        // If stock should be added
                        if ($add_stock && !empty($batch_no) && $quantity > 0 && !empty($expiry_date)) {
                            // Validate stock fields
                            if ($selling_price <= 0 || $mrp <= 0) {
                                throw new Exception("Selling price and MRP must be greater than 0.");
                            }
                            
                            if ($expiry_date <= date('Y-m-d')) {
                                throw new Exception("Expiry date must be in the future.");
                            }
                            
                            // Insert stock batch
                            $stock_stmt = $conn->prepare("
                                INSERT INTO stock_batches (
                                    medicine_id, batch_no, quantity, units_per_packet, packets_per_box,
                                    purchase_price, selling_price, mrp, supplier_id, received_date,
                                    expiry_date, location, is_expired, added_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
                            ");
                            
                            if ($stock_stmt) {
                                $stock_stmt->bind_param(
                                    'isiiidddissss',
                                    $new_medicine_id,
                                    $batch_no,
                                    $quantity,
                                    $units_per_packet,
                                    $packets_per_box,
                                    $purchase_price,
                                    $selling_price,
                                    $mrp,
                                    $supplier_id,
                                    $received_date,
                                    $expiry_date,
                                    $location
                                );
                                
                                if (!$stock_stmt->execute()) {
                                    throw new Exception("Failed to add stock: " . $stock_stmt->error);
                                }
                                $stock_stmt->close();
                            } else {
                                throw new Exception("Failed to prepare stock statement.");
                            }
                        }
                        
                        // Commit transaction
                        mysqli_commit($conn);
                        
                        header("Location: add_medicine.php?success=1&medicine_id=" . $new_medicine_id . "&stock_added=" . ($add_stock ? '1' : '0'));
                        exit;
                    } else {
                        throw new Exception("Database error: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $error = $e->getMessage();
            }
        }
    }
}

// Handle success via redirect (PRG pattern)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
    $new_medicine_id = intval($_GET['medicine_id'] ?? 0);
    $stock_added = isset($_GET['stock_added']) && $_GET['stock_added'] == '1';
}

// Fetch categories, types, generics, and suppliers
$categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
$types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
$generics = mysqli_query($conn, "SELECT id, name FROM medicine_generics ORDER BY name");
// Option 1: Just fetch all suppliers
$suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name");

// Option 2: If your table has an 'is_active' column (or similar) for active suppliers
// $suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");

// Store data for JavaScript
$category_data = [];
$type_data = [];
$generic_data = [];
$supplier_data = [];

while ($cat = mysqli_fetch_assoc($categories)) {
    $category_data[] = $cat;
}
mysqli_data_seek($categories, 0);

while ($type = mysqli_fetch_assoc($types)) {
    $type_data[] = $type;
}
mysqli_data_seek($types, 0);

while ($gen = mysqli_fetch_assoc($generics)) {
    $generic_data[] = $gen;
}
mysqli_data_seek($generics, 0);

while ($sup = mysqli_fetch_assoc($suppliers)) {
    $supplier_data[] = $sup;
}
mysqli_data_seek($suppliers, 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medicine - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            transform: translateY(-5px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .gradient-yellow {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
        }

        .gradient-green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
            border-color: var(--primary-yellow);
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

        .gray-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--primary-gray), var(--primary-gray-light));
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
        }

        .dropdown-search-container {
            position: relative;
        }

        .dropdown-search-input {
            width: 100%;
            padding: 0.5rem 2.5rem 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .dropdown-search-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
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
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dropdown-option:hover {
            background: #f3f4f6;
        }

        .dropdown-option.selected {
            background: #fef3c7;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #10b981;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        .stock-section {
            transition: all 0.3s ease;
        }

        .stock-section.disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .stock-indicator {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-indicator.in-stock {
            background: #d1fae5;
            color: #059669;
        }

        .stock-indicator.no-stock {
            background: #fee2e2;
            color: #dc2626;
        }

        .price-box {
            position: relative;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .price-box.purchase {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            border: 2px solid #9ca3af;
        }

        .price-box.selling {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border: 2px solid #10b981;
        }

        .price-box.mrp {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
        }

        .price-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .price-value {
            font-size: 24px;
            font-weight: 700;
            margin: 5px 0;
        }

        .profit-indicator {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .profit-positive {
            background: #d1fae5;
            color: #059669;
        }

        .profit-negative {
            background: #fee2e2;
            color: #dc2626;
        }

        .batch-info {
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
        }

        .batch-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid rgba(14, 165, 233, 0.2);
        }

        .batch-info-item:last-child {
            border-bottom: none;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .price-change-animation {
            animation: priceChange 0.5s ease-in-out;
        }

        @keyframes priceChange {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .auto-calc-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #3b82f6;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10"></div>
    <div class="gray-blob bottom-20 left-10"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden p-6">
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Medicine Added Successfully!</h3>
                            <p class="text-gray-600 mb-3">
                                New medicine has been added with ID:
                                <span class="font-semibold text-yellow-600">MED-<?php echo str_pad($new_medicine_id, 6, '0', STR_PAD_LEFT); ?></span>
                                <?php if (isset($stock_added) && $stock_added): ?>
                                    <span class="ml-2 stock-indicator in-stock">
                                        <i class="fas fa-check mr-1"></i> Initial stock added
                                    </span>
                                <?php else: ?>
                                    <span class="ml-2 stock-indicator no-stock">
                                        <i class="fas fa-exclamation-circle mr-1"></i> No initial stock
                                    </span>
                                <?php endif; ?>
                            </p>
                            <div class="flex space-x-3">
                                <a href="medicines.php" class="inline-flex items-center space-x-2 text-yellow-600 hover:text-yellow-800 font-medium px-4 py-2 bg-yellow-50 rounded-lg">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Medicines</span>
                                </a>
                                <?php if (!isset($stock_added) || !$stock_added): ?>
                                    <a href="add_stock.php?medicine_id=<?php echo $new_medicine_id; ?>" class="inline-flex items-center space-x-2 text-green-600 hover:text-green-800 font-medium px-4 py-2 bg-green-50 rounded-lg">
                                        <i class="fas fa-plus"></i>
                                        <span>Add Initial Stock</span>
                                    </a>
                                <?php endif; ?>
                                <a href="add_medicine.php" class="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-800 font-medium px-4 py-2 bg-blue-50 rounded-lg">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add Another</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Error Adding Medicine</h3>
                            <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Add New <span class="gradient-text">Medicine</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-pills text-yellow-500"></i>
                            <span>Register a new medicine with optional initial stock</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span>Pharmacist Access</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="medicines.php"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Medicines</span>
                        </a>
                        <button type="button" onclick="resetForm()"
                            class="px-6 py-3 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-redo"></i>
                            <span>Reset Form</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left Column - Form -->
                <div class="lg:col-span-2">
                    <form method="POST" class="space-y-6" id="medicineForm">
                        <!-- Medicine Details Card -->
                        <div class="glass-card rounded-2xl overflow-hidden animate-fade-in-up">
                            <div class="px-6 py-4 border-b border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">Medicine Details</h3>
                                        <p class="text-sm text-gray-600">Fill in the required information</p>
                                    </div>
                                    <div class="text-xs font-medium text-yellow-600 bg-yellow-100 px-3 py-1 rounded-full">
                                        <i class="fas fa-asterisk text-xs mr-1"></i> Required Fields
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 space-y-8">
                                <!-- Basic Information -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-info-circle text-blue-500"></i>
                                        <span>Basic Information</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Medicine Name -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-pills text-yellow-500 text-sm"></i>
                                                    <span>Medicine Name *</span>
                                                </span>
                                            </label>
                                            <input type="text"
                                                name="name"
                                                id="medicine_name"
                                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                placeholder="Enter medicine brand name"
                                                required>
                                        </div>

                                        <!-- Generic Name -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-dna text-blue-500 text-sm"></i>
                                                    <span>Generic Name *</span>
                                                </span>
                                            </label>
                                            <div class="dropdown-search-container">
                                                <input type="hidden" name="generic_id" id="generic_id" value="<?php echo isset($_POST['generic_id']) ? htmlspecialchars($_POST['generic_id']) : ''; ?>" required>
                                                <input type="text"
                                                    id="generic_search"
                                                    class="dropdown-search-input px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm w-full"
                                                    placeholder="Search or select generic name"
                                                    autocomplete="off">
                                                <div class="dropdown-search-icon">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                                <div class="dropdown-options" id="generic_options"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Classification -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-tags text-purple-500"></i>
                                        <span>Classification</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Category -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-tag text-teal-500 text-sm"></i>
                                                    <span>Category *</span>
                                                </span>
                                            </label>
                                            <div class="dropdown-search-container">
                                                <input type="hidden" name="category_id" id="category_id" value="<?php echo isset($_POST['category_id']) ? htmlspecialchars($_POST['category_id']) : ''; ?>" required>
                                                <input type="text"
                                                    id="category_search"
                                                    class="dropdown-search-input px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm w-full"
                                                    placeholder="Search or select category"
                                                    autocomplete="off">
                                                <div class="dropdown-search-icon">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                                <div class="dropdown-options" id="category_options"></div>
                                            </div>
                                        </div>

                                        <!-- Type -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-prescription-bottle-alt text-purple-500 text-sm"></i>
                                                    <span>Type *</span>
                                                </span>
                                            </label>
                                            <div class="dropdown-search-container">
                                                <input type="hidden" name="type_id" id="type_id" value="<?php echo isset($_POST['type_id']) ? htmlspecialchars($_POST['type_id']) : ''; ?>" required>
                                                <input type="text"
                                                    id="type_search"
                                                    class="dropdown-search-input px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm w-full"
                                                    placeholder="Search or select type"
                                                    autocomplete="off">
                                                <div class="dropdown-search-icon">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                                <div class="dropdown-options" id="type_options"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-file-alt text-yellow-500"></i>
                                        <span>Additional Information</span>
                                    </h4>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                        <textarea name="description"
                                            rows="4"
                                            class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                            placeholder="Enter medicine description..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Details Card -->
                        <div class="glass-card rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s">
                            <div class="px-6 py-4 border-b border-green-100 bg-gradient-to-r from-green-50 to-green-25">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                                            <i class="fas fa-boxes text-green-500"></i>
                                            <span>Initial Stock Details</span>
                                        </h3>
                                        <p class="text-sm text-gray-600">Optional: Add initial stock batch</p>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm font-medium text-gray-600">Add Stock</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="add_stock" id="add_stock" <?php echo isset($_POST['add_stock']) ? 'checked' : 'checked'; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 space-y-8 stock-section" id="stockSection">
                                <!-- Batch Information -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-barcode text-blue-500"></i>
                                        <span>Batch Information</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Batch Number -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-hashtag text-gray-500 text-sm"></i>
                                                    <span>Batch Number *</span>
                                                </span>
                                            </label>
                                            <div class="relative">
                                                <input type="text"
                                                    name="batch_no"
                                                    id="batch_no"
                                                    value="<?php echo isset($_POST['batch_no']) ? htmlspecialchars($_POST['batch_no']) : ''; ?>"
                                                    class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm pr-12"
                                                    placeholder="e.g., PAN-2501-001"
                                                    required>
                                                <button type="button" onclick="generateBatchNumber()"
                                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-sm flex items-center space-x-1 transition-colors">
                                                    <i class="fas fa-sync-alt"></i>
                                                    <span>Generate</span>
                                                </button>
                                            </div>
                                            <div id="batchInfo" class="batch-info mt-2 hidden">
                                                <div class="batch-info-item">
                                                    <span class="text-xs text-gray-600">Batch Format:</span>
                                                    <span class="text-xs font-mono font-bold" id="batchFormat"></span>
                                                </div>
                                                <div class="batch-info-item">
                                                    <span class="text-xs text-gray-600">Medicine Code:</span>
                                                    <span class="text-xs font-mono" id="batchPrefix"></span>
                                                </div>
                                                <div class="batch-info-item">
                                                    <span class="text-xs text-gray-600">Month-Year:</span>
                                                    <span class="text-xs" id="batchDate"></span>
                                                </div>
                                                <div class="batch-info-item">
                                                    <span class="text-xs text-gray-600">Sequence:</span>
                                                    <span class="text-xs font-mono" id="batchSequence"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Supplier -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-truck text-green-500 text-sm"></i>
                                                    <span>Supplier</span>
                                                </span>
                                            </label>
                                            <select name="supplier_id" id="supplier_id"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm">
                                                <option value="">Select Supplier</option>
                                                <?php foreach ($supplier_data as $sup): ?>
                                                    <option value="<?php echo $sup['id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $sup['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($sup['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stock Quantities -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-layer-group text-purple-500"></i>
                                        <span>Stock Quantities</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Quantity -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-box text-blue-500 text-sm"></i>
                                                    <span>Quantity *</span>
                                                </span>
                                            </label>
                                            <input type="number"
                                                name="quantity"
                                                id="quantity"
                                                value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '0'; ?>"
                                                min="0"
                                                step="1"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                placeholder="Enter quantity"
                                                required>
                                            <p class="text-xs text-gray-500 mt-2">Number of boxes</p>
                                        </div>

                                        <!-- Location -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-map-marker-alt text-red-500 text-sm"></i>
                                                    <span>Location</span>
                                                </span>
                                            </label>
                                            <input type="text"
                                                name="location"
                                                id="location"
                                                value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                placeholder="e.g., Shelf A1, Rack B2">
                                        </div>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-6 mt-4">
                                        <!-- Units per Packet -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-box-open text-teal-500 text-sm"></i>
                                                    <span>Units per Packet</span>
                                                </span>
                                            </label>
                                            <input type="number"
                                                name="units_per_packet"
                                                id="units_per_packet"
                                                value="<?php echo isset($_POST['units_per_packet']) ? htmlspecialchars($_POST['units_per_packet']) : '10'; ?>"
                                                min="1"
                                                step="1"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm">
                                            <p class="text-xs text-gray-500 mt-2">Individual units in each packet</p>
                                        </div>

                                        <!-- Packets per Box -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-boxes text-yellow-500 text-sm"></i>
                                                    <span>Packets per Box</span>
                                                </span>
                                            </label>
                                            <input type="number"
                                                name="packets_per_box"
                                                id="packets_per_box"
                                                value="<?php echo isset($_POST['packets_per_box']) ? htmlspecialchars($_POST['packets_per_box']) : '10'; ?>"
                                                min="1"
                                                step="1"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm">
                                            <p class="text-xs text-gray-500 mt-2">Packets in each box</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pricing Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-money-bill-wave text-green-500"></i>
                                        <span>Pricing Information</span>
                                    </h4>

                                    <div class="grid md:grid-cols-3 gap-4 mb-4">
                                        <!-- Purchase Price Box -->
                                        <div class="price-box purchase relative">
                                            <div class="price-label text-gray-600">
                                                <i class="fas fa-shopping-cart mr-1"></i>
                                                Purchase Price
                                            </div>
                                            <div class="price-value text-gray-800" id="purchasePriceDisplay">Rs0.00</div>
                                            <div class="flex items-center justify-between mt-2">
                                                <input type="hidden" name="purchase_price" id="purchase_price" value="0">
                                                <button type="button" onclick="decreasePrice('purchase')" class="w-8 h-8 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number"
                                                    id="purchase_price_input"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-24 text-center px-2 py-1 border border-gray-300 rounded bg-white text-gray-800 font-medium"
                                                    placeholder="0.00"
                                                    onkeyup="updatePrice('purchase', this.value)">
                                                <button type="button" onclick="increasePrice('purchase')" class="w-8 h-8 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <div class="mt-2 text-xs text-gray-500 text-center">
                                                <i class="fas fa-calculator mr-1"></i>
                                                Base price for calculations
                                            </div>
                                        </div>

                                        <!-- Selling Price Box -->
                                        <div class="price-box selling relative">
                                            <span class="auto-calc-badge">Auto-calculated</span>
                                            <div class="price-label text-green-700">
                                                <i class="fas fa-store mr-1"></i>
                                                Selling Price
                                            </div>
                                            <div class="price-value text-green-800" id="sellingPriceDisplay">Rs0.00</div>
                                            <div class="flex items-center justify-between mt-2">
                                                <input type="hidden" name="selling_price" id="selling_price" value="0">
                                                <button type="button" onclick="decreasePrice('selling')" class="w-8 h-8 bg-green-100 text-green-700 rounded-full hover:bg-green-200 transition-colors">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number"
                                                    id="selling_price_input"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-24 text-center px-2 py-1 border border-green-300 rounded bg-white text-green-800 font-medium"
                                                    placeholder="0.00"
                                                    onkeyup="updatePrice('selling', this.value)">
                                                <button type="button" onclick="increasePrice('selling')" class="w-8 h-8 bg-green-100 text-green-700 rounded-full hover:bg-green-200 transition-colors">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <div class="mt-2 text-xs text-green-600 text-center">
                                                <i class="fas fa-percentage mr-1"></i>
                                                33% markup from purchase
                                            </div>
                                        </div>

                                        <!-- MRP Box -->
                                        <div class="price-box mrp relative">
                                            <span class="auto-calc-badge">Auto-calculated</span>
                                            <div class="price-label text-yellow-700">
                                                <i class="fas fa-tag mr-1"></i>
                                                MRP (Maximum Retail Price)
                                            </div>
                                            <div class="price-value text-yellow-800" id="mrpDisplay">Rs0.00</div>
                                            <div class="flex items-center justify-between mt-2">
                                                <input type="hidden" name="mrp" id="mrp" value="0">
                                                <button type="button" onclick="decreasePrice('mrp')" class="w-8 h-8 bg-yellow-100 text-yellow-700 rounded-full hover:bg-yellow-200 transition-colors">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number"
                                                    id="mrp_input"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-24 text-center px-2 py-1 border border-yellow-300 rounded bg-white text-yellow-800 font-medium"
                                                    placeholder="0.00"
                                                    onkeyup="updatePrice('mrp', this.value)">
                                                <button type="button" onclick="increasePrice('mrp')" class="w-8 h-8 bg-yellow-100 text-yellow-700 rounded-full hover:bg-yellow-200 transition-colors">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <div class="mt-2 text-xs text-yellow-600 text-center">
                                                <i class="fas fa-percentage mr-1"></i>
                                                12% GST + 20% margin
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Price Calculations -->
                                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-xl border border-gray-200">
                                        <h5 class="font-medium text-gray-800 mb-3">Price Calculations</h5>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            <div class="text-center">
                                                <div class="text-xs text-gray-500">Markup</div>
                                                <div class="text-lg font-bold text-blue-600" id="markupAmount">Rs0.00</div>
                                                <div class="text-xs text-gray-600" id="markupPercent">0%</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-xs text-gray-500">Discount</div>
                                                <div class="text-lg font-bold text-purple-600" id="discountAmount">Rs0.00</div>
                                                <div class="text-xs text-gray-600" id="discountPercent">0%</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-xs text-gray-500">Profit Margin</div>
                                                <div class="text-lg font-bold text-green-600" id="profitMargin">0%</div>
                                                <div class="text-xs text-gray-600" id="profitAmount">Rs0.00</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-xs text-gray-500">Tax (GST 12%)</div>
                                                <div class="text-lg font-bold text-red-600" id="taxAmount">Rs0.00</div>
                                                <div class="text-xs text-gray-600">Included in MRP</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dates -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-calendar-alt text-purple-500"></i>
                                        <span>Date Information</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Received Date -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-calendar-plus text-blue-500 text-sm"></i>
                                                    <span>Received Date</span>
                                                </span>
                                            </label>
                                            <input type="date"
                                                name="received_date"
                                                id="received_date"
                                                value="<?php echo isset($_POST['received_date']) ? htmlspecialchars($_POST['received_date']) : date('Y-m-d'); ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm">
                                        </div>

                                        <!-- Expiry Date -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-calendar-times text-red-500 text-sm"></i>
                                                    <span>Expiry Date *</span>
                                                </span>
                                            </label>
                                            <input type="date"
                                                name="expiry_date"
                                                id="expiry_date"
                                                value="<?php echo isset($_POST['expiry_date']) ? htmlspecialchars($_POST['expiry_date']) : ''; ?>"
                                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stock Summary -->
                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-xl border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-3">Stock Summary</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div class="text-center">
                                            <div class="text-sm text-gray-500">Boxes</div>
                                            <div class="text-xl font-bold text-blue-600" id="summaryBoxes">0</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-sm text-gray-500">Packets</div>
                                            <div class="text-xl font-bold text-purple-600" id="summaryPackets">0</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-sm text-gray-500">Total Units</div>
                                            <div class="text-xl font-bold text-green-600" id="summaryUnits">0</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-sm text-gray-500">Total Value</div>
                                            <div class="text-xl font-bold text-yellow-600" id="summaryValue">Rs0.00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                            <div class="text-center">
                                <button type="submit"
                                    name="submit"
                                    class="gradient-yellow text-white py-4 px-12 rounded-xl font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] group shadow relative overflow-hidden">
                                    <span class="relative z-10 flex items-center justify-center space-x-3">
                                        <i class="fas fa-plus group-hover:rotate-90 transition-transform duration-300"></i>
                                        <span>Add Medicine & Stock</span>
                                        <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform duration-300 text-yellow-100"></i>
                                    </span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-yellow-600 to-yellow-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </button>
                                <p class="text-center text-sm text-gray-500 mt-3">
                                    <i class="fas fa-lightbulb text-yellow-500 mr-1"></i>
                                    Medicine will be added to inventory with optional initial stock
                                </p>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Right Column - Info Panel -->
                <div class="space-y-6">
                    <!-- Guidelines -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-info-circle text-yellow-500"></i>
                            <span>Guidelines</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0 shadow">
                                    <i class="fas fa-check text-yellow-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Accurate Information</p>
                                    <p class="text-xs text-gray-600">Provide correct generic names for proper identification</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 shadow">
                                    <i class="fas fa-layer-group text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Proper Classification</p>
                                    <p class="text-xs text-gray-600">Select correct category and type</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0 shadow">
                                    <i class="fas fa-notes-medical text-green-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Stock Details</p>
                                    <p class="text-xs text-gray-600">Optional but recommended for inventory</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Batch Format Help -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Batch Number Format</h3>
                        <div class="space-y-3">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="font-mono text-sm mb-2 text-center" id="batchExample">PAN-2501-001</div>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-cube text-yellow-500"></i>
                                        <span><strong id="examplePrefix">PAN</strong> = First 3 letters of medicine</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-calendar text-blue-500"></i>
                                        <span><strong id="exampleDate">2501</strong> = January 2025</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-hashtag text-green-500"></i>
                                        <span><strong id="exampleSeq">001</strong> = Sequence number</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-lightbulb text-yellow-500 mr-1"></i>
                                Click "Generate" button to auto-generate batch number
                            </p>
                        </div>
                    </div>

                    <!-- Quick Calculations -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Calculations</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Units:</span>
                                <span class="font-bold text-green-600" id="calcUnits">0</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Cost:</span>
                                <span class="font-bold text-blue-600" id="calcCost">Rs0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Value:</span>
                                <span class="font-bold text-purple-600" id="calcValue">Rs0.00</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t">
                                <span class="text-sm text-gray-600">Profit Margin:</span>
                                <span class="font-bold text-green-600" id="calcMargin">0%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Price Guidelines -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Price Guidelines</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-600">Selling Price</span>
                                <span class="text-xs font-medium text-green-600">33% markup</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-600">MRP</span>
                                <span class="text-xs font-medium text-yellow-600">GST 12% + 20% margin</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-600">Discount</span>
                                <span class="text-xs font-medium text-purple-600">20% from MRP</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-gradient-to-r from-gray-400 via-green-500 to-yellow-500 h-2 rounded-full" style="width: 100%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                Selling price & MRP auto-calculate from purchase price
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Data from PHP
        const categories = <?php echo json_encode($category_data); ?>;
        const types = <?php echo json_encode($type_data); ?>;
        const generics = <?php echo json_encode($generic_data); ?>;

        // Price calculation settings
        const PRICE_SETTINGS = {
            markupPercentage: 33.33,    // 33.33% markup from purchase to selling (1/3 markup)
            discountPercentage: 20,     // 20% discount from MRP to selling
            gstPercentage: 12          // 12% GST included in MRP
        };

        // Track if prices are being auto-calculated
        let isAutoCalculating = false;

        // Dropdown search functionality
        function initializeDropdownSearch(searchInputId, optionsId, hiddenInputId, data, displayField = 'name') {
            const searchInput = document.getElementById(searchInputId);
            const optionsDiv = document.getElementById(optionsId);
            const hiddenInput = document.getElementById(hiddenInputId);
            let selectedOption = null;

            searchInput.addEventListener('focus', function() {
                populateOptions('');
                optionsDiv.classList.add('active');
            });

            searchInput.addEventListener('input', function() {
                populateOptions(this.value);
            });

            function selectOption(item) {
                selectedOption = item;
                searchInput.value = item[displayField];
                hiddenInput.value = item.id;
                optionsDiv.classList.remove('active');
                updateProgress();

                optionsDiv.querySelectorAll('.dropdown-option').forEach(opt => {
                    opt.classList.remove('selected');
                    if (parseInt(opt.dataset.id) === item.id) {
                        opt.classList.add('selected');
                    }
                });
            }

            function populateOptions(searchTerm) {
                optionsDiv.innerHTML = '';
                const filtered = data.filter(item =>
                    item[displayField].toLowerCase().includes(searchTerm.toLowerCase())
                );

                if (filtered.length === 0) {
                    optionsDiv.innerHTML = `
                        <div class="dropdown-option p-3 text-gray-500 text-center">
                            No results found
                        </div>`;
                    return;
                }

                filtered.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'dropdown-option';
                    div.dataset.id = item.id;
                    div.textContent = item[displayField];
                    if (item.description) {
                        const desc = document.createElement('div');
                        desc.className = 'text-xs text-gray-500 mt-1';
                        desc.textContent = item.description;
                        div.appendChild(desc);
                    }
                    div.addEventListener('click', () => selectOption(item));

                    if (parseInt(hiddenInput.value) === item.id) {
                        div.classList.add('selected');
                        searchInput.value = item[displayField];
                    }

                    optionsDiv.appendChild(div);
                });
            }

            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !optionsDiv.contains(event.target)) {
                    optionsDiv.classList.remove('active');
                    if (selectedOption) {
                        searchInput.value = selectedOption[displayField];
                    }
                }
            });

            const initialValue = hiddenInput.value;
            if (initialValue) {
                const item = data.find(d => d.id == initialValue);
                if (item) {
                    selectOption(item);
                }
            }
        }

        // Calculate selling price and MRP based on purchase price
        function calculateDerivedPrices(purchasePrice) {
            const purchase = parseFloat(purchasePrice) || 0;
            
            // Calculate selling price: purchase + 33.33% markup
            const sellingPrice = purchase + (purchase * (PRICE_SETTINGS.markupPercentage / 100));
            
            // Calculate MRP: selling price with 12% GST included
            // Formula: MRP = Selling Price * (1 + GST/100)
            const mrp = sellingPrice * (1 + (PRICE_SETTINGS.gstPercentage / 100));
            
            return {
                sellingPrice: parseFloat(sellingPrice.toFixed(2)),
                mrp: parseFloat(mrp.toFixed(2))
            };
        }

        // Price management functions
        function updatePrice(type, value) {
            const price = parseFloat(value) || 0;
            const displayElement = document.getElementById(type + 'PriceDisplay');
            const hiddenElement = document.getElementById(type + '_price');
            const inputElement = document.getElementById(type + '_price_input');
            
            hiddenElement.value = price;
            displayElement.textContent = 'Rs' + price.toFixed(2);
            inputElement.value = price.toFixed(2);
            
            // Animate price change
            displayElement.classList.add('price-change-animation');
            setTimeout(() => {
                displayElement.classList.remove('price-change-animation');
            }, 500);
            
            // If purchase price changes, auto-calculate selling and MRP
            if (type === 'purchase' && !isAutoCalculating) {
                isAutoCalculating = true;
                const derivedPrices = calculateDerivedPrices(price);
                
                // Update selling price
                document.getElementById('selling_price').value = derivedPrices.sellingPrice;
                document.getElementById('sellingPriceDisplay').textContent = 'Rs' + derivedPrices.sellingPrice.toFixed(2);
                document.getElementById('selling_price_input').value = derivedPrices.sellingPrice.toFixed(2);
                
                // Update MRP
                document.getElementById('mrp').value = derivedPrices.mrp;
                document.getElementById('mrpDisplay').textContent = 'Rs' + derivedPrices.mrp.toFixed(2);
                document.getElementById('mrp_input').value = derivedPrices.mrp.toFixed(2);
                
                // Animate the calculated prices
                ['sellingPriceDisplay', 'mrpDisplay'].forEach(id => {
                    document.getElementById(id).classList.add('price-change-animation');
                    setTimeout(() => {
                        document.getElementById(id).classList.remove('price-change-animation');
                    }, 500);
                });
                
                isAutoCalculating = false;
            }
            
            // Update calculations
            calculatePriceSummary();
            calculateStockSummary();
        }

        function increasePrice(type) {
            const inputElement = document.getElementById(type + '_price_input');
            let currentValue = parseFloat(inputElement.value) || 0;
            
            // Different increment based on price type
            let increment = 1;
            if (type === 'selling') increment = 2;
            if (type === 'mrp') increment = 5;
            
            currentValue += increment;
            updatePrice(type, currentValue);
        }

        function decreasePrice(type) {
            const inputElement = document.getElementById(type + '_price_input');
            let currentValue = parseFloat(inputElement.value) || 0;
            
            // Different decrement based on price type
            let decrement = 1;
            if (type === 'selling') decrement = 2;
            if (type === 'mrp') decrement = 5;
            
            currentValue = Math.max(0, currentValue - decrement);
            updatePrice(type, currentValue);
        }

        // Calculate price summary
        function calculatePriceSummary() {
            const purchasePrice = parseFloat(document.getElementById('purchase_price').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
            const mrp = parseFloat(document.getElementById('mrp').value) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;

            // Calculate markup
            const markupAmount = sellingPrice - purchasePrice;
            const markupPercent = purchasePrice > 0 ? ((markupAmount / purchasePrice) * 100).toFixed(2) : 0;

            // Calculate discount (difference between MRP and selling price)
            const discountAmount = mrp - sellingPrice;
            const discountPercent = mrp > 0 ? ((discountAmount / mrp) * 100).toFixed(2) : 0;

            // Calculate profit
            const profitAmount = markupAmount * quantity;
            const profitMargin = purchasePrice > 0 ? (markupPercent) : 0;

            // Calculate tax (GST 12% on selling price)
            const taxAmount = sellingPrice > 0 ? (sellingPrice * (PRICE_SETTINGS.gstPercentage / 100)) : 0;

            // Update display
            document.getElementById('markupAmount').textContent = 'Rs' + markupAmount.toFixed(2);
            document.getElementById('markupPercent').textContent = markupPercent + '%';
            document.getElementById('discountAmount').textContent = 'Rs' + discountAmount.toFixed(2);
            document.getElementById('discountPercent').textContent = discountPercent + '%';
            document.getElementById('profitAmount').textContent = 'Rs' + profitAmount.toFixed(2);
            document.getElementById('profitMargin').textContent = profitMargin + '%';
            document.getElementById('taxAmount').textContent = 'Rs' + taxAmount.toFixed(2);

            // Update profit indicator color
            const profitMarginElement = document.getElementById('profitMargin');
            if (markupPercent > 50) {
                profitMarginElement.className = 'text-lg font-bold text-green-600';
            } else if (markupPercent > 20) {
                profitMarginElement.className = 'text-lg font-bold text-yellow-600';
            } else {
                profitMarginElement.className = 'text-lg font-bold text-red-600';
            }
        }

        // Calculate stock summary
        function calculateStockSummary() {
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const unitsPerPacket = parseInt(document.getElementById('units_per_packet').value) || 10;
            const packetsPerBox = parseInt(document.getElementById('packets_per_box').value) || 10;
            const purchasePrice = parseFloat(document.getElementById('purchase_price').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
            
            const totalPackets = quantity * packetsPerBox;
            const totalUnits = totalPackets * unitsPerPacket;
            const totalCost = quantity * purchasePrice;
            const totalValue = quantity * sellingPrice;
            const profitMargin = purchasePrice > 0 ? (((sellingPrice - purchasePrice) / purchasePrice) * 100).toFixed(2) : 0;

            // Update summary display
            document.getElementById('summaryBoxes').textContent = quantity;
            document.getElementById('summaryPackets').textContent = totalPackets;
            document.getElementById('summaryUnits').textContent = totalUnits.toLocaleString();
            document.getElementById('summaryValue').textContent = 'Rs' + totalValue.toFixed(2);

            // Update calculations
            document.getElementById('calcUnits').textContent = totalUnits.toLocaleString();
            document.getElementById('calcCost').textContent = 'Rs' + totalCost.toFixed(2);
            document.getElementById('calcValue').textContent = 'Rs' + totalValue.toFixed(2);
            document.getElementById('calcMargin').textContent = profitMargin + '%';
        }

        // Generate batch number
        function generateBatchNumber() {
            const medicineName = document.getElementById('medicine_name').value;
            
            if (!medicineName || medicineName.length < 3) {
                alert('Please enter a medicine name (at least 3 characters) to generate batch number.');
                return;
            }

            const generateBtn = document.querySelector('button[onclick="generateBatchNumber()"]');
            const originalHtml = generateBtn.innerHTML;
            
            // Show loading state
            generateBtn.innerHTML = '<div class="loading-spinner"></div>';
            generateBtn.disabled = true;

            // Get first 3 letters of medicine name (uppercase)
            const prefix = medicineName.substring(0, 3).toUpperCase();
            
            // Get current month and year (MMYY format)
            const now = new Date();
            const month = (now.getMonth() + 1).toString().padStart(2, '0');
            const year = now.getFullYear().toString().substring(2);
            const date_part = month + year;
            
            // For new medicine, always start with 001
            const sequence = '001';
            
            const batchNo = `${prefix}-${date_part}-${sequence}`;
            
            // Update batch number field
            document.getElementById('batch_no').value = batchNo;
            
            // Update batch info display
            const batchInfo = document.getElementById('batchInfo');
            document.getElementById('batchFormat').textContent = 'ABC-YYMM-001';
            document.getElementById('batchPrefix').textContent = prefix + ' (First 3 letters)';
            
            // Get month name
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                               'July', 'August', 'September', 'October', 'November', 'December'];
            const monthName = monthNames[parseInt(month) - 1];
            document.getElementById('batchDate').textContent = `${monthName} 20${year}`;
            document.getElementById('batchSequence').textContent = sequence + ' (First batch)';
            
            batchInfo.classList.remove('hidden');
            
            // Update batch example
            document.getElementById('batchExample').textContent = batchNo;
            document.getElementById('examplePrefix').textContent = prefix;
            document.getElementById('exampleDate').textContent = date_part;
            document.getElementById('exampleSeq').textContent = sequence;

            // Reset button state
            setTimeout(() => {
                generateBtn.innerHTML = originalHtml;
                generateBtn.disabled = false;
            }, 500);
        }

        // Toggle stock section
        function toggleStockSection() {
            const addStock = document.getElementById('add_stock');
            const stockSection = document.getElementById('stockSection');
            const stockStatus = document.getElementById('stockStatus');
            
            if (addStock.checked) {
                stockSection.classList.remove('disabled');
                stockStatus.textContent = 'Yes';
                stockStatus.className = 'text-xs font-medium text-green-600';
                
                // Enable required fields
                ['batch_no', 'quantity', 'purchase_price_input', 'selling_price_input', 'mrp_input', 'expiry_date'].forEach(id => {
                    const field = document.getElementById(id);
                    if (field) field.required = true;
                });
            } else {
                stockSection.classList.add('disabled');
                stockStatus.textContent = 'No';
                stockStatus.className = 'text-xs font-medium text-red-600';
                
                // Disable required fields
                ['batch_no', 'quantity', 'purchase_price_input', 'selling_price_input', 'mrp_input', 'expiry_date'].forEach(id => {
                    const field = document.getElementById(id);
                    if (field) field.required = false;
                });
            }
        }

        // Update progress
        function updateProgress() {
            const requiredFields = [
                document.querySelector('[name="name"]'),
                document.getElementById('generic_id'),
                document.getElementById('category_id'),
                document.getElementById('type_id')
            ];
            
            let filled = 0;
            requiredFields.forEach(field => {
                if (field && field.value.trim() !== '') filled++;
            });
            
            const total = requiredFields.length;
            const progress = (filled / total) * 100;
            const progressBar = document.getElementById('progressBar');
            const requiredCount = document.getElementById('requiredCount');
            
            progressBar.style.width = `${progress}%`;
            requiredCount.textContent = `${filled}/${total}`;
            
            progressBar.className = 'h-2 rounded-full ' +
                (progress === 100 ? 'bg-green-500' : progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
        }

        // Form validation
        function validateForm() {
            const form = document.getElementById('medicineForm');
            const addStock = document.getElementById('add_stock').checked;
            
            if (addStock) {
                const quantity = parseInt(document.getElementById('quantity').value) || 0;
                const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
                const mrp = parseFloat(document.getElementById('mrp').value) || 0;
                const expiryDate = document.getElementById('expiry_date').value;
                const purchasePrice = parseFloat(document.getElementById('purchase_price').value) || 0;
                
                if (quantity <= 0) {
                    alert('Quantity must be greater than 0 when adding stock.');
                    return false;
                }
                
                if (purchasePrice < 0) {
                    alert('Purchase price cannot be negative.');
                    return false;
                }
                
                if (sellingPrice <= 0) {
                    alert('Selling price must be greater than 0.');
                    return false;
                }
                
                if (mrp <= 0) {
                    alert('MRP must be greater than 0.');
                    return false;
                }
                
                if (sellingPrice < purchasePrice) {
                    alert('Selling price should not be less than purchase price.');
                    return false;
                }
                
                if (mrp < sellingPrice) {
                    alert('MRP should not be less than selling price.');
                    return false;
                }
                
                if (!expiryDate) {
                    alert('Please select an expiry date.');
                    return false;
                }
                
                if (new Date(expiryDate) <= new Date()) {
                    alert('Expiry date must be in the future.');
                    return false;
                }
            }
            
            return true;
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns
            initializeDropdownSearch('category_search', 'category_options', 'category_id', categories);
            initializeDropdownSearch('type_search', 'type_options', 'type_id', types);
            initializeDropdownSearch('generic_search', 'generic_options', 'generic_id', generics);

            // Toggle stock section
            const addStockToggle = document.getElementById('add_stock');
            addStockToggle.addEventListener('change', toggleStockSection);
            toggleStockSection(); // Initial call

            // Calculate stock summary on input
            const stockInputs = ['quantity', 'units_per_packet', 'packets_per_box', 'purchase_price_input', 'selling_price_input', 'mrp_input'];
            stockInputs.forEach(id => {
                document.getElementById(id).addEventListener('input', function() {
                    if (id.includes('price')) {
                        const type = id.replace('_price_input', '');
                        updatePrice(type, this.value);
                    } else {
                        calculateStockSummary();
                    }
                });
            });

            // Set expiry date minimum to tomorrow
            const expiryDate = document.getElementById('expiry_date');
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            expiryDate.min = tomorrow.toISOString().split('T')[0];

            // Set default expiry date to 1 year from now
            if (!expiryDate.value) {
                const oneYearLater = new Date();
                oneYearLater.setFullYear(oneYearLater.getFullYear() + 1);
                expiryDate.value = oneYearLater.toISOString().split('T')[0];
            }

            // Set default prices
            const initialPurchasePrice = 15.00;
            document.getElementById('purchase_price_input').value = initialPurchasePrice.toFixed(2);
            updatePrice('purchase', initialPurchasePrice);

            // Update progress on input
            const form = document.getElementById('medicineForm');
            form.addEventListener('input', updateProgress);
            
            // Also update when hidden inputs change
            document.getElementById('generic_id').addEventListener('change', updateProgress);
            document.getElementById('category_id').addEventListener('change', updateProgress);
            document.getElementById('type_id').addEventListener('change', updateProgress);

            // Initial progress check
            updateProgress();
            
            // Initial calculations
            calculateStockSummary();
            calculatePriceSummary();

            // Auto-save form data
            let autoSaveTimer;
            function saveFormData() {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                localStorage.setItem('medicineFormDraft', JSON.stringify(data));
                console.log('Form auto-saved');
            }

            form.addEventListener('input', () => {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(saveFormData, 30000);
            });

            // Load saved draft
            const saved = localStorage.getItem('medicineFormDraft');
            if (saved) {
                const data = JSON.parse(saved);
                Object.keys(data).forEach(name => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) field.value = data[name];
                });

                // Set dropdown values
                if (data.generic_id) {
                    const gen = generics.find(g => g.id == data.generic_id);
                    if (gen) {
                        document.getElementById('generic_search').value = gen.name;
                        document.getElementById('generic_id').value = gen.id;
                    }
                }
                if (data.category_id) {
                    const cat = categories.find(c => c.id == data.category_id);
                    if (cat) {
                        document.getElementById('category_search').value = cat.name;
                        document.getElementById('category_id').value = cat.id;
                    }
                }
                if (data.type_id) {
                    const typ = types.find(t => t.id == data.type_id);
                    if (typ) {
                        document.getElementById('type_search').value = typ.name;
                        document.getElementById('type_id').value = typ.id;
                    }
                }

                updateProgress();
                toggleStockSection();
                calculateStockSummary();
                calculatePriceSummary();
            }

            // Form submit handler
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Clear saved draft on successful submit
                localStorage.removeItem('medicineFormDraft');
                return true;
            });

            // Reset form
            window.resetForm = function() {
                if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                    form.reset();
                    
                    // Clear search inputs and hidden fields
                    ['generic_search', 'category_search', 'type_search'].forEach(id => {
                        document.getElementById(id).value = '';
                    });
                    ['generic_id', 'category_id', 'type_id'].forEach(id => {
                        document.getElementById(id).value = '';
                    });
                    
                    // Reset stock section
                    document.getElementById('add_stock').checked = true;
                    toggleStockSection();
                    
                    // Reset dates
                    document.getElementById('received_date').value = new Date().toISOString().split('T')[0];
                    const oneYearLater = new Date();
                    oneYearLater.setFullYear(oneYearLater.getFullYear() + 1);
                    document.getElementById('expiry_date').value = oneYearLater.toISOString().split('T')[0];
                    
                    // Reset defaults
                    document.getElementById('units_per_packet').value = '10';
                    document.getElementById('packets_per_box').value = '10';
                    document.getElementById('quantity').value = '0';
                    
                    // Reset prices
                    const resetPurchasePrice = 15.00;
                    document.getElementById('purchase_price_input').value = resetPurchasePrice.toFixed(2);
                    updatePrice('purchase', resetPurchasePrice);
                    
                    localStorage.removeItem('medicineFormDraft');
                    updateProgress();
                    calculateStockSummary();
                    calculatePriceSummary();

                    // Show success message
                    const toast = document.createElement('div');
                    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                    toast.textContent = 'Form reset successfully!';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                }
            };
        });
    </script>
</body>
</html>