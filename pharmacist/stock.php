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

// Get medicine_id from URL if specified
$medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;

// Fetch stock data
$query = "SELECT sb.*, m.name AS medicine_name, m.generic_name, m.category_id, m.type_id,
                 c.name AS category_name, t.name AS type_name,
                 s.name AS supplier_name
          FROM stock_batches sb
          JOIN medicines m ON sb.medicine_id = m.id
          LEFT JOIN medicine_categories c ON m.category_id = c.id
          LEFT JOIN medicine_types t ON m.type_id = t.id
          LEFT JOIN suppliers s ON sb.supplier_id = s.id";

if ($medicine_id > 0) {
    $query .= " WHERE sb.medicine_id = $medicine_id";
}

$query .= " ORDER BY sb.expiry_date ASC, sb.received_date DESC";

$result = mysqli_query($conn, $query);

// Get statistics
$total_batches = mysqli_num_rows($result);
$total_stock_value = 0;
$total_items = 0;

// Calculate statistics
$stats_result = mysqli_query(
    $conn,
    "SELECT 
        COUNT(DISTINCT medicine_id) as total_medicines,
        SUM(quantity) as total_quantity,
        SUM(quantity * purchase_price) as total_value,
        SUM(CASE WHEN quantity <= 50 THEN 1 ELSE 0 END) as low_stock_batches,
        SUM(CASE WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon
     FROM stock_batches"
);
$stats = mysqli_fetch_assoc($stats_result);

// Get low stock medicines
$low_stock_query = mysqli_query(
    $conn,
    "SELECT m.name, SUM(sb.quantity) as total_quantity
     FROM medicines m
     JOIN stock_batches sb ON m.id = sb.medicine_id
     GROUP BY m.id
     HAVING total_quantity <= 50
     ORDER BY total_quantity ASC
     LIMIT 5"
);

// Get expiring soon batches
$expiring_soon = mysqli_query(
    $conn,
    "SELECT sb.*, m.name as medicine_name
     FROM stock_batches sb
     JOIN medicines m ON sb.medicine_id = m.id
     WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY sb.expiry_date ASC
     LIMIT 5"
);

// Get recent stock additions
$recent_additions = mysqli_query(
    $conn,
    "SELECT sb.*, m.name AS medicine_name
     FROM stock_batches sb
     JOIN medicines m ON sb.medicine_id = m.id
     ORDER BY sb.received_date DESC
     LIMIT 5"
);

// Get all medicines for filter
$medicines = mysqli_query($conn, "SELECT id, name, generic_name FROM medicines ORDER BY name");
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

        .badge-expiry {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-batch {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-low-stock {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-good-stock {
            background: linear-gradient(135deg, #10b981, #059669);
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

        .status-expired {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .status-expiring {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .status-good {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .expiry-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .expiry-soon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .expired {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
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
        <main class="flex-1 overflow-hidden ">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Stock <span class="gradient-text">Management</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-boxes text-green-500"></i>
                            <span>Manage medicine batches, monitor stock levels, and track expiry dates</span>
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
                        <?php if ($can_add_batch): ?>
                            <a href="add_stock.php"
                                class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-plus"></i>
                                <span>Add Stock Batch</span>
                                <i class="fas fa-arrow-right text-green-100 text-sm"></i>
                            </a>
                        <?php endif; ?>
                        <a href="medicines.php"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-pills text-yellow-500"></i>
                            <span>View Medicines</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-layer-group text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_quantity'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Total Units in Stock</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-blue h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Value</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['total_value'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Stock Value</p>
                    <div class="flex items-center text-sm text-green-500">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>Current inventory worth</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Alerts</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $stats['low_stock_batches'] + $stats['expiring_soon']; ?></h3>
                    <p class="text-gray-600 mb-3">Active Alerts</p>
                    <div class="flex items-center space-x-2 text-sm">
                        <span class="text-red-500"><i class="fas fa-arrow-down mr-1"></i><?php echo $stats['low_stock_batches']; ?> Low</span>
                        <span class="text-yellow-500"><i class="fas fa-clock mr-1"></i><?php echo $stats['expiring_soon']; ?> Expiring</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-box-open text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Batches</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($total_batches); ?></h3>
                    <p class="text-gray-600 mb-3">Active Stock Batches</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-cubes mr-1"></i>
                        <span><?php echo $stats['total_medicines']; ?> different medicines</span>
                    </div>
                </div>
            </div>

            <!-- Stock Batches Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-green-100 bg-gradient-to-r from-green-50 to-green-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">Stock Batches</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo $total_batches; ?> stock batches</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search by medicine name or batch number..."
                                class="pl-10 pr-4 py-2 border border-green-200 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-green-400"></i>
                        </div>

                        <!-- Filter by Medicine -->
                        <select id="medicineFilter" class="px-4 py-2 border border-green-200 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="0">All Medicines</option>
                            <?php
                            mysqli_data_seek($medicines, 0); // Reset pointer
                            while ($medicine = mysqli_fetch_assoc($medicines)): ?>
                                <option value="<?php echo $medicine['id']; ?>" <?php echo ($medicine_id == $medicine['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($medicine['name']); ?>
                                    <?php if (!empty($medicine['generic_name'])): ?>
                                        (<?php echo htmlspecialchars($medicine['generic_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <!-- Filter by Status -->
                        <select id="statusFilter" class="px-4 py-2 border border-green-200 rounded-lg focus:ring-2 focus:ring-green-200 focus:border-green-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="">All Status</option>
                            <option value="good">Good Stock</option>
                            <option value="low">Low Stock</option>
                            <option value="expiring">Expiring Soon</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-green-50 to-green-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-2">
                                        <span>Medicine Details</span>
                                        <i class="fas fa-sort text-green-400 cursor-pointer hover:text-green-600"></i>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Batch Info
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Stock Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Price & Value
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-green-50">
                            <?php if ($total_batches > 0): ?>
                                <?php
                                if ($result && mysqli_num_rows($result) > 0) {
                                    mysqli_data_seek($result, 0);
                                    while ($row = mysqli_fetch_assoc($result)):
                                        // Calculate expiry status
                                        $expiry_date = new DateTime($row['expiry_date']);
                                        $today = new DateTime();
                                        $days_until_expiry = max(0, $today->diff($expiry_date)->days);

                                        if ($expiry_date < $today) {
                                            $expiry_status = 'expired';
                                            $expiry_class = 'expired';
                                            $expiry_text = 'Expired';
                                            $status_class = 'status-expired';
                                        } elseif ($days_until_expiry <= 30) {
                                            $expiry_status = 'expiring';
                                            $expiry_class = 'expiry-soon';
                                            $expiry_text = 'Expiring Soon';
                                            $status_class = 'status-expiring';
                                        } else {
                                            $expiry_status = 'good';
                                            $expiry_class = '';
                                            $expiry_text = 'Good';
                                            $status_class = 'status-good';
                                        }

                                        // Stock status
                                        $stock_status = $row['quantity'] <= 50 ? 'Low' : ($row['quantity'] <= 100 ? 'Medium' : 'Good');
                                        $stock_class = $row['quantity'] <= 50 ? 'badge-low-stock' : 'badge-good-stock';

                                        // Batch value
                                        $batch_value = $row['quantity'] * $row['purchase_price'];
                                ?>
                                        <tr class="table-row hover:bg-green-25 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="flex items-start space-x-4">
                                                    <div class="relative">
                                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                            <i class="fas fa-capsules text-lg"></i>
                                                        </div>
                                                        <?php if ($expiry_status !== 'good'): ?>
                                                            <div class="expiry-badge <?php echo $expiry_class; ?>">
                                                                <i class="fas fa-<?php echo $expiry_status === 'expired' ? 'exclamation' : 'clock'; ?>"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="font-semibold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($row['medicine_name']); ?></h4>
                                                        <p class="text-sm text-gray-500 mb-2">
                                                            <?php if (!empty($row['generic_name'])): ?>
                                                                <span class="font-medium"><?php echo htmlspecialchars($row['generic_name']); ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="space-y-3">
                                                    <div class="flex flex-wrap gap-2">
                                                        <span class="badge-batch">
                                                            <i class="fas fa-hashtag mr-1 text-xs"></i>
                                                            <?php echo htmlspecialchars($row['batch_no']); ?>
                                                        </span>
                                                        <?php if ($expiry_status !== 'good'): ?>
                                                            <span class="<?php echo $expiry_status === 'expired' ? 'badge-low-stock' : 'badge-expiry'; ?>">
                                                                <i class="fas fa-<?php echo $expiry_status === 'expired' ? 'exclamation-triangle' : 'clock'; ?> mr-1 text-xs"></i>
                                                                <?php echo $expiry_text; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2 text-sm text-gray-700">
                                                            <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                                            <span class="font-medium">
                                                                Expiry: <?php echo date('M d, Y', strtotime($row['expiry_date'])); ?>
                                                            </span>
                                                            <span class="text-xs text-gray-500">
                                                                (<?php echo $days_until_expiry; ?> days)
                                                            </span>
                                                        </div>
                                                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                                                            <i class="fas fa-calendar-plus text-gray-400 text-xs"></i>
                                                            <span>Received: <?php echo date('M d, Y', strtotime($row['received_date'])); ?></span>
                                                        </div>
                                                        <?php if (!empty($row['supplier_name'])): ?>
                                                            <div class="flex items-center space-x-2 text-sm text-gray-600">
                                                                <i class="fas fa-truck text-gray-400 text-xs"></i>
                                                                <span>Supplier: <?php echo htmlspecialchars($row['supplier_name']); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="space-y-3">
                                                    <div>
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="text-sm font-medium text-gray-700">Quantity</span>
                                                            <span class="<?php echo $stock_class; ?> text-xs"><?php echo number_format($row['quantity']); ?> units</span>
                                                        </div>
                                                        <div class="stock-indicator">
                                                            <div class="stock-fill <?php echo $status_class; ?>" style="width: <?php echo min(100, ($row['quantity'] / 200) * 100); ?>%;"></div>
                                                        </div>
                                                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                                                            <span>0</span>
                                                            <span><?php echo $stock_status; ?></span>
                                                            <span>200+</span>
                                                        </div>
                                                    </div>
                                                    <div class="text-xs text-gray-600">
                                                        <i class="fas fa-box mr-1"></i>
                                                        Location: <?php echo htmlspecialchars($row['location'] ?: 'Main Store'); ?>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Purchase Price</span>
                                                        <span class="text-sm font-bold text-blue-600">Rs <?php echo number_format($row['purchase_price'], 2); ?></span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Selling Price</span>
                                                        <span class="text-sm font-bold text-green-600">Rs <?php echo number_format($row['selling_price'], 2); ?></span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">MRP</span>
                                                        <span class="text-sm font-bold text-purple-600">Rs <?php echo number_format($row['mrp'], 2); ?></span>
                                                    </div>
                                                    <div class="pt-2 border-t border-gray-100">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-sm font-medium text-gray-600">Batch Value</span>
                                                            <span class="text-sm font-bold text-green-600">Rs <?php echo number_format($batch_value, 2); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex flex-col space-y-2">
                                                    <!-- View Details Button -->
                                                    <a href="view_stock.php?id=<?php echo $row['id']; ?>"
                                                        class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-eye text-sm"></i>
                                                        <span class="text-sm font-medium">View Details</span>
                                                    </a>

                                                    <!-- Action Buttons Row -->
                                                    <div class="flex space-x-2">
                                                        <!-- Edit Button (Pharmacist only) -->
                                                        <?php if ($can_edit_stock): ?>
                                                            <a href="edit_stock.php?id=<?php echo $row['id']; ?>"
                                                                class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                                <i class="fas fa-edit text-xs"></i>
                                                                <span class="text-xs font-medium">Edit</span>
                                                            </a>
                                                        <?php endif; ?>

                                                        <!-- Delete Button (Pharmacist only) -->
                                                        <?php if ($can_delete_batch): ?>
                                                            <button onclick="showDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['medicine_name'] . ' - Batch: ' . $row['batch_no']); ?>')"
                                                                class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                                <i class="fas fa-trash-alt text-xs"></i>
                                                                <span class="text-xs font-medium">Delete</span>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Quick Actions -->
                                                    <?php if ($can_edit_stock): ?>
                                                        <div class="pt-2 mt-2 border-t border-gray-100">
                                                            <button onclick="adjustStock(<?php echo $row['id']; ?>, '<?php echo addslashes($row['medicine_name']); ?>')"
                                                                class="w-full inline-flex items-center justify-center space-x-1 px-3 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors group shadow-sm text-xs">
                                                                <i class="fas fa-exchange-alt text-xs"></i>
                                                                <span class="font-medium">Adjust Stock</span>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                <?php
                                    endwhile;
                                }
                                ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-boxes text-green-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Stock Batches Found</h4>
                                            <p class="text-gray-600 mb-6">Get started by adding your first stock batch to the inventory.</p>
                                            <?php if ($can_add_batch): ?>
                                                <a href="add_stock.php"
                                                    class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add First Stock Batch</span>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500">Contact pharmacist to add stock batches</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 border-t border-green-100 bg-gradient-to-r from-green-50 to-green-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo $total_batches; ?> stock batches •
                                <span class="font-medium text-green-600">
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
                            <button class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-export text-green-500"></i>
                                <span class="text-sm text-gray-700">Export</span>
                            </button>
                            <button class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-print text-green-500"></i>
                                <span class="text-sm text-gray-700">Print</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts & Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mx-6 my-8">
                <!-- Low Stock Alerts -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                        <span>Low Stock Alerts</span>
                        <?php if ($stats['low_stock_batches'] > 0): ?>
                            <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                <?php echo $stats['low_stock_batches']; ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($low_stock_query) > 0): ?>
                            <?php while ($low = mysqli_fetch_assoc($low_stock_query)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-red-50 to-white rounded-lg border border-red-100 hover:border-red-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                            <i class="fas fa-exclamation text-red-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($low['name']); ?></h4>
                                            <p class="text-xs text-red-500">Critical stock level</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-red-600"><?php echo number_format($low['total_quantity']); ?></p>
                                        <p class="text-xs text-gray-400">units left</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <a href="medicines.php?filter=low_stock"
                                class="block text-center text-sm text-blue-600 hover:text-blue-800 font-medium mt-2">
                                View all low stock medicines →
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                                <p class="text-gray-500">All medicines have sufficient stock</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Expiring Soon -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-clock text-yellow-500"></i>
                        <span>Expiring Soon (30 days)</span>
                        <?php if ($stats['expiring_soon'] > 0): ?>
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                <?php echo $stats['expiring_soon']; ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($expiring_soon) > 0): ?>
                            <?php while ($exp = mysqli_fetch_assoc($expiring_soon)):
                                $exp_date = new DateTime($exp['expiry_date']);
                                $today = new DateTime();
                                $days_left = $today->diff($exp_date)->days;
                            ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                                            <i class="fas fa-calendar-alt text-yellow-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($exp['medicine_name']); ?></h4>
                                            <p class="text-xs text-gray-500">Batch: <?php echo htmlspecialchars($exp['batch_no']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold <?php echo $days_left <= 7 ? 'text-red-600' : 'text-yellow-600'; ?>">
                                            <?php echo date('M d', strtotime($exp['expiry_date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-400"><?php echo $days_left; ?> days left</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <a href="stock.php?filter=expiring"
                                class="block text-center text-sm text-blue-600 hover:text-blue-800 font-medium mt-2">
                                View all expiring batches →
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                                <p class="text-gray-500">No batches expiring soon</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Additions -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-history text-blue-500"></i>
                        <span>Recent Stock Additions</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($recent_additions) > 0): ?>
                            <?php while ($recent = mysqli_fetch_assoc($recent_additions)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-white rounded-lg border border-blue-100 hover:border-blue-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-plus text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($recent['medicine_name']); ?></h4>
                                            <p class="text-xs text-gray-500">Batch: <?php echo htmlspecialchars($recent['batch_no']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-green-600"><?php echo number_format($recent['quantity']); ?></p>
                                        <p class="text-xs text-gray-400">units</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <a href="stock.php?filter=recent"
                                class="block text-center text-sm text-blue-600 hover:text-blue-800 font-medium mt-2">
                                View all recent additions →
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-gray-500">No recent stock additions</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Stock Batch</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete <span id="deleteBatchName" class="font-semibold text-green-600"></span>?
                    This will remove the stock batch and its quantity from inventory. This action cannot be undone.
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-green-200 text-gray-700 rounded-xl hover:bg-green-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a id="deleteConfirmLink"
                        href="#"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                        Delete Batch
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-edit text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Adjust Stock</h3>
                <p class="text-gray-600 text-center mb-4">
                    Adjust quantity for <span id="adjustMedicineName" class="font-semibold text-green-600"></span>
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adjustment Type</label>
                        <select id="adjustmentType" class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition">
                            <option value="add">Add Stock</option>
                            <option value="remove">Remove Stock</option>
                            <option value="set">Set New Quantity</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" id="adjustQuantity" min="1" value="1"
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason (Optional)</label>
                        <textarea id="adjustReason" rows="2"
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition"
                            placeholder="e.g., Damaged goods, recount, etc."></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="hideAdjustModal()"
                            class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button onclick="submitAdjustment()"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Apply Adjustment
                        </button>
                    </div>
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
        function showDeleteModal(id, name) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteBatchName').textContent = name;
            document.getElementById('deleteConfirmLink').href = `delete_stock.php?id=${id}`;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Adjust stock modal functions
        let currentBatchId = null;

        function adjustStock(id, name) {
            currentBatchId = id;
            document.getElementById('adjustModal').classList.remove('hidden');
            document.getElementById('adjustMedicineName').textContent = name;
        }

        function hideAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
            currentBatchId = null;
        }

        function submitAdjustment() {
            const type = document.getElementById('adjustmentType').value;
            const quantity = document.getElementById('adjustQuantity').value;
            const reason = document.getElementById('adjustReason').value;

            if (!quantity || quantity <= 0) {
                alert('Please enter a valid quantity');
                return;
            }

            // Submit adjustment (this would typically be an AJAX call)
            alert(`Adjusting stock for batch ${currentBatchId}: ${type} ${quantity} units`);
            hideAdjustModal();

            // Reload the page to see changes
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // Transfer stock function
        function transferStock(batchId) {
            alert(`Opening transfer interface for batch ${batchId}`);
            // Implement transfer functionality
        }

        // Close modals when clicking outside
        [document.getElementById('deleteModal'), document.getElementById('adjustModal')].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (this.id === 'deleteModal') hideDeleteModal();
                        if (this.id === 'adjustModal') hideAdjustModal();
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

        // Medicine filter
        const medicineFilter = document.getElementById('medicineFilter');
        if (medicineFilter) {
            medicineFilter.addEventListener('change', function(e) {
                const selectedMedicine = e.target.value;
                if (selectedMedicine > 0) {
                    window.location.href = `stock.php?medicine_id=${selectedMedicine}`;
                } else {
                    window.location.href = 'stock.php';
                }
            });
        }

        // Status filter
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function(e) {
                const selectedStatus = e.target.value;
                if (selectedStatus) {
                    // Filter rows based on status
                    const rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const statusText = row.textContent.toLowerCase();
                        let shouldShow = true;

                        if (selectedStatus === 'good') {
                            shouldShow = !statusText.includes('expiring') && !statusText.includes('expired') && !statusText.includes('low');
                        } else if (selectedStatus === 'low') {
                            shouldShow = statusText.includes('low stock');
                        } else if (selectedStatus === 'expiring') {
                            shouldShow = statusText.includes('expiring soon');
                        } else if (selectedStatus === 'expired') {
                            shouldShow = statusText.includes('expired');
                        }

                        row.style.display = shouldShow ? '' : 'none';
                    });
                }
            });
        }

        // Sort functionality
        document.querySelectorAll('th .fa-sort').forEach(sortIcon => {
            sortIcon.addEventListener('click', function() {
                const th = this.closest('th');
                const colIndex = Array.from(th.parentNode.children).indexOf(th);
                const tbody = th.closest('table').querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr:not([style*="display: none"])'));

                const isAsc = !this.classList.contains('fa-sort-up');
                this.classList.toggle('fa-sort-up', isAsc);
                this.classList.toggle('fa-sort-down', !isAsc);

                rows.sort((a, b) => {
                    const aText = a.children[colIndex].textContent.trim();
                    const bText = b.children[colIndex].textContent.trim();

                    if (isAsc) {
                        return aText.localeCompare(bText);
                    } else {
                        return bText.localeCompare(aText);
                    }
                });

                rows.forEach(row => tbody.appendChild(row));
            });
        });

        // Animation on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in-up');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        document.querySelectorAll('.stat-card, .glass-card').forEach(card => {
            observer.observe(card);
        });

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
                console.log('Auto-refreshing stock data...');
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

            // Ctrl/Cmd + A for add batch (pharmacist only)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && <?php echo $can_add_batch ? 'true' : 'false'; ?>) {
                e.preventDefault();
                window.location.href = 'add_stock.php';
            }
        });
    </script>
</body>

</html>