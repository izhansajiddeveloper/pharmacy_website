<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Set role-based permissions
$can_edit = ($_SESSION['role'] === 'pharmacist');
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Calculate Total Revenue from payments (only completed sales)
$revenue_query = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN payment_type = 'sale' AND payment_status = 'completed' THEN amount ELSE 0 END) as total_sales_revenue,
        SUM(CASE WHEN payment_type = 'sale' AND payment_status = 'completed' THEN transaction_net_amount ELSE 0 END) as total_net_sales,
        SUM(CASE WHEN payment_type = 'return_to_company' AND payment_status = 'completed' THEN amount ELSE 0 END) as total_returns_cost,
        COUNT(CASE WHEN payment_type = 'sale' AND payment_status = 'completed' THEN 1 END) as total_sales_count,
        COUNT(CASE WHEN payment_type = 'return_to_company' AND payment_status = 'completed' THEN 1 END) as total_returns_count
    FROM payments 
    WHERE payment_date BETWEEN '$start_date' AND '$end_date 23:59:59'
");
$revenue_stats = mysqli_fetch_assoc($revenue_query);

// Calculate Total Expenses
$expenses_query = mysqli_query($conn, "
    SELECT 
        SUM(amount) as total_expenses,
        COUNT(*) as expenses_count,
        SUM(CASE WHEN expense_type = 'salary' THEN amount ELSE 0 END) as salary_expenses,
        SUM(CASE WHEN expense_type = 'utility' THEN amount ELSE 0 END) as utility_expenses,
        SUM(CASE WHEN expense_type = 'transport' THEN amount ELSE 0 END) as transport_expenses,
        SUM(CASE WHEN expense_type = 'store_expense' THEN amount ELSE 0 END) as store_expenses,
        SUM(CASE WHEN expense_type = 'internet' THEN amount ELSE 0 END) as internet_expenses,
        SUM(CASE WHEN expense_type = 'other' THEN amount ELSE 0 END) as other_expenses
    FROM expenses 
    WHERE expense_date BETWEEN '$start_date' AND '$end_date'
");
$expenses_stats = mysqli_fetch_assoc($expenses_query);

// Calculate Profit/Loss
$total_revenue = ($revenue_stats['total_net_sales'] ?: 0) - ($revenue_stats['total_returns_cost'] ?: 0);
$total_expenses = $expenses_stats['total_expenses'] ?: 0;
$net_profit = $total_revenue - $total_expenses;

// Calculate percentages
$revenue_percentage = $total_revenue > 0 ? 100 : 0;
$expenses_percentage = $total_revenue > 0 ? ($total_expenses / $total_revenue * 100) : 0;
$profit_percentage = $total_revenue > 0 ? ($net_profit / $total_revenue * 100) : 0;

// Get monthly profit trend
$monthly_trend = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
        COALESCE(SUM(CASE WHEN p.payment_type = 'sale' AND p.payment_status = 'completed' THEN p.transaction_net_amount ELSE 0 END), 0) as monthly_revenue,
        COALESCE(SUM(CASE WHEN p.payment_type = 'return_to_company' AND p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as monthly_returns,
        COALESCE(SUM(e.amount), 0) as monthly_expenses,
        COALESCE(SUM(CASE WHEN p.payment_type = 'sale' AND p.payment_status = 'completed' THEN p.transaction_net_amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN p.payment_type = 'return_to_company' AND p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) - 
        COALESCE(SUM(e.amount), 0) as monthly_profit
    FROM (
        SELECT DATE_FORMAT(payment_date, '%Y-%m-01') as month_date FROM payments 
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        UNION
        SELECT DATE_FORMAT(expense_date, '%Y-%m-01') as month_date FROM expenses 
        WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ) dates
    LEFT JOIN payments p ON DATE_FORMAT(p.payment_date, '%Y-%m-01') = dates.month_date
    LEFT JOIN expenses e ON DATE_FORMAT(e.expense_date, '%Y-%m-01') = dates.month_date
    GROUP BY dates.month_date
    ORDER BY dates.month_date DESC
    LIMIT 6
");

// Get top revenue sources (sales)
$top_sales = mysqli_query($conn, "
    SELECT 
        p.invoice_no,
        p.transaction_net_amount as amount,
        p.payment_date,
        'Sale' as type,
        s.total_amount as original_amount,
        s.discount
    FROM payments p
    JOIN sales s ON p.invoice_no = s.invoice_no
    WHERE p.payment_type = 'sale' 
    AND p.payment_status = 'completed'
    AND p.payment_date BETWEEN '$start_date' AND '$end_date 23:59:59'
    ORDER BY p.transaction_net_amount DESC
    LIMIT 10
");


// Get expense breakdown by category
$expense_breakdown = mysqli_query($conn, "
    SELECT 
        expense_type,
        category,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM expenses 
    WHERE expense_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY expense_type, category
    ORDER BY total_amount DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Analysis - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(251, 191, 36, 0.3);
            box-shadow: 0 4px 20px rgba(251, 191, 36, 0.1);
        }

        .gradient-blue {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
        }

        .gradient-red {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
        }

        .gradient-green {
            background: linear-gradient(135deg, var(--accent-green), #059669);
        }

        .gradient-purple {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
        }

        .gradient-teal {
            background: linear-gradient(135deg, var(--accent-teal), #0d9488);
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

        .profit-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .loss-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .revenue-badge {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .expense-badge {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="green-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include ($role === 'admin') ? "includes/admin_sidebar.php" : "includes/pharmacist_sidebar.php"; ?>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Pharmacy <span class="gradient-text">Profit Analysis</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-chart-line <?php echo $can_edit ? 'text-green-500' : 'text-blue-500'; ?>"></i>
                            <span><?php echo $can_edit ? 'Analyze and manage pharmacy profits' : 'View pharmacy profit analysis'; ?></span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-calendar-alt text-purple-500"></i>
                            <span><?php echo date('F Y', strtotime($start_date)); ?></span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <button onclick="exportProfitReport()"
                            class="gradient-blue text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-file-export"></i>
                            <span>Export Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="glass-card mx-6 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
                    <h3 class="text-lg font-semibold text-gray-800">Select Date Range</h3>
                    <form method="GET" action="" class="flex flex-wrap gap-4">
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">From:</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                                class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none bg-white/80 shadow-sm">
                        </div>
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">To:</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                                class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none bg-white/80 shadow-sm">
                        </div>
                        <button type="submit"
                            class="px-4 py-2 gradient-blue text-white rounded-lg hover:shadow-lg transition shadow">
                            <i class="fas fa-filter mr-2"></i>Apply Filter
                        </button>
                        <a href="profit.php"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition shadow-sm">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </a>
                    </form>
                </div>
            </div>

            <!-- Key Profit Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <!-- Net Profit Card -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl <?php echo $net_profit >= 0 ? 'gradient-green' : 'gradient-red'; ?> flex items-center justify-center shadow-lg">
                            <i class="fas <?php echo $net_profit >= 0 ? 'fa-chart-line' : 'fa-chart-line-down'; ?> text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold <?php echo $net_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?> bg-<?php echo $net_profit >= 0 ? 'green' : 'red'; ?>-50 px-3 py-1 rounded-full">
                            <?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?>
                        </span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format(abs($net_profit), 2); ?></h3>
                    <p class="text-gray-600 mb-3">Net <?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?></p>
                    <div class="flex items-center text-sm <?php echo $net_profit >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                        <i class="fas fa-percentage mr-1"></i>
                        <span><?php echo number_format(abs($profit_percentage), 1); ?>% of revenue</span>
                    </div>
                </div>

                <!-- Total Revenue Card -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Revenue</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($total_revenue, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-blue h-2 rounded-full" style="width: <?php echo min($revenue_percentage, 100); ?>%"></div>
                    </div>
                </div>

                <!-- Total Expenses Card -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-red flex items-center justify-center shadow-lg">
                            <i class="fas fa-money-bill-wave text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-red-600 bg-red-50 px-3 py-1 rounded-full">Expenses</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($total_expenses, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Expenses</p>
                    <div class="flex items-center text-sm text-red-500">
                        <i class="fas fa-percentage mr-1"></i>
                        <span><?php echo number_format($expenses_percentage, 1); ?>% of revenue</span>
                    </div>
                </div>

                <!-- Profit Margin Card -->
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-percent text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Margin</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($profit_percentage, 1); ?>%</h3>
                    <p class="text-gray-600 mb-3">Profit Margin</p>
                    <div class="flex items-center text-sm <?php echo $profit_percentage >= 20 ? 'text-green-500' : ($profit_percentage >= 10 ? 'text-yellow-500' : 'text-red-500'); ?>">
                        <i class="fas <?php echo $profit_percentage >= 20 ? 'fa-arrow-up' : ($profit_percentage >= 10 ? 'fa-minus' : 'fa-arrow-down'); ?> mr-1"></i>
                        <span>
                            <?php
                            if ($profit_percentage >= 20) echo 'Excellent';
                            elseif ($profit_percentage >= 10) echo 'Good';
                            elseif ($profit_percentage >= 0) echo 'Low';
                            else echo 'Loss';
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Profit Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-6">
                <!-- Profit/Loss Chart -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-pie text-blue-500"></i>
                        <span>Profit & Loss Breakdown</span>
                    </h3>
                    <div class="h-80">
                        <canvas id="profitChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Trend -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-chart-line text-green-500"></i>
                        <span>6-Month Profit Trend</span>
                    </h3>
                    <div class="h-80">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-6 my-6">
                <!-- Revenue Breakdown -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-receipt text-green-500"></i>
                        <span>Revenue Breakdown</span>
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-green-50 to-white rounded-lg border border-green-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-green-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Sales Revenue</h4>
                                    <p class="text-xs text-gray-500">Net sales after discounts</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-green-600">Rs <?php echo number_format($revenue_stats['total_net_sales'] ?: 0, 2); ?></p>
                                <p class="text-xs text-gray-500"><?php echo number_format($revenue_stats['total_sales_count'] ?: 0); ?> sales</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-red-50 to-white rounded-lg border border-red-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-undo-alt text-red-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Returns Cost</h4>
                                    <p class="text-xs text-gray-500">Returns to company</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-red-600">-Rs <?php echo number_format($revenue_stats['total_returns_cost'] ?: 0, 2); ?></p>
                                <p class="text-xs text-gray-500"><?php echo number_format($revenue_stats['total_returns_count'] ?: 0); ?> returns</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-white rounded-lg border border-blue-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Total Revenue</h4>
                                    <p class="text-xs text-gray-500">Net revenue after returns</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-blue-600">Rs <?php echo number_format($total_revenue, 2); ?></p>
                                <p class="text-xs text-green-500">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    <?php echo $total_revenue > 0 ? 'Positive Revenue' : 'Negative Revenue'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses Breakdown -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.9s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-file-invoice-dollar text-red-500"></i>
                        <span>Expenses Breakdown</span>
                    </h3>
                    <div class="space-y-4">
                        <?php
                        $expense_types = [
                            'salary' => ['icon' => 'fa-users', 'color' => 'blue'],
                            'utility' => ['icon' => 'fa-bolt', 'color' => 'yellow'],
                            'store_expense' => ['icon' => 'fa-store', 'color' => 'purple'],
                            'transport' => ['icon' => 'fa-car', 'color' => 'gray'],
                            'internet' => ['icon' => 'fa-wifi', 'color' => 'teal'],
                            'other' => ['icon' => 'fa-file-invoice-dollar', 'color' => 'gray']
                        ];

                        foreach ($expense_types as $type => $info):
                            $amount = 0;
                            switch ($type) {
                                case 'salary':
                                    $amount = $expenses_stats['salary_expenses'] ?: 0;
                                    break;
                                case 'utility':
                                    $amount = $expenses_stats['utility_expenses'] ?: 0;
                                    break;
                                case 'transport':
                                    $amount = $expenses_stats['transport_expenses'] ?: 0;
                                    break;
                                case 'store_expense':
                                    $amount = $expenses_stats['store_expenses'] ?: 0;
                                    break;
                                case 'internet':
                                    $amount = $expenses_stats['internet_expenses'] ?: 0;
                                    break;
                                case 'other':
                                    $amount = $expenses_stats['other_expenses'] ?: 0;
                                    break;
                            }

                            if ($amount > 0):
                                $color_classes = [
                                    'blue' => 'bg-blue-100 text-blue-600',
                                    'yellow' => 'bg-yellow-100 text-yellow-600',
                                    'purple' => 'bg-purple-100 text-purple-600',
                                    'gray' => 'bg-gray-100 text-gray-600',
                                    'teal' => 'bg-teal-100 text-teal-600'
                                ];
                        ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-red-50 to-white rounded-lg border border-red-100 hover:border-red-200 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-lg <?php echo $color_classes[$info['color']]; ?> flex items-center justify-center">
                                            <i class="fas <?php echo $info['icon']; ?>"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo $type === 'salary' ? 'Staff salaries' : ($type === 'utility' ? 'Bills & Utilities' : 'Store expenses'); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold text-red-600">Rs <?php echo number_format($amount, 2); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo $total_expenses > 0 ? number_format(($amount / $total_expenses) * 100, 1) : 0; ?>% of total
                                        </p>
                                    </div>
                                </div>
                        <?php endif;
                        endforeach; ?>

                        <div class="flex items-center justify-between p-3 bg-gradient-to-r from-red-100 to-red-50 rounded-lg border border-red-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-red-600 flex items-center justify-center">
                                    <i class="fas fa-calculator text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Total Expenses</h4>
                                    <p class="text-xs text-gray-500">All pharmacy expenses</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-red-600">Rs <?php echo number_format($total_expenses, 2); ?></p>
                                <p class="text-xs text-red-500">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    <?php echo number_format($expenses_stats['expenses_count'] ?: 0); ?> expense records
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Revenue Sources -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 1.0s">
                <div class="px-6 py-4 border-b border-green-100 bg-gradient-to-r from-green-50 to-green-25">
                    <h3 class="text-lg font-semibold text-gray-800">Top Revenue Sources</h3>
                    <p class="text-sm text-gray-600">Highest revenue-generating sales</p>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-green-50 to-green-25">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Invoice No</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Original Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Discount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Net Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-green-50">
                            <?php if (mysqli_num_rows($top_sales) > 0): ?>
                                <?php while ($sale = mysqli_fetch_assoc($top_sales)): ?>
                                    <tr class="table-row hover:bg-green-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="revenue-badge">
                                                <i class="fas fa-receipt mr-1"></i>
                                                <?php echo htmlspecialchars($sale['invoice_no']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($sale['payment_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            Rs <?php echo number_format($sale['original_amount'] ?: 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-red-600">
                                            -Rs <?php echo number_format($sale['discount'] ?: 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-lg font-bold text-green-600">
                                                Rs <?php echo number_format($sale['amount'], 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-chart-bar text-3xl text-gray-300 mb-2 block"></i>
                                        No revenue data found for the selected period
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Report -->
            <div class="glass-card mx-6 my-6 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.1s">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                    <i class="fas fa-file-alt text-purple-500"></i>
                    <span>Profit & Loss Summary</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-700 mb-2">Revenue Summary</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total Sales Revenue:</span>
                                <span class="font-bold text-blue-600">Rs <?php echo number_format($revenue_stats['total_net_sales'] ?: 0, 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Returns Cost:</span>
                                <span class="font-bold text-red-600">-Rs <?php echo number_format($revenue_stats['total_returns_cost'] ?: 0, 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center border-t pt-2">
                                <span class="font-semibold text-gray-700">Net Revenue:</span>
                                <span class="font-bold text-green-600">Rs <?php echo number_format($total_revenue, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-700 mb-2">Expenses Summary</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Operating Expenses:</span>
                                <span class="font-bold text-red-600">Rs <?php echo number_format($total_expenses, 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Number of Expenses:</span>
                                <span class="font-bold text-gray-600"><?php echo number_format($expenses_stats['expenses_count'] ?: 0); ?></span>
                            </div>
                            <div class="flex justify-between items-center border-t pt-2">
                                <span class="font-semibold text-gray-700">Cost of Revenue:</span>
                                <span class="font-bold text-red-600"><?php echo number_format($expenses_percentage, 1); ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 pt-6 border-t">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="text-xl font-bold text-gray-800">Net <?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?></h4>
                            <p class="text-gray-600">Period: <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?></p>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold <?php echo $net_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                Rs <?php echo number_format(abs($net_profit), 2); ?>
                            </div>
                            <div class="text-sm <?php echo $net_profit >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                                <?php echo $net_profit >= 0 ? 'Profit Margin:' : 'Loss Margin:'; ?> <?php echo number_format(abs($profit_percentage), 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
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

        // Initialize date pickers
        flatpickr("input[type='date']", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });

        // Profit & Loss Pie Chart
        const profitChartCtx = document.getElementById('profitChart').getContext('2d');
        const profitChart = new Chart(profitChartCtx, {
            type: 'doughnut',
            data: {
                labels: ['Revenue', 'Expenses', '<?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?>'],
                datasets: [{
                    data: [
                        <?php echo $total_revenue; ?>,
                        <?php echo $total_expenses; ?>,
                        <?php echo abs($net_profit); ?>
                    ],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        '<?php echo $net_profit >= 0 ? "rgba(16, 185, 129, 0.8)" : "rgba(239, 68, 68, 0.8)"; ?>'
                    ],
                    borderColor: [
                        'rgb(59, 130, 246)',
                        'rgb(239, 68, 68)',
                        '<?php echo $net_profit >= 0 ? "rgb(16, 185, 129)" : "rgb(239, 68, 68)"; ?>'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'Rs ' + context.parsed.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                });
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Monthly Trend Chart
        const trendChartCtx = document.getElementById('trendChart').getContext('2d');
        <?php
        $months = [];
        $profits = [];
        $revenues = [];
        $expenses = [];

        mysqli_data_seek($monthly_trend, 0);
        while ($trend = mysqli_fetch_assoc($monthly_trend)) {
            $months[] = date('M Y', strtotime($trend['month']));
            $profits[] = $trend['monthly_profit'];
            $revenues[] = $trend['monthly_revenue'] - $trend['monthly_returns'];
            $expenses[] = $trend['monthly_expenses'];
        }
        $months = array_reverse($months);
        $profits = array_reverse($profits);
        $revenues = array_reverse($revenues);
        $expenses = array_reverse($expenses);
        ?>

        const trendChart = new Chart(trendChartCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode($revenues); ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Expenses',
                        data: <?php echo json_encode($expenses); ?>,
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Profit/Loss',
                        data: <?php echo json_encode($profits); ?>,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs ' + value.toLocaleString('en-IN', {
                                    minimumFractionDigits: 0
                                });
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });

        // Export Profit Report
        function exportProfitReport() {
            try {
                const data = {
                    period: '<?php echo date("F d, Y", strtotime($start_date)) . " to " . date("F d, Y", strtotime($end_date)); ?>',
                    revenue: <?php echo $total_revenue; ?>,
                    expenses: <?php echo $total_expenses; ?>,
                    net_profit: <?php echo $net_profit; ?>,
                    profit_margin: <?php echo $profit_percentage; ?>,
                    sales_count: <?php echo $revenue_stats['total_sales_count'] ?: 0; ?>,
                    expenses_count: <?php echo $expenses_stats['expenses_count'] ?: 0; ?>,
                    report_date: new Date().toISOString().slice(0, 10)
                };

                // Create a blob with the data
                const blob = new Blob([JSON.stringify(data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Profit_Report_${data.report_date}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                showNotification('Profit report exported successfully!', 'success');
            } catch (error) {
                console.error('Export error:', error);
                showNotification('Error exporting report', 'error');
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportProfitReport();
            }

            if (e.key === 'Escape') {
                // Close any open modals if we had them
                const modals = document.querySelectorAll('.fixed.inset-0');
                modals.forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                    }
                });
            }
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>

</html>