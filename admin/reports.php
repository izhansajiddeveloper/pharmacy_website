<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Total stock per medicine (with category and type)
$stock_result = mysqli_query($conn, "SELECT m.name, m.generic_name, c.name as category_name, 
                                            SUM(sb.quantity) AS total_qty,
                                            AVG(sb.purchase_price) as avg_cost,
                                            SUM(sb.quantity * sb.purchase_price) as total_value
                                     FROM stock_batches sb
                                     JOIN medicines m ON sb.medicine_id = m.id
                                     LEFT JOIN medicine_categories c ON m.category_id = c.id
                                     GROUP BY m.id
                                     ORDER BY total_qty DESC
                                     LIMIT 10");

// Total stock value
$total_stock_value = mysqli_query($conn, "SELECT SUM(sb.quantity * sb.purchase_price) as total_value
                                          FROM stock_batches sb");
$stock_value = mysqli_fetch_assoc($total_stock_value);

// Sales summary
$sales_result = mysqli_query($conn, "SELECT 
                                        COUNT(*) AS total_sales, 
                                        SUM(total_amount) AS total_revenue,
                                        SUM(discount) as total_discount,
                                        AVG(total_amount) as avg_sale,
                                        MIN(sale_date) as first_sale,
                                        MAX(sale_date) as last_sale
                                     FROM sales");
$sales = mysqli_fetch_assoc($sales_result);

// Monthly sales trend
$monthly_sales = mysqli_query($conn, "SELECT 
                                        DATE_FORMAT(sale_date, '%Y-%m') as month,
                                        COUNT(*) as sales_count,
                                        SUM(total_amount) as monthly_revenue,
                                        SUM(discount) as monthly_discount
                                     FROM sales
                                     GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
                                     ORDER BY month DESC
                                     LIMIT 6");

// Top selling medicines
$top_medicines = mysqli_query($conn, "SELECT m.name, 
                                             SUM(si.quantity) as total_sold,
                                             SUM(si.quantity * si.price) as revenue,
                                             COUNT(DISTINCT s.id) as sale_count
                                      FROM sale_items si
                                      JOIN medicines m ON si.medicine_id = m.id
                                      JOIN sales s ON si.sale_id = s.id
                                      GROUP BY m.id
                                      ORDER BY total_sold DESC
                                      LIMIT 10");

// Top pharmacists
$top_pharmacists = mysqli_query($conn, "SELECT u.name, 
                                               COUNT(s.id) as sales_count,
                                               SUM(s.total_amount) as total_revenue,
                                               SUM(s.discount) as total_discount,
                                               AVG(s.total_amount) as avg_sale
                                        FROM sales s
                                        JOIN users u ON s.pharmacist_id = u.id
                                        WHERE u.role = 'pharmacist'
                                        GROUP BY u.id
                                        ORDER BY total_revenue DESC
                                        LIMIT 5");

// Expiry soon (within 30 days)
$expiry_result = mysqli_query($conn, "SELECT m.name, m.generic_name, 
                                             sb.batch_no, sb.quantity,
                                             sb.expiry_date, sb.purchase_price,
                                             DATEDIFF(sb.expiry_date, CURDATE()) as days_left
                                     FROM stock_batches sb
                                     JOIN medicines m ON sb.medicine_id = m.id
                                     WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                     ORDER BY sb.expiry_date ASC
                                     LIMIT 15");

// Low stock medicines (less than 50 units)
$low_stock = mysqli_query(
    $conn,
    "SELECT 
        m.name,
        m.generic_name,
        COALESCE(SUM(sb.quantity), 0) AS total_qty
     FROM medicines m
     JOIN stock_batches sb ON m.id = sb.medicine_id
     WHERE sb.is_expired = 0
     GROUP BY m.id, m.name, m.generic_name
     HAVING total_qty <= 50
     ORDER BY total_qty ASC
     LIMIT 10"
);


// Get report date range
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// Category-wise sales
$category_sales = mysqli_query($conn, "SELECT c.name as category_name,
                                              SUM(si.quantity) as total_sold,
                                              SUM(si.quantity * si.price) as revenue,
                                              COUNT(DISTINCT s.id) as sale_count
                                       FROM sale_items si
                                       JOIN medicines m ON si.medicine_id = m.id
                                       JOIN medicine_categories c ON m.category_id = c.id
                                       JOIN sales s ON si.sale_id = s.id
                                       WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'
                                       GROUP BY c.id
                                       ORDER BY revenue DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Reports - MediCare Pharma</title>
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
            --accent-orange: #f97316;
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

        .gradient-orange {
            background: linear-gradient(135deg, var(--accent-orange), #ea580c);
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

        .blue-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
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

        .badge-stock {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
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

        .badge-low {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .report-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .report-card:hover {
            border-left-color: var(--primary-yellow);
            transform: translateX(5px);
        }

        .metric-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .trend-up {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .trend-down {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .trend-neutral {
            background: rgba(156, 163, 175, 0.1);
            color: #6b7280;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="blue-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

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
                            Analytics <span class="gradient-text">Reports</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-chart-line text-blue-500"></i>
                            <span>Comprehensive analytics and insights for pharmacy management</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-calendar-alt text-green-500"></i>
                            <span>Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0">
                        <div class="flex space-x-3">
                            <!-- Date Range Selector -->
                            <form method="GET" class="flex items-center space-x-3">
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">From:</label>
                                    <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                                        class="px-3 py-2 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition bg-white/80 shadow-sm">
                                </div>
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">To:</label>
                                    <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                                        class="px-3 py-2 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition bg-white/80 shadow-sm">
                                </div>
                                <button type="submit"
                                    class="gradient-yellow text-white px-4 py-2 rounded-lg font-semibold hover:shadow-lg transition-all duration-300 flex items-center space-x-2 shadow">
                                    <i class="fas fa-filter"></i>
                                    <span>Apply</span>
                                </button>
                            </form>
                            <button onclick="printReport()"
                                class="px-4 py-2 border border-yellow-200 text-gray-700 rounded-lg hover:bg-yellow-50 transition font-semibold flex items-center space-x-2 shadow-sm">
                                <i class="fas fa-print text-yellow-500"></i>
                                <span>Print Report</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <!-- Total Revenue -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Revenue</span>
                            <p class="text-xs text-gray-500 mt-1">All Time</p>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($sales['total_revenue'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Revenue Generated</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-green-500">
                            <i class="fas fa-chart-line mr-1"></i>
                            <?php echo $sales['total_sales']; ?> Sales
                        </span>
                        <span class="text-red-500">
                            <i class="fas fa-tag mr-1"></i>
                            Rs <?php echo number_format($sales['total_discount'] ?: 0, 2); ?> Discount
                        </span>
                    </div>
                </div>

                <!-- Stock Value -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-boxes text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Inventory</span>
                            <p class="text-xs text-gray-500 mt-1">Current Value</p>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stock_value['total_value'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Stock Value</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-blue-500">
                            <i class="fas fa-pills mr-1"></i>
                            Multiple Medicines
                        </span>
                        <span class="text-gray-500">
                            <i class="fas fa-layer-group mr-1"></i>
                            Various Categories
                        </span>
                    </div>
                </div>

                <!-- Average Sale -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-calculator text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Average</span>
                            <p class="text-xs text-gray-500 mt-1">Per Transaction</p>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($sales['avg_sale'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Average Sale Value</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-history mr-1"></i>
                        <span>First Sale: <?php echo $sales['first_sale'] ? date('M d, Y', strtotime($sales['first_sale'])) : 'N/A'; ?></span>
                    </div>
                </div>

                <!-- Alerts Summary -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-red flex items-center justify-center shadow-lg">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <div class="flex flex-col space-y-1">
                                <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full">
                                    <?php echo mysqli_num_rows($expiry_result); ?> Expiring
                                </span>
                                <span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-full">
                                    <?php echo mysqli_num_rows($low_stock); ?> Low Stock
                                </span>
                            </div>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo mysqli_num_rows($expiry_result) + mysqli_num_rows($low_stock); ?></h3>
                    <p class="text-gray-600 mb-3">Active Alerts</p>
                    <div class="flex items-center text-sm text-red-500">
                        <i class="fas fa-bell mr-1"></i>
                        <span>Requires attention</span>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-6">
                <!-- Sales Trend Chart -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-bar text-blue-500"></i>
                        <span>Monthly Sales Trend</span>
                    </h3>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Category Sales Chart -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-pie text-purple-500"></i>
                        <span>Category-wise Revenue</span>
                    </h3>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Performers Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-6">
                <!-- Top Selling Medicines -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-trophy text-yellow-500"></i>
                            <span>Top Selling Medicines</span>
                        </h3>
                        <span class="text-sm text-gray-500">Last 30 Days</span>
                    </div>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($top_medicines) > 0): ?>
                            <?php $rank = 1; ?>
                            <?php while ($medicine = mysqli_fetch_assoc($top_medicines)): ?>
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-yellow-50 to-white rounded-xl border border-yellow-100 hover:border-yellow-200 transition report-card">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                            <span class="text-yellow-600 font-bold"><?php echo $rank++; ?></span>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($medicine['name']); ?></h4>
                                            <div class="flex items-center space-x-3 mt-1">
                                                <span class="metric-badge trend-up">
                                                    <i class="fas fa-box mr-1"></i>
                                                    <?php echo number_format($medicine['total_sold']); ?> units
                                                </span>
                                                <span class="metric-badge trend-neutral">
                                                    <i class="fas fa-receipt mr-1"></i>
                                                    <?php echo $medicine['sale_count']; ?> sales
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-green-600">Rs <?php echo number_format($medicine['revenue'], 2); ?></p>
                                        <p class="text-xs text-gray-500">revenue</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-pills text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No sales data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Pharmacists -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span>Top Performing Pharmacists</span>
                        </h3>
                        <span class="text-sm text-gray-500">By Revenue</span>
                    </div>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($top_pharmacists) > 0): ?>
                            <?php while ($pharmacist = mysqli_fetch_assoc($top_pharmacists)): ?>
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-white rounded-xl border border-blue-100 hover:border-blue-200 transition report-card">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user-md text-blue-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($pharmacist['name']); ?></h4>
                                            <div class="flex items-center space-x-3 mt-1">
                                                <span class="metric-badge trend-up">
                                                    <i class="fas fa-receipt mr-1"></i>
                                                    <?php echo number_format($pharmacist['sales_count']); ?> sales
                                                </span>
                                                <span class="metric-badge trend-down">
                                                    <i class="fas fa-tag mr-1"></i>
                                                    Rs <?php echo number_format($pharmacist['total_discount'], 2); ?> discount
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-blue-600">Rs <?php echo number_format($pharmacist['total_revenue'], 2); ?></p>
                                        <p class="text-xs text-gray-500">Avg: Rs <?php echo number_format($pharmacist['avg_sale'], 2); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-user-md text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No pharmacist data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Inventory Alerts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-6">
                <!-- Expiring Soon -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.9s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-clock text-orange-500"></i>
                            <span>Expiring Soon (30 Days)</span>
                        </h3>
                        <span class="badge-expiry">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?php echo mysqli_num_rows($expiry_result); ?> Items
                        </span>
                    </div>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($expiry_result) > 0): ?>
                            <?php mysqli_data_seek($expiry_result, 0); ?>
                            <?php while ($expiry = mysqli_fetch_assoc($expiry_result)):
                                $days_left = $expiry['days_left'];
                                $expiry_class = $days_left <= 7 ? 'border-red-200 bg-red-50' : 'border-orange-200 bg-orange-50';
                            ?>
                                <div class="flex items-center justify-between p-4 rounded-xl border <?php echo $expiry_class; ?> hover:border-orange-300 transition">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 rounded-lg <?php echo $days_left <= 7 ? 'bg-red-100' : 'bg-orange-100'; ?> flex items-center justify-center">
                                            <i class="fas <?php echo $days_left <= 7 ? 'fa-exclamation text-red-600' : 'fa-clock text-orange-600'; ?>"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($expiry['name']); ?></h4>
                                            <p class="text-sm text-gray-500">Batch: <?php echo htmlspecialchars($expiry['batch_no']); ?></p>
                                            <div class="flex items-center space-x-3 mt-1">
                                                <span class="text-xs <?php echo $days_left <= 7 ? 'text-red-600' : 'text-orange-600'; ?> font-medium">
                                                    <i class="fas fa-box mr-1"></i>
                                                    <?php echo number_format($expiry['quantity']); ?> units
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    Value: Rs <?php echo number_format($expiry['quantity'] * $expiry['purchase_price'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold <?php echo $days_left <= 7 ? 'text-red-600' : 'text-orange-600'; ?>">
                                            <?php echo date('M d, Y', strtotime($expiry['expiry_date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo $days_left; ?> days left
                                        </p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-check text-green-600 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No medicines expiring soon</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.0s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                            <span>Low Stock Alert</span>
                        </h3>
                        <span class="badge-low">
                            <i class="fas fa-arrow-down mr-1"></i>
                            ≤ 50 Units
                        </span>
                    </div>
                    <div class="space-y-4">
                        <?php while ($low = mysqli_fetch_assoc($low_stock)): ?>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800">
                                    <?php echo htmlspecialchars($low['name']); ?>
                                </h4>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($low['generic_name'] ?? 'N/A'); ?>
                                </p>
                                <p class="text-xs text-red-600 font-semibold">
                                    Qty: <?php echo (int)$low['total_qty']; ?>
                                </p>
                            </div>
                        <?php endwhile; ?>

                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-check text-green-600 text-xl"></i>
                            </div>
                            <p class="text-gray-500">All medicines have sufficient stock</p>
                        </div>
                        <?php  ?>
                    </div>
                </div>
            </div>

            <!-- Detailed Reports -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 1.1s">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-blue-25">
                    <h3 class="text-lg font-semibold text-gray-800">Stock Summary Report</h3>
                    <p class="text-sm text-gray-600">Top 10 medicines by stock quantity</p>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-blue-50 to-blue-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Medicine</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Stock Quantity</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Avg Cost</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Value</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            <?php if (mysqli_num_rows($stock_result) > 0): ?>
                                <?php mysqli_data_seek($stock_result, 0); ?>
                                <?php while ($stock = mysqli_fetch_assoc($stock_result)):
                                    $status = $stock['total_qty'] <= 50 ? 'Low' : ($stock['total_qty'] <= 100 ? 'Medium' : 'Good');
                                    $status_class = $stock['total_qty'] <= 50 ? 'badge-low' : ($stock['total_qty'] <= 100 ? 'badge-expiry' : 'badge-stock');
                                ?>
                                    <tr class="table-row hover:bg-blue-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                                    <i class="fas fa-pills text-blue-600 text-sm"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($stock['name']); ?></h4>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($stock['generic_name']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($stock['category_name'] ?: 'Uncategorized'); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-center">
                                                <span class="text-lg font-bold text-blue-600"><?php echo number_format($stock['total_qty']); ?></span>
                                                <p class="text-xs text-gray-500">units</p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-medium text-gray-700">Rs <?php echo number_format($stock['avg_cost'], 2); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-lg font-bold text-green-600">Rs <?php echo number_format($stock['total_value'], 2); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="<?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $status === 'Low' ? 'exclamation-triangle' : ($status === 'Medium' ? 'clock' : 'check'); ?> mr-1 text-xs"></i>
                                                <?php echo $status; ?> Stock
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-boxes text-blue-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Stock Data Available</h4>
                                            <p class="text-gray-600 mb-6">Stock information will appear here once inventory is added.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 border-t border-blue-100 bg-gradient-to-r from-blue-50 to-blue-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo min(10, $total_tock_items); ?> medicines by stock quantity •
                                <span class="font-medium text-blue-600">Last Updated: <?php echo date('M d, Y H:i'); ?></span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="exportStockReport()"
                                class="px-4 py-2 border border-blue-200 rounded-lg hover:bg-blue-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-excel text-green-500"></i>
                                <span class="text-sm text-gray-700">Export Stock Report</span>
                            </button>
                            <button onclick="exportFullPDFReport()"
                                class="px-4 py-2 border border-blue-200 rounded-lg hover:bg-blue-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-pdf text-red-500"></i>
                                <span class="text-sm text-gray-700">Full PDF Report</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Performance -->
            <div class="glass-card mx-6 my-8 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.2s">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                        <i class="fas fa-tags text-green-500"></i>
                        <span>Category Performance Report</span>
                    </h3>
                    <span class="text-sm text-gray-500">Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if (mysqli_num_rows($category_sales) > 0): ?>
                        <?php mysqli_data_seek($category_sales, 0); ?>
                        <?php while ($category = mysqli_fetch_assoc($category_sales)): ?>
                            <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl p-5 border border-gray-200 hover:border-green-200 transition">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-tag text-green-600"></i>
                                    </div>
                                    <span class="metric-badge trend-up">
                                        <?php echo number_format($category['sale_count']); ?> sales
                                    </span>
                                </div>
                                <h4 class="font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($category['category_name']); ?></h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Units Sold:</span>
                                        <span class="text-sm font-bold text-gray-800"><?php echo number_format($category['total_sold']); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Revenue:</span>
                                        <span class="text-sm font-bold text-green-600">Rs <?php echo number_format($category['revenue'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-4 text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-tags text-gray-400 text-xl"></i>
                            </div>
                            <p class="text-gray-500">No category sales data for selected date range</p>
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
            // Sales Trend Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');

            // Get monthly sales data from PHP (this would typically come from AJAX)
            const monthlyData = [{
                    month: 'Jan',
                    revenue: 45000,
                    discount: 500
                },
                {
                    month: 'Feb',
                    revenue: 52000,
                    discount: 800
                },
                {
                    month: 'Mar',
                    revenue: 48000,
                    discount: 600
                },
                {
                    month: 'Apr',
                    revenue: 55000,
                    discount: 700
                },
                {
                    month: 'May',
                    revenue: 60000,
                    discount: 900
                },
                {
                    month: 'Jun',
                    revenue: 58000,
                    discount: 750
                }
            ];

            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(item => item.month),
                    datasets: [{
                        label: 'Revenue',
                        data: monthlyData.map(item => item.revenue),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Discount',
                        data: monthlyData.map(item => item.discount),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
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
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += 'Rs ' + context.parsed.y.toLocaleString();
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rs ' + value.toLocaleString();
                                }
                            },
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');

            // Category data from PHP (this would typically come from AJAX)
            const categoryData = [{
                    category: 'Antibiotics',
                    revenue: 25000
                },
                {
                    category: 'Pain Relief',
                    revenue: 18000
                },
                {
                    category: 'Cardiac',
                    revenue: 15000
                },
                {
                    category: 'Diabetes',
                    revenue: 12000
                },
                {
                    category: 'Vitamins',
                    revenue: 8000
                },
                {
                    category: 'Other',
                    revenue: 5000
                }
            ];

            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryData.map(item => item.category),
                    datasets: [{
                        data: categoryData.map(item => item.revenue),
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

            // Update charts on date range change
            document.querySelector('form').addEventListener('submit', function(e) {
                // In a real app, you would fetch new data via AJAX here
                console.log('Date range changed, fetching new data...');
            });
        });

        // Export functions
        function printReport() {
            window.print();
        }

        function exportStockReport() {
            alert('Exporting stock report to Excel...');
            // Implement stock report export
        }

        function exportFullReport() {
            alert('Exporting full PDF report...');
            // Implement full report export
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printReport();
            }

            // Ctrl/Cmd + E for export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportFullReport();
            }
        });
    </script>
</body>

</html>