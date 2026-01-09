<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Both admin and pharmacists can access
if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    header("Location: ../index.php");
    exit;
}

// Check user permissions based on role
$can_view = true; // Both can view
$can_add = ($_SESSION['role'] === 'pharmacist');
$can_edit = ($_SESSION['role'] === 'pharmacist');
$can_delete = ($_SESSION['role'] === 'pharmacist');

// Fetch all medicines with categories and types
$result = mysqli_query($conn, "SELECT m.*, c.name AS category_name, t.name AS type_name
                               FROM medicines m
                               LEFT JOIN medicine_categories c ON m.category_id=c.id
                               LEFT JOIN medicine_types t ON m.type_id=t.id
                               ORDER BY m.name ASC");

// Get statistics
$total_medicines = mysqli_num_rows(mysqli_query($conn, "SELECT COUNT(*) as total FROM medicines"));
$total_categories = mysqli_num_rows(mysqli_query($conn, "SELECT COUNT(*) as total FROM medicine_categories"));
$total_types = mysqli_num_rows(mysqli_query($conn, "SELECT COUNT(*) as total FROM medicine_types"));

// Get stock data for current stock display
$stock_query = mysqli_query(
    $conn,
    "SELECT m.id, m.name, COALESCE(SUM(sb.quantity), 0) as total_stock,
            MIN(CASE WHEN sb.expiry_date >= CURDATE() THEN sb.expiry_date END) as next_expiry
     FROM medicines m
     LEFT JOIN stock_batches sb ON m.id = sb.medicine_id
     GROUP BY m.id"
);

$stock_data = [];
while ($row = mysqli_fetch_assoc($stock_query)) {
    $stock_data[$row['id']] = $row;
}

// Get low stock medicines
$low_stock = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT m.id) as low_stock_count 
     FROM medicines m
     LEFT JOIN stock_batches sb ON m.id = sb.medicine_id
     WHERE sb.quantity <= 50 OR sb.quantity IS NULL"
);
$low_stock_count = mysqli_fetch_assoc($low_stock)['low_stock_count'] ?: 0;

// Get expiring soon medicines
$expiring_soon = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT m.id) as expiring_count 
     FROM medicines m
     LEFT JOIN stock_batches sb ON m.id = sb.medicine_id
     WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
);
$expiring_count = mysqli_fetch_assoc($expiring_soon)['expiring_count'] ?: 0;

