<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacist can access
if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$pharmacist_id = $_SESSION['user_id'];

// Get filter parameters
$sale_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'all', 'regular', 'wholesale'
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build base query
$query = "SELECT s.*, 
                 i.customer_name,
                 COUNT(si.id) as items_count,
                 SUM(si.quantity) as total_quantity
          FROM sales s
          JOIN invoices i ON s.id = i.sale_id
          LEFT JOIN sale_items si ON s.id = si.sale_id
          WHERE s.pharmacist_id = $pharmacist_id";

// Add filters
if ($sale_type === 'regular') {
    $query .= " AND i.customer_name = 'Regular Customer'";
} elseif ($sale_type === 'wholesale') {
    $query .= " AND i.customer_name != 'Regular Customer'";
}

if (!empty($search_query)) {
    $query .= " AND (s.invoice_no LIKE '%$search_query%' OR i.customer_name LIKE '%$search_query%')";
}

if (!empty($date_filter)) {
    $query .= " AND DATE(s.sale_date) = '$date_filter'";
}

$query .= " GROUP BY s.id ORDER BY s.sale_date DESC";

$result = mysqli_query($conn, $query);
$total_sales = mysqli_num_rows($result);

// Get total counts for each type
$count_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN i.customer_name = 'Regular Customer' THEN 1 ELSE 0 END) as regular_count,
    SUM(CASE WHEN i.customer_name != 'Regular Customer' THEN 1 ELSE 0 END) as wholesale_count
    FROM sales s
    JOIN invoices i ON s.id = i.sale_id
    WHERE s.pharmacist_id = $pharmacist_id";

$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);

$regular_stats_query = mysqli_query(
    $conn,
    "SELECT 
        COUNT(*) AS regular_transactions,
        SUM(s.total_amount) AS regular_revenue,
        AVG(s.total_amount) AS regular_avg_sale_value,
        SUM(s.discount) AS regular_total_discount
     FROM sales s
     JOIN invoices i ON s.id = i.sale_id
     WHERE s.pharmacist_id = $pharmacist_id 
     AND i.customer_name = 'Regular Customer'"
);
$regular_stats = mysqli_fetch_assoc($regular_stats_query);

// Get pharmacist's sales statistics for wholesale sales
$wholesale_stats_query = mysqli_query(
    $conn,
    "SELECT 
        COUNT(*) AS wholesale_transactions,
        SUM(s.total_amount) AS wholesale_revenue,
        AVG(s.total_amount) AS wholesale_avg_sale_value,
        SUM(s.discount) AS wholesale_total_discount
     FROM sales s
     JOIN invoices i ON s.id = i.sale_id
     WHERE s.pharmacist_id = $pharmacist_id 
     AND i.customer_name != 'Regular Customer'"
);
$wholesale_stats = mysqli_fetch_assoc($wholesale_stats_query);


// Get today's sales
$today_sales = mysqli_query(
    $conn,
    "SELECT 
        SUM(s.total_amount) AS today_total, 
        SUM(s.discount) AS today_discount,
        COUNT(*) AS today_count,
        SUM(CASE WHEN i.customer_name = 'Regular Customer' THEN 1 ELSE 0 END) AS today_regular_count,
        SUM(CASE WHEN i.customer_name != 'Regular Customer' THEN 1 ELSE 0 END) AS today_wholesale_count
     FROM sales s
     JOIN invoices i ON s.id = i.sale_id
     WHERE s.pharmacist_id = $pharmacist_id 
     AND DATE(s.sale_date) = CURDATE()"
);
$today = mysqli_fetch_assoc($today_sales);


// Get recent sales
$recent_sales = mysqli_query(
    $conn,
    "SELECT s.*, i.customer_name
     FROM sales s
     JOIN invoices i ON s.id = i.sale_id
     WHERE s.pharmacist_id = $pharmacist_id
     ORDER BY s.sale_date DESC
     LIMIT 5"
);

