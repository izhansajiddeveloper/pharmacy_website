<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Get inventory statistics
$stats_query = mysqli_query(
    $conn,
    "SELECT 
        COUNT(DISTINCT m.id) as total_medicines,
        COUNT(DISTINCT sb.medicine_id) as stocked_medicines,
        SUM(sb.quantity) as total_units,
        SUM(sb.quantity * sb.purchase_price) as total_value,
        AVG(sb.quantity) as avg_stock_per_medicine,
        COUNT(CASE WHEN sb.quantity <= 50 THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN sb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_items
     FROM stock_batches sb
     JOIN medicines m ON sb.medicine_id = m.id"
);
$stats = mysqli_fetch_assoc($stats_query);

// Get category-wise inventory
$category_inventory = mysqli_query(
    $conn,
    "SELECT c.name as category_name,
            COUNT(DISTINCT m.id) as medicine_count,
            SUM(sb.quantity) as total_units,
            SUM(sb.quantity * sb.purchase_price) as total_value
     FROM medicines m
     JOIN medicine_categories c ON m.category_id = c.id
     JOIN stock_batches sb ON m.id = sb.medicine_id
     GROUP BY c.id
     ORDER BY total_value DESC"
);

// Get inventory by location
$location_inventory = mysqli_query(
    $conn,
    "SELECT 
        COALESCE(sb.location, 'Main Store') as location_name,
        COUNT(DISTINCT sb.medicine_id) as medicine_count,
        SUM(sb.quantity) as total_units,
        SUM(sb.quantity * sb.purchase_price) as total_value
     FROM stock_batches sb
     GROUP BY sb.location
     ORDER BY total_value DESC"
);

// Get top valuable medicines
$valuable_medicines = mysqli_query(
    $conn,
    "SELECT 
        m.name AS medicine_name,
        mg.name AS generic_name,
        SUM(sb.quantity) AS total_units,
        SUM(sb.quantity * sb.purchase_price) AS total_value,
        MAX(sb.purchase_price) AS max_unit_price
     FROM medicines m
     LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
     JOIN stock_batches sb ON m.id = sb.medicine_id
     GROUP BY m.id, m.name, mg.name
     ORDER BY total_value DESC
     LIMIT 10"
);


// Get inventory turnover (medicines with sales)
$turnover_query = mysqli_query(
    $conn,
    "SELECT m.name,
            SUM(sb.quantity) as current_stock,
            COALESCE(SUM(si.quantity), 0) as sold_units,
            CASE 
                WHEN COALESCE(SUM(si.quantity), 0) = 0 THEN 0
                ELSE (COALESCE(SUM(si.quantity), 0) / SUM(sb.quantity)) * 100 
            END as turnover_rate
     FROM medicines m
     JOIN stock_batches sb ON m.id = sb.medicine_id
     LEFT JOIN sale_items si ON m.id = si.medicine_id
     GROUP BY m.id
     HAVING sold_units > 0
     ORDER BY turnover_rate DESC
     LIMIT 10"
);

// Get supplier inventory
$supplier_inventory = mysqli_query(
    $conn,
    "SELECT s.name as supplier_name,
            COUNT(DISTINCT sb.medicine_id) as medicine_count,
            SUM(sb.quantity) as total_units,
            SUM(sb.quantity * sb.purchase_price) as total_value,
            AVG(DATEDIFF(sb.expiry_date, CURDATE())) as avg_days_to_expiry
     FROM suppliers s
     JOIN stock_batches sb ON s.id = sb.supplier_id
     GROUP BY s.id
     ORDER BY total_value DESC"
);

// Get inventory growth (last 30 days)
$growth_query = mysqli_query(
    $conn,
    "SELECT 
        DATE(sb.added_at) as stock_date,
        COUNT(DISTINCT sb.medicine_id) as new_medicines,
        SUM(sb.quantity) as added_units,
        SUM(sb.quantity * sb.purchase_price) as added_value
     FROM stock_batches sb
     WHERE sb.added_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY DATE(sb.added_at)
     ORDER BY stock_date DESC"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Analytics - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --accent-indigo: #6366f1;
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

        .gradient-purple {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
        }

        .gradient-red {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
        }

        .gradient-indigo {
            background: linear-gradient(135deg, var(--accent-indigo), #4f46e5);
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

        .indigo-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-indigo), #4f46e5);
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

        .inventory-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .inventory-card:hover {
            border-left-color: var(--primary-yellow);
            transform: translateX(5px);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .inventory-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }

        .badge-high-value {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }

        .badge-high-turnover {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-low-stock {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .stock-bar {
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
            position: relative;
        }

        .stock-fill {
            height: 100%;
            border-radius: 4px;
            position: absolute;
            left: 0;
            top: 0;
        }

        .stock-high {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stock-medium {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stock-low {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="indigo-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "siderbar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Inventory <span class="gradient-text">Analytics</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-boxes text-indigo-500"></i>
                            <span>Comprehensive inventory analysis and stock management insights</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-chart-line text-green-500"></i>
                            <span>Real-time Analytics Dashboard</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="stock.php"
                            class="gradient-indigo text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-layer-group"></i>
                            <span>View Stock Details</span>
                            <i class="fas fa-arrow-right text-indigo-100 text-sm"></i>
                        </a>
                        <button onclick="exportInventoryReport()"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-file-export text-yellow-500"></i>
                            <span>Export Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Inventory Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <!-- Total Inventory Value -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Value</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['total_value'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Inventory Value</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-green h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <!-- Total Units -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-box text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Units</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_units'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Total Units in Stock</p>
                    <div class="flex items-center text-sm text-blue-500">
                        <i class="fas fa-pills mr-1"></i>
                        <span><?php echo $stats['stocked_medicines']; ?> stocked medicines</span>
                    </div>
                </div>

                <!-- Stock Coverage -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-chart-pie text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full"><?php echo $stats['low_stock_items'] ?: 0; ?> Low</span>
                            <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full"><?php echo $stats['expiring_items'] ?: 0; ?> Expiring</span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo round(($stats['stocked_medicines'] / $stats['total_medicines']) * 100, 1); ?>%</h3>
                    <p class="text-gray-600 mb-3">Stock Coverage Rate</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-calculator mr-1"></i>
                        <span>Avg <?php echo round($stats['avg_stock_per_medicine'] ?: 0); ?> units/medicine</span>
                    </div>
                </div>

                <!-- Inventory Health -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-indigo flex items-center justify-center shadow-lg">
                            <i class="fas fa-heartbeat text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">Health</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">
                        <?php
                        $health_score = 100;
                        $health_score -= ($stats['low_stock_items'] * 2);
                        $health_score -= ($stats['expiring_items'] * 3);
                        echo max(0, $health_score);
                        ?>%
                    </h3>
                    <p class="text-gray-600 mb-3">Inventory Health Score</p>
                    <div class="stock-bar">
                        <div class="stock-fill <?php echo $health_score >= 70 ? 'stock-high' : ($health_score >= 40 ? 'stock-medium' : 'stock-low'); ?>"
                            style="width: <?php echo $health_score; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-6">
                <!-- Category Distribution -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-pie text-purple-500"></i>
                        <span>Category-wise Inventory Value</span>
                    </h3>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Location Distribution -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-map-marker-alt text-blue-500"></i>
                        <span>Inventory by Location</span>
                    </h3>
                    <div class="chart-container">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Valuable Medicines -->
            <div class="glass-card mx-6 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                        <i class="fas fa-crown text-yellow-500"></i>
                        <span>Top Valuable Medicines</span>
                    </h3>
                    <span class="text-sm text-gray-500">By Total Stock Value</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-yellow-50 to-yellow-25">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Medicine</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Stock Units</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Max Unit Price</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Value</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Value Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-yellow-50">
                            <?php if (mysqli_num_rows($valuable_medicines) > 0):
                                $total_inventory_value = $stats['total_value'] ?: 1;
                            ?>
                                <?php while ($medicine = mysqli_fetch_assoc($valuable_medicines)):
                                    $value_share = ($medicine['total_value'] / $total_inventory_value) * 100;
                                ?>
                                    <tr class="table-row hover:bg-yellow-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                                    <i class="fas fa-pills text-purple-600"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-medium text-gray-800">
                                                        <?php echo htmlspecialchars($medicine['medicine_name']); ?>
                                                    </h4>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo htmlspecialchars($medicine['generic_name'] ?? ''); ?>
                                                    </p>
                                                </div>

                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-center">
                                                <span class="text-lg font-bold text-blue-600"><?php echo number_format($medicine['total_units']); ?></span>
                                                <p class="text-xs text-gray-500">units</p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-medium text-gray-700">Rs <?php echo number_format($medicine['max_unit_price'], 2); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-lg font-bold text-green-600">Rs <?php echo number_format($medicine['total_value'], 2); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="stock-bar flex-1">
                                                    <div class="stock-fill stock-high" style="width: <?php echo min(100, $value_share * 5); ?>%"></div>
                                                </div>
                                                <span class="text-sm font-medium text-gray-700"><?php echo round($value_share, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i class="fas fa-pills text-gray-400 text-2xl"></i>
                                            </div>
                                            <p class="text-gray-500">No valuable medicines data available</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory Analysis Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-8">
                <!-- Category Performance -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                        <i class="fas fa-tags text-green-500"></i>
                        <span>Category Performance</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($category_inventory) > 0): ?>
                            <?php while ($category = mysqli_fetch_assoc($category_inventory)):
                                $unit_share = ($category['total_units'] / $stats['total_units']) * 100;
                                $value_share = ($category['total_value'] / $stats['total_value']) * 100;
                            ?>
                                <div class="flex items-center justify-between p-4 inventory-card bg-gradient-to-r from-green-50 to-white rounded-xl border border-green-100">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                            <i class="fas fa-tag text-green-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($category['category_name']); ?></h4>
                                            <div class="flex items-center space-x-3 mt-1">
                                                <span class="inventory-badge badge-high-turnover">
                                                    <i class="fas fa-box mr-1"></i>
                                                    <?php echo number_format($category['medicine_count']); ?> medicines
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-green-600">Rs <?php echo number_format($category['total_value'], 2); ?></p>
                                        <div class="flex items-center justify-end space-x-2 text-xs text-gray-500">
                                            <span><?php echo number_format($category['total_units']); ?> units</span>
                                            <span>•</span>
                                            <span><?php echo round($value_share, 1); ?>% value</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-tags text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No category data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Supplier Analysis -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.9s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                        <i class="fas fa-truck text-blue-500"></i>
                        <span>Supplier Analysis</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($supplier_inventory) > 0): ?>
                            <?php while ($supplier = mysqli_fetch_assoc($supplier_inventory)):
                                $expiry_status = $supplier['avg_days_to_expiry'] > 180 ? 'text-green-600' : ($supplier['avg_days_to_expiry'] > 90 ? 'text-yellow-600' : 'text-red-600');
                            ?>
                                <div class="flex items-center justify-between p-4 inventory-card bg-gradient-to-r from-blue-50 to-white rounded-xl border border-blue-100">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-industry text-blue-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h4>
                                            <div class="flex items-center space-x-3 mt-1">
                                                <span class="inventory-badge badge-high-value">
                                                    <i class="fas fa-cube mr-1"></i>
                                                    <?php echo number_format($supplier['medicine_count']); ?> items
                                                </span>
                                                <span class="text-xs <?php echo $expiry_status; ?>">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    Avg <?php echo round($supplier['avg_days_to_expiry']); ?> days expiry
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-blue-600">Rs <?php echo number_format($supplier['total_value'], 2); ?></p>
                                        <div class="flex items-center justify-end space-x-2 text-xs text-gray-500">
                                            <span><?php echo number_format($supplier['total_units']); ?> units</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-truck text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No supplier data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Inventory Turnover -->
            <div class="glass-card mx-6 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.0s">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                        <i class="fas fa-exchange-alt text-purple-500"></i>
                        <span>Inventory Turnover Analysis</span>
                    </h3>
                    <span class="text-sm text-gray-500">Sales vs Stock Ratio</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-purple-50 to-purple-25">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Medicine</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Current Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Sold Units</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Turnover Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-purple-50">
                            <?php if (mysqli_num_rows($turnover_query) > 0): ?>
                                <?php while ($turnover = mysqli_fetch_assoc($turnover_query)):
                                    $turnover_rate = $turnover['turnover_rate'];
                                    $status = $turnover_rate > 50 ? 'Fast Moving' : ($turnover_rate > 20 ? 'Moderate' : 'Slow Moving');
                                    $status_class = $turnover_rate > 50 ? 'badge-high-turnover' : ($turnover_rate > 20 ? 'inventory-badge bg-yellow-100 text-yellow-800' : 'badge-low-stock');
                                ?>
                                    <tr class="table-row hover:bg-purple-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                                    <i class="fas fa-pills text-purple-600"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($turnover['name']); ?></h4>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-lg font-bold text-blue-600"><?php echo number_format($turnover['current_stock']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-lg font-bold text-green-600"><?php echo number_format($turnover['sold_units']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="stock-bar flex-1">
                                                    <div class="stock-fill <?php echo $turnover_rate > 50 ? 'stock-high' : ($turnover_rate > 20 ? 'stock-medium' : 'stock-low'); ?>"
                                                        style="width: <?php echo min(100, $turnover_rate); ?>%"></div>
                                                </div>
                                                <span class="text-sm font-medium text-gray-700"><?php echo round($turnover_rate, 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="<?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $turnover_rate > 50 ? 'bolt' : ($turnover_rate > 20 ? 'chart-line' : 'hourglass-half'); ?> mr-1 text-xs"></i>
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i class="fas fa-exchange-alt text-gray-400 text-2xl"></i>
                                            </div>
                                            <p class="text-gray-500">No turnover data available</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Location-wise Inventory -->
            <div class="glass-card mx-6 my-8 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.1s">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                    <i class="fas fa-map-marked-alt text-indigo-500"></i>
                    <span>Location-wise Inventory Distribution</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if (mysqli_num_rows($location_inventory) > 0): ?>
                        <?php while ($location = mysqli_fetch_assoc($location_inventory)):
                            $value_share = ($location['total_value'] / $stats['total_value']) * 100;
                        ?>
                            <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl p-5 border border-gray-200 hover:border-indigo-200 transition">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-warehouse text-indigo-600"></i>
                                    </div>
                                    <span class="inventory-badge badge-high-value">
                                        <?php echo number_format($location['medicine_count']); ?> items
                                    </span>
                                </div>
                                <h4 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($location['location_name']); ?></h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Units:</span>
                                        <span class="text-sm font-bold text-gray-800"><?php echo number_format($location['total_units']); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Value:</span>
                                        <span class="text-sm font-bold text-green-600">Rs <?php echo number_format($location['total_value'], 2); ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                                            <span>Value Share</span>
                                            <span><?php echo round($value_share, 1); ?>%</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-high" style="width: <?php echo $value_share; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-4 text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-map-marked-alt text-gray-400 text-xl"></i>
                            </div>
                            <p class="text-gray-500">No location data available</p>
                        </div>
                    <?php endif; ?>
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

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');

            // Sample category data (in real app, fetch from PHP)
            const categoryData = [{
                    category: 'Antibiotics',
                    value: 250000
                },
                {
                    category: 'Pain Relief',
                    value: 180000
                },
                {
                    category: 'Cardiac',
                    value: 150000
                },
                {
                    category: 'Diabetes',
                    value: 120000
                },
                {
                    category: 'Vitamins',
                    value: 80000
                },
                {
                    category: 'Other',
                    value: 50000
                }
            ];

            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryData.map(item => item.category),
                    datasets: [{
                        data: categoryData.map(item => item.value),
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#6b7280'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                boxWidth: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: Rs ${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Location Chart
            const locationCtx = document.getElementById('locationChart').getContext('2d');

            // Sample location data (in real app, fetch from PHP)
            const locationData = [{
                    location: 'Main Store',
                    value: 450000,
                    units: 1200
                },
                {
                    location: 'Store Room A',
                    value: 250000,
                    units: 800
                },
                {
                    location: 'Store Room B',
                    value: 180000,
                    units: 600
                },
                {
                    location: 'Refrigerated',
                    value: 120000,
                    units: 300
                },
                {
                    location: 'Others',
                    value: 50000,
                    units: 150
                }
            ];

            const locationChart = new Chart(locationCtx, {
                type: 'bar',
                data: {
                    labels: locationData.map(item => item.location),
                    datasets: [{
                        label: 'Inventory Value (Rs )',
                        data: locationData.map(item => item.value),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Units',
                        data: locationData.map(item => item.units),
                        backgroundColor: 'rgba(245, 158, 11, 0.8)',
                        borderColor: 'rgb(245, 158, 11)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Value (Rs )'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rs ' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Units'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        });

        // Export functions
        function exportInventoryReport() {
            alert('Exporting inventory analytics report...');
            // Implement export functionality
        }

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

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
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
    </script>
</body>

</html>