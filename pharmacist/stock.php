<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Both admin and pharmacists can access
if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    header("Location: ../index.php");
    exit;
}

// Check user permissions based on role
$can_edit_stock = ($_SESSION['role'] === 'pharmacist');
$can_add_batch = ($_SESSION['role'] === 'pharmacist');
$can_delete_batch = ($_SESSION['role'] === 'pharmacist');

// Initialize search variables
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$low_stock = isset($_GET['low_stock']) ? 1 : 0;
$no_stock = isset($_GET['no_stock']) ? 1 : 0;
$full_stock = isset($_GET['full_stock']) ? 1 : 0;
$view_all = isset($_GET['view_all']) ? 1 : 0;

// Stock thresholds (in boxes)
$low_stock_threshold = 40;
$full_stock_threshold = 100;

// Pagination
$per_page = $view_all ? 1000 : 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Build base query for aggregated medicine stock with batch details
$query = "SELECT 
            m.id,
            m.name AS medicine_name,
            m.description,
            mg.name AS generic_name,
            c.name AS category_name,
            t.name AS type_name,
            COALESCE(SUM(sb.quantity), 0) as total_boxes,
            GROUP_CONCAT(
                CONCAT(
                    sb.batch_no, '|', 
                    sb.quantity, '|',
                    COALESCE(sb.units_per_packet, 1), '|',
                    COALESCE(sb.packets_per_box, 1), '|',
                    sb.expiry_date
                ) SEPARATOR ';'
            ) as batch_details
          FROM medicines m
          LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
          LEFT JOIN medicine_categories c ON m.category_id = c.id
          LEFT JOIN medicine_types t ON m.type_id = t.id
          LEFT JOIN stock_batches sb ON m.id = sb.medicine_id AND sb.quantity > 0 AND sb.is_expired = 0
          WHERE 1=1";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (m.name LIKE '%$search%' 
                OR mg.name LIKE '%$search%' 
                OR c.name LIKE '%$search%' 
                OR t.name LIKE '%$search%'
                OR m.description LIKE '%$search%')";
}

// Group by medicine
$query .= " GROUP BY m.id";

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT m.id) as total
                FROM medicines m
                LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
                LEFT JOIN medicine_categories c ON m.category_id = c.id
                LEFT JOIN medicine_types t ON m.type_id = t.id
                LEFT JOIN stock_batches sb ON m.id = sb.medicine_id AND sb.quantity > 0
                WHERE 1=1";

if (!empty($search)) {
    $count_query .= " AND (m.name LIKE '%$search%' 
                OR mg.name LIKE '%$search%' 
                OR c.name LIKE '%$search%' 
                OR t.name LIKE '%$search%'
                OR m.description LIKE '%$search%')";
}

// Execute count query
$count_result = mysqli_query($conn, $count_query);
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_rows = $count_row ? $count_row['total'] : 0;
} else {
    $total_rows = 0;
}
$total_pages = ceil($total_rows / $per_page);

// Add ordering and pagination
$query .= " ORDER BY m.name ASC LIMIT $offset, $per_page";

// Execute query
$result = mysqli_query($conn, $query);

