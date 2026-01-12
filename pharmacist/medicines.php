<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Both admin and pharmacists can access
if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    header("Location: ../index.php");
    exit;
}

// Check user permissions
$can_edit = ($_SESSION['role'] === 'pharmacist');
$can_delete = ($_SESSION['role'] === 'pharmacist');

// Initialize search variables
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$low_stock = isset($_GET['low_stock']) ? 1 : 0;
$no_stock = isset($_GET['no_stock']) ? 1 : 0;
$full_stock = isset($_GET['full_stock']) ? 1 : 0;
$view_all = isset($_GET['view_all']) ? 1 : 0;

// Stock thresholds
$low_stock_threshold = 40; // Can be changed to 10 or any value
$full_stock_threshold = 100;

// Pagination
$per_page = $view_all ? 1000 : 10; // 10 per page normally, 1000 for "view all"
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Build base query
$query = "SELECT m.*, c.name AS category_name, t.name AS type_name, g.name AS generic_name
          FROM medicines m
          LEFT JOIN medicine_categories c ON m.category_id = c.id
          LEFT JOIN medicine_types t ON m.type_id = t.id
          LEFT JOIN medicine_generics g ON m.generic_id = g.id
          WHERE 1=1";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (m.name LIKE '%$search%' 
                OR g.name LIKE '%$search%' 
                OR c.name LIKE '%$search%' 
                OR t.name LIKE '%$search%'
                OR m.description LIKE '%$search%'
                OR m.id IN (SELECT DISTINCT medicine_id FROM stock_batches WHERE batch_no LIKE '%$search%'))";
}

// Get total count for pagination
$count_query = str_replace(
    "SELECT m.*, c.name AS category_name, t.name AS type_name, g.name AS generic_name",
    "SELECT COUNT(*) as total",
    $query
);
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);

// Add ordering and pagination
$query .= " ORDER BY m.name ASC LIMIT $offset, $per_page";

// Execute query
$result = mysqli_query($conn, $query);

// Get stock data
$stock_query = mysqli_query(
    $conn,
    "SELECT m.id, COALESCE(SUM(sb.quantity), 0) as total_stock
     FROM medicines m
     LEFT JOIN stock_batches sb ON m.id = sb.medicine_id AND sb.quantity > 0
     GROUP BY m.id"
);

$stock_data = [];
while ($row = mysqli_fetch_assoc($stock_query)) {
    $stock_data[$row['id']] = $row['total_stock'];
}

