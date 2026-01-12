<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Both admin and pharmacists can access
if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    header("Location: ../index.php");
    exit;
}

// Check user permissions
$can_adjust = ($_SESSION['role'] === 'pharmacist');
$can_add_batch = ($_SESSION['role'] === 'pharmacist');

// Initialize search variables
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$low_stock = isset($_GET['low_stock']) ? 1 : 0;
$no_stock = isset($_GET['no_stock']) ? 1 : 0;
$full_stock = isset($_GET['full_stock']) ? 1 : 0;
$view_all = isset($_GET['view_all']) ? 1 : 0;

// Stock thresholds
$low_stock_threshold = 40;
$full_stock_threshold = 100;

// Pagination
$per_page = $view_all ? 1000 : 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Build base query to get total stock per medicine
$query = "SELECT 
            m.id,
            m.name,
            m.category_id,
            m.type_id,
            m.generic_id,
            m.description,
            m.created_at,
            c.name AS category_name,
            t.name AS type_name,
            g.name AS generic_name,
            COALESCE(SUM(sb.quantity), 0) as total_stock,
            COUNT(DISTINCT sb.id) as batch_count
          FROM medicines m
          LEFT JOIN medicine_categories c ON m.category_id = c.id
          LEFT JOIN medicine_types t ON m.type_id = t.id
          LEFT JOIN medicine_generics g ON m.generic_id = g.id
          LEFT JOIN stock_batches sb ON m.id = sb.medicine_id 
            AND sb.quantity > 0 
            AND sb.is_expired = 0
            AND sb.is_returned = 0
            AND sb.is_disposed = 0
          WHERE 1=1";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (m.name LIKE '%$search%' 
                OR g.name LIKE '%$search%' 
                OR c.name LIKE '%$search%' 
                OR t.name LIKE '%$search%'
                OR m.description LIKE '%$search%')";
}

// Group by medicine
$query .= " GROUP BY m.id";

// Get total count for pagination
$count_query = str_replace(
    "SELECT 
            m.id,
            m.name,
            m.category_id,
            m.type_id,
            m.generic_id,
            m.description,
            m.created_at,
            c.name AS category_name,
            t.name AS type_name,
            g.name AS generic_name,
            COALESCE(SUM(sb.quantity), 0) as total_stock,
            COUNT(DISTINCT sb.id) as batch_count",
    "SELECT COUNT(DISTINCT m.id) as total",
    $query
);
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);

// Add ordering and pagination to main query
$query .= " ORDER BY total_stock DESC, m.name ASC LIMIT $offset, $per_page";

// Execute query
$result = mysqli_query($conn, $query);

// Get additional stock data for each medicine
$medicine_ids = [];
$stock_data = [];
$batch_data = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $medicine_ids[] = $row['id'];
        $stock_data[$row['id']] = [
            'total_stock' => $row['total_stock'],
            'batch_count' => $row['batch_count']
        ];
    }
    
    // Get batch details for each medicine
    if (!empty($medicine_ids)) {
        $ids_str = implode(',', $medicine_ids);
        $batch_query = "
            SELECT medicine_id, batch_no, quantity, expiry_date, location, 
                   is_expired, is_returned, is_disposed
            FROM stock_batches 
            WHERE medicine_id IN ($ids_str) 
            AND quantity > 0 
            AND is_expired = 0
            AND is_returned = 0
            AND is_disposed = 0
            ORDER BY expiry_date ASC
        ";
        
        $batch_result = mysqli_query($conn, $batch_query);
        while ($batch = mysqli_fetch_assoc($batch_result)) {
            if (!isset($batch_data[$batch['medicine_id']])) {
                $batch_data[$batch['medicine_id']] = [];
            }
            $batch_data[$batch['medicine_id']][] = $batch;
        }
    }
}

// Apply stock filters after fetching all data
$filtered_result = [];
$display_count = 0;

