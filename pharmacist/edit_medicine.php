<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

/* ===============================
   ACCESS CONTROL
================================ */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    $_SESSION['error'] = "You don't have permission to edit medicines";
    header("Location: medicines.php");
    exit;
}

/* ===============================
   VALIDATE MEDICINE ID
================================ */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: medicines.php");
    exit;
}

/* ===============================
   FETCH MEDICINE DATA
================================ */
$result = mysqli_query(
    $conn,
    "SELECT 
        m.*, 
        c.name AS category_name, 
        t.name AS type_name,
        g.name AS generic_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     LEFT JOIN medicine_types t ON m.type_id = t.id
     LEFT JOIN medicine_generics g ON m.generic_id = g.id
     WHERE m.id = $id
     LIMIT 1"
);

$medicine = mysqli_fetch_assoc($result);

if (!$medicine) {
    header("Location: medicines.php");
    exit;
}

$success = false;
$error = '';
$success_message = '';

/* ===============================
   FETCH DROPDOWN DATA
================================ */
$categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
$types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
$generics = mysqli_query($conn, "SELECT * FROM medicine_generics ORDER BY name");

/* ===============================
   FETCH PRICE INFORMATION
================================ */
$price_query = mysqli_query(
    $conn,
    "SELECT 
        MIN(purchase_price) as min_purchase,
        MAX(purchase_price) as max_purchase,
        AVG(purchase_price) as avg_purchase,
        MIN(selling_price) as min_selling,
        MAX(selling_price) as max_selling,
        AVG(selling_price) as avg_selling,
        MIN(mrp) as min_mrp,
        MAX(mrp) as max_mrp,
        AVG(mrp) as avg_mrp
     FROM stock_batches
     WHERE medicine_id = $id
       AND expiry_date >= CURDATE()
       AND quantity > 0"
);

$price_data = mysqli_fetch_assoc($price_query) ?: [
    'min_purchase' => 0,
    'max_purchase' => 0,
    'avg_purchase' => 0,
    'min_selling' => 0,
    'max_selling' => 0,
    'avg_selling' => 0,
    'min_mrp' => 0,
    'max_mrp' => 0,
    'avg_mrp' => 0
];

/* ===============================
   FETCH LATEST PRICE FOR DISPLAY
================================ */
$latest_price_query = mysqli_query(
    $conn,
    "SELECT 
        purchase_price,
        selling_price,
        mrp
     FROM stock_batches
     WHERE medicine_id = $id
       AND expiry_date >= CURDATE()
       AND quantity > 0
     ORDER BY added_at DESC
     LIMIT 1"
);

$latest_prices = mysqli_fetch_assoc($latest_price_query) ?: [
    'purchase_price' => 0,
    'selling_price' => 0,
    'mrp' => 0
];

/* ===============================
   HANDLE FORM SUBMISSION - FIXED
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $category_id = intval($_POST['category_id'] ?? 0);
    $type_id = intval($_POST['type_id'] ?? 0);
    $generic_id = intval($_POST['generic_id'] ?? 0);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

    // Validate required fields
    if (empty($name)) {
        $error = "Medicine name is required.";
    } elseif ($category_id <= 0) {
        $error = "Please select a category.";
    } elseif ($type_id <= 0) {
        $error = "Please select a type.";
    } else {
        // Prepare the update query
        $update_query = "
            UPDATE medicines SET 
                name = '$name',
                category_id = $category_id,
                type_id = $type_id,
                generic_id = " . ($generic_id > 0 ? $generic_id : "NULL") . ",
                description = '$description',
                updated_at = NOW()
            WHERE id = $id
        ";

        // Execute the query
        if (mysqli_query($conn, $update_query)) {
            // Update was successful
            $success_message = "Medicine updated successfully!";

            // Refresh the medicine data to show updated values
            $result = mysqli_query(
                $conn,
                "SELECT 
                    m.*, 
                    c.name AS category_name, 
                    t.name AS type_name,
                    g.name AS generic_name
                 FROM medicines m
                 LEFT JOIN medicine_categories c ON m.category_id = c.id
                 LEFT JOIN medicine_types t ON m.type_id = t.id
                 LEFT JOIN medicine_generics g ON m.generic_id = g.id
                 WHERE m.id = $id
                 LIMIT 1"
            );
            $medicine = mysqli_fetch_assoc($result);

            // Set success flag for JavaScript redirect
            $success = true;
        } else {
            $error = "Database Error: " . mysqli_error($conn);
        }
    }
}

/* ===============================
   FETCH STOCK SUMMARY
================================ */
$stock_query = mysqli_query(
    $conn,
    "SELECT 
        COALESCE(SUM(quantity), 0) as total_stock
     FROM stock_batches
     WHERE medicine_id = $id
       AND expiry_date >= CURDATE()
       AND quantity > 0"
);

