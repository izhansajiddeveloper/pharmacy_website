<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch all sales with pharmacist details and sale items count
$query = "SELECT s.*, u.name AS pharmacist_name, 
                 COUNT(si.id) as items_count,
                 SUM(si.quantity) as total_quantity
          FROM sales s
          LEFT JOIN users u ON s.pharmacist_id = u.id
          LEFT JOIN sale_items si ON s.id = si.sale_id
          GROUP BY s.id
          ORDER BY s.sale_date DESC";

$result = mysqli_query($conn, $query);
$total_sales = mysqli_num_rows($result);

// Get sales statistics
$stats_query = mysqli_query(
    $conn,
    "SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_sale_value,
        SUM(discount) as total_discount,
        COUNT(DISTINCT DATE(sale_date)) as sales_days,
        COUNT(DISTINCT pharmacist_id) as active_pharmacists,
        SUM(CASE WHEN payment_method = 'Cash' THEN 1 ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 'Online' THEN 1 ELSE 0 END) as online_sales
     FROM sales"
);
$stats = mysqli_fetch_assoc($stats_query);

// Get today's sales
$today_sales = mysqli_query(
    $conn,
    "SELECT SUM(total_amount) as today_total, 
            SUM(discount) as today_discount,
            COUNT(*) as today_count
     FROM sales 
     WHERE DATE(sale_date) = CURDATE()"
);
$today = mysqli_fetch_assoc($today_sales);

// Get recent sales (last 5)
$recent_sales = mysqli_query(
    $conn,
    "SELECT s.*, u.name as pharmacist_name
     FROM sales s
     LEFT JOIN users u ON s.pharmacist_id = u.id
     ORDER BY s.sale_date DESC
     LIMIT 5"
);

// Get top selling medicines
$top_medicines = mysqli_query(
    $conn,
    "SELECT m.name, SUM(si.quantity) as total_sold, SUM(si.quantity * si.price) as revenue
     FROM sale_items si
     JOIN medicines m ON si.medicine_id = m.id
     GROUP BY m.id
     ORDER BY total_sold DESC
     LIMIT 5"
);

