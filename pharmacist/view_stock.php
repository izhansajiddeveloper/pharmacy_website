<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Both admin and pharmacists can view stock
if (!in_array($_SESSION['role'], ['admin', 'pharmacist'])) {
    header("Location: ../index.php");
    exit;
}

// Check if medicine_id is provided
if (!isset($_GET['medicine_id']) || empty($_GET['medicine_id'])) {
    header("Location: stock.php");
    exit;
}

$medicine_id = intval($_GET['medicine_id']);

// Get medicine details
$medicine_query = "SELECT m.*, mg.name as generic_name, c.name as category_name, t.name as type_name
                   FROM medicines m
                   LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
                   LEFT JOIN medicine_categories c ON m.category_id = c.id
                   LEFT JOIN medicine_types t ON m.type_id = t.id
                   WHERE m.id = ?";
$medicine_stmt = mysqli_prepare($conn, $medicine_query);
mysqli_stmt_bind_param($medicine_stmt, 'i', $medicine_id);
mysqli_stmt_execute($medicine_stmt);
$medicine_result = mysqli_stmt_get_result($medicine_stmt);
$medicine = mysqli_fetch_assoc($medicine_result);

if (!$medicine) {
    header("Location: stock.php");
    exit;
}

// Get all stock batches for this medicine (including expired, returned, disposed)
$batches_query = "SELECT sb.*, s.name as supplier_name,
                  CASE 
                      WHEN sb.is_expired = 1 THEN 'Expired'
                      WHEN sb.is_returned = 1 THEN 'Returned'
                      WHEN sb.is_disposed = 1 THEN 'Disposed'
                      WHEN sb.quantity <= 0 THEN 'Out of Stock'
                      WHEN sb.expiry_date < CURDATE() THEN 'Expired'
                      WHEN sb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Near Expiry'
                      ELSE 'Active'
                  END as status
                  FROM stock_batches sb
                  LEFT JOIN suppliers s ON sb.supplier_id = s.id
                  WHERE sb.medicine_id = ?
                  ORDER BY sb.expiry_date ASC";
$batches_stmt = mysqli_prepare($conn, $batches_query);
mysqli_stmt_bind_param($batches_stmt, 'i', $medicine_id);
mysqli_stmt_execute($batches_stmt);
$batches_result = mysqli_stmt_get_result($batches_stmt);

// Calculate statistics
$stats_query = "SELECT 
                  COUNT(*) as total_batches,
                  SUM(CASE WHEN quantity > 0 AND is_expired = 0 AND is_returned = 0 AND is_disposed = 0 THEN 1 ELSE 0 END) as active_batches,
                  SUM(CASE WHEN is_expired = 1 THEN 1 ELSE 0 END) as expired_batches,
                  SUM(CASE WHEN is_returned = 1 THEN 1 ELSE 0 END) as returned_batches,
                  SUM(CASE WHEN is_disposed = 1 THEN 1 ELSE 0 END) as disposed_batches,
                  COALESCE(SUM(CASE WHEN quantity > 0 AND is_expired = 0 AND is_returned = 0 AND is_disposed = 0 THEN quantity ELSE 0 END), 0) as active_quantity,
                  COALESCE(SUM(CASE WHEN quantity > 0 AND is_expired = 0 AND is_returned = 0 AND is_disposed = 0 THEN purchase_price * quantity ELSE 0 END), 0) as total_purchase_value,
                  COALESCE(SUM(CASE WHEN quantity > 0 AND is_expired = 0 AND is_returned = 0 AND is_disposed = 0 THEN selling_price * quantity ELSE 0 END), 0) as total_selling_value,
                  COALESCE(SUM(CASE WHEN quantity > 0 AND is_expired = 0 AND is_returned = 0 AND is_disposed = 0 THEN mrp * quantity ELSE 0 END), 0) as total_mrp_value
                FROM stock_batches
                WHERE medicine_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, 'i', $medicine_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get near expiry batches (expiring in next 30 days)