$stock_data = mysqli_fetch_assoc($stock_query) ?: ['total_stock' => 0];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8fafc;
            min-height: 100vh;
        }

        .glass-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .gradient-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .gradient-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .gradient-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .gradient-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>

<body class="min-h-screen font-sans">

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden p-4 lg:p-6">
            <!-- Success Message -->
            <?php if ($success_message): ?>
                <div class="glass-card p-6 mb-6 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 animate-fade-in">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-4">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Success!</h3>
                            <p class="text-green-600 mt-1"><?php echo htmlspecialchars($success_message); ?></p>
                            <p class="text-sm text-gray-500 mt-2">Redirecting to medicines page in <span id="countdown">3</span> seconds...</p>
                        </div>
                    </div>
                </div>

                <script>
                    // Auto-redirect after 3 seconds
                    let seconds = 3;
                    const countdownElement = document.getElementById('countdown');
                    const countdownInterval = setInterval(() => {
                        seconds--;
                        countdownElement.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(countdownInterval);
                            window.location.href = 'medicines.php?success=Medicine updated successfully';
                        }
                    }, 1000);
                </script>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="glass-card p-6 mb-6 border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 animate-fade-in">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Update Failed</h3>
                            <p class="text-red-600 mt-1"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="glass-card p-6 mb-6 animate-fade-in">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-edit text-blue-600 mr-2"></i>
                            Edit Medicine
                        </h1>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <span class="font-mono bg-gray-100 px-2 py-1 rounded">
                                    MED-<?php echo str_pad($medicine['id'], 6, '0', STR_PAD_LEFT); ?>
                                </span>
                                <span class="text-gray-400">•</span>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($medicine['name']); ?></span>
                            </div>
                            <?php if (!empty($medicine['category_name'])): ?>
                                <span class="text-gray-400">•</span>
                                <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded">
                                    <i class="fas fa-tag mr-1 text-xs"></i>
                                    <?php echo htmlspecialchars($medicine['category_name']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($medicine['type_name'])): ?>
                                <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                                    <i class="fas fa-box mr-1 text-xs"></i>
                                    <?php echo htmlspecialchars($medicine['type_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <a href="medicines.php"
                            class="px-5 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Medicines</span>
                        </a>
                        <a href="add_stock.php?medicine_id=<?php echo $id; ?>"
                            class="gradient-primary text-white px-5 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Stock</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left Column - Edit Form -->
                <div class="lg:col-span-2">
                    <!-- Edit Form Card -->
                    <div class="glass-card p-6 mb-6 animate-fade-in" style="animation-delay: 0.1s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                            <i class="fas fa-pills text-blue-500"></i>
                            Edit Medicine Information
                        </h3>

                        <form method="POST" action="" class="space-y-6">
                            <!-- Medicine Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Medicine Name *
                                </label>
                                <input type="text"
                                    name="name"
                                    value="<?php echo htmlspecialchars($medicine['name']); ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition bg-white shadow-sm form-input"
                                    required
                                    placeholder="Enter medicine name">
                            </div>

                            <!-- Category and Type -->
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Category *
                                    </label>
                                    <select name="category_id"
                                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition bg-white shadow-sm appearance-none"
                                        required>
                                        <option value="">Select Category</option>
                                        <?php
                                        mysqli_data_seek($categories, 0);
                                        while ($cat = mysqli_fetch_assoc($categories)):
                                        ?>
                                            <option value="<?php echo $cat['id']; ?>"
                                                <?php echo ($medicine['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Type *
                                    </label>
                                    <select name="type_id"
                                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition bg-white shadow-sm appearance-none"
                                        required>
                                        <option value="">Select Type</option>
                                        <?php
                                        mysqli_data_seek($types, 0);
                                        while ($type = mysqli_fetch_assoc($types)):
                                        ?>
                                            <option value="<?php echo $type['id']; ?>"
                                                <?php echo ($medicine['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Generic Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Generic Name
                                </label>
                                <select name="generic_id"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition bg-white shadow-sm appearance-none">
                                    <option value="">Select Generic</option>
                                    <?php
                                    mysqli_data_seek($generics, 0);
                                    while ($generic = mysqli_fetch_assoc($generics)):
                                    ?>
                                        <option value="<?php echo $generic['id']; ?>"
                                            <?php echo ($medicine['generic_id'] == $generic['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($generic['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Description
                                </label>
                                <textarea name="description"
                                    rows="4"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition bg-white shadow-sm form-input"
                                    placeholder="Enter medicine description (usage, side effects, precautions, etc.)"><?php echo htmlspecialchars($medicine['description']); ?></textarea>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex flex-col md:flex-row gap-4 pt-6 border-t border-gray-200">
                                <button type="submit"
                                    name="submit"
                                    class="gradient-primary text-white px-6 py-4 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md flex-1">
                                    <i class="fas fa-save"></i>
                                    <span>Save Changes</span>
                                </button>

                                <a href="medicines.php"
                                    class="px-6 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium flex items-center justify-center gap-2 flex-1">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel</span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Information -->
                <div class="space-y-6">
                    <!-- Stock Information -->
                    <div class="glass-card p-6 animate-fade-in" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-boxes text-green-500"></i>
                            Stock Information
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Total Stock</span>
                                <span class="text-xl font-bold <?php echo $stock_data['total_stock'] == 0 ? 'text-red-600' : ($stock_data['total_stock'] < 40 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                    <?php echo number_format($stock_data['total_stock']); ?> units
                                </span>
                            </div>

                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <?php
                                $stock_percent = $stock_data['total_stock'] > 100 ? 100 : $stock_data['total_stock'];
                                $stock_color = $stock_data['total_stock'] == 0 ? 'bg-red-500' : ($stock_data['total_stock'] < 40 ? 'bg-yellow-500' : 'bg-green-500');
                                ?>
                                <div class="h-full <?php echo $stock_color; ?>" style="width: <?php echo $stock_percent; ?>%"></div>
                            </div>

                            <div class="pt-4">
                                <a href="stock.php?medicine_id=<?php echo $id; ?>"
                                    class="w-full block text-center px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:shadow-lg transition font-medium shadow-sm">
                                    <i class="fas fa-chart-bar mr-2"></i>
                                    View Stock Details
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Price Information -->
                    <div class="glass-card p-6 animate-fade-in" style="animation-delay: 0.3s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-tag text-purple-500"></i>
                            Price Information
                        </h3>

                        <!-- Current Prices -->
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Current Prices</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Purchase Price:</span>
                                    <span class="font-medium text-blue-600">
                                        Rs <?php echo number_format($latest_prices['purchase_price'], 2); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Selling Price:</span>
                                    <span class="font-medium text-green-600">
                                        Rs <?php echo number_format($latest_prices['selling_price'], 2); ?>
                                    </span>
                                </div>
                                <?php if ($latest_prices['mrp'] > 0): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">MRP:</span>
                                        <span class="font-medium text-purple-600">
                                            Rs <?php echo number_format($latest_prices['mrp'], 2); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Price Range -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Price Range (All Batches)</h4>
                            <div class="space-y-4">
                                <div class="p-4 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-white">
                                    <div class="text-sm text-gray-600 mb-1">Purchase Price</div>
                                    <div class="flex items-center justify-between">
                                        <div class="text-lg font-bold text-blue-600">
                                            Rs <?php echo number_format($price_data['min_purchase'], 2); ?>
                                            <?php if ($price_data['max_purchase'] > $price_data['min_purchase']): ?>
                                                <span class="text-sm font-normal text-gray-500"> - Rs <?php echo number_format($price_data['max_purchase'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($price_data['avg_purchase'] > 0): ?>
                                            <div class="text-xs text-gray-500">Avg: Rs <?php echo number_format($price_data['avg_purchase'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="p-4 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-white">
                                    <div class="text-sm text-gray-600 mb-1">Selling Price</div>
                                    <div class="flex items-center justify-between">
                                        <div class="text-lg font-bold text-green-600">
                                            Rs <?php echo number_format($price_data['min_selling'], 2); ?>
                                            <?php if ($price_data['max_selling'] > $price_data['min_selling']): ?>
                                                <span class="text-sm font-normal text-gray-500"> - Rs <?php echo number_format($price_data['max_selling'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($price_data['avg_selling'] > 0): ?>
                                            <div class="text-xs text-gray-500">Avg: Rs <?php echo number_format($price_data['avg_selling'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($price_data['min_mrp'] > 0): ?>
                                    <div class="p-4 border-l-4 border-purple-500 bg-gradient-to-r from-purple-50 to-white">
                                        <div class="text-sm text-gray-600 mb-1">MRP</div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-lg font-bold text-purple-600">
                                                Rs <?php echo number_format($price_data['min_mrp'], 2); ?>
                                                <?php if ($price_data['max_mrp'] > $price_data['min_mrp']): ?>
                                                    <span class="text-sm font-normal text-gray-500"> - Rs <?php echo number_format($price_data['max_mrp'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($price_data['avg_mrp'] > 0): ?>
                                                <div class="text-xs text-gray-500">Avg: Rs <?php echo number_format($price_data['avg_mrp'], 2); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Profit Margin -->
                        <?php if ($latest_prices['purchase_price'] > 0 && $latest_prices['selling_price'] > 0): ?>
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Profit Margin</span>
                                    <?php
                                    $margin = (($latest_prices['selling_price'] - $latest_prices['purchase_price']) / $latest_prices['purchase_price']) * 100;
                                    $margin_color = $margin >= 20 ? 'text-green-600' : ($margin >= 10 ? 'text-yellow-600' : 'text-red-600');
                                    ?>
                                    <span class="text-lg font-bold <?php echo $margin_color; ?>">
                                        <?php echo number_format($margin, 1); ?>%
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Based on current purchase and selling prices
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-6">
                            <a href="add_stock.php?medicine_id=<?php echo $id; ?>"
                                class="w-full block text-center px-4 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-xl hover:shadow-lg transition font-medium shadow-sm">
                                <i class="fas fa-plus mr-2"></i>
                                Update Prices via Stock
                            </a>
                        </div>
                    </div>

                    <!-- Delete Section -->
                    <div class="glass-card p-6 animate-fade-in" style="animation-delay: 0.4s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-trash-alt text-red-500"></i>
                            Dangerous Zone
                        </h3>

                        <div class="space-y-4">
                            <p class="text-sm text-gray-600">
                                Deleting a medicine will remove it permanently from the system along with all associated stock records.
                            </p>

                            <?php if ($stock_data['total_stock'] > 0): ?>
                                <div class="p-4 bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-xl">
                                    <div class="flex items-start">
                                        <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 mr-3"></i>
                                        <div>
                                            <h4 class="font-medium text-red-800">Warning</h4>
                                            <p class="text-sm text-red-600 mt-1">
                                                This medicine has <?php echo number_format($stock_data['total_stock']); ?> units in stock.
                                                Deleting will remove all stock records.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button onclick="showDeleteModal()"
                                class="w-full px-4 py-3 gradient-danger text-white rounded-xl hover:shadow-lg transition font-medium shadow-sm">
                                <i class="fas fa-trash-alt mr-2"></i>
                                Delete Medicine
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Delete Medicine</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete <strong class="text-red-600"><?php echo htmlspecialchars($medicine['name']); ?></strong>?
                    <?php if ($stock_data['total_stock'] > 0): ?>
                        <span class="block text-red-600 font-medium mt-2">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            This will delete <?php echo number_format($stock_data['total_stock']); ?> units of stock.
                        </span>
                    <?php endif; ?>
                </p>
                <div class="flex gap-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                        Cancel
                    </button>
                    <a href="delete_medicine.php?id=<?php echo $id; ?>"
                        onclick="return confirm('Final confirmation: Delete this medicine and all associated stock? This action cannot be undone.')"
                        class="flex-1 px-4 py-3 gradient-danger text-white rounded-xl hover:shadow-lg transition text-center font-medium">
                        Delete Medicine
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Delete modal functions
        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Form submission with loading state
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');

            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                // Show loading state
                submitBtn.innerHTML = `
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    <span>Saving Changes...</span>
                `;
                submitBtn.disabled = true;
            });
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideDeleteModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
    </script>
</body>

</html>