// Get top pharmacists
$top_pharmacists = mysqli_query(
    $conn,
    "SELECT u.name, COUNT(s.id) as sales_count, 
            SUM(s.total_amount) as total_revenue,
            SUM(s.discount) as total_discount_given
     FROM sales s
     JOIN users u ON s.pharmacist_id = u.id
     WHERE u.role = 'pharmacist'
     GROUP BY u.id
     ORDER BY total_revenue DESC
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- SheetJS for Excel export -->
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

        .purple-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
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

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-completed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-cancelled {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-invoice {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .status-completed {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .status-cancelled {
            background: linear-gradient(135deg, #ef4444, #dc2626);
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
    <div class="purple-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "siderbar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden ">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Sales <span class="gradient-text">Management</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-shopping-cart text-purple-500"></i>
                            <span>View all sales transactions and revenue reports</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-shield text-blue-500"></i>
                            <span>Admin View Only Access</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="reports.php"
                            class="gradient-purple text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-chart-line"></i>
                            <span>Generate Reports</span>
                            <i class="fas fa-arrow-right text-purple-100 text-sm"></i>
                        </a>
                        <button onclick="exportAllToPDF()"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-file-export text-yellow-500"></i>
                            <span>Export Data</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Revenue</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['total_revenue'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-green h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-receipt text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Today</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($today['today_count'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Today's Transactions</p>
                    <div class="flex items-center text-sm text-blue-500">
                        <i class="fas fa-rupee-sign mr-1"></i>
                        <span>Rs <?php echo number_format($today['today_total'] ?: 0, 2); ?> today</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-credit-card text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1 text-right">
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Cash: <?php echo $stats['cash_sales'] ?: 0; ?></span>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Online: <?php echo $stats['online_sales'] ?: 0; ?></span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format(($stats['total_revenue'] ?: 0) - ($stats['total_discount'] ?: 0), 2); ?></h3>
                    <p class="text-gray-600 mb-3">Net Revenue</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-tag mr-1"></i>
                        <span>Discount: Rs <?php echo number_format($stats['total_discount'] ?: 0, 2); ?></span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Active</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['active_pharmacists'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Active Pharmacists</p>
                    <div class="flex items-center text-sm text-yellow-500">
                        <i class="fas fa-calendar-day mr-1"></i>
                        <span><?php echo $stats['sales_days'] ?: 0; ?> sales days</span>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-purple-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">All Sales Transactions</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo $total_sales; ?> sales records</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search by invoice, pharmacist, or amount..."
                                class="pl-10 pr-4 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-purple-400"></i>
                        </div>

                        <!-- Date Filter -->
                        <input type="date"
                            id="dateFilter"
                            class="px-4 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500 focus:outline-none transition bg-white/80 shadow-sm">

                        <!-- Payment Method Filter -->
                        <select id="paymentFilter" class="px-4 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-200 focus:border-purple-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="">All Payments</option>
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-purple-50 to-purple-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-2">
                                        <span>Invoice Details</span>
                                        <i class="fas fa-sort text-purple-400 cursor-pointer hover:text-purple-600"></i>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Pharmacist
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
                        <tbody class="divide-y divide-purple-50">
                            <?php if ($total_sales > 0): ?>
                                <?php
                                mysqli_data_seek($result, 0);
                                while ($row = mysqli_fetch_assoc($result)):
                                    // Format dates
                                    $sale_date = new DateTime($row['sale_date']);
                                    $formatted_date = $sale_date->format('M d, Y');
                                    $formatted_time = $sale_date->format('h:i A');

                                    // Calculate net amount
                                    $net_amount = $row['total_amount'] - $row['discount'];

                                    // Determine payment method class
                                    $payment_class = $row['payment_method'] === 'Cash' ? 'text-green-600' : 'text-blue-600';
                                    $payment_icon = $row['payment_method'] === 'Cash' ? 'fa-money-bill-wave' : 'fa-credit-card';
                                ?>
                                    <tr class="table-row hover:bg-purple-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas fa-receipt text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-800 text-lg mb-1">
                                                        <span class="badge-invoice mr-2">
                                                            <i class="fas fa-hashtag mr-1 text-xs"></i>
                                                            <?php echo htmlspecialchars($row['invoice_no']); ?>
                                                        </span>
                                                    </h4>
                                                    <p class="text-sm text-gray-500 mb-2">
                                                        Sale ID: <span class="font-mono"><?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                    </p>
                                                    <span class="badge-completed">
                                                        <i class="fas fa-check mr-1 text-xs"></i>
                                                        Completed
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <i class="fas fa-user-md text-blue-600 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="font-medium text-gray-800"><?php echo htmlspecialchars($row['pharmacist_name'] ?: 'Unknown'); ?></h5>
                                                        <p class="text-xs text-gray-500">ID: <?php echo $row['pharmacist_id']; ?></p>
                                                    </div>
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
                                                <div class="text-xs text-gray-500">
                                                    <i class="fas fa-box mr-1"></i>
                                                    Various medicines sold
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Total Amount</span>
                                                    <span class="text-sm font-bold text-green-600">
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
                                                <div class="flex items-center space-x-2 text-sm <?php echo $payment_class; ?>">
                                                    <i class="fas <?php echo $payment_icon; ?>"></i>
                                                    <span><?php echo ucfirst($row['payment_method']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="text-sm font-medium text-gray-800"><?php echo $formatted_date; ?></div>
                                                <div class="text-sm text-gray-500"><?php echo $formatted_time; ?></div>
                                                <div class="text-xs text-gray-400">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php
                                                    $now = new DateTime();
                                                    $interval = $now->diff($sale_date);
                                                    if ($interval->y > 0) {
                                                        echo $interval->y . ' years ago';
                                                    } elseif ($interval->m > 0) {
                                                        echo $interval->m . ' months ago';
                                                    } elseif ($interval->d > 0) {
                                                        echo $interval->d . ' days ago';
                                                    } elseif ($interval->h > 0) {
                                                        echo $interval->h . ' hours ago';
                                                    } elseif ($interval->i > 0) {
                                                        echo $interval->i . ' minutes ago';
                                                    } else {
                                                        echo 'Just now';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col space-y-2">
                                                <!-- View Details Button -->
                                                <button onclick="showSaleModal(<?php echo $row['id']; ?>)"
                                                    class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-eye text-sm"></i>
                                                    <span class="text-sm font-medium">View Details</span>
                                                </button>

                                                <!-- Action Buttons Row -->
                                                <div class="flex space-x-2">
                                                    <!-- Print Button -->
                                                    <button onclick="printInvoice(<?php echo $row['id']; ?>)"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-print text-xs"></i>
                                                        <span class="text-xs font-medium">Print</span>
                                                    </button>

                                                    <!-- Email Button -->
                                                    <button onclick="showEmailModal('<?php echo $row['invoice_no']; ?>')"
                                                        class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-envelope text-xs"></i>
                                                        <span class="text-xs font-medium">Email</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-shopping-cart text-purple-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Sales Found</h4>
                                            <p class="text-gray-600 mb-6">No sales transactions have been recorded yet.</p>
                                            <p class="text-sm text-gray-500">Sales will appear here when pharmacists make transactions</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 border-t border-purple-100 bg-gradient-to-r from-purple-50 to-purple-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo $total_sales; ?> sales •
                                <span class="font-medium text-purple-600">
                                    Admin View Only Access
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="exportToExcel()"
                                class="px-4 py-2 border border-purple-200 rounded-lg hover:bg-purple-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-excel text-green-500"></i>
                                <span class="text-sm text-gray-700">Export Excel</span>
                            </button>
                            <button onclick="exportToPDF()"
                                class="px-4 py-2 border border-purple-200 rounded-lg hover:bg-purple-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-pdf text-red-500"></i>
                                <span class="text-sm text-gray-700">Export PDF</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics & Top Performers -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mx-6 my-8">
                <!-- Top Selling Medicines -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-line text-green-500"></i>
                        <span>Top Selling Medicines</span>
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

                <!-- Top Pharmacists -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-trophy text-yellow-500"></i>
                        <span>Top Performing Pharmacists</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($top_pharmacists) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php while ($pharmacist = mysqli_fetch_assoc($top_pharmacists)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                                            <i class="fas fa-crown text-yellow-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($pharmacist['name']); ?></h4>
                                            <p class="text-xs text-gray-500">Sales: <?php echo number_format($pharmacist['sales_count'] ?: 0); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-yellow-600">Rs <?php echo number_format($pharmacist['total_revenue'] ?: 0, 2); ?></p>
                                        <p class="text-xs text-gray-400">generated</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-user-md text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No pharmacist data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-history text-blue-500"></i>
                        <span>Recent Sales</span>
                    </h3>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($recent_sales) > 0): ?>
                            <?php while ($recent = mysqli_fetch_assoc($recent_sales)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-white rounded-lg border border-blue-100 hover:border-blue-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-receipt text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($recent['invoice_no']); ?></h4>
                                            <p class="text-xs text-gray-500">By <?php echo htmlspecialchars($recent['pharmacist_name'] ?: 'Unknown'); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-blue-600">Rs <?php echo number_format($recent['total_amount'], 2); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($recent['sale_date'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-receipt text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No recent sales</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Sale Details Modal -->
    <div id="saleModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden animate-fade-in-up">
            <div class="p-6 border-b border-purple-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-receipt text-purple-500 mr-2"></i>
                        <span id="modalSaleInvoice">Sale Details</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <!-- Export PDF Button -->
                        <button onclick="exportSaleToPDF()"
                            class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2">
                            <i class="fas fa-file-pdf"></i>
                            <span>Export PDF</span>
                        </button>
                        <!-- Print Button -->
                        <button onclick="printSale()"
                            class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:shadow-lg transition flex items-center space-x-2">
                            <i class="fas fa-print"></i>
                            <span>Print</span>
                        </button>
                        <button onclick="hideSaleModal()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition">
                            <i class="fas fa-times text-gray-600"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] custom-scrollbar" id="modalContent">
                <!-- Content will be loaded dynamically via JavaScript -->
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-purple-500 text-3xl mb-4"></i>
                    <p class="text-gray-600">Loading sale details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Modal -->
    <div id="emailModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-envelope text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-4">Send Invoice via Email</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Number</label>
                        <input type="text" id="emailInvoiceNo" readonly
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recipient Email</label>
                        <input type="email" id="recipientEmail" placeholder="customer@example.com"
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message (Optional)</label>
                        <textarea id="emailMessage" rows="3" placeholder="Your receipt for purchase from MediCare Pharma..."
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition resize-none"></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="hideEmailModal()"
                            class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button onclick="sendEmailReceipt()"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Send Email
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
        let currentSaleData = null;
        let currentInvoiceNo = null;

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

        // Show sale details modal
        async function showSaleModal(saleId) {
            try {
                document.getElementById('saleModal').classList.remove('hidden');

                // Show loading state
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-purple-500 text-3xl mb-4"></i>
                        <p class="text-gray-600">Loading sale details...</p>
                    </div>
                `;

                // Fetch sale details via AJAX
                const response = await fetch(`ajax/get_sale_details.php?id=${saleId}`);
                const data = await response.json();

                if (data.success) {
                    currentSaleData = data;
                    updateSaleModal(data);
                } else {
                    throw new Error('Failed to load sale details');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i>
                        <p class="text-gray-600">Error loading sale details</p>
                        <button onclick="hideSaleModal()" class="mt-4 px-4 py-2 bg-purple-500 text-white rounded-lg">
                            Close
                        </button>
                    </div>
                `;
            }
        }

        // Update modal content with sale data
        function updateSaleModal(data) {
            const sale = data.sale;
            const items = data.items;
            const pharmacist = data.pharmacist;

            document.getElementById('modalSaleInvoice').textContent = `Invoice: ${sale.invoice_no}`;

            const saleDate = new Date(sale.sale_date);
            const formattedDate = saleDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const formattedTime = saleDate.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });

            // Calculate totals
            const netAmount = sale.total_amount - sale.discount;
            const taxAmount = sale.tax_amount || 0;

            let html = `
                <div class="space-y-8">
                    <!-- Header Information -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-store text-purple-500 mr-2"></i>
                                Store Information
                            </h4>
                            <div class="space-y-2">
                                <h2 class="text-xl font-bold text-purple-600">MediCare Pharma</h2>
                                <p class="text-gray-600">123 Health Street, Medical City</p>
                                <p class="text-gray-600">Phone: (123) 456-7890</p>
                                <p class="text-gray-600">Email: info@medicarepharma.com</p>
                                <p class="text-gray-600">GSTIN: 27AABCU9603R1ZX</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-receipt text-green-500 mr-2"></i>
                                Invoice Details
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Invoice No:</span>
                                    <span class="font-semibold">${sale.invoice_no}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-semibold">${formattedDate}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Time:</span>
                                    <span class="font-semibold">${formattedTime}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Sale ID:</span>
                                    <span class="font-semibold">SALE-${String(sale.id).padStart(6, '0')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pharmacist Information -->
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-user-md text-blue-500 mr-2"></i>
                            Processed By
                        </h4>
                        <div class="bg-blue-50 p-4 rounded-xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-user-md text-blue-600"></i>
                                </div>
                                <div>
                                    <h5 class="font-bold text-gray-800">${pharmacist.name}</h5>
                                    <p class="text-sm text-gray-600">Pharmacist ID: ${sale.pharmacist_id}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sale Items Table -->
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-list text-yellow-500 mr-2"></i>
                            Items Purchased
                        </h4>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-purple-50">
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">#</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Medicine Name</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Batch No</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Quantity</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Unit Price</th>
                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${items.map((item, index) => `
                                    <tr class="border-t border-purple-100 hover:bg-purple-50">
                                        <td class="px-4 py-3">${index + 1}</td>
                                        <td class="px-4 py-3">
                                            <div>
                                                <span class="font-medium text-gray-800">${item.medicine_name}</span>
                                                ${item.generic_name ? `<p class="text-xs text-gray-500">${item.generic_name}</p>` : ''}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">${item.batch_no || 'N/A'}</td>
                                        <td class="px-4 py-3">${item.quantity}</td>
                                        <td class="px-4 py-3">Rs ${parseFloat(item.price).toFixed(2)}</td>
                                        <td class="px-4 py-3 font-semibold text-green-600">Rs ${(item.quantity * item.price).toFixed(2)}</td>
                                    </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Payment Summary -->
                    <div class="space-y-4">
                        <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-calculator text-green-500 mr-2"></i>
                            Payment Summary
                        </h4>
                        <div class="bg-green-50 p-6 rounded-xl">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-semibold">Rs ${parseFloat(sale.total_amount).toFixed(2)}</span>
                                </div>
                                ${sale.discount > 0 ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-semibold text-red-600">-Rs ${parseFloat(sale.discount).toFixed(2)}</span>
                                </div>
                                ` : ''}
                                ${taxAmount > 0 ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tax (GST):</span>
                                    <span class="font-semibold">Rs ${parseFloat(taxAmount).toFixed(2)}</span>
                                </div>
                                ` : ''}
                                <div class="border-t border-green-200 pt-3 mt-3">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-gray-800">Total Amount:</span>
                                        <span class="text-xl font-bold text-green-600">Rs ${parseFloat(netAmount).toFixed(2)}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center mt-4">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-semibold ${sale.payment_method === 'Cash' ? 'text-green-600' : 'text-blue-600'}">
                                        <i class="fas ${sale.payment_method === 'Cash' ? 'fa-money-bill-wave' : 'fa-credit-card'} mr-1"></i>
                                        ${sale.payment_method}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Notes -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="text-center space-y-2">
                            <p class="text-sm text-gray-500">Thank you for shopping with MediCare Pharma!</p>
                            <p class="text-xs text-gray-400">
                                This is a computer-generated invoice. No signature required.
                            </p>
                            <p class="text-xs text-gray-400">
                                For any queries, contact: support@medicarepharma.com | Phone: 1800-123-4567
                            </p>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('modalContent').innerHTML = html;
        }

        // Hide sale modal
        function hideSaleModal() {
            document.getElementById('saleModal').classList.add('hidden');
            currentSaleData = null;
        }

        // Export sale to PDF
        async function exportSaleToPDF() {
            if (!currentSaleData) return;

            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                const sale = currentSaleData.sale;
                const items = currentSaleData.items;
                const saleDate = new Date(sale.sale_date);
                const netAmount = sale.total_amount - sale.discount;

                // Add header with logo
                doc.setFontSize(20);
                doc.setTextColor(139, 92, 246); // Purple color
                doc.text('MediCare Pharma', 105, 20, null, null, 'center');

                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('123 Health Street, Medical City', 105, 28, null, null, 'center');
                doc.text('Phone: (123) 456-7890 | Email: info@medicarepharma.com', 105, 33, null, null, 'center');
                doc.text('GSTIN: 27AABCU9603R1ZX', 105, 38, null, null, 'center');

                // Add invoice title
                doc.setFontSize(18);
                doc.setTextColor(0, 0, 0);
                doc.text('INVOICE', 105, 50, null, null, 'center');

                // Invoice details
                doc.setFontSize(10);
                doc.text(`Invoice No: ${sale.invoice_no}`, 20, 60);
                doc.text(`Date: ${saleDate.toLocaleDateString()}`, 20, 65);
                doc.text(`Time: ${saleDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`, 20, 70);
                doc.text(`Sale ID: SALE-${String(sale.id).padStart(6, '0')}`, 20, 75);

                // Pharmacist info
                doc.text('Processed By:', 140, 60);
                doc.text(currentSaleData.pharmacist.name, 140, 65);
                doc.text(`ID: ${sale.pharmacist_id}`, 140, 70);

                // Table header
                let y = 90;
                doc.setFillColor(139, 92, 246);
                doc.rect(10, y, 190, 8, 'F');
                doc.setTextColor(255, 255, 255);
                doc.text('#', 15, y + 6);
                doc.text('Medicine Name', 25, y + 6);
                doc.text('Batch', 100, y + 6);
                doc.text('Qty', 130, y + 6);
                doc.text('Price', 150, y + 6);
                doc.text('Total', 170, y + 6);

                y += 15;
                doc.setTextColor(0, 0, 0);

                // Add items
                items.forEach((item, index) => {
                    if (y > 250) {
                        doc.addPage();
                        y = 20;
                    }

                    doc.text((index + 1).toString(), 15, y);
                    doc.text(item.medicine_name.substring(0, 35), 25, y);
                    doc.text(item.batch_no || 'N/A', 100, y);
                    doc.text(item.quantity.toString(), 130, y);
                    doc.text(`Rs ${parseFloat(item.price).toFixed(2)}`, 150, y);
                    doc.text(`Rs ${(item.quantity * item.price).toFixed(2)}`, 170, y);

                    y += 7;
                });

                // Add totals
                y += 10;
                doc.text(`Subtotal: Rs ${parseFloat(sale.total_amount).toFixed(2)}`, 140, y);
                y += 7;

                if (sale.discount > 0) {
                    doc.text(`Discount: -Rs ${parseFloat(sale.discount).toFixed(2)}`, 140, y);
                    y += 7;
                }

                doc.setFontSize(12);
                doc.setFont(undefined, 'bold');
                doc.text(`Total Amount: Rs ${parseFloat(netAmount).toFixed(2)}`, 140, y);
                doc.setFont(undefined, 'normal');

                y += 10;
                doc.setFontSize(10);
                doc.text(`Payment Method: ${sale.payment_method}`, 140, y);

                // Add footer
                y = 280;
                doc.setFontSize(9);
                doc.setTextColor(100, 100, 100);
                doc.text('Thank you for shopping with MediCare Pharma!', 105, y, null, null, 'center');
                doc.text('This is a computer-generated invoice. No signature required.', 105, y + 5, null, null, 'center');
                doc.text('For queries: support@medicarepharma.com | 1800-123-4567', 105, y + 10, null, null, 'center');

                // Save the PDF
                doc.save(`Invoice_${sale.invoice_no}_${saleDate.toISOString().slice(0,10)}.pdf`);

                showNotification('Invoice PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Print sale invoice
        function printSale() {
            if (!currentSaleData) return;

            const sale = currentSaleData.sale;
            const items = currentSaleData.items;
            const pharmacist = currentSaleData.pharmacist;
            const saleDate = new Date(sale.sale_date);
            const netAmount = sale.total_amount - sale.discount;

            let printContent = `
                <div style="padding: 30px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;">
                    <!-- Store Header -->
                    <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #8b5cf6; padding-bottom: 20px;">
                        <h1 style="color: #8b5cf6; font-size: 28px; margin: 0 0 10px 0;">MediCare Pharma</h1>
                        <p style="color: #666; margin: 5px 0;">123 Health Street, Medical City</p>
                        <p style="color: #666; margin: 5px 0;">Phone: (123) 456-7890 | Email: info@medicarepharma.com</p>
                        <p style="color: #666; margin: 5px 0;">GSTIN: 27AABCU9603R1ZX</p>
                    </div>
                    
                    <!-- Invoice Title -->
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2 style="color: #333; font-size: 24px; margin: 0 0 20px 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">INVOICE</h2>
                    </div>
                    
                    <!-- Invoice Details -->
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                        <div>
                            <h3 style="color: #666; font-size: 14px; margin: 0 0 10px 0;">INVOICE DETAILS</h3>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Invoice No:</strong> ${sale.invoice_no}
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Date:</strong> ${saleDate.toLocaleDateString()}
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Time:</strong> ${saleDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                            </p>
                            <p style="margin: 5px 0; color: #333;">
                                <strong>Sale ID:</strong> SALE-${String(sale.id).padStart(6, '0')}
                            </p>
                        </div>
                        <div>
                            <h3 style="color: #666; font-size: 14px; margin: 0 0 10px 0; text-align: right;">PROCESSED BY</h3>
                            <p style="margin: 5px 0; color: #333; text-align: right;">
                                <strong>${pharmacist.name}</strong>
                            </p>
                            <p style="margin: 5px 0; color: #333; text-align: right;">
                                Pharmacist ID: ${sale.pharmacist_id}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Items Table -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; border: 1px solid #ddd;">
                        <thead>
                            <tr style="background: #8b5cf6; color: white;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">#</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Medicine Name</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Batch No</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Quantity</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Unit Price</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map((item, index) => `
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px;">${index + 1}</td>
                                <td style="padding: 10px;">
                                    <strong>${item.medicine_name}</strong>
                                    ${item.generic_name ? `<br><span style="color: #666; font-size: 12px;">${item.generic_name}</span>` : ''}
                                </td>
                                <td style="padding: 10px;">${item.batch_no || 'N/A'}</td>
                                <td style="padding: 10px; text-align: center;">${item.quantity}</td>
                                <td style="padding: 10px;">Rs ${parseFloat(item.price).toFixed(2)}</td>
                                <td style="padding: 10px; font-weight: bold; color: #10b981;">Rs ${(item.quantity * item.price).toFixed(2)}</td>
                            </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    
                    <!-- Payment Summary -->
                    <div style="background: #f7f7f7; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                        <h3 style="color: #666; font-size: 16px; margin: 0 0 15px 0;">PAYMENT SUMMARY</h3>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Subtotal:</span>
                            <span style="font-weight: bold;">Rs ${parseFloat(sale.total_amount).toFixed(2)}</span>
                        </div>
                        ${sale.discount > 0 ? `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Discount:</span>
                            <span style="font-weight: bold; color: #ef4444;">-Rs ${parseFloat(sale.discount).toFixed(2)}</span>
                        </div>
                        ` : ''}
                        <div style="display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                            <span style="font-size: 18px; font-weight: bold; color: #333;">Total Amount:</span>
                            <span style="font-size: 24px; font-weight: bold; color: #10b981;">Rs ${parseFloat(netAmount).toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                            <span style="color: #666;">Payment Method:</span>
                            <span style="font-weight: bold; color: ${sale.payment_method === 'Cash' ? '#10b981' : '#3b82f6'}">
                                ${sale.payment_method}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="text-align: center; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
                        <p style="margin: 10px 0;">Thank you for shopping with MediCare Pharma!</p>
                        <p style="margin: 5px 0; font-size: 12px;">This is a computer-generated invoice. No signature required.</p>
                        <p style="margin: 5px 0; font-size: 12px;">For any queries, contact: support@medicarepharma.com | Phone: 1800-123-4567</p>
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
                    <title>Invoice - ${sale.invoice_no}</title>
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

        // Email modal functions
        function showEmailModal(invoiceNo) {
            currentInvoiceNo = invoiceNo;
            document.getElementById('emailModal').classList.remove('hidden');
            document.getElementById('emailInvoiceNo').value = invoiceNo;
        }

        function hideEmailModal() {
            document.getElementById('emailModal').classList.add('hidden');
            currentInvoiceNo = null;
        }

        function sendEmailReceipt() {
            const email = document.getElementById('recipientEmail').value;
            const message = document.getElementById('emailMessage').value;

            if (!email) {
                alert('Please enter recipient email address');
                return;
            }

            if (!validateEmail(email)) {
                alert('Please enter a valid email address');
                return;
            }

            // Show loading
            const sendBtn = document.querySelector('#emailModal button:last-child');
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            sendBtn.disabled = true;

            // Simulate API call (replace with actual email API)
            setTimeout(() => {
                showNotification(`Invoice sent to ${email} successfully!`, 'success');
                hideEmailModal();
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
            }, 2000);
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Export all sales to Excel
        function exportToExcel() {
            try {
                // Prepare data
                const rows = [];

                // Add header row
                rows.push([
                    'Invoice No',
                    'Sale ID',
                    'Date',
                    'Time',
                    'Pharmacist',
                    'Items Count',
                    'Total Quantity',
                    'Total Amount',
                    'Discount',
                    'Net Amount',
                    'Payment Method'
                ]);

                // Add data rows
                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                    $sale_date = new DateTime($row['sale_date']);
                    $net_amount = $row['total_amount'] - $row['discount'];
                ?>
                    rows.push([
                        '<?php echo $row['invoice_no']; ?>',
                        'SALE-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>',
                        '<?php echo $sale_date->format('Y-m-d'); ?>',
                        '<?php echo $sale_date->format('H:i:s'); ?>',
                        '<?php echo addslashes($row['pharmacist_name'] ?: 'Unknown'); ?>',
                        <?php echo $row['items_count'] ?: 0; ?>,
                        <?php echo $row['total_quantity'] ?: 0; ?>,
                        <?php echo $row['total_amount']; ?>,
                        <?php echo $row['discount']; ?>,
                        <?php echo $net_amount; ?>,
                        '<?php echo $row['payment_method']; ?>'
                    ]);
                <?php endwhile; ?>

                // Create worksheet
                const ws = XLSX.utils.aoa_to_sheet(rows);

                // Create workbook
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Sales Data');

                // Generate Excel file
                const today = new Date().toISOString().slice(0, 10);
                XLSX.writeFile(wb, `Sales_Report_${today}.xlsx`);

                showNotification('Excel file exported successfully!', 'success');
            } catch (error) {
                console.error('Excel export error:', error);
                showNotification('Error exporting Excel file', 'error');
            }
        }

        // Export all sales to PDF
        function exportToPDF() {
            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                const today = new Date().toISOString().slice(0, 10);

                // Add header
                doc.setFontSize(20);
                doc.setTextColor(139, 92, 246);
                doc.text('Sales Report - MediCare Pharma', 105, 20, null, null, 'center');

                doc.setFontSize(11);
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${today}`, 105, 30, null, null, 'center');
                doc.text(`Total Sales: ${<?php echo $total_sales; ?>} transactions`, 105, 35, null, null, 'center');

                // Add table headers
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                let y = 50;

                // Table headers
                doc.setFillColor(139, 92, 246);
                doc.rect(10, y, 190, 10, 'F');
                doc.setTextColor(255, 255, 255);
                doc.text('Invoice', 15, y + 7);
                doc.text('Date', 50, y + 7);
                doc.text('Pharmacist', 80, y + 7);
                doc.text('Items', 120, y + 7);
                doc.text('Amount', 150, y + 7);
                doc.text('Payment', 180, y + 7);

                y += 15;
                doc.setTextColor(0, 0, 0);

                // Add sales data
                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                    $sale_date = new DateTime($row['sale_date']);
                    $net_amount = $row['total_amount'] - $row['discount'];
                ?>
                    if (y > 270) {
                        doc.addPage();
                        y = 20;
                    }

                    doc.text('<?php echo $row['invoice_no']; ?>', 15, y);
                    doc.text('<?php echo $sale_date->format('m/d/Y'); ?>', 50, y);
                    doc.text('<?php echo substr(addslashes($row['pharmacist_name'] ?: 'Unknown'), 0, 12); ?>', 80, y);
                    doc.text('<?php echo $row['items_count'] ?: 0; ?>', 120, y);
                    doc.text('Rs <?php echo number_format($net_amount, 2); ?>', 150, y);
                    doc.text('<?php echo $row['payment_method']; ?>', 180, y);

                    y += 7;
                <?php endwhile; ?>

                // Add summary
                y += 10;
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('Summary:', 10, y);
                doc.setFont(undefined, 'normal');
                doc.setFontSize(12);

                y += 10;
                doc.text(`Total Revenue: Rs <?php echo number_format($stats['total_revenue'] ?: 0, 2); ?>`, 10, y);
                y += 7;
                doc.text(`Total Discount: Rs <?php echo number_format($stats['total_discount'] ?: 0, 2); ?>`, 10, y);
                y += 7;
                doc.text(`Net Revenue: Rs <?php echo number_format(($stats['total_revenue'] ?: 0) - ($stats['total_discount'] ?: 0), 2); ?>`, 10, y);
                y += 7;
                doc.text(`Total Transactions: <?php echo $total_sales; ?>`, 10, y);

                // Add footer
                doc.setFontSize(10);
                doc.setTextColor(150, 150, 150);
                doc.text('Confidential - For Internal Use Only', 105, 285, null, null, 'center');

                // Save the PDF
                doc.save(`Sales_Report_${today}.pdf`);

                showNotification('Sales PDF exported successfully!', 'success');
            } catch (error) {
                console.error('PDF export error:', error);
                showNotification('Error exporting PDF', 'error');
            }
        }

        // Export all sales data
        function exportAllToPDF() {
            exportToPDF();
        }

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

        // Date filter
        const dateFilter = document.getElementById('dateFilter');
        if (dateFilter) {
            dateFilter.addEventListener('change', function(e) {
                const selectedDate = e.target.value;
                if (selectedDate) {
                    const rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const dateText = row.cells[4]?.textContent.toLowerCase();
                        row.style.display = dateText.includes(selectedDate) ? '' : 'none';
                    });
                }
            });
        }

        // Payment method filter
        const paymentFilter = document.getElementById('paymentFilter');
        if (paymentFilter) {
            paymentFilter.addEventListener('change', function(e) {
                const selectedPayment = e.target.value;
                if (selectedPayment) {
                    const rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const paymentText = row.cells[3]?.textContent.toLowerCase();
                        row.style.display = paymentText.includes(selectedPayment.toLowerCase()) ? '' : 'none';
                    });
                } else {
                    // Show all rows if no filter selected
                    const rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        row.style.display = '';
                    });
                }
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

            // Set today's date as default in date filter
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateFilter').value = today;
        });

        // Close modals when clicking outside
        [document.getElementById('saleModal'), document.getElementById('emailModal')].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (this.id === 'saleModal') hideSaleModal();
                        if (this.id === 'emailModal') hideEmailModal();
                    }
                });
            }
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

            // Ctrl/Cmd + E for export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportAllToPDF();
            }

            // Escape key to close modals
            if (e.key === 'Escape') {
                if (!document.getElementById('saleModal').classList.contains('hidden')) {
                    hideSaleModal();
                }
                if (!document.getElementById('emailModal').classList.contains('hidden')) {
                    hideEmailModal();
                }
            }
        });
    </script>
</body>

</html>