$near_expiry_query = "SELECT COUNT(*) as near_expiry_count,
                             SUM(quantity) as near_expiry_quantity
                      FROM stock_batches
                      WHERE medicine_id = ?
                      AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                      AND quantity > 0
                      AND is_expired = 0
                      AND is_returned = 0
                      AND is_disposed = 0";
$near_expiry_stmt = mysqli_prepare($conn, $near_expiry_query);
mysqli_stmt_bind_param($near_expiry_stmt, 'i', $medicine_id);
mysqli_stmt_execute($near_expiry_stmt);
$near_expiry_result = mysqli_stmt_get_result($near_expiry_stmt);
$near_expiry = mysqli_fetch_assoc($near_expiry_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Details - <?php echo htmlspecialchars($medicine['name']); ?> | MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --accent-purple: #8b5cf6;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
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

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }

        .status-active {
            background: #d1fae5;
            color: #059669;
        }

        .status-expired {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-near-expiry {
            background: #fef3c7;
            color: #d97706;
        }

        .status-returned {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .status-disposed {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-4 mb-4">
                            <a href="stock.php" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-3xl font-bold text-gray-800">
                                Stock <span class="text-blue-600">Details</span>
                            </h1>
                        </div>
                        
                        <!-- Medicine Info -->
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-xl border border-blue-200">
                            <div class="flex flex-col lg:flex-row lg:items-start space-y-4 lg:space-y-0 lg:space-x-6">
                                <!-- Medicine Details -->
                                <div class="flex-1">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-16 h-16 rounded-xl gradient-blue flex items-center justify-center text-white font-bold shadow-lg">
                                            <i class="fas fa-capsules text-2xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h2 class="text-2xl font-bold text-gray-800 mb-1">
                                                <?php echo htmlspecialchars($medicine['name']); ?>
                                            </h2>
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                <?php if (!empty($medicine['generic_name'])): ?>
                                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 text-sm rounded-full">
                                                        <i class="fas fa-dna mr-1"></i>
                                                        <?php echo htmlspecialchars($medicine['generic_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($medicine['category_name'])): ?>
                                                    <span class="px-3 py-1 bg-green-100 text-green-700 text-sm rounded-full">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        <?php echo htmlspecialchars($medicine['category_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($medicine['type_name'])): ?>
                                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 text-sm rounded-full">
                                                        <i class="fas fa-pills mr-1"></i>
                                                        <?php echo htmlspecialchars($medicine['type_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($medicine['description'])): ?>
                                                <p class="text-gray-600 text-sm mb-2">
                                                    <?php echo htmlspecialchars($medicine['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="text-sm text-gray-600">
                                                <span class="font-medium">Medicine ID:</span>
                                                <span class="ml-2 px-2 py-1 bg-gray-200 text-gray-700 rounded font-mono">
                                                    MED-<?php echo str_pad($medicine['id'], 6, '0', STR_PAD_LEFT); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stock Summary -->
                                <div class="lg:w-1/3">
                                    <div class="bg-white p-4 rounded-xl shadow-sm border">
                                        <h4 class="font-bold text-gray-800 mb-3">Stock Summary</h4>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Active Stock:</span>
                                                <span class="font-bold text-lg text-green-600">
                                                    <?php echo number_format($stats['active_quantity']); ?> units
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Active Batches:</span>
                                                <span class="font-bold text-blue-600">
                                                    <?php echo $stats['active_batches']; ?>
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Total Batches:</span>
                                                <span class="font-bold text-gray-800">
                                                    <?php echo $stats['total_batches']; ?>
                                                </span>
                                            </div>
                                            <?php if ($near_expiry['near_expiry_count'] > 0): ?>
                                                <div class="flex justify-between items-center pt-2 border-t">
                                                    <span class="text-sm text-yellow-600 font-medium">Near Expiry:</span>
                                                    <span class="font-bold text-yellow-600">
                                                        <?php echo $near_expiry['near_expiry_quantity']; ?> units
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mx-6 my-6">
                <!-- Active Stock Value -->
                <div class="glass-card p-5 rounded-2xl stat-card animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Active Stock Value</p>
                            <h3 class="text-2xl font-bold text-green-600">
                                Rs<?php echo number_format($stats['total_selling_value'], 2); ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500">
                        <span>Purchase: Rs<?php echo number_format($stats['total_purchase_value'], 2); ?></span>
                        <span class="mx-2">•</span>
                        <span>MRP: Rs<?php echo number_format($stats['total_mrp_value'], 2); ?></span>
                    </div>
                </div>

                <!-- Batch Status -->
                <div class="glass-card p-5 rounded-2xl stat-card animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Batch Status</p>
                            <h3 class="text-2xl font-bold text-blue-600">
                                <?php echo $stats['active_batches']; ?>/<?php echo $stats['total_batches']; ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-boxes text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="flex space-x-2">
                            <?php if ($stats['expired_batches'] > 0): ?>
                                <span class="status-badge status-expired">
                                    <?php echo $stats['expired_batches']; ?> Expired
                                </span>
                            <?php endif; ?>
                            <?php if ($stats['returned_batches'] > 0): ?>
                                <span class="status-badge status-returned">
                                    <?php echo $stats['returned_batches']; ?> Returned
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stock Health -->
                <div class="glass-card p-5 rounded-2xl stat-card animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Stock Health</p>
                            <h3 class="text-2xl font-bold <?php echo $stats['active_quantity'] == 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo $stats['active_quantity'] == 0 ? 'Out of Stock' : 'Healthy'; ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 rounded-full <?php echo $stats['active_quantity'] == 0 ? 'bg-red-100' : 'bg-green-100'; ?> flex items-center justify-center">
                            <i class="fas fa-heartbeat <?php echo $stats['active_quantity'] == 0 ? 'text-red-600' : 'text-green-600'; ?> text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?php if ($near_expiry['near_expiry_count'] > 0): ?>
                            <div class="text-sm text-yellow-600 font-medium">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <?php echo $near_expiry['near_expiry_count']; ?> batches near expiry
                            </div>
                        <?php else: ?>
                            <div class="text-sm text-green-600">
                                <i class="fas fa-check-circle mr-1"></i>
                                All batches healthy
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="glass-card p-5 rounded-2xl stat-card animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Quick Actions</p>
                            <h3 class="text-lg font-bold text-gray-800">Manage Stock</h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <i class="fas fa-cogs text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-3 flex space-x-2">
                        <?php if ($_SESSION['role'] === 'pharmacist'): ?>
                            <a href="edit_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                                class="flex-1 text-center px-3 py-2 bg-yellow-50 text-yellow-700 text-sm rounded-lg hover:bg-yellow-100 transition">
                                Edit
                            </a>
                        <?php endif; ?>
                        <a href="add_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                            class="flex-1 text-center px-3 py-2 bg-green-50 text-green-700 text-sm rounded-lg hover:bg-green-100 transition">
                            Add Batch
                        </a>
                    </div>
                </div>
            </div>

            <!-- Batch Details Table -->
            <div class="glass-card mx-6 mb-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <!-- Table Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h3 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-boxes text-blue-500 mr-2"></i>
                            Stock Batches Details
                        </h3>
                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-gray-600">
                                Showing <?php echo mysqli_num_rows($batches_result); ?> batches
                            </div>
                            <button onclick="printStockReport()"
                                class="px-4 py-2 border border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition text-sm font-medium">
                                <i class="fas fa-print mr-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <?php if (mysqli_num_rows($batches_result) > 0): ?>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Batch Details
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Stock Info
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Pricing (Rs)
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Dates
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php mysqli_data_seek($batches_result, 0); ?>
                                <?php while ($batch = mysqli_fetch_assoc($batches_result)): 
                                    $is_expired = $batch['is_expired'] == 1 || strtotime($batch['expiry_date']) < time();
                                    $is_near_expiry = !$is_expired && strtotime($batch['expiry_date']) < strtotime('+30 days');
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <!-- Batch Details -->
                                        <td class="px-4 py-4">
                                            <div class="space-y-2">
                                                <div class="font-mono font-bold text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($batch['batch_no']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php if (!empty($batch['supplier_name'])): ?>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-truck mr-1"></i>
                                                            <?php echo htmlspecialchars($batch['supplier_name']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($batch['location'])): ?>
                                                        <div class="flex items-center mt-1">
                                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                                            <?php echo htmlspecialchars($batch['location']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    Batch ID: <?php echo $batch['id']; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Stock Info -->
                                        <td class="px-4 py-4">
                                            <div class="space-y-2">
                                                <div class="font-bold text-lg <?php echo $batch['quantity'] > 0 ? 'text-gray-800' : 'text-red-600'; ?>">
                                                    <?php echo number_format($batch['quantity']); ?> units
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    <div class="flex justify-between">
                                                        <span>Units/Packet:</span>
                                                        <span class="font-medium"><?php echo $batch['units_per_packet']; ?></span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span>Packets/Box:</span>
                                                        <span class="font-medium"><?php echo $batch['packets_per_box']; ?></span>
                                                    </div>
                                                </div>
                                                <?php if ($batch['quantity'] > 0): ?>
                                                    <div class="text-xs text-gray-500">
                                                        Total: <?php echo number_format($batch['quantity'] * $batch['units_per_packet'] * $batch['packets_per_box']); ?> individual units
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Pricing -->
                                        <td class="px-4 py-4">
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-gray-500">Purchase:</span>
                                                    <span class="font-medium">Rs<?php echo number_format($batch['purchase_price'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-gray-500">Selling:</span>
                                                    <span class="font-medium text-green-600">Rs<?php echo number_format($batch['selling_price'], 2); ?></span>
                                                </div>
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-gray-500">MRP:</span>
                                                    <span class="font-medium text-blue-600">Rs<?php echo number_format($batch['mrp'], 2); ?></span>
                                                </div>
                                                <?php if ($batch['quantity'] > 0): ?>
                                                    <div class="pt-2 border-t text-xs">
                                                        <div class="flex justify-between">
                                                            <span>Total Value:</span>
                                                            <span class="font-bold">
                                                                Rs<?php echo number_format($batch['quantity'] * $batch['selling_price'], 2); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Dates -->
                                        <td class="px-4 py-4">
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="text-xs text-gray-500">Received:</div>
                                                    <div class="text-sm font-medium">
                                                        <?php echo date('d/m/Y', strtotime($batch['received_date'])); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500">Expiry:</div>
                                                    <div class="text-sm font-medium <?php echo $is_expired ? 'text-red-600' : ($is_near_expiry ? 'text-yellow-600' : 'text-green-600'); ?>">
                                                        <?php echo date('d/m/Y', strtotime($batch['expiry_date'])); ?>
                                                    </div>
                                                </div>
                                                <?php if ($batch['updated_at']): ?>
                                                    <div class="text-xs text-gray-400">
                                                        Updated: <?php echo date('d/m/Y H:i', strtotime($batch['updated_at'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-4 py-4">
                                            <?php
                                            $status_class = 'status-active';
                                            $status_text = $batch['status'];
                                            
                                            if ($batch['is_expired'] == 1 || $is_expired) {
                                                $status_class = 'status-expired';
                                                $status_text = 'Expired';
                                            } elseif ($batch['is_returned'] == 1) {
                                                $status_class = 'status-returned';
                                                $status_text = 'Returned';
                                            } elseif ($batch['is_disposed'] == 1) {
                                                $status_class = 'status-disposed';
                                                $status_text = 'Disposed';
                                            } elseif ($is_near_expiry) {
                                                $status_class = 'status-near-expiry';
                                                $status_text = 'Near Expiry';
                                            } elseif ($batch['quantity'] <= 0) {
                                                $status_class = 'status-expired';
                                                $status_text = 'Out of Stock';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                            
                                            <?php if ($batch['is_returned'] == 1 && $batch['returned_at']): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?php echo date('d/m/Y', strtotime($batch['returned_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($batch['is_disposed'] == 1 && $batch['disposed_at']): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    Disposed: <?php echo date('d/m/Y', strtotime($batch['disposed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box-open text-gray-400 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-800 mb-2">No Stock Batches Found</h4>
                        <p class="text-gray-600 mb-6">No stock batches have been added for this medicine yet.</p>
                        <a href="add_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                            class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-200 inline-flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add First Batch</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Actions -->
            <div class="glass-card mx-6 mb-6 p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.6s">
                <div class="flex flex-wrap gap-4 justify-between">
                    <div class="flex flex-wrap gap-3">
                        <a href="stock.php"
                            class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Stock</span>
                        </a>
                        <a href="add_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                            class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-200 flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add New Batch</span>
                        </a>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <?php if ($_SESSION['role'] === 'pharmacist'): ?>
                            <a href="edit_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                                class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-200 flex items-center space-x-2">
                                <i class="fas fa-edit"></i>
                                <span>Edit Stock</span>
                            </a>
                        <?php endif; ?>
                        <button onclick="exportStockDetails()"
                            class="px-6 py-3 border-2 border-blue-200 text-blue-600 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2">
                            <i class="fas fa-download"></i>
                            <span>Export Data</span>
                        </button>
                    </div>
                </div>
            </div>
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

        // Print stock report
        function printStockReport() {
            const originalContent = document.body.innerHTML;
            const printContent = document.querySelector('.glass-card.mx-6.mb-6').outerHTML;
            
            document.body.innerHTML = `
                <div style="padding: 20px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h1 style="color: #1e40af; font-size: 24px;">Stock Batch Report</h1>
                        <h2 style="color: #374151; font-size: 18px;">
                            <?php echo htmlspecialchars($medicine['name']); ?>
                        </h2>
                        <p style="color: #6b7280; font-size: 14px;">
                            Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                        </p>
                    </div>
                    ${printContent}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
        }

        // Export stock details to CSV
        function exportStockDetails() {
            const rows = [];
            const headers = ['Batch No', 'Quantity', 'Units/Packet', 'Packets/Box', 'Purchase Price', 'Selling Price', 'MRP', 'Received Date', 'Expiry Date', 'Location', 'Supplier', 'Status'];
            
            // Get all batch rows
            document.querySelectorAll('tbody tr').forEach(row => {
                const batchNo = row.querySelector('.font-mono')?.textContent || '';
                const quantity = row.querySelector('.font-bold.text-lg')?.textContent.replace(' units', '') || '0';
                const unitsPerPacket = row.querySelector('.text-sm.text-gray-600 div:nth-child(1) span:nth-child(2)')?.textContent || '0';
                const packetsPerBox = row.querySelector('.text-sm.text-gray-600 div:nth-child(2) span:nth-child(2)')?.textContent || '0';
                const purchasePrice = row.querySelector('td:nth-child(3) div:nth-child(1) span:nth-child(2)')?.textContent.replace('Rs', '') || '0';
                const sellingPrice = row.querySelector('td:nth-child(3) div:nth-child(2) span:nth-child(2)')?.textContent.replace('Rs', '') || '0';
                const mrp = row.querySelector('td:nth-child(3) div:nth-child(3) span:nth-child(2)')?.textContent.replace('Rs', '') || '0';
                const receivedDate = row.querySelector('td:nth-child(4) div:nth-child(1) div:nth-child(2)')?.textContent || '';
                const expiryDate = row.querySelector('td:nth-child(4) div:nth-child(2) div:nth-child(2)')?.textContent || '';
                const location = row.querySelector('td:nth-child(1) div:nth-child(2) div:nth-child(2)')?.textContent || '';
                const supplier = row.querySelector('td:nth-child(1) div:nth-child(2) div:nth-child(1)')?.textContent.replace('', '').trim() || '';
                const status = row.querySelector('.status-badge')?.textContent || '';
                
                rows.push([batchNo, quantity, unitsPerPacket, packetsPerBox, purchasePrice, sellingPrice, mrp, receivedDate, expiryDate, location, supplier, status]);
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
            a.download = `stock_details_<?php echo $medicine['name']; ?>_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printStockReport();
            }
            
            // Ctrl/Cmd + E to export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportStockDetails();
            }
            
            // Esc to go back
            if (e.key === 'Escape') {
                window.location.href = 'stock.php';
            }
        });
    </script>
</body>
</html>