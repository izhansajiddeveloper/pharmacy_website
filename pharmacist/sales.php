<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacist can access
if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$pharmacist_id = $_SESSION['user_id'];

// Fetch pharmacist's sales
$query = "SELECT s.*, 
                 COUNT(si.id) as items_count,
                 SUM(si.quantity) as total_quantity
          FROM sales s
          LEFT JOIN sale_items si ON s.id = si.sale_id
          WHERE s.pharmacist_id = $pharmacist_id
          GROUP BY s.id
          ORDER BY s.sale_date DESC";

$result = mysqli_query($conn, $query);
$total_sales = mysqli_num_rows($result);

// Get pharmacist's sales statistics
$stats_query = mysqli_query(
    $conn,
    "SELECT 
        COUNT(*) as total_transactions,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_sale_value,
        SUM(discount) as total_discount,
        COUNT(DISTINCT DATE(sale_date)) as sales_days,
        SUM(CASE WHEN payment_method = 'Cash' THEN 1 ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 'Online' THEN 1 ELSE 0 END) as online_sales
     FROM sales
     WHERE pharmacist_id = $pharmacist_id"
);
$stats = mysqli_fetch_assoc($stats_query);

// Get today's sales
$today_sales = mysqli_query(
    $conn,
    "SELECT SUM(total_amount) as today_total, 
            SUM(discount) as today_discount,
            COUNT(*) as today_count
     FROM sales 
     WHERE pharmacist_id = $pharmacist_id 
     AND DATE(sale_date) = CURDATE()"
);
$today = mysqli_fetch_assoc($today_sales);

// Get recent sales
$recent_sales = mysqli_query(
    $conn,
    "SELECT *
     FROM sales
     WHERE pharmacist_id = $pharmacist_id
     ORDER BY sale_date DESC
     LIMIT 5"
);

// Get top selling medicines for this pharmacist
$top_medicines = mysqli_query(
    $conn,
    "SELECT m.name, SUM(si.quantity) as total_sold, SUM(si.quantity * si.price) as revenue
     FROM sale_items si
     JOIN medicines m ON si.medicine_id = m.id
     JOIN sales s ON si.sale_id = s.id
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

        .badge-completed {
            background: linear-gradient(135deg, #10b981, #059669);
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
                            class="gradient-blue text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-plus"></i>
                            <span>Create New Sale</span>
                            <i class="fas fa-arrow-right text-blue-100 text-sm"></i>
                        </a>
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
                    <p class="text-gray-600 mb-3">Your Total Revenue</p>
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
                    <p class="text-gray-600 mb-3">Today's Sales</p>
                    <div class="flex items-center text-sm text-blue-500">
                        <i class="fas fa-rupee-sign mr-1"></i>
                        <span>Rs <?php echo number_format($today['today_total'] ?: 0, 2); ?> today</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1 text-right">
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Cash: <?php echo $stats['cash_sales'] ?: 0; ?></span>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Online: <?php echo $stats['online_sales'] ?: 0; ?></span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_transactions'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Total Transactions</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-tag mr-1"></i>
                        <span>Avg: Rs <?php echo number_format($stats['avg_sale_value'] ?: 0, 2); ?></span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-teal-500 to-teal-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-calendar-alt text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Activity</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['sales_days'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Active Sales Days</p>
                    <div class="flex items-center text-sm text-teal-500">
                        <i class="fas fa-percentage mr-1"></i>
                        <span>Discount: Rs <?php echo number_format($stats['total_discount'] ?: 0, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="px-6 py-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-blue-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">Your Sales Transactions</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo $total_sales; ?> sales records</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search by invoice or amount..."
                                class="pl-10 pr-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-blue-400"></i>
                        </div>

                        <input type="date"
                            id="dateFilter"
                            class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm">
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-blue-50 to-blue-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Invoice Details
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
                                ?>
                                    <tr class="table-row hover:bg-blue-25 transition-colors">
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
                                            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-shopping-cart text-blue-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Sales Found</h4>
                                            <p class="text-gray-600 mb-6">You haven't made any sales yet.</p>
                                            <a href="create_sale.php"
                                                class="gradient-blue text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                <i class="fas fa-plus"></i>
                                                <span>Create First Sale</span>
                                            </a>
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
                                <span class="font-medium text-blue-600">
                                    Full Management Access
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="exportMySalesToExcel()"
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
                            <?php while ($recent = mysqli_fetch_assoc($recent_sales)): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-white rounded-lg border border-blue-100 hover:border-blue-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-receipt text-blue-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($recent['invoice_no']); ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo date('M d', strtotime($recent['sale_date'])); ?></p>
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

        // Export my sales to Excel
        function exportMySalesToExcel() {
            try {
                const rows = [];
                rows.push(['Invoice No', 'Date', 'Time', 'Items Count', 'Total Quantity', 'Total Amount', 'Discount', 'Net Amount', 'Payment Method']);

                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                    $sale_date = new DateTime($row['sale_date']);
                    $net_amount = $row['total_amount'] - $row['discount'];
                ?>
                    rows.push([
                        '<?php echo $row['invoice_no']; ?>',
                        '<?php echo $sale_date->format('Y-m-d'); ?>',
                        '<?php echo $sale_date->format('H:i:s'); ?>',
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
                XLSX.utils.book_append_sheet(wb, ws, 'My Sales Data');

                const today = new Date().toISOString().slice(0, 10);
                XLSX.writeFile(wb, `My_Sales_${today}.xlsx`);

                showNotification('Excel file exported successfully!', 'success');
            } catch (error) {
                console.error('Excel export error:', error);
                showNotification('Error exporting Excel file', 'error');
            }
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
                        const dateText = row.cells[3]?.textContent.toLowerCase();
                        row.style.display = dateText.includes(selectedDate) ? '' : 'none';
                    });
                } else {
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
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'create_sale.php';
            }
        });
    </script>
</body>

</html>