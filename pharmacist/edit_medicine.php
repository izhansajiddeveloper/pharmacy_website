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
        t.name AS type_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     LEFT JOIN medicine_types t ON m.type_id = t.id
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

/* ===============================
   FETCH DROPDOWN DATA
================================ */
$categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
$types      = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");

/* ===============================
   FETCH LATEST PRICES
================================ */
$price_query = mysqli_query(
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

$latest_prices = mysqli_fetch_assoc($price_query) ?: [
    'purchase_price' => 0,
    'selling_price'  => 0,
    'mrp'            => 0
];

/* ===============================
   HANDLE FORM SUBMISSION
================================ */
if (isset($_POST['submit'])) {

    $name         = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $generic_name = mysqli_real_escape_string($conn, $_POST['generic_name'] ?? '');
    $category_id  = intval($_POST['category_id'] ?? 0);
    $type_id      = intval($_POST['type_id'] ?? 0);
    $manufacturer = mysqli_real_escape_string($conn, $_POST['manufacturer'] ?? '');
    $strength     = mysqli_real_escape_string($conn, $_POST['strength'] ?? '');
    $unit         = mysqli_real_escape_string($conn, $_POST['unit'] ?? '');
    $description  = mysqli_real_escape_string($conn, $_POST['description'] ?? '');

    if ($name === '' || $category_id === 0 || $type_id === 0) {
        $error = "Please fill all required fields.";
    } else {

        $medicine_update = "
            UPDATE medicines SET 
                name = '$name',
                generic_name = '$generic_name',
                category_id = $category_id,
                type_id = $type_id,
                manufacturer = '$manufacturer',
                strength = '$strength',
                unit = '$unit',
                description = '$description',
                updated_at = NOW()
            WHERE id = $id
        ";

        if (mysqli_query($conn, $medicine_update)) {
            $success = true;

            /* Update local array so page refresh not required */
            $medicine['name']         = $name;
            $medicine['generic_name'] = $generic_name;
            $medicine['category_id']  = $category_id;
            $medicine['type_id']      = $type_id;
            $medicine['manufacturer'] = $manufacturer;
            $medicine['strength']     = $strength;
            $medicine['unit']         = $unit;
            $medicine['description']  = $description;
            $medicine['updated_at']   = date('Y-m-d H:i:s');
        } else {
            $error = "Database Error: " . mysqli_error($conn);
        }
    }
}

/* ===============================
   STOCK SUMMARY
================================ */
$stock_info = mysqli_query(
    $conn,
    "SELECT 
        COALESCE(SUM(quantity), 0) AS total_stock,
        COUNT(DISTINCT batch_no) AS batches,
        MIN(expiry_date) AS next_expiry
     FROM stock_batches
     WHERE medicine_id = $id
       AND expiry_date >= CURDATE()
       AND quantity > 0"
);

$stock_data = mysqli_fetch_assoc($stock_info) ?: [
    'total_stock' => 0,
    'batches'     => 0,
    'next_expiry' => null
];

/* ===============================
   STOCK STATISTICS
================================ */
$stock_stats = mysqli_query(
    $conn,
    "SELECT 
        COUNT(*) AS total_batches,
        SUM(CASE 
            WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            THEN 1 ELSE 0 END) AS expiring_soon,
        SUM(CASE 
            WHEN quantity <= 20 THEN 1 ELSE 0 END) AS low_stock_batches
     FROM stock_batches
     WHERE medicine_id = $id
       AND expiry_date >= CURDATE()
       AND quantity > 0"
);

$stats_data = mysqli_fetch_assoc($stock_stats) ?: [
    'total_batches'     => 0,
    'expiring_soon'     => 0,
    'low_stock_batches' => 0
];
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine - MediCare Pharma</title>
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

        .badge-category {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-type {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-in-stock {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-low-stock {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .status-out-of-stock {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
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
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Medicine Updated Successfully!</h3>
                            <p class="text-gray-600">Changes have been saved to the system.</p>
                            <div class="mt-3 flex space-x-3">
                                <a href="medicines.php" class="inline-flex items-center space-x-2 text-yellow-600 hover:text-yellow-800 font-medium px-4 py-2 bg-yellow-50 rounded-lg">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Medicines</span>
                                </a>
                                <a href="edit_medicine.php?id=<?php echo $id; ?>" class="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-800 font-medium px-4 py-2 bg-blue-50 rounded-lg">
                                    <i class="fas fa-redo"></i>
                                    <span>Continue Editing</span>
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
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Update Failed</h3>
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
                            Edit <span class="gradient-text">Medicine</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-pills text-yellow-500"></i>
                            <span>Update medicine information and settings</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span>Pharmacist Access</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="medicines.php"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to List</span>
                        </a>
                        <a href="add_stock.php?medicine_id=<?php echo $id; ?>"
                            class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 flex items-center space-x-2 shadow">
                            <i class="fas fa-plus"></i>
                            <span>Add Stock</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left Column - Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Medicine Header -->
                    <div class="glass-card rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s">
                        <div class="px-6 py-6 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow">
                                        <i class="fas fa-pills text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($medicine['name']); ?></h3>
                                        <p class="text-blue-100">ID: MED-<?php echo str_pad($medicine['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                        <div class="flex items-center space-x-3 mt-2">
                                            <span class="badge-category"><?php echo htmlspecialchars($medicine['category_name']); ?></span>
                                            <span class="badge-type"><?php echo htmlspecialchars($medicine['type_name']); ?></span>
                                            <?php
                                            $stock_status = $stock_data['total_stock'] > 100 ? 'status-in-stock' : ($stock_data['total_stock'] > 20 ? 'status-low-stock' : 'status-out-of-stock');
                                            $status_text = $stock_data['total_stock'] > 100 ? 'In Stock' : ($stock_data['total_stock'] > 20 ? 'Low Stock' : 'Out of Stock');
                                            ?>
                                            <span class="status-badge <?php echo $stock_status; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($latest_prices && $latest_prices['selling_price']): ?>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold">Rs <?php echo number_format($latest_prices['selling_price'], 2); ?></div>
                                        <div class="text-sm text-blue-200">Current Selling Price</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Form -->
                        <form method="POST" class="p-6">
                            <div class="space-y-8">
                                <!-- Basic Information Section -->
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
                                                value="<?php echo htmlspecialchars($medicine['name']); ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
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
                                            <input type="text"
                                                name="generic_name"
                                                value="<?php echo htmlspecialchars($medicine['generic_name']); ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Classification Section -->
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
                                            <select name="category_id"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm appearance-none"
                                                required>
                                                <option value="">Select Category</option>
                                                <?php
                                                mysqli_data_seek($categories, 0);
                                                while ($cat = mysqli_fetch_assoc($categories)): ?>
                                                    <option value="<?php echo $cat['id']; ?>"
                                                        <?php echo ($medicine['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <!-- Type -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-prescription-bottle-alt text-purple-500 text-sm"></i>
                                                    <span>Type *</span>
                                                </span>
                                            </label>
                                            <select name="type_id"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm appearance-none"
                                                required>
                                                <option value="">Select Type</option>
                                                <?php
                                                mysqli_data_seek($types, 0);
                                                while ($type = mysqli_fetch_assoc($types)): ?>
                                                    <option value="<?php echo $type['id']; ?>"
                                                        <?php echo ($medicine['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Manufacturer & Strength Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-industry text-green-500"></i>
                                        <span>Manufacturing Details</span>
                                    </h4>
                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Manufacturer -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-industry text-gray-500 text-sm"></i>
                                                    <span>Manufacturer</span>
                                                </span>
                                            </label>
                                            <input type="text"
                                                name="manufacturer"
                                                value="<?php echo htmlspecialchars($medicine['manufacturer'] ?? ''); ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                placeholder="Enter manufacturer name">
                                        </div>

                                        <!-- Strength -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-weight text-green-500 text-sm"></i>
                                                    <span>Strength</span>
                                                </span>
                                            </label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <input type="text"
                                                    name="strength"
                                                    value="<?php echo htmlspecialchars($medicine['strength'] ?? ''); ?>"
                                                    class="px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                    placeholder="e.g., 500">
                                                <input type="text"
                                                    name="unit"
                                                    value="<?php echo htmlspecialchars($medicine['unit'] ?? ''); ?>"
                                                    class="px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                    placeholder="e.g., mg">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-file-alt text-yellow-500"></i>
                                        <span>Additional Information</span>
                                    </h4>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="flex items-center space-x-2">
                                                <i class="fas fa-align-left text-gray-500 text-sm"></i>
                                                <span>Description</span>
                                            </span>
                                        </label>
                                        <textarea name="description"
                                            rows="4"
                                            class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                            placeholder="Enter medicine description, usage instructions, side effects, precautions, etc."><?php echo htmlspecialchars($medicine['description']); ?></textarea>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col md:flex-row gap-4 pt-6 border-t border-yellow-100">
                                    <button type="submit"
                                        name="submit"
                                        class="flex-1 gradient-yellow text-white py-4 rounded-xl font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] group shadow relative overflow-hidden">
                                        <span class="relative z-10 flex items-center justify-center space-x-3">
                                            <i class="fas fa-save group-hover:rotate-12 transition-transform duration-300"></i>
                                            <span>Save Changes</span>
                                            <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform duration-300 text-yellow-100"></i>
                                        </span>
                                        <div class="absolute inset-0 bg-gradient-to-r from-yellow-600 to-yellow-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    </button>

                                    <a href="medicines.php"
                                        class="flex-1 px-4 py-4 border-2 border-yellow-200 text-gray-700 rounded-xl font-bold text-lg hover:bg-yellow-50 transition text-center shadow-sm">
                                        <span class="flex items-center justify-center space-x-3">
                                            <i class="fas fa-times"></i>
                                            <span>Cancel</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stock Information -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-boxes text-green-500"></i>
                            <span>Stock Information</span>
                        </h3>
                        <div class="grid md:grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-gray-800 mb-2"><?php echo number_format($stock_data['total_stock'] ?? 0); ?></div>
                                <p class="text-sm text-gray-600">Total Stock</p>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $stock_data['batches'] ?? 0; ?></div>
                                <p class="text-sm text-gray-600">Active Batches</p>
                            </div>
                            <?php if ($stock_data && $stock_data['next_expiry']): ?>
                                <div class="text-center">
                                    <div class="text-xl font-bold <?php echo (strtotime($stock_data['next_expiry']) - time() < 30 * 24 * 60 * 60) ? 'text-red-600' : 'text-gray-800'; ?> mb-2">
                                        <?php echo date('M d, Y', strtotime($stock_data['next_expiry'])); ?>
                                    </div>
                                    <p class="text-sm text-gray-600">Next Expiry</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($stats_data): ?>
                            <div class="mt-4 grid md:grid-cols-3 gap-4">
                                <div class="text-center p-3 bg-yellow-50 rounded-xl">
                                    <div class="text-lg font-bold text-yellow-600"><?php echo $stats_data['total_batches']; ?></div>
                                    <p class="text-xs text-gray-600">Total Batches</p>
                                </div>
                                <div class="text-center p-3 bg-red-50 rounded-xl">
                                    <div class="text-lg font-bold text-red-600"><?php echo $stats_data['expiring_soon']; ?></div>
                                    <p class="text-xs text-gray-600">Expiring Soon</p>
                                </div>
                                <div class="text-center p-3 bg-blue-50 rounded-xl">
                                    <div class="text-lg font-bold text-blue-600"><?php echo $stats_data['low_stock_batches']; ?></div>
                                    <p class="text-xs text-gray-600">Low Stock</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column - Information -->
                <div class="space-y-6">
                    <!-- Price Information -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-tag text-purple-500"></i>
                            <span>Price Information</span>
                        </h3>
                        <?php if ($latest_prices): ?>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Purchase Price</span>
                                    <span class="text-lg font-bold text-blue-600">
                                        Rs <?php echo number_format($latest_prices['purchase_price'], 2); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Selling Price</span>
                                    <span class="text-lg font-bold text-green-600">
                                        Rs <?php echo number_format($latest_prices['selling_price'], 2); ?>
                                    </span>
                                </div>
                                <?php if ($latest_prices['mrp']): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">MRP</span>
                                        <span class="text-lg font-bold text-purple-600">
                                            Rs <?php echo number_format($latest_prices['mrp'], 2); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($latest_prices['purchase_price'] && $latest_prices['selling_price']):
                                    $margin = (($latest_prices['selling_price'] - $latest_prices['purchase_price']) / $latest_prices['purchase_price']) * 100;
                                ?>
                                    <div class="pt-4 border-t border-yellow-100">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Profit Margin</span>
                                            <span class="text-lg font-bold <?php echo $margin >= 20 ? 'text-green-600' : ($margin >= 10 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                <?php echo number_format($margin, 1); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-exclamation-circle text-yellow-500 text-2xl mb-2"></i>
                                <p class="text-gray-600">No price information available</p>
                                <p class="text-sm text-gray-500 mt-1">Add stock to set prices</p>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4">
                            <a href="add_stock.php?medicine_id=<?php echo $id; ?>"
                                class="w-full flex items-center justify-center space-x-2 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:shadow-lg transition shadow-sm">
                                <i class="fas fa-plus"></i>
                                <span>Update Prices via Stock</span>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <a href="add_stock.php?medicine_id=<?php echo $id; ?>"
                                class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-yellow-100 border border-yellow-200 rounded-xl hover:bg-yellow-100 transition group shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                        <i class="fas fa-plus text-yellow-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Add Stock</h4>
                                        <p class="text-xs text-gray-600">Add new stock batch</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-yellow-500 group-hover:translate-x-2 transition-transform"></i>
                            </a>

                            <a href="stock.php?medicine_id=<?php echo $id; ?>"
                                class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl hover:bg-blue-100 transition group shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-chart-bar text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Stock Details</h4>
                                        <p class="text-xs text-gray-600">View batch details</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-blue-500 group-hover:translate-x-2 transition-transform"></i>
                            </a>

                            <button onclick="showDeleteModal()"
                                class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-xl hover:bg-red-100 transition group shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-trash-alt text-red-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-red-700 text-sm">Delete Medicine</h4>
                                        <p class="text-xs text-red-500">Permanent action</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-red-500 group-hover:translate-x-2 transition-transform"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Medicine Stats -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Medicine Stats</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Medicine ID</span>
                                <span class="font-medium">MED-<?php echo str_pad($medicine['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Created</span>
                                <span class="text-sm font-medium">
                                    <?php echo date('M d, Y', strtotime($medicine['created_at'])); ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Last Updated</span>
                                <span class="text-sm font-medium">
                                    <?php
                                    echo (!empty($medicine['updated_at']))
                                        ? date('M d, Y', strtotime($medicine['updated_at']))
                                        : 'Never';
                                    ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Status</span>
                                <span class="text-sm font-medium px-3 py-1 rounded-full <?php echo $stock_status; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>

                        <?php
                        $medicine_id = (int)$id;

                        $activity_query = mysqli_query(
                            $conn,
                            "SELECT 
            'Stock Added' AS type,
            quantity,
            received_date AS activity_date
         FROM stock_batches
         WHERE medicine_id = $medicine_id
         ORDER BY received_date DESC
         LIMIT 3"
                        );
                        ?>

                        <div class="space-y-3">
                            <?php if ($activity_query && mysqli_num_rows($activity_query) > 0): ?>
                                <?php while ($activity = mysqli_fetch_assoc($activity_query)): ?>
                                    <div class="flex items-center justify-between p-2 hover:bg-yellow-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                                <i class="fas fa-plus text-green-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($activity['type']); ?>
                                                </h4>
                                                <p class="text-xs text-gray-500">
                                                    <?php
                                                    echo !empty($activity['activity_date'])
                                                        ? date('M d, Y', strtotime($activity['activity_date']))
                                                        : 'N/A';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>

                                        <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded">
                                            +<?php echo (int)$activity['quantity']; ?>
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history text-gray-300 text-xl mb-2"></i>
                                    <p class="text-sm text-gray-500">No recent activity</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Medicine</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete <span class="font-semibold text-yellow-600"><?php echo htmlspecialchars($medicine['name']); ?></span>?
                    <?php if ($stock_data['total_stock'] > 0): ?>
                        <span class="block text-red-600 font-medium mt-2">
                            <i class="fas fa-exclamation-circle"></i>
                            Warning: This medicine has <?php echo $stock_data['total_stock']; ?> units in stock. Deleting will remove all stock records.
                        </span>
                    <?php endif; ?>
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a href="delete_medicine.php?id=<?php echo $id; ?>"
                        onclick="return confirm('Final confirmation: Delete this medicine and all associated stock?')"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
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

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');

            form.addEventListener('submit', function(e) {
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = `
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    <span>Saving Changes...</span>
                `;
                submitBtn.disabled = true;

                // Re-enable after 10 seconds if something goes wrong
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 10000);
            });

            // Auto-save form data
            let autoSaveTimer;
            form.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    saveFormData();
                }, 30000); // 30 seconds
            });

            function saveFormData() {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                localStorage.setItem('editMedicineForm_' + <?php echo $id; ?>, JSON.stringify(data));
                console.log('Form data auto-saved');
            }

            // Load saved form data
            const savedData = localStorage.getItem('editMedicineForm_' + <?php echo $id; ?>);
            if (savedData) {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox' || field.type === 'radio') {
                            field.checked = data[key] === 'on';
                        } else {
                            field.value = data[key];
                        }
                    }
                });
                console.log('Form data restored from draft');
            }

            // Clear saved data on successful submission
            form.addEventListener('submit', function() {
                localStorage.removeItem('editMedicineForm_' + <?php echo $id; ?>);
            });
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[type="submit"]').click();
            }

            // Escape to close delete modal
            if (e.key === 'Escape') {
                hideDeleteModal();
            }

            // Ctrl/Cmd + D for delete
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                showDeleteModal();
            }
        });

        // Close modal on outside click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
    </script>
</body>

</html>