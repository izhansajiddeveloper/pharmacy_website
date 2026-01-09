<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

// Fetch all medicines for dropdown
$medicines = mysqli_query($conn, "SELECT id, name, generic_name FROM medicines ORDER BY name");
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

    // Get prices from POST data
    $purchase_price = 0;
    $selling_price = 0;
    $mrp = 0;

    // Debug log price values
    error_log("Purchase price raw: " . $_POST['purchase_price']);
    error_log("Selling price raw: " . $_POST['selling_price']);
    error_log("MRP raw: " . $_POST['mrp']);

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
                (medicine_id, batch_no, quantity, purchase_price, selling_price, mrp, supplier_id, received_date, expiry_date, location, is_expired) 
                VALUES 
                (
                    $medicine_id,
                    '$batch_no',
                    $quantity,
                    $purchase_price,
                    $selling_price,
                    $mrp,
                    $supplier_id,
                    " . ($received_date ? "'$received_date'" : "NULL") . ",
                    " . ($expiry_date ? "'$expiry_date'" : "NULL") . ",
                    '$location',
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

            <!-- Main Form - NOW THE FORM WRAPS EVERYTHING -->
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
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-capsules text-gray-400 mr-1"></i>
                                        Select Medicine
                                    </label>
                                    <select name="medicine_id" id="medicine_id" required
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
                                        <option value="">Choose a medicine...</option>
                                        <?php
                                        $medicines_result = mysqli_query($conn, "SELECT id, name, generic_name FROM medicines ORDER BY name");
                                        while ($med = mysqli_fetch_assoc($medicines_result)): ?>
                                            <option value="<?php echo $med['id']; ?>"
                                                data-medicine-name="<?php echo htmlspecialchars($med['name']); ?>"
                                                <?php echo (isset($_POST['medicine_id']) && $_POST['medicine_id'] == $med['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($med['name']); ?>
                                                <?php if (!empty($med['generic_name'])): ?>
                                                    (<?php echo htmlspecialchars($med['generic_name']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
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

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                            <i class="fas fa-boxes text-gray-400 mr-1"></i>
                                            Quantity
                                        </label>
                                        <input type="number" name="quantity" min="1" required
                                            placeholder="Enter quantity"
                                            value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500">
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
                                        Purchase Price (Rs)
                                    </label>
                                    <input type="number" name="purchase_price" step="0.01" min="0" required
                                        placeholder="0.00"
                                        value="<?php echo isset($_POST['purchase_price']) ? htmlspecialchars($_POST['purchase_price']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500"
                                        id="purchase_price">
                                    <p class="text-xs text-gray-500 mt-1">Cost price of the medicine</p>
                                </div>

                                <div class="price-card rounded-xl p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-cash-register text-green-500 mr-1"></i>
                                        Selling Price (Rs)
                                    </label>
                                    <input type="number" name="selling_price" step="0.01" min="0" required
                                        placeholder="0.00"
                                        value="<?php echo isset($_POST['selling_price']) ? htmlspecialchars($_POST['selling_price']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500"
                                        id="selling_price">
                                    <p class="text-xs text-gray-500 mt-1">Price at which you sell to customers</p>
                                </div>

                                <div class="price-card rounded-xl p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2 required">
                                        <i class="fas fa-tags text-purple-500 mr-1"></i>
                                        MRP (Rs)
                                    </label>
                                    <input type="number" name="mrp" step="0.01" min="0" required
                                        placeholder="0.00"
                                        value="<?php echo isset($_POST['mrp']) ? htmlspecialchars($_POST['mrp']) : ''; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500"
                                        id="mrp">
                                    <p class="text-xs text-gray-500 mt-1">Maximum Retail Price printed on package</p>
                                </div>

                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-700 mb-2">Price Summary</h4>
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
        const medicineSelect = document.getElementById('medicine_id');
        const batchNumberInput = document.getElementById('batch_no');
        const generateBatchBtn = document.getElementById('generate-batch-btn');
        const batchExplanation = document.getElementById('batch-explanation');
        const explanationDetails = document.getElementById('explanation-details');

        // Generate batch number function
        async function generateBatchNumber() {
            const medicineId = medicineSelect.value;
            const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
            const medicineName = selectedOption.getAttribute('data-medicine-name');

            if (!medicineId || medicineId === '') {
                alert('Please select a medicine first');
                medicineSelect.focus();
                return;
            }

            if (!medicineName) {
                alert('Medicine name not found');
                return;
            }

            // Show loading state
            generateBatchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Generating...</span>';
            generateBatchBtn.disabled = true;

            try {
                const response = await fetch(`ajax/generate_batch_no.php?ajax=generate_batch_no&medicine_id=${medicineId}&medicine_name=${encodeURIComponent(medicineName)}`);
                const data = await response.json();

                if (data.success) {
                    batchNumberInput.value = data.batch_no;

                    // Show explanation
                    explanationDetails.innerHTML = data.explanation;
                    batchExplanation.classList.remove('hidden');

                    // Show success notification
                    showNotification('Batch number generated successfully!', 'success');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error generating batch number:', error);
                alert('Failed to generate batch number. Please try again.');
            } finally {
                // Reset button state
                generateBatchBtn.innerHTML = '<i class="fas fa-bolt"></i><span>Generate Batch No</span>';
                generateBatchBtn.disabled = false;
            }
        }

        // Clear batch number function
        function clearBatchNumber() {
            batchNumberInput.value = '';
            batchExplanation.classList.add('hidden');
            batchNumberInput.focus();
        }

        // Event listeners
        generateBatchBtn.addEventListener('click', generateBatchNumber);

        // Also generate batch number when medicine is selected and batch field is empty
        medicineSelect.addEventListener('change', function() {
            if (!batchNumberInput.value) {
                // Auto-generate batch number when medicine is selected and batch field is empty
                generateBatchNumber();
            }
        });

        // Price calculation and validation
        const purchasePriceInput = document.getElementById('purchase_price');
        const sellingPriceInput = document.getElementById('selling_price');
        const mrpInput = document.getElementById('mrp');

        const purchaseSummary = document.getElementById('purchase_summary');
        const sellingSummary = document.getElementById('selling_summary');
        const mrpSummary = document.getElementById('mrp_summary');

        // Function to update price summaries
        function updatePriceSummaries() {
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;
            const mrp = parseFloat(mrpInput.value) || 0;

            purchaseSummary.textContent = 'Rs ' + purchasePrice.toFixed(2);
            sellingSummary.textContent = 'Rs ' + sellingPrice.toFixed(2);
            mrpSummary.textContent = 'Rs ' + mrp.toFixed(2);

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
                batchExplanation.classList.add('hidden');
                updatePriceSummaries();
            }
        }

        // Form validation before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const medicineId = medicineSelect.value;
            const batchNo = batchNumberInput.value.trim();
            const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;
            const mrp = parseFloat(mrpInput.value) || 0;

            if (!medicineId) {
                e.preventDefault();
                alert('Please select a medicine');
                medicineSelect.focus();
                return false;
            }

            if (!batchNo) {
                e.preventDefault();
                alert('Please enter or generate a batch number');
                batchNumberInput.focus();
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
            const medicineId = medicineSelect.value;
            if (medicineId && !batchNumberInput.value) {
                setTimeout(() => {
                    generateBatchNumber();
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