if ($result && mysqli_num_rows($result) > 0) {
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        $medicine_id = $row['id'];
        $total_stock = isset($stock_data[$medicine_id]) ? $stock_data[$medicine_id]['total_stock'] : 0;

        // Apply stock filters
        $include = true;

        if ($low_stock || $no_stock || $full_stock) {
            $include = false;

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
    <title>Stock Overview - MediCare Pharma</title>
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

        /* Stock status colors */
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

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .batch-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            margin: 2px;
        }

        .batch-badge.expiring {
            background: #fef3c7;
            border-color: #fbbf24;
            color: #92400e;
        }

        .batch-badge.expired {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        /* Notification */
        .notification {
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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
            <!-- Success Notification -->
            <div id="successNotification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden notification">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <span id="successMessage">Stock adjusted successfully!</span>
                </div>
            </div>

            <!-- Error Notification -->
            <div id="errorNotification" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden notification">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorMessage">Error adjusting stock!</span>
                </div>
            </div>

            <!-- Page Header -->
            <div class="glass-card p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">
                            <i class="fas fa-boxes text-blue-600 mr-2"></i>
                            Stock Overview
                        </h1>
                        <p class="text-gray-600 text-sm lg:text-base">
                            View total stock levels for all medicines
                        </p>
                    </div>
                    <?php if ($can_add_batch): ?>
                        <a href="add_stock.php"
                            class="gradient-primary text-white px-5 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md w-full lg:w-auto">
                            <i class="fas fa-plus"></i>
                            <span>Add New Stock Batch</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Search Section -->
            <div class="glass-card p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-search text-blue-500 mr-2"></i>
                        Search Stock
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
                            placeholder="Search by medicine name, generic name, category, or type..."
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
                                <span>Showing all medicines stock</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button type="submit"
                                class="gradient-primary text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center gap-2 shadow-md">
                                <i class="fas fa-search"></i>
                                <span>Search Stock</span>
                            </button>
                            <a href="stock.php"
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

            <!-- Stock Table -->
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
                        <tbody class="divide-y divide-gray-100" id="stockTableBody">
                            <?php if (count($filtered_result) > 0): ?>
                                <?php foreach ($filtered_result as $row):
                                    $medicine_id = $row['id'];
                                    $total_stock = isset($stock_data[$medicine_id]) ? $stock_data[$medicine_id]['total_stock'] : 0;
                                    $batch_count = isset($stock_data[$medicine_id]) ? $stock_data[$medicine_id]['batch_count'] : 0;
                                    $medicine_batches = isset($batch_data[$medicine_id]) ? $batch_data[$medicine_id] : [];

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
                                                    <?php if ($batch_count > 0): ?>
                                                        <div class="mt-2 flex flex-wrap gap-1">
                                                            <?php 
                                                            $displayed_batches = 0;
                                                            foreach ($medicine_batches as $batch): 
                                                                if ($displayed_batches >= 3) break;
                                                                $expiry_date = strtotime($batch['expiry_date']);
                                                                $today = time();
                                                                $days_diff = floor(($expiry_date - $today) / (60 * 60 * 24));
                                                                $badge_class = '';
                                                                
                                                                if ($days_diff <= 0) {
                                                                    $badge_class = 'expired';
                                                                    $icon = 'fa-exclamation-triangle';
                                                                } elseif ($days_diff <= 30) {
                                                                    $badge_class = 'expiring';
                                                                    $icon = 'fa-clock';
                                                                } else {
                                                                    $icon = 'fa-hashtag';
                                                                }
                                                            ?>
                                                                <span class="batch-badge <?php echo $badge_class; ?>">
                                                                    <i class="fas <?php echo $icon; ?> text-xs"></i>
                                                                    <?php echo htmlspecialchars($batch['batch_no']); ?>
                                                                </span>
                                                            <?php 
                                                                $displayed_batches++;
                                                            endforeach; 
                                                            if ($batch_count > 3): ?>
                                                                <span class="batch-badge">
                                                                    +<?php echo $batch_count - 3; ?> more
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
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
                                                <?php if ($batch_count > 0): ?>
                                                    <div class="text-xs text-gray-500">
                                                        <i class="fas fa-boxes mr-1"></i>
                                                        <?php echo $batch_count; ?> batch<?php echo $batch_count > 1 ? 'es' : ''; ?>
                                                    </div>
                                                <?php endif; ?>
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

                                                <!-- View Batches -->
                                                <a href="stock_batches.php?medicine_id=<?php echo $row['id']; ?>"
                                                    class="action-btn bg-green-50 text-green-600 hover:bg-green-100">
                                                    <i class="fas fa-boxes text-xs"></i>
                                                    <span>Batches</span>
                                                </a>

                                                <!-- Add Stock -->
                                                <?php if ($can_add_batch): ?>
                                                    <a href="add_stock.php?medicine_id=<?php echo $row['id']; ?>"
                                                        class="action-btn bg-purple-50 text-purple-600 hover:bg-purple-100">
                                                        <i class="fas fa-plus text-xs"></i>
                                                        <span>Add Stock</span>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Adjust Stock -->
                                                <?php if ($can_adjust): ?>
                                                    <button onclick="openAdjustModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', <?php echo $total_stock; ?>)"
                                                        class="action-btn bg-yellow-50 text-yellow-600 hover:bg-yellow-100">
                                                        <i class="fas fa-edit text-xs"></i>
                                                        <span>Adjust</span>
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
                                                <i class="fas fa-boxes text-yellow-500 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Stock Found</h4>
                                            <p class="text-gray-600 mb-6">
                                                <?php if ($search || $low_stock || $no_stock || $full_stock): ?>
                                                    No medicines match your search criteria. Try different filters.
                                                <?php else: ?>
                                                    No stock found in the inventory.
                                                <?php endif; ?>
                                            </p>
                                            <div class="flex flex-wrap gap-3 justify-center">
                                                <a href="stock.php"
                                                    class="px-5 py-2 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                                    Clear Filters
                                                </a>
                                                <?php if ($can_add_batch): ?>
                                                    <a href="add_stock.php"
                                                        class="gradient-primary text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200">
                                                        Add Stock
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

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full modal-content">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-edit text-yellow-500 mr-2"></i>
                        <span id="modalTitle">Adjust Stock</span>
                    </h3>
                    <button onclick="closeAdjustModal()"
                        class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="adjustStockForm" class="space-y-6">
                    <input type="hidden" id="medicineId" name="medicine_id">
                    <input type="hidden" id="action" name="action" value="adjust_stock">

                    <!-- Current Stock Display -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Current Stock:</span>
                            <span id="currentStock" class="text-lg font-bold text-gray-900">0 units</span>
                        </div>
                    </div>

                    <!-- Adjustment Type -->
                    <div>
                        <label class="form-label">Adjustment Type</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button type="button"
                                onclick="setAdjustmentType('add')"
                                id="addType"
                                class="p-3 border-2 border-green-200 text-green-700 rounded-lg hover:bg-green-50 transition font-medium text-center adjustment-type active">
                                <i class="fas fa-plus mb-1 block"></i>
                                <span>Add</span>
                            </button>
                            <button type="button"
                                onclick="setAdjustmentType('remove')"
                                id="removeType"
                                class="p-3 border-2 border-red-200 text-red-700 rounded-lg hover:bg-red-50 transition font-medium text-center adjustment-type">
                                <i class="fas fa-minus mb-1 block"></i>
                                <span>Remove</span>
                            </button>
                            <button type="button"
                                onclick="setAdjustmentType('set')"
                                id="setType"
                                class="p-3 border-2 border-blue-200 text-blue-700 rounded-lg hover:bg-blue-50 transition font-medium text-center adjustment-type">
                                <i class="fas fa-edit mb-1 block"></i>
                                <span>Set</span>
                            </button>
                        </div>
                        <input type="hidden" id="adjustmentType" name="adjustment_type" value="add">
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number"
                            id="quantity"
                            name="quantity"
                            min="1"
                            value="1"
                            class="form-input"
                            required
                            placeholder="Enter quantity">
                        <p id="quantityHelp" class="text-xs text-gray-500 mt-2">
                            Enter quantity to add to current stock
                        </p>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label for="reason" class="form-label">Reason (Optional)</label>
                        <textarea id="reason"
                            name="reason"
                            rows="3"
                            class="form-input"
                            placeholder="e.g., Damaged goods, recount, correction, etc."></textarea>
                    </div>

                    <!-- New Total Preview -->
                    <div id="newTotalPreview" class="hidden bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">New Total Stock:</span>
                            <span id="newTotal" class="text-lg font-bold text-green-700">0 units</span>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="adjustLoading" class="text-center py-4 hidden">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                        <p class="text-gray-600 mt-2">Processing adjustment...</p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col md:flex-row gap-4 pt-6 border-t border-gray-200">
                        <button type="submit"
                            id="saveBtn"
                            class="gradient-primary text-white px-6 py-4 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md flex-1">
                            <i class="fas fa-save"></i>
                            <span>Apply Adjustment</span>
                        </button>

                        <button type="button"
                            onclick="closeAdjustModal()"
                            class="px-6 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium flex items-center justify-center gap-2 flex-1">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Adjust Stock Modal Functions
        let currentMedicineId = null;
        let currentStock = 0;

        function openAdjustModal(medicineId, medicineName, stock) {
            currentMedicineId = medicineId;
            currentStock = stock;
            
            const modal = document.getElementById('adjustModal');
            modal.classList.remove('hidden');

            // Update modal title
            document.getElementById('modalTitle').textContent = `Adjust Stock - ${medicineName}`;
            document.getElementById('currentStock').textContent = `${stock.toLocaleString()} units`;
            document.getElementById('medicineId').value = medicineId;

            // Reset form
            document.getElementById('adjustStockForm').reset();
            document.getElementById('adjustmentType').value = 'add';
            document.getElementById('quantity').value = 1;
            document.getElementById('newTotalPreview').classList.add('hidden');
            
            // Reset button states
            document.querySelectorAll('.adjustment-type').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.remove('border-green-500', 'text-green-700', 'bg-green-50');
                btn.classList.add('border-green-200', 'text-green-700');
            });
            document.getElementById('addType').classList.add('active', 'border-green-500', 'bg-green-50');
            
            // Update quantity help text
            updateQuantityHelp();
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
            currentMedicineId = null;
            currentStock = 0;
        }

        function setAdjustmentType(type) {
            document.getElementById('adjustmentType').value = type;
            
            // Update button states
            document.querySelectorAll('.adjustment-type').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.remove('border-green-500', 'text-green-700', 'bg-green-50');
                btn.classList.remove('border-red-500', 'text-red-700', 'bg-red-50');
                btn.classList.remove('border-blue-500', 'text-blue-700', 'bg-blue-50');
                
                if (btn.id === type + 'Type') {
                    btn.classList.add('active');
                    if (type === 'add') {
                        btn.classList.add('border-green-500', 'text-green-700', 'bg-green-50');
                    } else if (type === 'remove') {
                        btn.classList.add('border-red-500', 'text-red-700', 'bg-red-50');
                    } else {
                        btn.classList.add('border-blue-500', 'text-blue-700', 'bg-blue-50');
                    }
                }
            });
            
            updateQuantityHelp();
            updateNewTotalPreview();
        }

        function updateQuantityHelp() {
            const type = document.getElementById('adjustmentType').value;
            const quantityInput = document.getElementById('quantity');
            let helpText = '';
            
            if (type === 'add') {
                helpText = 'Enter quantity to add to current stock';
                quantityInput.min = 1;
            } else if (type === 'remove') {
                helpText = 'Enter quantity to remove from current stock';
                quantityInput.min = 1;
                quantityInput.max = currentStock;
            } else {
                helpText = 'Enter new total stock quantity';
                quantityInput.min = 0;
                quantityInput.max = '';
            }
            
            document.getElementById('quantityHelp').textContent = helpText;
            updateNewTotalPreview();
        }

        function updateNewTotalPreview() {
            const type = document.getElementById('adjustmentType').value;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            let newTotal = currentStock;
            
            if (type === 'add') {
                newTotal = currentStock + quantity;
            } else if (type === 'remove') {
                newTotal = Math.max(0, currentStock - quantity);
            } else {
                newTotal = quantity;
            }
            
            if (newTotal !== currentStock) {
                document.getElementById('newTotal').textContent = `${newTotal.toLocaleString()} units`;
                document.getElementById('newTotalPreview').classList.remove('hidden');
            } else {
                document.getElementById('newTotalPreview').classList.add('hidden');
            }
        }

        // Handle form submission
        document.getElementById('adjustStockForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const saveBtn = document.getElementById('saveBtn');
            const originalText = saveBtn.innerHTML;

            // Show loading state
            saveBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                <span>Processing...</span>
            `;
            saveBtn.disabled = true;
            document.getElementById('adjustLoading').classList.remove('hidden');

            try {
                const response = await fetch('ajax/adjust_stock.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message || 'Stock adjusted successfully!', 'success');
                    
                    setTimeout(() => {
                        closeAdjustModal();
                        // Reload to see changes
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message || 'Failed to adjust stock', 'error');
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    document.getElementById('adjustLoading').classList.add('hidden');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error adjusting stock', 'error');
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                document.getElementById('adjustLoading').classList.add('hidden');
            }
        });

        // Event listeners for quantity input
        document.getElementById('quantity').addEventListener('input', updateNewTotalPreview);

        // Show notification
        function showNotification(message, type = 'success') {
            if (type === 'success') {
                const notification = document.getElementById('successNotification');
                document.getElementById('successMessage').textContent = message;
                notification.classList.remove('hidden');
                notification.classList.add('notification');

                setTimeout(() => {
                    notification.classList.remove('notification');
                    setTimeout(() => {
                        notification.classList.add('hidden');
                    }, 300);
                }, 3000);
            } else {
                const notification = document.getElementById('errorNotification');
                document.getElementById('errorMessage').textContent = message;
                notification.classList.remove('hidden');
                notification.classList.add('notification');

                setTimeout(() => {
                    notification.classList.remove('notification');
                    setTimeout(() => {
                        notification.classList.add('hidden');
                    }, 300);
                }, 3000);
            }
        }

        // Export to CSV
        function exportToCSV() {
            const rows = [];
            const headers = ['Medicine ID', 'Medicine Name', 'Generic Name', 'Category', 'Type', 'Total Stock', 'Stock Status', 'Batch Count'];
            
            // Get visible rows
            document.querySelectorAll('#stockTableBody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    const medicineId = row.querySelector('.medicine-id')?.textContent.replace('MED-', '') || '';
                    const medicineName = row.querySelector('.medicine-name')?.textContent || '';
                    const genericName = row.querySelector('.generic-name')?.textContent || '';
                    const categoryName = row.querySelector('.category-name')?.textContent || '';
                    const typeName = row.querySelector('.type-name')?.textContent || '';
                    const stockText = row.querySelector('.font-bold')?.textContent || '';
                    const stockStatus = row.querySelector('.text-xs.font-medium')?.textContent || '';
                    const batchInfo = row.querySelector('.text-xs.text-gray-500')?.textContent || '';
                    
                    const totalStock = stockText.replace(' units', '').replace(/,/g, '');
                    const batchCount = batchInfo.match(/\d+/)?.[0] || '0';
                    
                    rows.push([
                        medicineId,
                        medicineName,
                        genericName,
                        categoryName,
                        typeName,
                        totalStock,
                        stockStatus,
                        batchCount
                    ]);
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
            a.download = `stock_overview_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdjustModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('adjustModal').addEventListener('click', function(e) {
            if (e.target === this) closeAdjustModal();
        });

        // Auto-focus search input on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
<div class="flex">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-gradient-to-b from-gray-900 to-gray-800 text-white fixed lg:sticky top-12 h-full lg:h-[calc(100vh-4rem)] w-64 lg:w-72 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 z-40 overflow-y-auto custom-scrollbar shadow-2xl">
        <div class="p-6">
            <!-- User Info -->
            <div class="flex items-center space-x-3 mb-8 pb-6 border-b border-gray-700/50">
                <div class="w-12 h-12 rounded-full gradient-yellow flex items-center justify-center text-white font-bold text-xl shadow-lg">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <h3 class="font-bold text-white"><?php echo htmlspecialchars($user_name); ?></h3>
                    <p class="text-xs text-gray-300">Pharmacist</p>
                    <p class="text-xs text-green-400 mt-1 flex items-center">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                        Online
                    </p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg gradient-yellow text-white shadow-lg">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                        <i class="fas fa-tachometer-alt text-white text-lg"></i>
                    </div>
                    <span class="font-medium text-white">Dashboard</span>
                    <span class="ml-auto">
                        <i class="fas fa-chevron-right text-yellow-100 text-xs"></i>
                    </span>
                </a>

                <!-- Medicines Dropdown -->
                <div class="space-y-1">
                    <button type="button" class="medicines-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-700/50 flex items-center justify-center">
                                <i class="fas fa-pills text-gray-300 text-lg"></i>
                            </div>
                            <span class="font-medium text-gray-200">Medicines</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>
                    
                    <!-- Medicines Submenu -->
                    <div class="medicines-submenu pl-4 ml-4 border-l border-gray-700/50 hidden">
                        <!-- All Medicines -->
                        <a href="medicines.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">All Medicines</span>
                            <span class="ml-auto bg-gray-600/50 text-gray-300 text-xs px-2 py-1 rounded-full font-medium"><?php echo $medicines; ?></span>
                        </a>
                        
                        <!-- Search Brand -->
                        <a href="search_brand.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Search Brand</span>
                        </a>
                        
                        <!-- Search Generic -->
                        <a href="search_generic.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Search Generic</span>
                        </a>
                        
                        <!-- Return to Company -->
                        <a href="return_to_company.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Return to Company</span>
                        </a>
                        
                        <!-- Expired Medicines -->
                        <a href="expired_medicines.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Expired Medicines</span>
                        </a>
                    </div>
                </div>

                <!-- Stock Dropdown -->
                <div class="space-y-1">
                    <button type="button" class="stock-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-700/50 flex items-center justify-center">
                                <i class="fas fa-boxes text-gray-300 text-lg"></i>
                            </div>
                            <span class="font-medium text-gray-200">Stock</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>
                    
                    <!-- Stock Submenu -->
                    <div class="stock-submenu pl-4 ml-4 border-l border-gray-700/50 hidden">
                        <!-- View Stock -->
                        <a href="stock.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">View Stock</span>
                            <span class="ml-auto bg-gray-600/50 text-gray-300 text-xs px-2 py-1 rounded-full font-medium"><?php echo number_format($stock); ?></span>
                        </a>
                        
                        <!-- Add Stock -->
                        <a href="add_stock.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Add Stock</span>
                        </a>
                    </div>
                </div>

                <!-- Sale Dropdown -->
                <div class="space-y-1">
                    <button type="button" class="sale-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-700/50 flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-gray-300 text-lg"></i>
                            </div>
                            <span class="font-medium text-gray-200">Sale</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>
                    
                    <!-- Sale Submenu -->
                    <div class="sale-submenu pl-4 ml-4 border-l border-gray-700/50 hidden">
                        <!-- Create Sale -->
                        <a href="create_sale.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Create Sale</span>
                        </a>
                        
                        <!-- View Sales -->
                        <a href="sales.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">View Sales</span>
                            <span class="ml-auto bg-yellow-500/20 text-yellow-300 text-xs px-2 py-1 rounded-full font-medium"><?php echo $sales; ?></span>
                        </a>
                        
                        <!-- Payments -->
                        <a href="payments.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Payments</span>
                        </a>
                        
                        <!-- Expenses -->
                        <a href="expenses.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Expenses</span>
                        </a>
                        
                        <!-- Profit Analysis -->
                        <a href="profit.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Profit Analysis</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Divider -->
            <div class="my-6 border-t border-gray-700/50"></div>

            <!-- Quick Stats -->
            <div class="space-y-4">
                <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Today's Stats</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <span class="text-sm text-gray-400">Revenue</span>
                        </div>
                        <span class="font-bold text-yellow-400">Rs <?php echo number_format($today_revenue); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                            <span class="text-sm text-gray-400">Medicines</span>
                        </div>
                        <span class="font-bold text-gray-300"><?php echo $active_medicines_count; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-sm text-gray-400">Alerts</span>
                        </div>
                        <span class="font-bold text-red-400"><?php echo count($low_stock) + count($expiring_soon); ?></span>
                    </div>
                </div>
            </div>

            <!-- Logout -->
            <div class="mt-8 pt-6 border-t border-gray-700/50">
                <a href="../auth/logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-red-500/10 to-red-600/10 hover:from-red-500/20 hover:to-red-600/20 transition-all duration-200 group">
                    <i class="fas fa-sign-out-alt text-red-400 group-hover:text-red-300"></i>
                    <span class="text-red-300 group-hover:text-red-200">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Overlay for mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

    <!-- Main Content -->
    <main class="flex-1 overflow-hidden">
        <!-- Dashboard Header -->
        <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                        <span class="bg-gradient-to-r from-yellow-500 to-yellow-600 bg-clip-text text-transparent">Pharmacist Dashboard</span> Overview
                    </h1>
                    <p class="text-gray-600 flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-yellow-500"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                        <span class="text-gray-400 mx-2">•</span>
                        <i class="fas fa-clock text-gray-500"></i>
                        <span id="current-time">Loading...</span>
                        <span class="text-gray-400 mx-2">•</span>
                        <i class="fas fa-user-md text-yellow-500"></i>
                        <span><?php echo htmlspecialchars($user_name); ?></span>
                    </p>
                </div>
                <div class="mt-4 lg:mt-0 flex items-center space-x-4">
                    <button onclick="refreshDashboard()" class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 flex items-center space-x-2 pulse-glow">
                        <i class="fas fa-redo"></i>
                        <span>Refresh Data</span>
                    </button>
                    <a href="create_sale.php" class="gradient-mixed text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-cash-register"></i>
                        <span>New Sale</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-container">
            <!-- Revenue Stats -->
            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Today</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($today_revenue); ?></h3>
                    <p class="text-gray-600 mb-3">Daily Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-yellow h-2 rounded-full" style="width: <?php echo min(100, ($today_revenue / 50000) * 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-gray flex items-center justify-center shadow-lg">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-600 bg-gray-50 px-3 py-1 rounded-full">Weekly</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($week_revenue); ?></h3>
                    <p class="text-gray-600 mb-3">Weekly Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-gray h-2 rounded-full" style="width: <?php echo min(100, ($week_revenue / 250000) * 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-mixed flex items-center justify-center shadow-lg">
                            <i class="fas fa-calendar-alt text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Monthly</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($month_revenue); ?></h3>
                    <p class="text-gray-600 mb-3">Monthly Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-mixed h-2 rounded-full" style="width: <?php echo min(100, ($month_revenue / 1000000) * 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-teal-500 to-teal-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-shopping-cart text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($sales); ?></h3>
                    <p class="text-gray-600 mb-3">Total Sales</p>
                    <div class="flex items-center text-sm">
                        <span class="text-yellow-500 mr-2 flex items-center">
                            <i class="fas fa-chart-line mr-1"></i>
                            <?php echo $sales > 0 ? round($sales / 30) : 0; ?>/day avg
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Chart Area -->
            <div class="grid-col-8">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Sales Performance</h3>
                            <p class="text-gray-600">Your revenue and sales count over last 6 months</p>
                        </div>
                        <div class="flex space-x-2 mt-4 lg:mt-0">
                            <button class="chart-filter active px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg font-medium" data-filter="revenue">Revenue</button>
                            <button class="chart-filter px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium" data-filter="sales">Sales Count</button>
                            <button class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="h-80">
                        <canvas id="combinedChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stock Overview -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Stock Overview</h3>
                        <span class="text-sm text-yellow-600 font-medium bg-yellow-50 px-3 py-1 rounded-full">Live</span>
                    </div>
                    <div class="h-64 flex flex-col justify-center items-center">
                        <div class="relative w-40 h-40 mb-6">
                            <canvas id="stockChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-gray-800"><?php echo $active_medicines_count; ?></div>
                                    <div class="text-sm text-gray-600">Active</div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 w-full">
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-lg font-bold text-green-600"><?php echo $medicines; ?></div>
                                <div class="text-xs text-gray-600">Total Medicines</div>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 rounded-lg">
                                <div class="text-lg font-bold text-yellow-600"><?php echo number_format($stock); ?></div>
                                <div class="text-xs text-gray-600">Total Stock</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Sales Line Chart -->
            <div class="grid-col-6">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Daily Sales Trend</h3>
                            <p class="text-gray-600">Your performance in last 7 days</p>
                        </div>
                        <span class="text-sm text-teal-600 bg-teal-50 px-3 py-1 rounded-full">
                            Weekly View
                        </span>
                    </div>
                    <div class="h-64">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Selling Medicines -->
            <div class="grid-col-6">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.9s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Top Selling Medicines</h3>
                        <span class="text-sm text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Your Sales</span>
                    </div>
                    <div class="space-y-4">
                        <?php if (count($top_medicines) > 0): ?>
                            <?php foreach ($top_medicines as $index => $medicine): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-yellow-100 transition group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg gradient-yellow flex items-center justify-center text-white font-bold shadow">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600"><?php echo htmlspecialchars($medicine['medicine_name']); ?></h4>
                                            <p class="text-xs text-gray-500">Sold: <?php echo $medicine['total_sold']; ?> units</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-gray-800">Rs <?php echo number_format($medicine['revenue']); ?></div>
                                        <div class="text-xs text-gray-500">Revenue</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-chart-bar text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No sales data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Inventory Alerts -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.1s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Inventory Alerts</h3>
                        <div class="flex space-x-2">
                            <span class="px-3 py-1 bg-red-100 text-red-600 rounded-full text-xs font-medium shadow">
                                <?php echo count($low_stock); ?> Low
                            </span>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-600 rounded-full text-xs font-medium shadow">
                                <?php echo count($expiring_soon); ?> Expiring
                            </span>
                        </div>
                    </div>
                    <div class="space-y-4 max-h-80 overflow-y-auto custom-scrollbar">
                        <!-- Low Stock Items -->
                        <?php if (count($low_stock) > 0): ?>
                            <?php foreach ($low_stock as $item): ?>
                                <div class="p-3 bg-gradient-to-r from-red-50 to-white rounded-lg border border-red-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                                <i class="fas fa-exclamation-triangle text-red-600 text-sm"></i>
                                            </div>
                                            <h4 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($item['medicine_name']); ?></h4>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-red-600"><?php echo $item['quantity']; ?></div>
                                            <div class="text-xs text-gray-500">units left</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span>Expires: <?php echo date('M j', strtotime($item['expiry_date'])); ?></span>
                                        <span class="<?php echo $item['days_until_expiry'] < 30 ? 'text-red-600 font-medium' : 'text-gray-500'; ?>">
                                            <?php echo $item['days_until_expiry']; ?> days
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Expiring Soon Items -->
                        <?php if (count($expiring_soon) > 0): ?>
                            <?php foreach ($expiring_soon as $item): ?>
                                <div class="p-3 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                                                <i class="fas fa-clock text-yellow-600 text-sm"></i>
                                            </div>
                                            <h4 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($item['medicine_name']); ?></h4>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-yellow-600"><?php echo $item['quantity']; ?></div>
                                            <div class="text-xs text-gray-500">in stock</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span>Expires: <?php echo date('M j', strtotime($item['expiry_date'])); ?></span>
                                        <span class="text-red-600 font-medium">
                                            <?php echo $item['days_until_expiry']; ?> days left
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (count($low_stock) == 0 && count($expiring_soon) == 0): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3 shadow">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                                <p class="text-gray-500">No inventory alerts</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Quick Actions</h3>
                        <span class="text-sm text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Daily Tasks</span>
                    </div>
                    <div class="space-y-4">
                        <a href="create_sale.php" class="p-4 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-yellow flex items-center justify-center">
                                <i class="fas fa-cash-register text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600">Create New Sale</h4>
                                <p class="text-xs text-gray-500">Process customer purchase</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-yellow-600"></i>
                        </a>

                        <a href="add_stock.php" class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-gray-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-gray flex items-center justify-center">
                                <i class="fas fa-plus-circle text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-gray-600">Add Stock</h4>
                                <p class="text-xs text-gray-500">Add new medicine batch</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-gray-600"></i>
                        </a>

                        <a href="medicines.php" class="p-4 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-yellow flex items-center justify-center">
                                <i class="fas fa-pills text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600">Browse Medicines</h4>
                                <p class="text-xs text-gray-500">View medicine catalog</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-yellow-600"></i>
                        </a>

                        <a href="stock.php" class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-gray-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-gray flex items-center justify-center">
                                <i class="fas fa-boxes text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-gray-600">Check Stock</h4>
                                <p class="text-xs text-gray-500">Review inventory levels</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-gray-600"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Sales Activity -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.2s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Recent Sales</h3>
                        <a href="sales.php" class="text-sm text-yellow-600 hover:text-yellow-800 font-medium flex items-center">
                            View All <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($recent_sales) > 0): ?>
                            <?php while ($sale = mysqli_fetch_assoc($recent_sales)): ?>
                                <div class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-100 hover:border-yellow-200 transition">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-10 h-10 rounded-full gradient-yellow flex items-center justify-center">
                                                <i class="fas fa-shopping-cart text-white"></i>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-800">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xl font-bold text-yellow-600">Rs <?php echo number_format($sale['total_amount']); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-600">
                                        <span><?php echo date('M j', strtotime($sale['sale_date'])); ?></span>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                            Completed
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-shopping-cart text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No recent sales found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add JavaScript for dropdown functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown functionality
        const medicinesToggle = document.querySelector('.medicines-dropdown-toggle');
        const stockToggle = document.querySelector('.stock-dropdown-toggle');
        const saleToggle = document.querySelector('.sale-dropdown-toggle');

        const medicinesSubmenu = document.querySelector('.medicines-submenu');
        const stockSubmenu = document.querySelector('.stock-submenu');
        const saleSubmenu = document.querySelector('.sale-submenu');

        // Toggle dropdowns
        function toggleDropdown(toggleBtn, submenu, chevron) {
            toggleBtn.addEventListener('click', () => {
                const isExpanded = !submenu.classList.contains('hidden');
                
                // Close all other dropdowns
                [medicinesSubmenu, stockSubmenu, saleSubmenu].forEach(menu => {
                    if (menu !== submenu) {
                        menu.classList.add('hidden');
                        const otherBtn = menu.previousElementSibling;
                        if (otherBtn) {
                            otherBtn.querySelector('.fa-chevron-down').classList.remove('rotate-180');
                        }
                    }
                });

                // Toggle current dropdown
                submenu.classList.toggle('hidden');
                chevron.classList.toggle('rotate-180');
            });
        }

        if (medicinesToggle && medicinesSubmenu) {
            toggleDropdown(medicinesToggle, medicinesSubmenu, medicinesToggle.querySelector('.fa-chevron-down'));
        }

        if (stockToggle && stockSubmenu) {
            toggleDropdown(stockToggle, stockSubmenu, stockToggle.querySelector('.fa-chevron-down'));
        }

        if (saleToggle && saleSubmenu) {
            toggleDropdown(saleToggle, saleSubmenu, saleToggle.querySelector('.fa-chevron-down'));
        }

        // Mobile sidebar toggle
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
    });
</script>
            // Add click handlers for checkboxes
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
    </script>
</body>

</html>