// Apply stock filters after fetching all data
$filtered_result = [];
$display_count = 0;
if ($result && mysqli_num_rows($result) > 0) {
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        $medicine_id = $row['id'];
        $total_stock = isset($stock_data[$medicine_id]) ? $stock_data[$medicine_id] : 0;

        // Apply stock filters - FIXED LOGIC
        $include = true;

        // If any stock filter is checked, we need to check if this medicine matches
        if ($low_stock || $no_stock || $full_stock) {
            $include = false; // Start with false, will become true if matches any checked filter

            // Low Stock: stock > 0 AND stock < 40
            if ($low_stock && $total_stock > 0 && $total_stock < $low_stock_threshold) {
                $include = true;
            }

            // No Stock: stock == 0
            if ($no_stock && $total_stock == 0) {
                $include = true;
            }

            // Full Stock: stock >= 100
            if ($full_stock && $total_stock >= $full_stock_threshold) {
                $include = true;
            }
        }

        if ($include) {
            $filtered_result[] = $row;
            $display_count++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines - MediCare Pharma</title>
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

        .gradient-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .gradient-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .gradient-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .table-header {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-bottom: 2px solid #cbd5e1;
        }

        .table-row:hover {
            background: linear-gradient(135deg, #fef3c7, #fef9c3);
            transform: translateY(-1px);
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
        }

        .stock-indicator {
            height: 6px;
            border-radius: 3px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .stock-fill {
            height: 100%;
            border-radius: 3px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .pagination-btn {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #3b82f6;
            color: white;
            transform: translateY(-1px);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .search-input {
            background: white;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        /* Fixed Checkbox Styles */
        .checkbox-container {
            position: relative;
        }

        .checkbox-container input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 8px;
            background: white;
            border: 2px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
            width: 100%;
        }

        .checkbox-label:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .checkbox-label.active {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .checkbox-icon {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .checkbox-label.active .checkbox-icon {
            border-color: #3b82f6;
            background: #3b82f6;
        }

        .checkbox-label.active .checkbox-icon i {
            opacity: 1;
            transform: scale(1);
        }

        .checkbox-icon i {
            color: white;
            font-size: 10px;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s ease;
        }

        .checkbox-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .checkbox-text {
            font-weight: 500;
            color: #374151;
        }

        .checkbox-count {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        .low-stock .checkbox-count {
            background: #fef3c7;
            color: #d97706;
        }

        .no-stock .checkbox-count {
            background: #fee2e2;
            color: #dc2626;
        }

        .full-stock .checkbox-count {
            background: #d1fae5;
            color: #059669;
        }

        .medicine-id {
            font-family: 'Courier New', monospace;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }

        /* Stock status colors in PHP */
        .stock-badge-red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stock-badge-yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .stock-badge-green {
            background: #d1fae5;
            color: #059669;
        }

        .stock-badge-emerald {
            background: #d1fae5;
            color: #047857;
        }

        /* Modal Styles */
        .modal-overlay {
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .batch-row {
            transition: all 0.2s ease;
        }

        .batch-row:hover {
            background: #f9fafb;
        }

        .expiring-soon {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .low-quantity {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
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
            <!-- Page Header -->
            <div class="glass-card p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-pills text-blue-600 mr-2"></i>
                            Medicines Inventory
                        </h1>
                        <p class="text-gray-600 text-sm lg:text-base">
                            Search and manage all medicines in the pharmacy
                        </p>
                    </div>
                    <?php if ($_SESSION['role'] === 'pharmacist'): ?>
                        <a href="add_medicine.php"
                            class="gradient-primary text-white px-5 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md w-full lg:w-auto">
                            <i class="fas fa-plus"></i>
                            <span>Add New Medicine</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Search Section -->
            <div class="glass-card p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-search text-blue-500 mr-2"></i>
                        Search Medicines
                    </h3>
                    <div class="text-sm text-gray-500">
                        Low stock threshold: <span class="font-bold text-yellow-600"><?php echo $low_stock_threshold; ?> units</span>
                    </div>
                </div>

                <form method="GET" class="space-y-6">
                    <!-- Main Search Bar -->
                    <div class="relative">
                        <input type="text"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by medicine name, generic name, category, type, or batch number..."
                            class="w-full search-input px-5 py-4 pl-12 rounded-xl text-base"
                            autofocus>
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>

                    <!-- Stock Filter Checkboxes -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Low Stock Checkbox -->
                        <div class="checkbox-container low-stock">
                            <input type="checkbox"
                                id="low_stock"
                                name="low_stock"
                                value="1"
                                <?php echo $low_stock ? 'checked' : ''; ?>>
                            <label for="low_stock" class="checkbox-label <?php echo $low_stock ? 'active' : ''; ?>">
                                <div class="checkbox-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="checkbox-content">
                                    <span class="checkbox-text">Low Stock</span>
                                    <span class="checkbox-count">&lt; <?php echo $low_stock_threshold; ?></span>
                                </div>
                            </label>
                        </div>

                        <!-- No Stock Checkbox -->
                        <div class="checkbox-container no-stock">
                            <input type="checkbox"
                                id="no_stock"
                                name="no_stock"
                                value="1"
                                <?php echo $no_stock ? 'checked' : ''; ?>>
                            <label for="no_stock" class="checkbox-label <?php echo $no_stock ? 'active' : ''; ?>">
                                <div class="checkbox-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="checkbox-content">
                                    <span class="checkbox-text">No Stock</span>
                                    <span class="checkbox-count">0 units</span>
                                </div>
                            </label>
                        </div>

                        <!-- Full Stock Checkbox -->
                        <div class="checkbox-container full-stock">
                            <input type="checkbox"
                                id="full_stock"
                                name="full_stock"
                                value="1"
                                <?php echo $full_stock ? 'checked' : ''; ?>>
                            <label for="full_stock" class="checkbox-label <?php echo $full_stock ? 'active' : ''; ?>">
                                <div class="checkbox-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="checkbox-content">
                                    <span class="checkbox-text">Full Stock</span>
                                    <span class="checkbox-count">100+ units</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Hidden field for view_all -->
                    <input type="hidden" name="view_all" value="<?php echo $view_all; ?>">

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-200">
                        <div class="text-sm text-gray-600">
                            <?php if ($search || $low_stock || $no_stock || $full_stock): ?>
                                <span class="font-medium text-blue-600">
                                    <i class="fas fa-filter mr-1"></i>
                                    Filters active
                                </span>
                            <?php else: ?>
                                <span>Showing all medicines</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button type="submit"
                                class="gradient-primary text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center gap-2 shadow-md">
                                <i class="fas fa-search"></i>
                                <span>Search Medicines</span>
                            </button>
                            <a href="medicines.php"
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold flex items-center gap-2">
                                <i class="fas fa-redo"></i>
                                <span>Reset Filters</span>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <div class="text-gray-700">
                    <span class="font-bold text-lg"><?php echo $display_count; ?></span> medicines found
                    <?php if ($search || $low_stock || $no_stock || $full_stock): ?>
                        <span class="text-sm text-gray-500 ml-2">(filtered from <?php echo $total_rows; ?> total)</span>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <!-- View All / View Less Toggle -->
                    <?php if ($view_all): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view_all' => 0, 'page' => 1])); ?>"
                            class="gradient-warning text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-list"></i>
                            <span>Show 10 per page</span>
                        </a>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view_all' => 1, 'page' => 1])); ?>"
                            class="gradient-success text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-list-ol"></i>
                            <span>View All Medicines</span>
                        </a>
                    <?php endif; ?>

                    <!-- Export Button -->
                    <button onclick="exportToCSV()"
                        class="px-5 py-2 border-2 border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition font-medium flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- Medicines Table -->
            <div class="glass-card overflow-hidden">
                <!-- Table Container -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="table-header">
                                <th class="px-5 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-capsules mr-2"></i>Medicine
                                </th>
                                <th class="px-5 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-tags mr-2"></i>Category
                                </th>
                                <th class="px-5 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-box mr-2"></i>Stock
                                </th>
                                <th class="px-5 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-cog mr-2"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="medicinesTableBody">
                            <?php if (count($filtered_result) > 0): ?>
                                <?php foreach ($filtered_result as $row):
                                    $medicine_id = $row['id'];
                                    $total_stock = isset($stock_data[$medicine_id]) ? $stock_data[$medicine_id] : 0;

                                    // Determine stock status
                                    if ($total_stock == 0) {
                                        $stock_status = 'No Stock';
                                        $stock_color = 'red';
                                        $stock_bg = 'stock-badge-red';
                                        $stock_text = 'text-red-700';
                                        $stock_percent = 0;
                                    } elseif ($total_stock < $low_stock_threshold) {
                                        $stock_status = 'Low Stock';
                                        $stock_color = 'yellow';
                                        $stock_bg = 'stock-badge-yellow';
                                        $stock_text = 'text-yellow-700';
                                        $stock_percent = ($total_stock / $low_stock_threshold) * 100;
                                    } elseif ($total_stock < $full_stock_threshold) {
                                        $stock_status = 'Medium';
                                        $stock_color = 'green';
                                        $stock_bg = 'stock-badge-green';
                                        $stock_text = 'text-green-700';
                                        $stock_percent = ($total_stock / $full_stock_threshold) * 100;
                                    } else {
                                        $stock_status = 'Good Stock';
                                        $stock_color = 'emerald';
                                        $stock_bg = 'stock-badge-emerald';
                                        $stock_text = 'text-emerald-700';
                                        $stock_percent = 100;
                                    }
                                ?>
                                    <tr class="table-row group" data-id="<?php echo $row['id']; ?>">
                                        <!-- Medicine Details -->
                                        <td class="px-5 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold flex-shrink-0 shadow">
                                                    <i class="fas fa-pills"></i>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <h4 class="font-semibold text-gray-900 text-sm truncate group-hover:text-blue-600 transition-colors medicine-name">
                                                        <?php echo htmlspecialchars($row['name']); ?>
                                                    </h4>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span class="medicine-id">MED-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                        <?php if (!empty($row['generic_name'])): ?>
                                                            <span class="text-xs text-blue-600 font-medium truncate generic-name">
                                                                <?php echo htmlspecialchars($row['generic_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($row['type_name'])): ?>
                                                        <span class="inline-block mt-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs font-medium rounded type-name">
                                                            <?php echo htmlspecialchars($row['type_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Category -->
                                        <td class="px-5 py-4">
                                            <?php if (!empty($row['category_name'])): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 category-name">
                                                    <i class="fas fa-tag mr-1 text-xs"></i>
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Stock Status -->
                                        <td class="px-5 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-bold <?php echo $stock_text; ?> text-sm">
                                                        <?php echo number_format($total_stock); ?> units
                                                    </span>
                                                    <span class="text-xs font-medium <?php echo $stock_text; ?> px-2 py-1 rounded-full <?php echo $stock_bg; ?>">
                                                        <?php echo $stock_status; ?>
                                                    </span>
                                                </div>
                                                <div class="stock-indicator">
                                                    <div class="stock-fill bg-<?php echo $stock_color; ?>-500" style="width: <?php echo $stock_percent; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-5 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <!-- View Details -->
                                                <button onclick="showMedicineDetails(<?php echo $row['id']; ?>)"
                                                    class="action-btn bg-blue-50 text-blue-600 hover:bg-blue-100">
                                                    <i class="fas fa-eye text-xs"></i>
                                                    <span>View</span>
                                                </button>

                                                <!-- Stock -->
                                                <a href="stock.php?medicine_id=<?php echo $row['id']; ?>"
                                                    class="action-btn bg-green-50 text-green-600 hover:bg-green-100">
                                                    <i class="fas fa-boxes text-xs"></i>
                                                    <span>Stock</span>
                                                </a>

                                                <!-- Edit - Redirect to separate edit page -->
                                                <?php if ($can_edit): ?>
                                                    <a href="edit_medicine.php?id=<?php echo $row['id']; ?>"
                                                        class="action-btn bg-yellow-50 text-yellow-600 hover:bg-yellow-100">
                                                        <i class="fas fa-edit text-xs"></i>
                                                        <span>Edit</span>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Delete -->
                                                <?php if ($can_delete): ?>
                                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')"
                                                        class="action-btn bg-red-50 text-red-600 hover:bg-red-100">
                                                        <i class="fas fa-trash text-xs"></i>
                                                        <span>Delete</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 rounded-full bg-yellow-100 flex items-center justify-center mx-auto mb-4">
                                                <i class="fas fa-pills text-yellow-500 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Medicines Found</h4>
                                            <p class="text-gray-600 mb-6">
                                                <?php if ($search || $low_stock || $no_stock || $full_stock): ?>
                                                    No medicines match your search criteria. Try different filters.
                                                <?php else: ?>
                                                    No medicines found in the inventory.
                                                <?php endif; ?>
                                            </p>
                                            <div class="flex flex-wrap gap-3 justify-center">
                                                <a href="medicines.php"
                                                    class="px-5 py-2 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                                    Clear Filters
                                                </a>
                                                <?php if ($_SESSION['role'] === 'pharmacist'): ?>
                                                    <a href="add_medicine.php"
                                                        class="gradient-primary text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200">
                                                        Add New Medicine
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1 && !$view_all): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-600">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                • Showing <?php echo min($per_page, $display_count); ?> of <?php echo $total_rows; ?> medicines
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- First Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                    class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>

                                <!-- Previous Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>"
                                    class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>

                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($p = $start_page; $p <= $end_page; $p++):
                                ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"
                                        class="pagination-btn <?php echo $p == $page ? 'gradient-primary text-white' : ''; ?>">
                                        <?php echo $p; ?>
                                    </a>
                                <?php endfor; ?>

                                <!-- Next Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>"
                                    class="pagination-btn <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>

                                <!-- Last Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                    class="pagination-btn <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($view_all): ?>
                    <div class="px-6 py-4 border-t border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-emerald-700 font-medium">
                                <i class="fas fa-info-circle mr-2"></i>
                                Viewing all <?php echo $display_count; ?> medicines
                            </div>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['view_all' => 0, 'page' => 1])); ?>"
                                class="gradient-warning text-white px-4 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 text-sm">
                                Show 10 per page
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- View Medicine Details Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden modal-content">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-pills text-blue-500 mr-2"></i>
                        <span id="modalTitle">Medicine Details</span>
                    </h3>
                    <button onclick="closeViewModal()"
                        class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                <div id="medicineDetails" class="space-y-6">
                    <!-- Loading State -->
                    <div id="viewLoading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-3xl"></i>
                        <p class="text-gray-600 mt-3">Loading medicine details...</p>
                    </div>

                    <!-- Content will be loaded here -->
                    <div id="viewContent" class="hidden">
                        <!-- Medicine Name -->
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-xl">
                            <h2 class="text-xl font-bold text-gray-900" id="viewName"></h2>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-sm text-gray-600" id="viewId"></span>
                                <span class="text-sm text-blue-600 font-medium" id="viewGeneric"></span>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Category -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Category</h4>
                                <p class="text-lg font-semibold text-gray-900" id="viewCategory"></p>
                            </div>

                            <!-- Type -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Type</h4>
                                <p class="text-lg font-semibold text-gray-900" id="viewType"></p>
                            </div>
                        </div>

                        <!-- Stock Status -->
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Stock Status</h4>
                            <div class="flex items-center justify-between">
                                <span class="text-2xl font-bold text-gray-900" id="viewStock"></span>
                                <span class="px-3 py-1 rounded-full text-sm font-medium" id="viewStockStatus"></span>
                            </div>
                            <div class="mt-3">
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" id="viewStockBar"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="bg-white border border-gray-200 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Description</h4>
                            <p class="text-gray-700 leading-relaxed" id="viewDescription"></p>
                        </div>

                        <!-- Additional Info -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Additional Information</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">Created:</span>
                                    <span class="ml-2 text-gray-900" id="viewCreated"></span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Last Updated:</span>
                                    <span class="ml-2 text-gray-900" id="viewUpdated"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full modal-content">
            <div class="p-6">
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Delete Medicine</h3>
                <p class="text-gray-600 text-center mb-6" id="deleteMessage">
                    Are you sure you want to delete this medicine?
                </p>
                <div class="flex gap-3">
                    <button onclick="closeDeleteModal()"
                        class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                        Cancel
                    </button>
                    <a id="deleteLink" href="#"
                        class="flex-1 px-4 py-3 gradient-danger text-white rounded-xl hover:shadow-lg transition text-center font-medium">
                        Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // View Modal Functions
        async function showMedicineDetails(medicineId) {
            const modal = document.getElementById('viewModal');
            modal.classList.remove('hidden');

            // Show loading state
            document.getElementById('viewLoading').classList.remove('hidden');
            document.getElementById('viewContent').classList.add('hidden');

            try {
                // Fetch medicine details
                const response = await fetch(`ajax/get_medicine_details.php?id=${medicineId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                console.log('Raw response:', text);
                
                // Check if response is valid JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    // If not JSON, try to extract error from HTML
                    const errorMatch = text.match(/<b>([^<]+)<\/b>/);
                    const errorMessage = errorMatch ? errorMatch[1] : 'Invalid response from server';
                    throw new Error(errorMessage);
                }

                if (data.success) {
                    // Update modal title
                    document.getElementById('modalTitle').textContent = data.medicine.name;
                    
                    // Update medicine details
                    document.getElementById('viewName').textContent = data.medicine.name;
                    document.getElementById('viewId').textContent = `MED-${String(data.medicine.id).padStart(6, '0')}`;
                    document.getElementById('viewGeneric').textContent = data.medicine.generic_name || 'No generic specified';
                    document.getElementById('viewCategory').textContent = data.medicine.category_name || 'Not specified';
                    document.getElementById('viewType').textContent = data.medicine.type_name || 'Not specified';
                    document.getElementById('viewDescription').textContent = data.medicine.description || 'No description available';
                    
                    // Format dates
                    const createdDate = new Date(data.medicine.created_at);
                    const updatedDate = new Date(data.medicine.updated_at || data.medicine.created_at);
                    document.getElementById('viewCreated').textContent = createdDate.toLocaleDateString() + ' ' + createdDate.toLocaleTimeString();
                    document.getElementById('viewUpdated').textContent = updatedDate.toLocaleDateString() + ' ' + updatedDate.toLocaleTimeString();
                    
                    // Get stock data
                    const stockResponse = await fetch(`ajax/get_medicine_stock.php?id=${medicineId}`);
                    if (stockResponse.ok) {
                        const stockData = await stockResponse.json();
                        if (stockData.success) {
                            const totalStock = stockData.total_stock || 0;
                            document.getElementById('viewStock').textContent = `${totalStock} units`;
                            
                            // Determine stock status
                            let statusText, statusColor, percent;
                            if (totalStock == 0) {
                                statusText = 'No Stock';
                                statusColor = 'bg-red-100 text-red-800';
                                percent = 0;
                            } else if (totalStock < 40) {
                                statusText = 'Low Stock';
                                statusColor = 'bg-yellow-100 text-yellow-800';
                                percent = (totalStock / 40) * 100;
                            } else if (totalStock < 100) {
                                statusText = 'Medium Stock';
                                statusColor = 'bg-green-100 text-green-800';
                                percent = (totalStock / 100) * 100;
                            } else {
                                statusText = 'Good Stock';
                                statusColor = 'bg-emerald-100 text-emerald-800';
                                percent = 100;
                            }
                            
                            document.getElementById('viewStockStatus').textContent = statusText;
                            document.getElementById('viewStockStatus').className = `px-3 py-1 rounded-full text-sm font-medium ${statusColor}`;
                            document.getElementById('viewStockBar').style.width = `${Math.min(percent, 100)}%`;
                        }
                    }
                    
                    // Hide loading, show content
                    document.getElementById('viewLoading').classList.add('hidden');
                    document.getElementById('viewContent').classList.remove('hidden');
                    
                } else {
                    alert(data.message || 'Failed to load medicine details');
                    closeViewModal();
                }
            } catch (error) {
                console.error('Error loading medicine details:', error);
                alert('Error: ' + error.message);
                closeViewModal();
            }
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Confirm delete
        function confirmDelete(id, name) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteMessage').innerHTML = `
                Are you sure you want to delete <strong class="text-red-600">${name}</strong>?<br>
                This will also delete all associated stock records. This action cannot be undone.
            `;
            document.getElementById('deleteLink').href = `delete_medicine.php?id=${id}`;
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeViewModal();
                closeDeleteModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Auto-focus search input on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }

            // Add click handlers for checkboxes to update their visual state
            document.querySelectorAll('.checkbox-container input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const label = this.nextElementSibling;
                    if (this.checked) {
                        label.classList.add('active');
                    } else {
                        label.classList.remove('active');
                    }
                });
            });
        });

        // Export to CSV function
        function exportToCSV() {
            // Get all medicine data from table
            const rows = [];
            const headers = ['ID', 'Name', 'Generic Name', 'Category', 'Type', 'Description', 'Stock'];
            
            // Get visible rows
            document.querySelectorAll('#medicinesTableBody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const rowData = [
                        row.querySelector('.medicine-id')?.textContent.replace('MED-', '') || '',
                        row.querySelector('.medicine-name')?.textContent || '',
                        row.querySelector('.generic-name')?.textContent || '',
                        row.querySelector('.category-name')?.textContent || '',
                        row.querySelector('.type-name')?.textContent || '',
                        row.getAttribute('data-description') || '',
                        row.querySelector('.font-bold')?.textContent.replace(' units', '') || '0'
                    ];
                    rows.push(rowData);
                }
            });
            
            // Create CSV content
            let csvContent = headers.join(',') + '\n';
            rows.forEach(row => {
                csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
            });
            
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `medicines_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>

</html>