// Get recently added medicines
$recent_medicines = mysqli_query(
    $conn,
    "SELECT m.name, m.created_at, c.name as category_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     ORDER BY m.created_at DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines Management - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

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

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(251, 191, 36, 0.3);
            box-shadow: 0 4px 20px rgba(251, 191, 36, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(251, 191, 36, 0.2);
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(251, 191, 36, 0.1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: var(--primary-yellow);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: var(--primary-yellow-dark);
        }

        .table-row:hover {
            background-color: rgba(254, 243, 199, 0.3);
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

        .stock-indicator {
            width: 80px;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .stock-fill {
            height: 100%;
            border-radius: 4px;
        }

        .status-active {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .status-inactive {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .status-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }

            #printable-content,
            #printable-content * {
                visibility: visible;
            }

            #printable-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background: white;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>


        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden ">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Medicines <span class="gradient-text">Inventory</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-pills text-yellow-500"></i>
                            <span>Manage all medicines in the pharmacy inventory</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span><?php echo ucfirst($_SESSION['role']); ?> Access</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <i class="fas fa-eye text-green-500"></i>
                                <span class="text-green-600 font-medium">View Only Mode</span>
                            <?php else: ?>
                                <i class="fas fa-edit text-blue-500"></i>
                                <span class="text-blue-600 font-medium">Edit Mode</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <?php if ($can_add): ?>
                            <a href="add_medicine.php"
                                class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-plus"></i>
                                <span>Add New Medicine</span>
                                <i class="fas fa-arrow-right text-yellow-100 text-sm"></i>
                            </a>
                        <?php endif; ?>
                        <a href="stock.php"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-boxes text-yellow-500"></i>
                            <span>View Stock</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-pills text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($total_medicines); ?></h3>
                    <p class="text-gray-600 mb-3">Registered Medicines</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-yellow h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-teal-500 to-teal-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-tags text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Categories</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($total_categories); ?></h3>
                    <p class="text-gray-600 mb-3">Medicine Categories</p>
                    <div class="flex items-center text-sm text-teal-500">
                        <i class="fas fa-layer-group mr-1"></i>
                        <span>Organized classification</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-prescription-bottle-alt text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Types</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($total_types); ?></h3>
                    <p class="text-gray-600 mb-3">Medicine Types</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-vial mr-1"></i>
                        <span>Form & dosage types</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-red-500 to-red-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1">
                            <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full text-center"><?php echo $low_stock_count; ?> Low Stock</span>
                            <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded-full text-center"><?php echo $expiring_count; ?> Expiring</span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $low_stock_count + $expiring_count; ?></h3>
                    <p class="text-gray-600 mb-3">Inventory Alerts</p>
                    <div class="flex items-center text-sm text-red-500">
                        <i class="fas fa-bell mr-1"></i>
                        <span>Requires attention</span>
                    </div>
                </div>
            </div>

            <!-- Medicines Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">All Medicines</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo mysqli_num_rows($result); ?> medicines in inventory</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search by name, generic, or manufacturer..."
                                class="pl-10 pr-4 py-2 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-yellow-400"></i>
                        </div>

                        <!-- Filter by Category -->
                        <select class="px-4 py-2 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="">All Categories</option>
                            <?php
                            $categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
                            while ($cat = mysqli_fetch_assoc($categories)):
                            ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-yellow-50 to-yellow-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-2">
                                        <span>Medicine Details</span>
                                        <i class="fas fa-sort text-yellow-400 cursor-pointer hover:text-yellow-600"></i>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Classification
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Stock Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Price Info
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-yellow-50">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    $stock_info = $stock_data[$row['id']] ?? ['total_stock' => 0, 'next_expiry' => null];
                                    $total_stock = $stock_info['total_stock'];
                                    $stock_percentage = min(100, ($total_stock / 200) * 100);

                                    // Determine stock status
                                    if ($total_stock <= 50) {
                                        $stock_status = 'Low';
                                        $status_class = 'status-warning';
                                    } elseif ($total_stock <= 100) {
                                        $stock_status = 'Medium';
                                        $status_class = 'status-active';
                                    } else {
                                        $stock_status = 'Good';
                                        $status_class = 'status-active';
                                    }

                                    // Get price information from stock batches
                                    $price_query = mysqli_query(
                                        $conn,
                                        "SELECT MIN(purchase_price) as min_purchase, 
                                                MAX(purchase_price) as max_purchase,
                                                MIN(selling_price) as min_selling,
                                                MAX(selling_price) as max_selling,
                                                MIN(mrp) as min_mrp,
                                                MAX(mrp) as max_mrp
                                         FROM stock_batches 
                                         WHERE medicine_id = {$row['id']} 
                                         AND quantity > 0"
                                    );
                                    $price_data = mysqli_fetch_assoc($price_query);
                                ?>
                                    <tr class="table-row hover:bg-yellow-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas fa-pills text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($row['name']); ?></h4>
                                                    <p class="text-sm text-gray-500 mb-2">
                                                        ID: <span class="font-mono"><?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                        <?php if (!empty($row['generic_name'])): ?>
                                                            • <span class="font-medium"><?php echo htmlspecialchars($row['generic_name']); ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($row['description'])): ?>
                                                        <p class="text-xs text-gray-600 line-clamp-2">
                                                            <?php echo htmlspecialchars(substr($row['description'], 0, 120)); ?>
                                                            <?php if (strlen($row['description']) > 120): ?>...<?php endif; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <?php if (!empty($row['category_name'])): ?>
                                                        <span class="badge-category">
                                                            <i class="fas fa-tag mr-1 text-xs"></i>
                                                            <?php echo htmlspecialchars($row['category_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['type_name'])): ?>
                                                        <span class="badge-type">
                                                            <i class="fas fa-prescription-bottle mr-1 text-xs"></i>
                                                            <?php echo htmlspecialchars($row['type_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($row['manufacturer'])): ?>
                                                    <div class="flex items-center space-x-2 text-sm text-gray-700">
                                                        <i class="fas fa-industry text-gray-400 text-xs"></i>
                                                        <span class="font-medium"><?php echo htmlspecialchars($row['manufacturer']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['strength']) && !empty($row['unit'])): ?>
                                                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                                                        <i class="fas fa-weight text-gray-400 text-xs"></i>
                                                        <span><?php echo htmlspecialchars($row['strength'] . ' ' . $row['unit']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div>
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-sm font-medium text-gray-700">Current Stock</span>
                                                        <span class="text-sm font-bold <?php echo $total_stock <= 50 ? 'text-red-600' : ($total_stock <= 100 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                                            <?php echo number_format($total_stock); ?> units
                                                        </span>
                                                    </div>
                                                    <div class="stock-indicator">
                                                        <div class="stock-fill <?php echo $status_class; ?>" style="width: <?php echo $stock_percentage; ?>%"></div>
                                                    </div>
                                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                                        <span>0</span>
                                                        <span><?php echo $stock_status; ?></span>
                                                        <span>200+</span>
                                                    </div>
                                                </div>
                                                <?php if ($stock_info['next_expiry']): ?>
                                                    <div class="text-xs text-gray-600">
                                                        <i class="fas fa-calendar-alt mr-1"></i>
                                                        Next expiry: <?php echo date('M d, Y', strtotime($stock_info['next_expiry'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <?php if (!empty($price_data['min_purchase'])): ?>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Purchase Price</span>
                                                        <span class="text-sm font-bold text-blue-600">
                                                            Rs <?php echo number_format($price_data['min_purchase'], 2); ?>
                                                            <?php if ($price_data['min_purchase'] != $price_data['max_purchase']): ?>
                                                                - <?php echo number_format($price_data['max_purchase'], 2); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($price_data['min_selling'])): ?>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Selling Price</span>
                                                        <span class="text-sm font-bold text-green-600">
                                                            Rs <?php echo number_format($price_data['min_selling'], 2); ?>
                                                            <?php if ($price_data['min_selling'] != $price_data['max_selling']): ?>
                                                                - <?php echo number_format($price_data['max_selling'], 2); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($price_data['min_mrp'])): ?>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">MRP</span>
                                                        <span class="text-sm font-bold text-purple-600">
                                                            Rs <?php echo number_format($price_data['min_mrp'], 2); ?>
                                                            <?php if ($price_data['min_mrp'] != $price_data['max_mrp']): ?>
                                                                - <?php echo number_format($price_data['max_mrp'], 2); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col space-y-2">
                                                <!-- View Details Button -->
                                                <button onclick="showMedicineModal(<?php echo $row['id']; ?>)"
                                                    class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-eye text-sm"></i>
                                                    <span class="text-sm font-medium">View Details</span>
                                                </button>

                                                <!-- Action Buttons Row -->
                                                <div class="flex space-x-2">
                                                    <!-- Stock Button -->
                                                    <a href="stock.php?medicine_id=<?php echo $row['id']; ?>"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-boxes text-xs"></i>
                                                        <span class="text-xs font-medium">Stock</span>
                                                    </a>

                                                    <!-- Edit Button (Pharmacist only) -->
                                                    <?php if ($can_edit): ?>
                                                        <a href="edit_medicine.php?id=<?php echo $row['id']; ?>"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-edit text-xs"></i>
                                                            <span class="text-xs font-medium">Edit</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Delete Button (Pharmacist only) -->
                                                    <?php if ($can_delete): ?>
                                                        <button onclick="showDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-trash-alt text-xs"></i>
                                                            <span class="text-xs font-medium">Delete</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-pills text-yellow-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Medicines Found</h4>
                                            <p class="text-gray-600 mb-6">Get started by adding your first medicine to the inventory.</p>
                                            <?php if ($can_add): ?>
                                                <a href="add_medicine.php"
                                                    class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add First Medicine</span>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500">Contact pharmacist to add medicines</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 border-t border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo mysqli_num_rows($result); ?> medicines •
                                <span class="font-medium text-yellow-600">
                                    <?php
                                    if ($_SESSION['role'] === 'admin') {
                                        echo 'View Only Access';
                                    } else {
                                        echo 'Full Management Access';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-4 py-2 border border-yellow-200 rounded-lg hover:bg-yellow-50 transition flex items-center space-x-2 bg-white/80 shadow-sm" onclick="exportAllToPDF()">
                                <i class="fas fa-file-export text-yellow-500"></i>
                                <span class="text-sm text-gray-700">Export</span>
                            </button>
                            <button class="px-4 py-2 border border-yellow-200 rounded-lg hover:bg-yellow-50 transition flex items-center space-x-2 bg-white/80 shadow-sm" onclick="printAllMedicines()">
                                <i class="fas fa-print text-yellow-500"></i>
                                <span class="text-sm text-gray-700">Print</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Additions & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mx-6 my-8">
                <!-- Recent Additions -->
                <div class="glass-card rounded-2xl p-6 lg:col-span-2 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-history text-yellow-500"></i>
                        <span>Recently Added Medicines</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($recent_medicines) > 0): ?>
                            <?php while ($recent = mysqli_fetch_assoc($recent_medicines)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-pills text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($recent['name']); ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($recent['category_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500"><?php echo date('M d', strtotime($recent['created_at'])); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($recent['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-gray-500">No recent additions</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-6">
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <?php if ($can_add): ?>
                                <a href="add_medicine.php"
                                    class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                    <span class="text-gray-700">Add New Medicine</span>
                                    <i class="fas fa-plus text-yellow-500"></i>
                                </a>
                            <?php endif; ?>
                            <a href="stock.php"
                                class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition shadow-sm">
                                <span class="text-gray-700">View Stock Levels</span>
                                <i class="fas fa-chart-bar text-blue-500"></i>
                            </a>
                            <a href="categories.php"
                                class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition shadow-sm">
                                <span class="text-gray-700">Manage Categories</span>
                                <i class="fas fa-tags text-green-500"></i>
                            </a>
                            <button onclick="showBarcodeModal()"
                                class="w-full flex items-center justify-between p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition shadow-sm">
                                <span class="text-gray-700">Generate Barcodes</span>
                                <i class="fas fa-barcode text-purple-500"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Medicine Details Modal -->
    <div id="medicineModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden animate-fade-in-up">
            <div class="p-6 border-b border-yellow-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-pills text-yellow-500 mr-2"></i>
                        <span id="modalMedicineName">Medicine Details</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <!-- Export PDF Button -->
                        <button onclick="exportToPDF()"
                            class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2">
                            <i class="fas fa-file-pdf"></i>
                            <span>Export PDF</span>
                        </button>
                        <!-- Print Button -->
                        <button onclick="printMedicine()"
                            class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </button>
                        <button onclick="hideMedicineModal()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                            <i class="fas fa-times text-gray-600"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] custom-scrollbar" id="modalContent">
                <!-- Content will be loaded dynamically via JavaScript -->
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-yellow-500 text-3xl mb-4"></i>
                    <p class="text-gray-600">Loading medicine details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Medicine</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete <span id="deleteMedicineName" class="font-semibold text-yellow-600"></span>?
                    This will also delete all associated stock records. This action cannot be undone.
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a id="deleteConfirmLink"
                        href="#"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                        Delete Medicine
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Barcode Modal -->
    <div id="barcodeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-barcode text-purple-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-4">Generate Barcodes</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Medicine</label>
                        <select class="w-full px-4 py-3 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500 focus:outline-none transition">
                            <option value="">All Medicines</option>
                            <?php
                            mysqli_data_seek($result, 0); // Reset pointer
                            while ($med = mysqli_fetch_assoc($result)):
                            ?>
                                <option value="<?php echo $med['id']; ?>"><?php echo htmlspecialchars($med['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" min="1" max="100" value="10"
                            class="w-full px-4 py-3 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500 focus:outline-none transition">
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="hideBarcodeModal()"
                            class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button onclick="generateBarcodes()"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Printable Content (hidden by default) -->
    <div id="printable-content" class="hidden">
        <!-- This will be populated when printing -->
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Global variables
        let currentMedicineData = null;

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

        // Show medicine details modal
        async function showMedicineModal(medicineId) {
            try {
                document.getElementById('medicineModal').classList.remove('hidden');

                // Show loading state
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-yellow-500 text-3xl mb-4"></i>
                        <p class="text-gray-600">Loading medicine details...</p>
                    </div>
                `;

                // Fetch medicine details via AJAX
                const response = await fetch(`ajax/get_medicine_details.php?id=${medicineId}`);
                const data = await response.json();

                if (data.success) {
                    currentMedicineData = data;
                    updateMedicineModal(data);
                } else {
                    throw new Error('Failed to load medicine details');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i>
                        <p class="text-gray-600">Error loading medicine details</p>
                        <button onclick="hideMedicineModal()" class="mt-4 px-4 py-2 bg-yellow-500 text-white rounded-lg">
                            Close
                        </button>
                    </div>
                `;
            }
        }

        // Update modal content with medicine data
        function updateMedicineModal(data) {
            const medicine = data.medicine;
            const stockInfo = data.stockInfo;
            const batches = data.batches;

            document.getElementById('modalMedicineName').textContent = medicine.name;

            let html = `
                <div class="space-y-8">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                Basic Information
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Medicine ID:</span>
                                    <span class="font-semibold">MED-${String(medicine.id).padStart(6, '0')}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Brand Name:</span>
                                    <span class="font-semibold text-gray-800">${medicine.name}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Generic Name:</span>
                                    <span class="font-semibold">${medicine.generic_name || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Category:</span>
                                    <span class="badge-category">${medicine.category_name}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Type:</span>
                                    <span class="badge-type">${medicine.type_name}</span>
                                </div>
                                <div class="flex justify-between items-start">
                                    <span class="text-gray-600">Description:</span>
                                    <span class="text-right text-gray-800">${medicine.description || 'No description available'}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Added Date:</span>
                                    <span class="text-gray-800">${new Date(medicine.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stock Information -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-boxes text-green-500 mr-2"></i>
                                Stock Information
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Total Stock:</span>
                                    <span class="font-semibold ${stockInfo.total_stock <= 50 ? 'text-red-600' : stockInfo.total_stock <= 100 ? 'text-yellow-600' : 'text-green-600'}">
                                        ${stockInfo.total_stock} units
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Stock Status:</span>
                                    <span class="${stockInfo.total_stock <= 50 ? 'bg-red-100 text-red-800' : stockInfo.total_stock <= 100 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'} px-3 py-1 rounded-full text-sm font-medium">
                                        ${stockInfo.total_stock <= 50 ? 'Low Stock' : stockInfo.total_stock <= 100 ? 'Medium Stock' : 'Good Stock'}
                                    </span>
                                </div>
                                ${stockInfo.next_expiry ? `
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Next Expiry:</span>
                                    <span class="font-semibold ${new Date(stockInfo.next_expiry) - new Date() < 30*24*60*60*1000 ? 'text-red-600' : 'text-gray-800'}">
                                        ${new Date(stockInfo.next_expiry).toLocaleDateString()}
                                    </span>
                                </div>
                                ` : ''}
                                ${stockInfo.low_stock_batches > 0 ? `
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Low Stock Batches:</span>
                                    <span class="font-semibold text-red-600">${stockInfo.low_stock_batches}</span>
                                </div>
                                ` : ''}
                                ${stockInfo.expiring_soon > 0 ? `
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Expiring Soon:</span>
                                    <span class="font-semibold text-yellow-600">${stockInfo.expiring_soon} batches</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Price Information -->
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-tag text-purple-500 mr-2"></i>
                            Price Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-xl">
                                <div class="text-center mb-2">
                                    <i class="fas fa-shopping-cart text-blue-500 text-xl"></i>
                                </div>
                                <h5 class="font-semibold text-gray-800 text-center mb-2">Purchase Price</h5>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-blue-600">Rs  ${data.priceInfo.min_purchase ? parseFloat(data.priceInfo.min_purchase).toFixed(2) : '0.00'}</p>
                                    ${data.priceInfo.min_purchase !== data.priceInfo.max_purchase ? `
                                    <p class="text-sm text-gray-600">Range: Rs  ${parseFloat(data.priceInfo.min_purchase).toFixed(2)} - Rs  ${parseFloat(data.priceInfo.max_purchase).toFixed(2)}</p>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="bg-green-50 p-4 rounded-xl">
                                <div class="text-center mb-2">
                                    <i class="fas fa-cash-register text-green-500 text-xl"></i>
                                </div>
                                <h5 class="font-semibold text-gray-800 text-center mb-2">Selling Price</h5>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-green-600">Rs  ${data.priceInfo.min_selling ? parseFloat(data.priceInfo.min_selling).toFixed(2) : '0.00'}</p>
                                    ${data.priceInfo.min_selling !== data.priceInfo.max_selling ? `
                                    <p class="text-sm text-gray-600">Range: Rs  ${parseFloat(data.priceInfo.min_selling).toFixed(2)} - Rs  ${parseFloat(data.priceInfo.max_selling).toFixed(2)}</p>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 p-4 rounded-xl">
                                <div class="text-center mb-2">
                                    <i class="fas fa-tags text-purple-500 text-xl"></i>
                                </div>
                                <h5 class="font-semibold text-gray-800 text-center mb-2">MRP</h5>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-purple-600">Rs  ${data.priceInfo.min_mrp ? parseFloat(data.priceInfo.min_mrp).toFixed(2) : '0.00'}</p>
                                    ${data.priceInfo.min_mrp !== data.priceInfo.max_mrp ? `
                                    <p class="text-sm text-gray-600">Range: Rs  ${parseFloat(data.priceInfo.min_mrp).toFixed(2)} - Rs  ${parseFloat(data.priceInfo.max_mrp).toFixed(2)}</p>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        ${data.priceInfo.min_purchase && data.priceInfo.min_selling ? `
                        <div class="mt-4 text-center">
                            <p class="text-gray-600">
                                Average Margin: 
                                <span class="font-bold ${((data.priceInfo.avg_selling - data.priceInfo.avg_purchase) / data.priceInfo.avg_purchase * 100) >= 20 ? 'text-green-600' : ((data.priceInfo.avg_selling - data.priceInfo.avg_purchase) / data.priceInfo.avg_purchase * 100) >= 10 ? 'text-yellow-600' : 'text-red-600'}">
                                    ${((data.priceInfo.avg_selling - data.priceInfo.avg_purchase) / data.priceInfo.avg_purchase * 100).toFixed(1)}%
                                </span>
                            </p>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Stock Batches -->
                    ${batches && batches.length > 0 ? `
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-layer-group text-yellow-500 mr-2"></i>
                            Stock Batches
                        </h4>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-yellow-50">
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Batch No</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Quantity</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Purchase Price</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Selling Price</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">MRP</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Expiry Date</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${batches.map(batch => `
                                    <tr class="border-t border-yellow-100 hover:bg-yellow-50">
                                        <td class="px-4 py-2">${batch.batch_no || 'N/A'}</td>
                                        <td class="px-4 py-2 ${batch.quantity <= 10 ? 'text-red-600 font-semibold' : ''}">${batch.quantity}</td>
                                        <td class="px-4 py-2">Rs  ${parseFloat(batch.purchase_price || 0).toFixed(2)}</td>
                                        <td class="px-4 py-2">Rs  ${parseFloat(batch.selling_price || 0).toFixed(2)}</td>
                                        <td class="px-4 py-2">Rs  ${parseFloat(batch.mrp || 0).toFixed(2)}</td>
                                        <td class="px-4 py-2 ${new Date(batch.expiry_date) - new Date() < 30*24*60*60*1000 ? 'text-red-600 font-semibold' : ''}">
                                            ${new Date(batch.expiry_date).toLocaleDateString()}
                                        </td>
                                        <td class="px-4 py-2">${batch.supplier_name || 'N/A'}</td>
                                    </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('modalContent').innerHTML = html;
        }

        // Hide medicine modal
        function hideMedicineModal() {
            document.getElementById('medicineModal').classList.add('hidden');
            currentMedicineData = null;
        }

        // Export to PDF
        async function exportToPDF() {
            if (!currentMedicineData) return;

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                // Add header
                doc.setFontSize(20);
                doc.setTextColor(245, 158, 11);
                doc.text('MediCare Pharma - Medicine Report', 105, 20, null, null, 'center');

                doc.setFontSize(11);
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`, 105, 30, null, null, 'center');

                // Add medicine details
                doc.setFontSize(16);
                doc.setTextColor(0, 0, 0);
                doc.text(`Medicine: ${currentMedicineData.medicine.name}`, 20, 45);

                doc.setFontSize(12);
                doc.text(`ID: MED-${String(currentMedicineData.medicine.id).padStart(6, '0')}`, 20, 55);
                doc.text(`Generic: ${currentMedicineData.medicine.generic_name || 'N/A'}`, 20, 60);
                doc.text(`Category: ${currentMedicineData.medicine.category_name}`, 20, 65);
                doc.text(`Type: ${currentMedicineData.medicine.type_name}`, 20, 70);

                // Add stock info
                doc.setFontSize(14);
                doc.text('Stock Information', 20, 85);
                doc.setFontSize(12);
                doc.text(`Total Stock: ${currentMedicineData.stockInfo.total_stock} units`, 20, 95);
                doc.text(`Stock Status: ${currentMedicineData.stockInfo.total_stock <= 50 ? 'Low' : currentMedicineData.stockInfo.total_stock <= 100 ? 'Medium' : 'Good'}`, 20, 100);

                if (currentMedicineData.stockInfo.next_expiry) {
                    doc.text(`Next Expiry: ${new Date(currentMedicineData.stockInfo.next_expiry).toLocaleDateString()}`, 20, 105);
                }

                // Add price info
                doc.setFontSize(14);
                doc.text('Price Information', 20, 125);
                doc.setFontSize(12);

                const priceInfo = currentMedicineData.priceInfo;
                doc.text(`Purchase Price: Rs  ${priceInfo.min_purchase ? parseFloat(priceInfo.min_purchase).toFixed(2) : '0.00'}`, 20, 135);
                if (priceInfo.min_purchase !== priceInfo.max_purchase) {
                    doc.text(`Range: Rs  ${parseFloat(priceInfo.min_purchase).toFixed(2)} - Rs  ${parseFloat(priceInfo.max_purchase).toFixed(2)}`, 20, 140);
                }

                doc.text(`Selling Price: Rs  ${priceInfo.min_selling ? parseFloat(priceInfo.min_selling).toFixed(2) : '0.00'}`, 20, 150);
                if (priceInfo.min_selling !== priceInfo.max_selling) {
                    doc.text(`Range: Rs  ${parseFloat(priceInfo.min_selling).toFixed(2)} - Rs  ${parseFloat(priceInfo.max_selling).toFixed(2)}`, 20, 155);
                }

                doc.text(`MRP: Rs  ${priceInfo.min_mrp ? parseFloat(priceInfo.min_mrp).toFixed(2) : '0.00'}`, 20, 165);
                if (priceInfo.min_mrp !== priceInfo.max_mrp) {
                    doc.text(`Range: Rs  ${parseFloat(priceInfo.min_mrp).toFixed(2)} - Rs  ${parseFloat(priceInfo.max_mrp).toFixed(2)}`, 20, 170);
                }

                if (priceInfo.avg_purchase && priceInfo.avg_selling) {
                    const margin = ((priceInfo.avg_selling - priceInfo.avg_purchase) / priceInfo.avg_purchase * 100).toFixed(1);
                    doc.text(`Average Margin: ${margin}%`, 20, 180);
                }

                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text('Confidential - For Internal Use Only', 105, 280, null, null, 'center');

                // Save the PDF
                doc.save(`Medicine_${currentMedicineData.medicine.name}_${new Date().toISOString().slice(0,10)}.pdf`);

                showNotification('PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Print medicine details
        function printMedicine() {
            if (!currentMedicineData) return;

            const medicine = currentMedicineData.medicine;
            const stockInfo = currentMedicineData.stockInfo;
            const priceInfo = currentMedicineData.priceInfo;
            const batches = currentMedicineData.batches || [];

            let printContent = `
                <div style="padding: 20px; font-family: Arial, sans-serif;">
                    <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f59e0b; padding-bottom: 15px;">
                        <h1 style="color: #f59e0b; margin: 0;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 10px 0 5px 0;">Medicine Details Report</h2>
                        <p style="color: #666; margin: 0;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                    </div>
                    
                    <!-- Basic Info -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #3b82f6; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Basic Information</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Medicine ID:</td>
                                <td style="padding: 8px 0; font-weight: bold;">MED-${String(medicine.id).padStart(6, '0')}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Brand Name:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #333;">${medicine.name}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Generic Name:</td>
                                <td style="padding: 8px 0;">${medicine.generic_name || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Category:</td>
                                <td style="padding: 8px 0;">
                                    <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                                        ${medicine.category_name}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Type:</td>
                                <td style="padding: 8px 0;">
                                    <span style="background: #8b5cf6; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                                        ${medicine.type_name}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Description:</td>
                                <td style="padding: 8px 0;">${medicine.description || 'No description available'}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Stock Info -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #10b981; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Stock Information</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Total Stock:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: ${stockInfo.total_stock <= 50 ? '#ef4444' : stockInfo.total_stock <= 100 ? '#f59e0b' : '#10b981'}">
                                    ${stockInfo.total_stock} units
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Stock Status:</td>
                                <td style="padding: 8px 0;">
                                    <span style="background: ${stockInfo.total_stock <= 50 ? '#ef4444' : stockInfo.total_stock <= 100 ? '#f59e0b' : '#10b981'}; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                                        ${stockInfo.total_stock <= 50 ? 'Low Stock' : stockInfo.total_stock <= 100 ? 'Medium Stock' : 'Good Stock'}
                                    </span>
                                </td>
                            </tr>
                            ${stockInfo.next_expiry ? `
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Next Expiry:</td>
                                <td style="padding: 8px 0; color: ${new Date(stockInfo.next_expiry) - new Date() < 30*24*60*60*1000 ? '#ef4444' : '#333'}; font-weight: ${new Date(stockInfo.next_expiry) - new Date() < 30*24*60*60*1000 ? 'bold' : 'normal'}">
                                    ${new Date(stockInfo.next_expiry).toLocaleDateString()}
                                </td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                    
                    <!-- Price Info -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #8b5cf6; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Price Information</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                            <tr>
                                <td style="padding: 8px 0; width: 40%; color: #666;">Purchase Price:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #3b82f6;">
                                    Rs  ${priceInfo.min_purchase ? parseFloat(priceInfo.min_purchase).toFixed(2) : '0.00'}
                                    ${priceInfo.min_purchase !== priceInfo.max_purchase ? ` (Range: Rs  ${parseFloat(priceInfo.min_purchase).toFixed(2)} - Rs  ${parseFloat(priceInfo.max_purchase).toFixed(2)})` : ''}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Selling Price:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #10b981;">
                                    Rs  ${priceInfo.min_selling ? parseFloat(priceInfo.min_selling).toFixed(2) : '0.00'}
                                    ${priceInfo.min_selling !== priceInfo.max_selling ? ` (Range: Rs  ${parseFloat(priceInfo.min_selling).toFixed(2)} - Rs  ${parseFloat(priceInfo.max_selling).toFixed(2)})` : ''}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">MRP:</td>
                                <td style="padding: 8px 0; font-weight: bold; color: #8b5cf6;">
                                    Rs  ${priceInfo.min_mrp ? parseFloat(priceInfo.min_mrp).toFixed(2) : '0.00'}
                                    ${priceInfo.min_mrp !== priceInfo.max_mrp ? ` (Range: Rs  ${parseFloat(priceInfo.min_mrp).toFixed(2)} - Rs  ${parseFloat(priceInfo.max_mrp).toFixed(2)})` : ''}
                                </td>
                            </tr>
                        </table>
                        
                        ${priceInfo.avg_purchase && priceInfo.avg_selling ? `
                        <div style="background: #fef3c7; padding: 10px; border-radius: 5px; border-left: 4px solid #f59e0b;">
                            <p style="margin: 0; color: #333;">
                                <strong>Average Margin:</strong> 
                                <span style="color: ${((priceInfo.avg_selling - priceInfo.avg_purchase) / priceInfo.avg_purchase * 100) >= 20 ? '#10b981' : ((priceInfo.avg_selling - priceInfo.avg_purchase) / priceInfo.avg_purchase * 100) >= 10 ? '#f59e0b' : '#ef4444'}; font-weight: bold;">
                                    ${((priceInfo.avg_selling - priceInfo.avg_purchase) / priceInfo.avg_purchase * 100).toFixed(1)}%
                                </span>
                            </p>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Batches Table -->
                    ${batches.length > 0 ? `
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: #f59e0b; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Stock Batches</h3>
                        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 12px;">
                            <thead>
                                <tr style="background: #fef3c7;">
                                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Batch No</th>
                                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Quantity</th>
                                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Purchase Price</th>
                                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Selling Price</th>
                                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">MRP</th>
                                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${batches.map(batch => `
                                <tr>
                                    <td style="border: 1px solid #ddd; padding: 8px;">${batch.batch_no || 'N/A'}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; ${batch.quantity <= 10 ? 'color: #ef4444; font-weight: bold;' : ''}">${batch.quantity}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px;">Rs  ${parseFloat(batch.purchase_price || 0).toFixed(2)}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px;">Rs  ${parseFloat(batch.selling_price || 0).toFixed(2)}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px;">Rs  ${parseFloat(batch.mrp || 0).toFixed(2)}</td>
                                    <td style="border: 1px solid #ddd; padding: 8px; ${new Date(batch.expiry_date) - new Date() < 30*24*60*60*1000 ? 'color: #ef4444; font-weight: bold;' : ''}">
                                        ${new Date(batch.expiry_date).toLocaleDateString()}
                                    </td>
                                </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    ` : ''}
                    
                    <!-- Footer -->
                    <div style="margin-top: 40px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px;">
                        <p style="margin: 5px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 5px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 5px 0;">Page 1 of 1</p>
                    </div>
                </div>
            `;

            // Set printable content
            document.getElementById('printable-content').innerHTML = printContent;

            // Trigger print
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print Medicine Details - ${medicine.name}</title>
                    <style>
                        @media print {
                            @page { margin: 20mm; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            window.onafterprint = function() {
                                window.close();
                            };
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        // Export all medicines to PDF
        async function exportAllToPDF() {
            try {
                showNotification('Generating PDF report...', 'info');

                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                // Add header
                doc.setFontSize(20);
                doc.setTextColor(245, 158, 11);
                doc.text('MediCare Pharma - All Medicines Report', 105, 20, null, null, 'center');

                doc.setFontSize(11);
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 105, 30, null, null, 'center');
                doc.text(`Total Medicines: ${<?php echo $total_medicines; ?>}`, 105, 35, null, null, 'center');

                // Add table headers
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                let y = 50;

                // Table headers
                doc.setFillColor(245, 158, 11);
                doc.rect(10, y, 190, 10, 'F');
                doc.setTextColor(255, 255, 255);
                doc.text('ID', 15, y + 7);
                doc.text('Medicine Name', 35, y + 7);
                doc.text('Generic', 80, y + 7);
                doc.text('Category', 110, y + 7);
                doc.text('Stock', 140, y + 7);
                doc.text('Price (Rs )', 160, y + 7);

                y += 15;
                doc.setTextColor(0, 0, 0);

                // Reset result pointer
                <?php mysqli_data_seek($result, 0); ?>

                // Add medicine rows
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                    $stock_info  = $stock_data[$row['id']] ?? ['total_stock' => 0];
                    $total_stock = $stock_info['total_stock'];

                    $price_query = mysqli_query(
                        $conn,
                        "SELECT MIN(selling_price) as min_price 
         FROM stock_batches 
         WHERE medicine_id = {$row['id']}"
                    );
                    $price = mysqli_fetch_assoc($price_query);
                    $min_price = $price['min_price'] ? number_format($price['min_price'], 2) : '0.00';
                    ?>

                    <?php echo "
        if (y > 270) {
            doc.addPage();
            y = 20;
        }

        doc.text('MED-" . str_pad($row['id'], 6, '0', STR_PAD_LEFT) . "', 15, y);
        doc.text('" . addslashes(substr($row['name'], 0, 20)) . "', 35, y);
        doc.text('" . addslashes(substr($row['generic_name'] ?? 'N/A', 0, 15)) . "', 80, y);
        doc.text('" . addslashes(substr($row['category_name'] ?? 'N/A', 0, 12)) . "', 110, y);
        doc.text('" . $total_stock . "', 140, y);
        doc.text('" . $min_price . "', 160, y);

        y = y + 7;
    "; ?>

                <?php endwhile; ?>


                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text('Page 1', 105, 285, null, null, 'center');
                doc.text('Confidential - For Internal Use Only', 105, 290, null, null, 'center');

                // Save the PDF
                doc.save(`All_Medicines_${new Date().toISOString().slice(0,10)}.pdf`);

                showNotification('All medicines PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Print all medicines
        function printAllMedicines() {
            let printContent = `
                <div style="padding: 20px; font-family: Arial, sans-serif;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #f59e0b; padding-bottom: 10px;">
                        <h1 style="color: #f59e0b; margin: 0;">MediCare Pharma</h1>
                        <h2 style="color: #333; margin: 10px 0 5px 0;">All Medicines Inventory</h2>
                        <p style="color: #666; margin: 0 0 5px 0;">Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                        <p style="color: #666; margin: 0;">Total Medicines: ${<?php echo $total_medicines; ?>}</p>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 12px; margin-top: 20px;">
                        <thead>
                            <tr style="background: #fef3c7;">
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">ID</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Medicine Name</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Generic Name</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Category</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Type</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Stock</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Selling Price</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            // Reset result pointer
            <?php mysqli_data_seek($result, 0); ?>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php
                $stock_info = $stock_data[$row['id']] ?? ['total_stock' => 0];
                $total_stock = $stock_info['total_stock'];
                $price_query = mysqli_query($conn, "SELECT MIN(selling_price) as min_price FROM stock_batches WHERE medicine_id = {$row['id']}");
                $price = mysqli_fetch_assoc($price_query);
                ?>

                printContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">MED-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; font-weight: bold;"><?php echo htmlspecialchars($row['name']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($row['generic_name'] ?? 'N/A'); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($row['type_name'] ?? 'N/A'); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px; ${<?php echo $total_stock; ?> <= 50 ? 'color: #ef4444; font-weight: bold;' : <?php echo $total_stock; ?> <= 100 ? 'color: #f59e0b;' : 'color: #10b981;'}"><?php echo $total_stock; ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;">Rs  <?php echo $price['min_price'] ? number_format($price['min_price'], 2) : '0.00'; ?></td>
                    </tr>
                `;
            <?php endwhile; ?>

            printContent += `
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px;">
                        <p style="margin: 5px 0;">Confidential - For Internal Use Only</p>
                        <p style="margin: 5px 0;">MediCare Pharmacy Management System</p>
                        <p style="margin: 5px 0;">Page 1 of 1</p>
                    </div>
                </div>
            `;

            // Set printable content
            document.getElementById('printable-content').innerHTML = printContent;

            // Trigger print
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print All Medicines</title>
                    <style>
                        @media print {
                            @page { margin: 15mm; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            window.onafterprint = function() {
                                window.close();
                            };
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        // Delete modal functions
        function showDeleteModal(id, name) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteMedicineName').textContent = name;
            document.getElementById('deleteConfirmLink').href = `delete_medicine.php?id=${id}`;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Barcode modal functions
        function showBarcodeModal() {
            document.getElementById('barcodeModal').classList.remove('hidden');
        }

        function hideBarcodeModal() {
            document.getElementById('barcodeModal').classList.add('hidden');
        }

        function generateBarcodes() {
            const medicineId = document.querySelector('#barcodeModal select').value;
            const quantity = document.querySelector('#barcodeModal input[type="number"]').value;

            // Generate barcodes logic here
            alert(`Generating ${quantity} barcodes for selected medicine...`);
            hideBarcodeModal();
        }

        // Close modals when clicking outside
        [document.getElementById('deleteModal'), document.getElementById('barcodeModal'), document.getElementById('medicineModal')].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (this.id === 'deleteModal') hideDeleteModal();
                        if (this.id === 'barcodeModal') hideBarcodeModal();
                        if (this.id === 'medicineModal') hideMedicineModal();
                    }
                });
            }
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

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

            // Auto-refresh stock data every 30 seconds
            setInterval(() => {
                console.log('Auto-refreshing medicines data...');
                // Here you would typically make an AJAX call to refresh stock data
            }, 30000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Ctrl/Cmd + N for new medicine (pharmacist only)
            if ((e.ctrlKey || e.metaKey) && e.key === 'n' && <?php echo $can_add ? 'true' : 'false'; ?>) {
                e.preventDefault();
                window.location.href = 'add_medicine.php';
            }

            // Escape key to close modals
            if (e.key === 'Escape') {
                if (!document.getElementById('medicineModal').classList.contains('hidden')) {
                    hideMedicineModal();
                }

                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    hideDeleteModal();
                }
                if (!document.getElementById('barcodeModal').classList.contains('hidden')) {
                    hideBarcodeModal();
                }
            }
        });
    </script>
</body>

</html>