// Get top selling medicines for this pharmacist
$top_medicines = mysqli_query(
    $conn,
    "SELECT m.name, SUM(si.quantity) as total_sold, SUM(si.quantity * si.price) as revenue
     FROM sale_items si
     JOIN medicines m ON si.medicine_id = m.id
     JOIN sales s ON si.sale_id = s.id
     JOIN invoices i ON s.id = i.sale_id
     WHERE s.pharmacist_id = $pharmacist_id
     GROUP BY m.id
     ORDER BY total_sold DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sales - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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

        .gradient-blue {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
        }

        .gradient-green {
            background: linear-gradient(135deg, var(--accent-green), #059669);
        }

        .gradient-purple {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
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

        .badge-regular {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-wholesale {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-invoice {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .tab-active {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="blue-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            My <span class="gradient-text">Sales</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span>View and manage your sales transactions</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-edit text-green-500"></i>
                            <span>Full Management Access</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="create_sale.php"
                            class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-users"></i>
                            <span>Wholesale Sale</span>
                        </a>
                        <a href="create_regular_sale.php"
                            class="gradient-purple text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-plus"></i>
                            <span>Regular Sale</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sales Type Tabs -->
            <div class="mx-6 my-6">
                <div class="glass-card rounded-2xl p-2">
                    <div class="flex space-x-2">
                        <a href="sales.php?type=all<?php echo !empty($date_filter) ? "&date=$date_filter" : ''; ?><?php echo !empty($search_query) ? "&search=$search_query" : ''; ?>"
                            class="flex-1 px-4 py-3 text-center rounded-xl font-medium transition-all duration-300 <?php echo $sale_type === 'all' ? 'tab-active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                            <i class="fas fa-layer-group mr-2"></i>
                            All Sales (<?php echo $count_data['total'] ?: 0; ?>)
                        </a>
                        <a href="sales.php?type=regular<?php echo !empty($date_filter) ? "&date=$date_filter" : ''; ?><?php echo !empty($search_query) ? "&search=$search_query" : ''; ?>"
                            class="flex-1 px-4 py-3 text-center rounded-xl font-medium transition-all duration-300 <?php echo $sale_type === 'regular' ? 'tab-active' : 'bg-green-50 text-green-700 hover:bg-green-100'; ?>">
                            <i class="fas fa-user mr-2"></i>
                            Regular (<?php echo $count_data['regular_count'] ?: 0; ?>)
                        </a>
                        <a href="sales.php?type=wholesale<?php echo !empty($date_filter) ? "&date=$date_filter" : ''; ?><?php echo !empty($search_query) ? "&search=$search_query" : ''; ?>"
                            class="flex-1 px-4 py-3 text-center rounded-xl font-medium transition-all duration-300 <?php echo $sale_type === 'wholesale' ? 'tab-active' : 'bg-purple-50 text-purple-700 hover:bg-purple-100'; ?>">
                            <i class="fas fa-users mr-2"></i>
                            Wholesale (<?php echo $count_data['wholesale_count'] ?: 0; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <!-- Today's Sales -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-calendar-day text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Today</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($today['today_count'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Today's Sales</p>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Reg: <?php echo $today['today_regular_count'] ?: 0; ?></span>
                        <span class="text-purple-600">Whole: <?php echo $today['today_wholesale_count'] ?: 0; ?></span>
                    </div>
                </div>

                <!-- Regular Sales Stats -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-user text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Regular</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($regular_stats['regular_revenue'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Regular Sales Revenue</p>
                    <div class="flex items-center text-sm text-green-500">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>Avg: Rs <?php echo number_format($regular_stats['regular_avg_sale_value'] ?: 0, 2); ?></span>
                    </div>
                </div>

                <!-- Wholesale Sales Stats -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Wholesale</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($wholesale_stats['wholesale_revenue'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Wholesale Sales Revenue</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>Avg: Rs <?php echo number_format($wholesale_stats['wholesale_avg_sale_value'] ?: 0, 2); ?></span>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-teal-500 to-teal-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format(($regular_stats['regular_revenue'] + $wholesale_stats['wholesale_revenue']), 2); ?></h3>
                    <p class="text-gray-600 mb-3">Combined Revenue</p>
                    <div class="flex items-center text-sm text-teal-500">
                        <i class="fas fa-percentage mr-1"></i>
                        <span>Discount: Rs <?php echo number_format(($regular_stats['regular_total_discount'] + $wholesale_stats['wholesale_total_discount']), 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="px-6 py-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-blue-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <?php
                            echo $sale_type === 'all' ? 'All Sales' : ($sale_type === 'regular' ? 'Regular Sales' : 'Wholesale Sales');
                            ?>
                        </h3>
                        <p class="text-sm text-gray-600">Showing <?php echo $total_sales; ?> sales records</p>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center space-y-3 md:space-y-0 md:space-x-4">
                        <form method="GET" class="flex items-center space-x-2">
                            <input type="hidden" name="type" value="<?php echo $sale_type; ?>">
                            <div class="relative">
                                <input type="text"
                                    name="search"
                                    value="<?php echo htmlspecialchars($search_query); ?>"
                                    placeholder="Search invoice or customer..."
                                    class="pl-10 pr-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm w-full md:w-64">
                                <i class="fas fa-search absolute left-3 top-3 text-blue-400"></i>
                            </div>

                            <input type="date"
                                name="date"
                                value="<?php echo htmlspecialchars($date_filter); ?>"
                                class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm">

                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                Filter
                            </button>

                            <?php if (!empty($search_query) || !empty($date_filter)): ?>
                                <a href="sales.php?type=<?php echo $sale_type; ?>"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition font-medium">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-blue-50 to-blue-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Invoice & Customer
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Sale Items
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Payment Details
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Date & Time
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            <?php if ($total_sales > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    $sale_date = new DateTime($row['sale_date']);
                                    $net_amount = $row['total_amount'] - $row['discount'];
                                    $is_regular = $row['customer_name'] === 'Regular Customer';
                                ?>
                                    <tr class="table-row hover:bg-blue-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl <?php echo $is_regular ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-purple-500 to-purple-600'; ?> flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas <?php echo $is_regular ? 'fa-user' : 'fa-users'; ?> text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-800 text-lg mb-1">
                                                        <span class="badge-invoice mr-2">
                                                            <i class="fas fa-hashtag mr-1 text-xs"></i>
                                                            <?php echo htmlspecialchars($row['invoice_no']); ?>
                                                        </span>
                                                    </h4>
                                                    <div class="flex items-center space-x-2 mt-2">
                                                        <?php if ($is_regular): ?>
                                                            <span class="badge-regular">
                                                                <i class="fas fa-user mr-1 text-xs"></i>
                                                                Regular Sale
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge-wholesale">
                                                                <i class="fas fa-users mr-1 text-xs"></i>
                                                                Wholesale Sale
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!$is_regular): ?>
                                                        <p class="text-sm text-gray-600 mt-2">
                                                            <i class="fas fa-user-tag mr-1"></i>
                                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Items Count</span>
                                                    <span class="text-sm font-bold text-purple-600">
                                                        <?php echo number_format($row['items_count'] ?: 0); ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Total Quantity</span>
                                                    <span class="text-sm font-bold text-green-600">
                                                        <?php echo number_format($row['total_quantity'] ?: 0); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Total Amount</span>
                                                    <span class="text-sm font-bold <?php echo $is_regular ? 'text-green-600' : 'text-purple-600'; ?>">
                                                        Rs <?php echo number_format($row['total_amount'], 2); ?>
                                                    </span>
                                                </div>
                                                <?php if ($row['discount'] > 0): ?>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Discount</span>
                                                        <span class="text-sm font-bold text-red-600">
                                                            -Rs <?php echo number_format($row['discount'], 2); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Net Amount</span>
                                                        <span class="text-sm font-bold text-blue-600">
                                                            Rs <?php echo number_format($net_amount, 2); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex items-center space-x-2 text-sm <?php echo $row['payment_method'] === 'Cash' ? 'text-green-600' : 'text-blue-600'; ?>">
                                                    <i class="fas <?php echo $row['payment_method'] === 'Cash' ? 'fa-money-bill-wave' : 'fa-credit-card'; ?>"></i>
                                                    <span><?php echo ucfirst($row['payment_method']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="text-sm font-medium text-gray-800"><?php echo $sale_date->format('M d, Y'); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo $sale_date->format('h:i A'); ?></div>
                                                <div class="text-xs <?php echo $is_regular ? 'text-green-500' : 'text-purple-500'; ?>">
                                                    <i class="fas <?php echo $is_regular ? 'fa-user' : 'fa-users'; ?>"></i>
                                                    <?php echo $is_regular ? 'Regular' : 'Wholesale'; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col space-y-2">
                                                <a href="view_sale.php?id=<?php echo $row['id']; ?>"
                                                    class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-eye text-sm"></i>
                                                    <span class="text-sm font-medium">View</span>
                                                </a>

                                                <div class="flex space-x-2">
                                                    <a href="edit_sale.php?id=<?php echo $row['id']; ?>"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-edit text-xs"></i>
                                                        <span class="text-xs font-medium">Edit</span>
                                                    </a>

                                                    <button onclick="showDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['invoice_no']); ?>')"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-trash-alt text-xs"></i>
                                                        <span class="text-xs font-medium">Delete</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 <?php echo $sale_type === 'regular' ? 'bg-green-100' : ($sale_type === 'wholesale' ? 'bg-purple-100' : 'bg-blue-100'); ?> rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-shopping-cart <?php echo $sale_type === 'regular' ? 'text-green-400' : ($sale_type === 'wholesale' ? 'text-purple-400' : 'text-blue-400'); ?> text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No <?php echo $sale_type === 'all' ? 'Sales' : ($sale_type === 'regular' ? 'Regular Sales' : 'Wholesale Sales'); ?> Found</h4>
                                            <p class="text-gray-600 mb-6">
                                                <?php if (!empty($search_query) || !empty($date_filter)): ?>
                                                    No sales match your search criteria.
                                                <?php else: ?>
                                                    You haven't made any <?php echo $sale_type === 'regular' ? 'regular' : ($sale_type === 'wholesale' ? 'wholesale' : ''); ?> sales yet.
                                                <?php endif; ?>
                                            </p>
                                            <div class="flex space-x-3 justify-center">
                                                <a href="create_sale.php"
                                                    class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                    <i class="fas fa-user"></i>
                                                    <span>New Regular Sale</span>
                                                </a>
                                                <a href="create_sale_wholesale.php"
                                                    class="gradient-purple text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                    <i class="fas fa-users"></i>
                                                    <span>New Wholesale Sale</span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-blue-100 bg-gradient-to-r from-blue-50 to-blue-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo $total_sales; ?> sales •
                                <span class="font-medium <?php echo $sale_type === 'regular' ? 'text-green-600' : ($sale_type === 'wholesale' ? 'text-purple-600' : 'text-blue-600'); ?>">
                                    <?php echo $sale_type === 'all' ? 'All Sales' : ($sale_type === 'regular' ? 'Regular Sales' : 'Wholesale Sales'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="exportSalesToExcel('<?php echo $sale_type; ?>')"
                                class="px-4 py-2 border border-blue-200 rounded-lg hover:bg-blue-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-excel text-green-500"></i>
                                <span class="text-sm text-gray-700">Export Excel</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-8">
                <!-- Recent Sales -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-history text-blue-500"></i>
                        <span>Recent Sales</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($recent_sales) > 0): ?>
                            <?php while ($recent = mysqli_fetch_assoc($recent_sales)):
                                $is_recent_regular = $recent['customer_name'] === 'Regular Customer';
                            ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r <?php echo $is_recent_regular ? 'from-green-50' : 'from-purple-50'; ?> to-white rounded-lg border <?php echo $is_recent_regular ? 'border-green-100' : 'border-purple-100'; ?> hover:<?php echo $is_recent_regular ? 'border-green-200' : 'border-purple-200'; ?> transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg <?php echo $is_recent_regular ? 'bg-green-100' : 'bg-purple-100'; ?> flex items-center justify-center">
                                            <i class="fas <?php echo $is_recent_regular ? 'fa-user text-green-600' : 'fa-users text-purple-600'; ?> text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($recent['invoice_no']); ?></h4>
                                            <p class="text-xs <?php echo $is_recent_regular ? 'text-green-500' : 'text-purple-500'; ?>">
                                                <i class="fas <?php echo $is_recent_regular ? 'fa-user' : 'fa-users'; ?> mr-1"></i>
                                                <?php echo $is_recent_regular ? 'Regular' : htmlspecialchars($recent['customer_name']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold <?php echo $is_recent_regular ? 'text-green-600' : 'text-purple-600'; ?>">Rs <?php echo number_format($recent['total_amount'], 2); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('M d, h:i A', strtotime($recent['sale_date'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-gray-500">No recent sales</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Medicines -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-line text-green-500"></i>
                        <span>Your Top Selling Medicines</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($top_medicines) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php while ($medicine = mysqli_fetch_assoc($top_medicines)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-green-50 to-white rounded-lg border border-green-100 hover:border-green-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                            <span class="text-green-600 font-bold text-sm"><?php echo $counter++; ?></span>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($medicine['name']); ?></h4>
                                            <p class="text-xs text-gray-500">Units sold: <?php echo number_format($medicine['total_sold'] ?: 0); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-green-600">Rs <?php echo number_format($medicine['revenue'] ?: 0, 2); ?></p>
                                        <p class="text-xs text-gray-400">revenue</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-pills text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No sales data available</p>
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
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Sale</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete sale <span id="deleteSaleInvoice" class="font-semibold text-blue-600"></span>?
                    This will also delete all associated sale items. This action cannot be undone.
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a id="deleteConfirmLink"
                        href="#"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                        Delete Sale
                    </a>
                </div>
            </div>
        </div>
    </div>

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
        function showDeleteModal(id, invoiceNo) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteSaleInvoice').textContent = invoiceNo;
            document.getElementById('deleteConfirmLink').href = `delete_sale.php?id=${id}`;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideDeleteModal();
                }
            });
        }

        // Export sales to Excel based on type
        function exportSalesToExcel(type) {
            try {
                const rows = [];
                let filename = '';

                if (type === 'regular') {
                    rows.push(['Regular Sales Report']);
                    filename = 'Regular_Sales_Report_';
                } else if (type === 'wholesale') {
                    rows.push(['Wholesale Sales Report']);
                    filename = 'Wholesale_Sales_Report_';
                } else {
                    rows.push(['All Sales Report']);
                    filename = 'All_Sales_Report_';
                }

                rows.push([]); // Empty row for spacing
                rows.push(['Invoice No', 'Customer', 'Date', 'Time', 'Sale Type', 'Items Count', 'Total Quantity', 'Total Amount', 'Discount', 'Net Amount', 'Payment Method']);

                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                    $sale_date = new DateTime($row['sale_date']);
                    $net_amount = $row['total_amount'] - $row['discount'];
                    $is_regular = $row['customer_name'] === 'Regular Customer';
                ?>
                    rows.push([
                        '<?php echo $row['invoice_no']; ?>',
                        '<?php echo htmlspecialchars($row['customer_name']); ?>',
                        '<?php echo $sale_date->format('Y-m-d'); ?>',
                        '<?php echo $sale_date->format('H:i:s'); ?>',
                        '<?php echo $is_regular ? 'Regular' : 'Wholesale'; ?>',
                        <?php echo $row['items_count'] ?: 0; ?>,
                        <?php echo $row['total_quantity'] ?: 0; ?>,
                        <?php echo $row['total_amount']; ?>,
                        <?php echo $row['discount']; ?>,
                        <?php echo $net_amount; ?>,
                        '<?php echo $row['payment_method']; ?>'
                    ]);
                <?php endwhile; ?>

                const ws = XLSX.utils.aoa_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Sales Data');

                const today = new Date().toISOString().slice(0, 10);
                XLSX.writeFile(wb, `${filename}${today}.xlsx`);

                showNotification('Excel file exported successfully!', 'success');
            } catch (error) {
                console.error('Excel export error:', error);
                showNotification('Error exporting Excel file', 'error');
            }
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
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'create_sale.php';
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'w') {
                e.preventDefault();
                window.location.href = 'create_sale_wholesale.php';
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                window.location.href = 'sales.php';
            }
        });
    </script>
</body>

</html>