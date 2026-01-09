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

/* ===============================
   HANDLE EDIT FORM SUBMISSION (AJAX)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_medicine') {
    if ($_SESSION['role'] !== 'pharmacist') {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $category_id = intval($_POST['category_id'] ?? 0);
    $type_id = intval($_POST['type_id'] ?? 0);
    $generic_id = intval($_POST['generic_id'] ?? 0);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

    // Validate
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
        exit;
    }

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Medicine name is required']);
        exit;
    }

    if ($category_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        exit;
    }

    if ($type_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Type is required']);
        exit;
    }

    // Update medicine
    $update_query = "UPDATE medicines SET 
        name = '$name',
        category_id = $category_id,
        type_id = $type_id,
        generic_id = " . ($generic_id > 0 ? $generic_id : "NULL") . ",
        description = '$description',
        updated_at = NOW()
        WHERE id = $id";

    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'Medicine updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit;
}

/* ===============================
   FETCH DATA FOR EDIT MODAL (AJAX)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_medicine') {
    if ($_SESSION['role'] !== 'pharmacist') {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
        exit;
    }

    // Fetch medicine details
    $medicine_query = mysqli_query(
        $conn,
        "SELECT m.*, c.name AS category_name, t.name AS type_name, g.name AS generic_name
         FROM medicines m
         LEFT JOIN medicine_categories c ON m.category_id = c.id
         LEFT JOIN medicine_types t ON m.type_id = t.id
         LEFT JOIN medicine_generics g ON m.generic_id = g.id
         WHERE m.id = $id"
    );

    if ($medicine = mysqli_fetch_assoc($medicine_query)) {
        // Fetch categories, types, generics for dropdowns
        $categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
        $types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
        $generics = mysqli_query($conn, "SELECT * FROM medicine_generics ORDER BY name");

        $categories_data = [];
        while ($cat = mysqli_fetch_assoc($categories)) {
            $categories_data[] = $cat;
        }

        $types_data = [];
        while ($type = mysqli_fetch_assoc($types)) {
            $types_data[] = $type;
        }

        $generics_data = [];
        while ($generic = mysqli_fetch_assoc($generics)) {
            $generics_data[] = $generic;
        }

        echo json_encode([
            'success' => true,
            'medicine' => $medicine,
            'categories' => $categories_data,
            'types' => $types_data,
            'generics' => $generics_data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Medicine not found']);
    }
    exit;
}

/* ===============================
   CONTINUE WITH NORMAL PAGE LOAD
================================ */

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

        /* Edit Modal Styles */
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

        .select-input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            transition: all 0.2s;
        }

        .select-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            <div id="successNotification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden transform transition-all duration-300 translate-x-full">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <span id="successMessage">Medicine updated successfully!</span>
                </div>
            </div>

            <!-- Error Notification -->
            <div id="errorNotification" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden transform transition-all duration-300 translate-x-full">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorMessage">Error updating medicine!</span>
                </div>
            </div>

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

                                                <!-- Edit -->
                                                <?php if ($can_edit): ?>
                                                    <button onclick="openEditModal(<?php echo $row['id']; ?>)"
                                                        class="action-btn bg-yellow-50 text-yellow-600 hover:bg-yellow-100 edit-btn">
                                                        <i class="fas fa-edit text-xs"></i>
                                                        <span>Edit</span>
                                                    </button>
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

    <!-- Edit Medicine Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden modal-content">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-edit text-blue-500 mr-2"></i>
                        <span id="modalTitle">Edit Medicine</span>
                    </h3>
                    <button onclick="closeEditModal()"
                        class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                <form id="editMedicineForm" class="space-y-6">
                    <input type="hidden" id="medicineId" name="id">
                    <input type="hidden" name="action" value="edit_medicine">

                    <!-- Medicine Name -->
                    <div>
                        <label for="editName" class="form-label">Medicine Name *</label>
                        <input type="text"
                            id="editName"
                            name="name"
                            class="form-input"
                            required
                            placeholder="Enter medicine name">
                    </div>

                    <!-- Category and Type -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label for="editCategory" class="form-label">Category *</label>
                            <select id="editCategory"
                                name="category_id"
                                class="select-input"
                                required>
                                <option value="">Select Category</option>
                                <!-- Categories will be loaded dynamically -->
                            </select>
                        </div>

                        <div>
                            <label for="editType" class="form-label">Type *</label>
                            <select id="editType"
                                name="type_id"
                                class="select-input"
                                required>
                                <option value="">Select Type</option>
                                <!-- Types will be loaded dynamically -->
                            </select>
                        </div>
                    </div>

                    <!-- Generic Name -->
                    <div>
                        <label for="editGeneric" class="form-label">Generic Name</label>
                        <select id="editGeneric"
                            name="generic_id"
                            class="select-input">
                            <option value="">Select Generic</option>
                            <!-- Generics will be loaded dynamically -->
                        </select>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea id="editDescription"
                            name="description"
                            rows="4"
                            class="form-input"
                            placeholder="Enter medicine description (usage, side effects, precautions, etc.)"></textarea>
                    </div>

                    <!-- Loading State -->
                    <div id="editLoading" class="text-center py-4 hidden">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                        <p class="text-gray-600 mt-2">Loading medicine details...</p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col md:flex-row gap-4 pt-6 border-t border-gray-200">
                        <button type="submit"
                            id="saveBtn"
                            class="gradient-primary text-white px-6 py-4 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md flex-1">
                            <i class="fas fa-save"></i>
                            <span>Save Changes</span>
                        </button>

                        <button type="button"
                            onclick="closeEditModal()"
                            class="px-6 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium flex items-center justify-center gap-2 flex-1">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                    </div>
                </form>
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
        // Edit Modal Functions
        let currentMedicineId = null;

        function openEditModal(medicineId) {
            currentMedicineId = medicineId;
            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');

            // Show loading state
            document.getElementById('editLoading').classList.remove('hidden');
            document.getElementById('editMedicineForm').classList.add('hidden');

            // Reset form
            document.getElementById('editMedicineForm').reset();

            // Load medicine data
            loadMedicineData(medicineId);
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            currentMedicineId = null;
        }

        async function loadMedicineData(medicineId) {
            try {
                const response = await fetch(`medicines.php?action=get_medicine&id=${medicineId}`);
                const data = await response.json();

                if (data.success) {
                    // Populate form
                    document.getElementById('medicineId').value = data.medicine.id;
                    document.getElementById('editName').value = data.medicine.name;
                    document.getElementById('editDescription').value = data.medicine.description || '';

                    // Populate categories dropdown
                    const categorySelect = document.getElementById('editCategory');
                    categorySelect.innerHTML = '<option value="">Select Category</option>';
                    data.categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        option.selected = (category.id == data.medicine.category_id);
                        categorySelect.appendChild(option);
                    });

                    // Populate types dropdown
                    const typeSelect = document.getElementById('editType');
                    typeSelect.innerHTML = '<option value="">Select Type</option>';
                    data.types.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.id;
                        option.textContent = type.name;
                        option.selected = (type.id == data.medicine.type_id);
                        typeSelect.appendChild(option);
                    });

                    // Populate generics dropdown
                    const genericSelect = document.getElementById('editGeneric');
                    genericSelect.innerHTML = '<option value="">Select Generic</option>';
                    data.generics.forEach(generic => {
                        const option = document.createElement('option');
                        option.value = generic.id;
                        option.textContent = generic.name;
                        option.selected = (generic.id == data.medicine.generic_id);
                        genericSelect.appendChild(option);
                    });

                    // Hide loading, show form
                    document.getElementById('editLoading').classList.add('hidden');
                    document.getElementById('editMedicineForm').classList.remove('hidden');

                    // Update modal title
                    document.getElementById('modalTitle').textContent = `Edit ${data.medicine.name}`;
                } else {
                    showNotification(data.message || 'Failed to load medicine data', 'error');
                    closeEditModal();
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error loading medicine data', 'error');
                closeEditModal();
            }
        }

        // Handle form submission
        document.getElementById('editMedicineForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const saveBtn = document.getElementById('saveBtn');
            const originalText = saveBtn.innerHTML;

            // Show loading state
            saveBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                <span>Saving...</span>
            `;
            saveBtn.disabled = true;

            try {
                const response = await fetch('medicines.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Show success notification
                    showNotification(data.message || 'Medicine updated successfully!', 'success');

                    // Update the table row in real-time
                    updateTableRow(formData);

                    // Close modal after a short delay
                    setTimeout(() => {
                        closeEditModal();
                    }, 1500);
                } else {
                    showNotification(data.message || 'Failed to update medicine', 'error');
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error updating medicine', 'error');
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        });

        // Update table row after successful edit
        function updateTableRow(formData) {
            const medicineId = formData.get('id');
            const row = document.querySelector(`tr[data-id="${medicineId}"]`);

            if (row) {
                // Update medicine name
                const nameElement = row.querySelector('.medicine-name');
                if (nameElement) {
                    nameElement.textContent = formData.get('name');
                }

                // Update description in data attribute if needed
                row.dataset.description = formData.get('description') || '';

                // Note: Category, type, and generic names would need to be fetched from server
                // or you could reload the page: window.location.reload();

                // For now, we'll reload the page to show all updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            if (type === 'success') {
                const notification = document.getElementById('successNotification');
                document.getElementById('successMessage').textContent = message;
                notification.classList.remove('hidden', 'translate-x-full');
                notification.classList.add('translate-x-0');

                setTimeout(() => {
                    notification.classList.remove('translate-x-0');
                    notification.classList.add('translate-x-full');
                    setTimeout(() => {
                        notification.classList.add('hidden');
                    }, 300);
                }, 3000);
            } else {
                const notification = document.getElementById('errorNotification');
                document.getElementById('errorMessage').textContent = message;
                notification.classList.remove('hidden', 'translate-x-full');
                notification.classList.add('translate-x-0');

                setTimeout(() => {
                    notification.classList.remove('translate-x-0');
                    notification.classList.add('translate-x-full');
                    setTimeout(() => {
                        notification.classList.add('hidden');
                    }, 300);
                }, 3000);
            }
        }

        // Rest of your existing functions...
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
                closeEditModal();
                closeDeleteModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
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
    </script>
</body>

</html>