// Apply stock filters after fetching all data
$filtered_result = [];
$display_count = 0;
if ($result && mysqli_num_rows($result) > 0) {
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        $medicine_id = $row['id'];
        $total_boxes = $row['total_boxes'];
        
        // Calculate stock details from batches
        $total_packets = 0;
        $total_units = 0;
        $packets_per_box = 1;
        $units_per_packet = 1;
        
        if (!empty($row['batch_details'])) {
            $batches = explode(';', $row['batch_details']);
            foreach ($batches as $batch) {
                if (!empty($batch)) {
                    $batch_info = explode('|', $batch);
                    if (count($batch_info) >= 5) {
                        $batch_boxes = intval($batch_info[1]);
                        $batch_units_per_packet = intval($batch_info[2]);
                        $batch_packets_per_box = intval($batch_info[3]);
                        
                        // Use the first batch's packaging info for calculation
                        if ($packets_per_box == 1 && $batch_packets_per_box > 1) {
                            $packets_per_box = $batch_packets_per_box;
                        }
                        if ($units_per_packet == 1 && $batch_units_per_packet > 1) {
                            $units_per_packet = $batch_units_per_packet;
                        }
                        
                        // Calculate packets and units from boxes
                        $total_packets += ($batch_boxes * $batch_packets_per_box);
                        $total_units += ($batch_boxes * $batch_packets_per_box * $batch_units_per_packet);
                    }
                }
            }
        }
        
        // If no batch details, use default values
        if ($total_boxes > 0 && $total_packets == 0) {
            $total_packets = $total_boxes * $packets_per_box;
            $total_units = $total_packets * $units_per_packet;
        }
        
        // Format stock display
        $stock_display = [];
        
        if ($total_boxes > 0) {
            $stock_display[] = "$total_boxes Boxes";
        }
        
        if ($total_packets > 0 && $packets_per_box > 1) {
            $stock_display[] = "$total_packets Packets";
        }
        
        if ($total_units > 0 && $units_per_packet > 1) {
            $stock_display[count($stock_display) - 1] .= " ($total_units Units)";
        } elseif ($total_boxes > 0) {
            // If we only have boxes display
            $stock_display[count($stock_display) - 1] .= " ($total_packets Packets)";
        }
        
        $row['stock_display'] = implode(', ', $stock_display);
        $row['total_boxes'] = $total_boxes;
        $row['total_packets'] = $total_packets;
        $row['total_units'] = $total_units;
        $row['packets_per_box'] = $packets_per_box;
        $row['units_per_packet'] = $units_per_packet;

        // Apply stock filters (based on boxes)
        $include = true;

        // If any stock filter is checked, we need to check if this medicine matches
        if ($low_stock || $no_stock || $full_stock) {
            $include = false; // Start with false, will become true if matches any checked filter

            // Low Stock: boxes > 0 AND boxes < 40
            if ($low_stock && $total_boxes > 0 && $total_boxes < $low_stock_threshold) {
                $include = true;
            }

            // No Stock: boxes == 0
            if ($no_stock && $total_boxes == 0) {
                $include = true;
            }

            // Full Stock: boxes >= 100
            if ($full_stock && $total_boxes >= $full_stock_threshold) {
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
    <title>Stock Management - MediCare Pharma</title>
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
            transform: translateY(-5px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .gradient-yellow {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
        }

        .gradient-green {
            background: linear-gradient(135deg, var(--accent-green), #059669);
        }

        .gradient-blue {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
        }

        .gradient-red {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
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
        
        .stock-detail {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }
        
        .packaging-info {
            font-size: 11px;
            color: #9ca3af;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 4px;
            display: inline-block;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden ">
            <!-- Page Header - UPDATED AS REQUESTED -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Stock <span class="gradient-text">Management</span>
                        </h1>
                        <p class="text-gray-600">
                            <i class="fas fa-boxes text-green-500 mr-2"></i>
                            Manage medicine batches, monitor stock levels, and track expiry dates
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <?php if ($can_add_batch): ?>
                            <a href="add_stock.php"
                                class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-plus"></i>
                                <span>Add Stock</span>
                            </a>
                        <?php endif; ?>
                        <a href="medicines.php"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-eye text-yellow-500"></i>
                            <span>View Medicines</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="glass-card mx-6 my-6 p-6 rounded-2xl animate-fade-in-up">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-search text-blue-500 mr-2"></i>
                        Search Stock
                    </h3>
                    <div class="text-sm text-gray-500">
                        Low stock threshold: <span class="font-bold text-yellow-600"><?php echo $low_stock_threshold; ?> boxes</span>
                    </div>
                </div>

                <form method="GET" class="space-y-6">
                    <!-- Main Search Bar -->
                    <div class="relative">
                        <input type="text"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by medicine name, generic name, category, or type..."
                            class="w-full px-5 py-4 pl-12 rounded-xl text-base border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition-all"
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
                                    <span class="checkbox-count">0 boxes</span>
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
                                    <span class="checkbox-count">100+ boxes</span>
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
                                class="gradient-blue text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center gap-2 shadow-md">
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
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mx-6 mb-4">
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
                            class="gradient-yellow text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-list"></i>
                            <span>Show 10 per page</span>
                        </a>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view_all' => 1, 'page' => 1])); ?>"
                            class="gradient-green text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-list-ol"></i>
                            <span>View All Stock</span>
                        </a>
                    <?php endif; ?>

                    <!-- Export Button -->
                    <button onclick="exportStockToCSV()"
                        class="px-5 py-2 border-2 border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition font-medium flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- Stock Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-green-50 to-green-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-capsules"></i>
                                        <span>Medicine</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-tags mr-1"></i>Category
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-box mr-1"></i>Stock
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-cog mr-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-green-50">
                            <?php if (count($filtered_result) > 0): ?>
                                <?php foreach ($filtered_result as $row):
                                    $medicine_id = $row['id'];
                                    $total_boxes = $row['total_boxes'];
                                    $total_packets = $row['total_packets'];
                                    $total_units = $row['total_units'];

                                    // Determine stock status based on boxes
                                    if ($total_boxes == 0) {
                                        $stock_status = 'No Stock';
                                        $stock_color = 'red';
                                        $stock_bg = 'stock-badge-red';
                                        $stock_text = 'text-red-700';
                                        $stock_percent = 0;
                                    } elseif ($total_boxes < $low_stock_threshold) {
                                        $stock_status = 'Low Stock';
                                        $stock_color = 'yellow';
                                        $stock_bg = 'stock-badge-yellow';
                                        $stock_text = 'text-yellow-700';
                                        $stock_percent = ($total_boxes / $low_stock_threshold) * 100;
                                    } elseif ($total_boxes < $full_stock_threshold) {
                                        $stock_status = 'Good Stock';
                                        $stock_color = 'green';
                                        $stock_bg = 'stock-badge-green';
                                        $stock_text = 'text-green-700';
                                        $stock_percent = ($total_boxes / $full_stock_threshold) * 100;
                                    } else {
                                        $stock_status = 'Excellent Stock';
                                        $stock_color = 'emerald';
                                        $stock_bg = 'stock-badge-emerald';
                                        $stock_text = 'text-emerald-700';
                                        $stock_percent = 100;
                                    }
                                ?>
                                    <tr class="table-row hover:bg-green-25 transition-colors" data-id="<?php echo $row['id']; ?>">
                                        <!-- Medicine Details -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas fa-capsules text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($row['medicine_name']); ?></h4>
                                                    <p class="text-sm text-gray-500 mb-2">
                                                        <?php if (!empty($row['generic_name'])): ?>
                                                            <span class="font-medium"><?php echo htmlspecialchars($row['generic_name']); ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <div class="flex items-center gap-2">
                                                        <span class="medicine-id">MED-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                        <?php if (!empty($row['type_name'])): ?>
                                                            <span class="inline-block px-2 py-1 bg-purple-100 text-purple-700 text-xs font-medium rounded">
                                                                <?php echo htmlspecialchars($row['type_name']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($row['units_per_packet'] > 1 || $row['packets_per_box'] > 1): ?>
                                                        <div class="packaging-info mt-2">
                                                            <i class="fas fa-info-circle mr-1"></i>
                                                            <?php if ($row['packets_per_box'] > 1): ?>
                                                                1 Box = <?php echo $row['packets_per_box']; ?> Packets
                                                            <?php endif; ?>
                                                            <?php if ($row['units_per_packet'] > 1): ?>
                                                                <?php if ($row['packets_per_box'] > 1): ?> • <?php endif; ?>
                                                                1 Packet = <?php echo $row['units_per_packet']; ?> Units
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Category -->
                                        <td class="px-6 py-4">
                                            <?php if (!empty($row['category_name'])): ?>
                                                <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-tag mr-1 text-xs"></i>
                                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Stock Status -->
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <span class="font-bold <?php echo $stock_text; ?> text-lg">
                                                            <?php echo $row['stock_display']; ?>
                                                        </span>
                                                        <div class="stock-detail">
                                                            <?php if ($row['units_per_packet'] > 1): ?>
                                                                Total: <?php echo number_format($total_units); ?> Units
                                                            <?php else: ?>
                                                                Total: <?php echo number_format($total_packets); ?> Packets
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <span class="text-sm font-medium <?php echo $stock_text; ?> px-3 py-1 rounded-full <?php echo $stock_bg; ?>">
                                                        <?php echo $stock_status; ?>
                                                    </span>
                                                </div>
                                                <div class="stock-indicator">
                                                    <div class="stock-fill bg-<?php echo $stock_color; ?>-500" style="width: <?php echo $stock_percent; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Actions - UPDATED: Added Sale button -->
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col space-y-2">
                                                <!-- View Details Button -->
                                                <a href="view_stock.php?medicine_id=<?php echo $row['id']; ?>"
                                                    class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-eye text-sm"></i>
                                                    <span class="text-sm font-medium">View Details</span>
                                                </a>

                                                <!-- Sale Button - Only show if stock is available -->
                                                <?php if ($total_boxes > 0): ?>
                                                    <a href="../pharmacist/create_sale.php?medicine_id=<?php echo $row['id']; ?>"
                                                        class="inline-flex items-center justify-center space-x-2 px-4 py-2 gradient-green text-white rounded-lg hover:shadow-lg transition-colors group shadow-sm">
                                                        <i class="fas fa-shopping-cart text-sm"></i>
                                                        <span class="text-sm font-medium">Create Sale</span>
                                                    </a>
                                                <?php else: ?>
                                                    <button disabled
                                                        class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed shadow-sm">
                                                        <i class="fas fa-shopping-cart text-sm"></i>
                                                        <span class="text-sm font-medium">Out of Stock</span>
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Action Buttons Row -->
                                                <div class="flex space-x-2">
                                                    <!-- Edit Stock Button (Pharmacist only) -->
                                                    <?php if ($can_edit_stock): ?>
                                                        <a href="edit_stock.php?medicine_id=<?php echo $row['id']; ?>"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-edit text-xs"></i>
                                                            <span class="text-xs font-medium">Edit Stock</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Delete Button (Pharmacist only) -->
                                                    <?php if ($can_delete_batch): ?>
                                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['medicine_name']); ?>')"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-trash-alt text-xs"></i>
                                                            <span class="text-xs font-medium">Delete</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-boxes text-green-400 text-2xl"></i>
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
                                                        class="gradient-green text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200">
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

                <!-- Table Footer with Pagination -->
                <?php if ($total_pages > 1 && !$view_all): ?>
                    <div class="px-6 py-4 border-t border-green-100 bg-gradient-to-r from-green-50 to-green-25">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-600">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                • Showing <?php echo min($per_page, $display_count); ?> of <?php echo $total_rows; ?> medicines
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- First Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                    class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm <?php echo $page == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>

                                <!-- Previous Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>"
                                    class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm <?php echo $page == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>

                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($p = $start_page; $p <= $end_page; $p++):
                                ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"
                                        class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm <?php echo $p == $page ? 'gradient-green text-white' : ''; ?>">
                                        <?php echo $p; ?>
                                    </a>
                                <?php endfor; ?>

                                <!-- Next Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>"
                                    class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm <?php echo $page == $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>

                                <!-- Last Page -->
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                    class="px-4 py-2 border border-green_200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm <?php echo $page == $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($view_all): ?>
                    <div class="px-6 py-4 border-t border-green-100 bg-gradient-to-r from-green-50 to-green-25">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-emerald-700 font-medium">
                                <i class="fas fa-info-circle mr-2"></i>
                                Viewing all <?php echo $display_count; ?> medicines stock
                            </div>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['view_all' => 0, 'page' => 1])); ?>"
                                class="gradient-yellow text-white px-4 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 text-sm">
                                Show 10 per page
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full modal-content">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
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
                        class="flex-1 px-4 py-3 gradient-red text-white rounded-xl hover:shadow-lg transition text-center font-medium">
                        Delete
                    </a>
                </div>
            </div>
        </div>
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

        // Delete modal functions
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

        // Close modals when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Auto-focus search input
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }

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

        // Export to CSV function
        function exportStockToCSV() {
            // Get all stock data from table
            const rows = [];
            const headers = ['Medicine ID', 'Medicine Name', 'Generic Name', 'Category', 'Type', 'Stock Display', 'Total Boxes', 'Total Packets', 'Total Units', 'Stock Status'];
            
            // Get visible rows
            document.querySelectorAll('tbody tr').forEach(row => {
                const medicineId = row.querySelector('.medicine-id')?.textContent.replace('MED-', '') || '';
                const medicineName = row.querySelector('.font-semibold.text-gray-800')?.textContent || '';
                const genericName = row.querySelector('.text-sm.text-gray-500 span')?.textContent || '';
                const category = row.querySelector('.bg-blue-100')?.textContent || '';
                const type = row.querySelector('.bg-purple-100')?.textContent || '';
                const stockDisplay = row.querySelector('.font-bold.text-lg')?.textContent || '';
                const totalBoxes = stockDisplay.match(/(\d+)\s*Boxes/)?.[1] || '0';
                const totalPackets = stockDisplay.match(/(\d+)\s*Packets/)?.[1] || '0';
                const totalUnits = row.querySelector('.stock-detail')?.textContent.replace('Total: ', '').replace(' Units', '').replace(' Packets', '') || '0';
                const stockStatus = row.querySelector('.text-sm.font-medium')?.textContent || '';
                
                const rowData = [
                    medicineId,
                    medicineName,
                    genericName,
                    category,
                    type,
                    stockDisplay,
                    totalBoxes,
                    totalPackets,
                    totalUnits,
                    stockStatus
                ];
                rows.push(rowData);
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
            a.download = `stock_inventory_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Ctrl/Cmd + A for add stock (pharmacist only)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && <?php echo $can_add_batch ? 'true' : 'false'; ?>) {
                e.preventDefault();
                window.location.href = 'add_stock.php';
            }

            // Ctrl/Cmd + S for quick sale (when medicine is selected)
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const selectedRow = document.querySelector('tbody tr:hover');
                if (selectedRow) {
                    const medicineId = selectedRow.getAttribute('data-id');
                    if (medicineId) {
                        // Check if medicine has stock
                        const stockDisplay = selectedRow.querySelector('.font-bold.text-lg')?.textContent;
                        if (stockDisplay && !stockDisplay.includes('0 Boxes')) {
                            window.location.href = `../pharmacist/create_sale.php?medicine_id=${medicineId}`;
                        } else {
                            alert('This medicine is out of stock!');
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>