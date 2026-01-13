<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Total medicines
$result_medicines = mysqli_query($conn, "SELECT COUNT(*) AS total_medicines FROM medicines");
$medicines = mysqli_fetch_assoc($result_medicines)['total_medicines'];

// Total stock items
$result_stock = mysqli_query($conn, "SELECT SUM(quantity) AS total_stock FROM stock_batches WHERE is_expired = 0");
$stock = mysqli_fetch_assoc($result_stock)['total_stock'] ?: 0;

// Total sales
$result_sales = mysqli_query($conn, "SELECT COUNT(*) AS total_sales FROM sales WHERE pharmacist_id = $user_id");
$sales = mysqli_fetch_assoc($result_sales)['total_sales'];

// Today's sales revenue
$today_sales = mysqli_query(
    $conn,
    "SELECT SUM(total_amount) AS revenue 
     FROM sales 
     WHERE DATE(sale_date) = CURDATE() 
     AND pharmacist_id = $user_id"
);
$today_revenue = mysqli_fetch_assoc($today_sales)['revenue'] ?: 0;

// This week's revenue
$week_sales = mysqli_query(
    $conn,
    "SELECT SUM(total_amount) AS revenue 
     FROM sales 
     WHERE YEARWEEK(sale_date) = YEARWEEK(CURDATE()) 
     AND pharmacist_id = $user_id"
);
$week_revenue = mysqli_fetch_assoc($week_sales)['revenue'] ?: 0;

// This month's revenue
$month_sales = mysqli_query(
    $conn,
    "SELECT SUM(total_amount) AS revenue 
     FROM sales 
     WHERE MONTH(sale_date) = MONTH(CURDATE()) 
     AND YEAR(sale_date) = YEAR(CURDATE())
     AND pharmacist_id = $user_id"
);
$month_revenue = mysqli_fetch_assoc($month_sales)['revenue'] ?: 0;

// Monthly sales data for chart
$monthly_sales = [];
$monthly_query = mysqli_query(
    $conn,
    "SELECT DATE_FORMAT(sale_date, '%b') as month, 
            COUNT(*) as total_sales,
            SUM(total_amount) as revenue
     FROM sales 
     WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     AND pharmacist_id = $user_id
     GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
     ORDER BY sale_date ASC
     LIMIT 6"
);

while ($row = mysqli_fetch_assoc($monthly_query)) {
    $monthly_sales[] = $row;
}

// Daily sales for line chart
$daily_sales = [];
$daily_query = mysqli_query(
    $conn,
    "SELECT DATE(sale_date) as date, 
            DAYNAME(sale_date) as day,
            COUNT(*) as total_sales,
            SUM(total_amount) as revenue
     FROM sales 
     WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     AND pharmacist_id = $user_id
     GROUP BY DATE(sale_date)
     ORDER BY date ASC"
);

while ($row = mysqli_fetch_assoc($daily_query)) {
    $daily_sales[] = $row;
}

// Top selling medicines
$top_medicines = [];
$top_query = mysqli_query(
    $conn,
    "SELECT m.name AS medicine_name, 
            SUM(si.quantity) AS total_sold, 
            SUM(si.quantity * si.price) AS revenue
     FROM sale_items si
     JOIN medicines m ON si.medicine_id = m.id
     JOIN sales s ON si.sale_id = s.id
     WHERE s.pharmacist_id = $user_id
     GROUP BY si.medicine_id
     ORDER BY total_sold DESC
     LIMIT 5"
);

while ($row = mysqli_fetch_assoc($top_query)) {
    $top_medicines[] = $row;
}

// Low stock medicines
$low_stock_query = mysqli_query(
    $conn,
    "SELECT m.name AS medicine_name, sb.quantity, sb.expiry_date,
            DATEDIFF(sb.expiry_date, CURDATE()) as days_until_expiry
     FROM stock_batches sb
     JOIN medicines m ON sb.medicine_id = m.id
     WHERE sb.quantity <= 50 AND sb.is_expired = 0
     ORDER BY sb.quantity ASC
     LIMIT 5"
);

$low_stock = [];
while ($row = mysqli_fetch_assoc($low_stock_query)) {
    $low_stock[] = $row;
}

// Expiring soon medicines
$expiring_query = mysqli_query(
    $conn,
    "SELECT m.name AS medicine_name, sb.quantity, sb.expiry_date,
            DATEDIFF(sb.expiry_date, CURDATE()) as days_until_expiry
     FROM stock_batches sb
     JOIN medicines m ON sb.medicine_id = m.id
     WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND sb.is_expired = 0
     ORDER BY sb.expiry_date ASC
     LIMIT 5"
);

$expiring_soon = [];
while ($row = mysqli_fetch_assoc($expiring_query)) {
    $expiring_soon[] = $row;
}

// Recent sales
$recent_sales = mysqli_query(
    $conn,
    "SELECT s.id, s.total_amount, s.sale_date
     FROM sales s
     WHERE s.pharmacist_id = $user_id
     ORDER BY s.sale_date DESC
     LIMIT 6"
);

// Active medicines count
$active_medicines = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT m.id) as active_medicines
     FROM medicines m
     JOIN stock_batches sb ON m.id = sb.medicine_id
     WHERE sb.is_expired = 0 AND sb.quantity > 0"
);
$active_medicines_count = mysqli_fetch_assoc($active_medicines)['active_medicines'] ?: 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --accent-blue: #3b82f6;
            --accent-teal: #14b8a6;
            --accent-purple: #8b5cf6;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 50%, #fef3c7 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            padding: 1.5rem;
        }

        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
        }

        .grid-col-3 {
            grid-column: span 3;
        }

        .grid-col-4 {
            grid-column: span 4;
        }

        .grid-col-6 {
            grid-column: span 6;
        }

        .grid-col-8 {
            grid-column: span 8;
        }

        .grid-col-9 {
            grid-column: span 9;
        }

        .grid-col-12 {
            grid-column: span 12;
        }

        @media (max-width: 1024px) {

            .grid-col-3,
            .grid-col-4,
            .grid-col-6,
            .grid-col-8,
            .grid-col-9 {
                grid-column: span 6;
            }
        }

        @media (max-width: 768px) {

            .grid-col-3,
            .grid-col-4,
            .grid-col-6,
            .grid-col-8,
            .grid-col-9,
            .grid-col-12 {
                grid-column: span 1;
            }
        }

        .gradient-yellow {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
        }

        .gradient-gray {
            background: linear-gradient(135deg, var(--primary-gray), var(--primary-gray-light));
        }

        .gradient-mixed {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-gray));
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

        .chart-container {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(251, 191, 36, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .chart-container:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            transition: all 0.3s ease;
        }

        /* Custom scrollbar */
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

        /* Animations */
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

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
            }

            50% {
                box-shadow: 0 0 30px rgba(251, 191, 36, 0.5);
            }
        }

        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
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

        /* Custom shapes */
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

        .gray-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--primary-gray), var(--primary-gray-light));
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
        }

        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }

        /* Progress ring */
        .progress-ring {
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(90deg);
            transform-origin: 50% 50%;
            stroke-linecap: round;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>
    <div class="yellow-blob bottom-40 right-40 animate-float" style="animation-delay: 2s; width: 200px; height: 200px;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-gradient-to-b from-gray-900 to-gray-800 text-white fixed lg:sticky top-12 h-full lg:h-[calc(100vh-4rem)] w-64 lg:w-72 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 z-40 overflow-y-auto custom-scrollbar shadow-2xl">
        <div class="p-6">
            <!-- User Info -->
            <div class="flex items-center space-x-3 mb-8 pb-6 border-b border-gray-700/50">
                <div class="w-12 h-12 rounded-full gradient-yellow flex items-center justify-center text-white font-bold text-xl shadow-lg">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <h3 class="font-bold text-white"><?php echo htmlspecialchars($user_name); ?></h3>
                    <p class="text-xs text-gray-300">Pharmacist</p>
                    <p class="text-xs text-green-400 mt-1 flex items-center">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                        Online
                    </p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg gradient-yellow text-white shadow-lg">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                        <i class="fas fa-tachometer-alt text-white text-lg"></i>
                    </div>
                    <span class="font-medium text-white">Dashboard</span>
                    <span class="ml-auto">
                        <i class="fas fa-chevron-right text-yellow-100 text-xs"></i>
                    </span>
                </a>

                <!-- Medicines Dropdown -->
                <div class="space-y-1">
                    <button type="button" class="medicines-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-700/50 flex items-center justify-center">
                                <i class="fas fa-pills text-gray-300 text-lg"></i>
                            </div>
                            <span class="font-medium text-gray-200">Medicines</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>
                    
                    <!-- Medicines Submenu -->
                    <div class="medicines-submenu pl-4 ml-4 border-l border-gray-700/50 hidden">
                        <!-- All Medicines -->
                        <a href="medicines.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">All Medicines</span>
                            <span class="ml-auto bg-gray-600/50 text-gray-300 text-xs px-2 py-1 rounded-full font-medium"><?php echo $medicines; ?></span>
                        </a>
                        
                        <!-- Search Brand -->
                        <a href="search_brand.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Search Brand</span>
                        </a>
                        
                        <!-- Search Generic -->
                        <a href="search_generic.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Search Generic</span>
                        </a>
                        
                        <!-- Return to Company -->
                        <a href="return_to_company.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Return to Company</span>
                        </a>
                        
                        <!-- Expired Medicines -->
                        <a href="expired_medicines.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Expired Medicines</span>
                        </a>
                    </div>
                </div>

                <!-- Stock Dropdown -->
                <div class="space-y-1">
                    <button type="button" class="stock-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-700/50 flex items-center justify-center">
                                <i class="fas fa-boxes text-gray-300 text-lg"></i>
                            </div>
                            <span class="font-medium text-gray-200">Stock</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>
                    
                    <!-- Stock Submenu -->
                    <div class="stock-submenu pl-4 ml-4 border-l border-gray-700/50 hidden">
                        <!-- View Stock -->
                        <a href="stock.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">View Stock</span>
                            <span class="ml-auto bg-gray-600/50 text-gray-300 text-xs px-2 py-1 rounded-full font-medium"><?php echo number_format($stock); ?></span>
                        </a>
                        
                        <!-- Add Stock -->
                        <a href="add_stock.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Add Stock</span>
                        </a>
                    </div>
                </div>

                <!-- Sale Dropdown -->
                <div class="space-y-1">
                    <button type="button" class="sale-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-700/50 flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-gray-300 text-lg"></i>
                            </div>
                            <span class="font-medium text-gray-200">Sale</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                    </button>
                    
                    <!-- Sale Submenu -->
                    <div class="sale-submenu pl-4 ml-4 border-l border-gray-700/50 hidden">
                        <!-- Create Sale -->
                        <a href="create_sale.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Create Sale</span>
                        </a>
                        <a href="create_regular_sale.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Create Regular Sale</span>
                        </a>
                        
                        <!-- View Sales -->
                        <a href="sales.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">View Sales</span>
                            <span class="ml-auto bg-yellow-500/20 text-yellow-300 text-xs px-2 py-1 rounded-full font-medium"><?php echo $sales; ?></span>
                        </a>
                        
                        <!-- Payments -->
                        <a href="payments.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Payments</span>
                        </a>
                        
                        <!-- Expenses -->
                        <a href="expenses.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Expenses</span>
                        </a>
                        
                        <!-- Profit Analysis -->
                        <a href="profit.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200">
                            <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                            <span class="text-sm font-medium text-gray-300">Profit Analysis</span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Divider -->
            <div class="my-6 border-t border-gray-700/50"></div>

            <!-- Quick Stats -->
            <div class="space-y-4">
                <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Today's Stats</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <span class="text-sm text-gray-400">Revenue</span>
                        </div>
                        <span class="font-bold text-yellow-400">Rs <?php echo number_format($today_revenue); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                            <span class="text-sm text-gray-400">Medicines</span>
                        </div>
                        <span class="font-bold text-gray-300"><?php echo $active_medicines_count; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-sm text-gray-400">Alerts</span>
                        </div>
                        <span class="font-bold text-red-400"><?php echo count($low_stock) + count($expiring_soon); ?></span>
                    </div>
                </div>
            </div>

            <!-- Logout -->
            <div class="mt-8 pt-6 border-t border-gray-700/50">
                <a href="../auth/logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-red-500/10 to-red-600/10 hover:from-red-500/20 hover:to-red-600/20 transition-all duration-200 group">
                    <i class="fas fa-sign-out-alt text-red-400 group-hover:text-red-300"></i>
                    <span class="text-red-300 group-hover:text-red-200">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Overlay for mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

    <!-- Main Content -->
    <main class="flex-1 overflow-hidden">
        <!-- Dashboard Header -->
        <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                        <span class="bg-gradient-to-r from-yellow-500 to-yellow-600 bg-clip-text text-transparent">Pharmacist Dashboard</span> Overview
                    </h1>
                    <p class="text-gray-600 flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-yellow-500"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                        <span class="text-gray-400 mx-2">•</span>
                        <i class="fas fa-clock text-gray-500"></i>
                        <span id="current-time">Loading...</span>
                        <span class="text-gray-400 mx-2">•</span>
                        <i class="fas fa-user-md text-yellow-500"></i>
                        <span><?php echo htmlspecialchars($user_name); ?></span>
                    </p>
                </div>
                <div class="mt-4 lg:mt-0 flex items-center space-x-4">
                    <button onclick="refreshDashboard()" class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 flex items-center space-x-2 pulse-glow">
                        <i class="fas fa-redo"></i>
                        <span>Refresh Data</span>
                    </button>
                    <a href="create_sale.php" class="gradient-mixed text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-cash-register"></i>
                        <span>New Sale</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-container">
            <!-- Revenue Stats -->
            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Today</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($today_revenue); ?></h3>
                    <p class="text-gray-600 mb-3">Daily Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-yellow h-2 rounded-full" style="width: <?php echo min(100, ($today_revenue / 50000) * 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-gray flex items-center justify-center shadow-lg">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-600 bg-gray-50 px-3 py-1 rounded-full">Weekly</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($week_revenue); ?></h3>
                    <p class="text-gray-600 mb-3">Weekly Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-gray h-2 rounded-full" style="width: <?php echo min(100, ($week_revenue / 250000) * 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-mixed flex items-center justify-center shadow-lg">
                            <i class="fas fa-calendar-alt text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Monthly</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($month_revenue); ?></h3>
                    <p class="text-gray-600 mb-3">Monthly Revenue</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-mixed h-2 rounded-full" style="width: <?php echo min(100, ($month_revenue / 1000000) * 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid-col-3">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-teal-500 to-teal-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-shopping-cart text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($sales); ?></h3>
                    <p class="text-gray-600 mb-3">Total Sales</p>
                    <div class="flex items-center text-sm">
                        <span class="text-yellow-500 mr-2 flex items-center">
                            <i class="fas fa-chart-line mr-1"></i>
                            <?php echo $sales > 0 ? round($sales / 30) : 0; ?>/day avg
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Chart Area -->
            <div class="grid-col-8">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Sales Performance</h3>
                            <p class="text-gray-600">Your revenue and sales count over last 6 months</p>
                        </div>
                        <div class="flex space-x-2 mt-4 lg:mt-0">
                            <button class="chart-filter active px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg font-medium" data-filter="revenue">Revenue</button>
                            <button class="chart-filter px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium" data-filter="sales">Sales Count</button>
                            <button class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="h-80">
                        <canvas id="combinedChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stock Overview -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Stock Overview</h3>
                        <span class="text-sm text-yellow-600 font-medium bg-yellow-50 px-3 py-1 rounded-full">Live</span>
                    </div>
                    <div class="h-64 flex flex-col justify-center items-center">
                        <div class="relative w-40 h-40 mb-6">
                            <canvas id="stockChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-gray-800"><?php echo $active_medicines_count; ?></div>
                                    <div class="text-sm text-gray-600">Active</div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 w-full">
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-lg font-bold text-green-600"><?php echo $medicines; ?></div>
                                <div class="text-xs text-gray-600">Total Medicines</div>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 rounded-lg">
                                <div class="text-lg font-bold text-yellow-600"><?php echo number_format($stock); ?></div>
                                <div class="text-xs text-gray-600">Total Stock</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Sales Line Chart -->
            <div class="grid-col-6">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Daily Sales Trend</h3>
                            <p class="text-gray-600">Your performance in last 7 days</p>
                        </div>
                        <span class="text-sm text-teal-600 bg-teal-50 px-3 py-1 rounded-full">
                            Weekly View
                        </span>
                    </div>
                    <div class="h-64">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Selling Medicines -->
            <div class="grid-col-6">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.9s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Top Selling Medicines</h3>
                        <span class="text-sm text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Your Sales</span>
                    </div>
                    <div class="space-y-4">
                        <?php if (count($top_medicines) > 0): ?>
                            <?php foreach ($top_medicines as $index => $medicine): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-yellow-100 transition group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg gradient-yellow flex items-center justify-center text-white font-bold shadow">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600"><?php echo htmlspecialchars($medicine['medicine_name']); ?></h4>
                                            <p class="text-xs text-gray-500">Sold: <?php echo $medicine['total_sold']; ?> units</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-gray-800">Rs <?php echo number_format($medicine['revenue']); ?></div>
                                        <div class="text-xs text-gray-500">Revenue</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-chart-bar text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No sales data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Inventory Alerts -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.1s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Inventory Alerts</h3>
                        <div class="flex space-x-2">
                            <span class="px-3 py-1 bg-red-100 text-red-600 rounded-full text-xs font-medium shadow">
                                <?php echo count($low_stock); ?> Low
                            </span>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-600 rounded-full text-xs font-medium shadow">
                                <?php echo count($expiring_soon); ?> Expiring
                            </span>
                        </div>
                    </div>
                    <div class="space-y-4 max-h-80 overflow-y-auto custom-scrollbar">
                        <!-- Low Stock Items -->
                        <?php if (count($low_stock) > 0): ?>
                            <?php foreach ($low_stock as $item): ?>
                                <div class="p-3 bg-gradient-to-r from-red-50 to-white rounded-lg border border-red-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                                <i class="fas fa-exclamation-triangle text-red-600 text-sm"></i>
                                            </div>
                                            <h4 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($item['medicine_name']); ?></h4>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-red-600"><?php echo $item['quantity']; ?></div>
                                            <div class="text-xs text-gray-500">units left</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span>Expires: <?php echo date('M j', strtotime($item['expiry_date'])); ?></span>
                                        <span class="<?php echo $item['days_until_expiry'] < 30 ? 'text-red-600 font-medium' : 'text-gray-500'; ?>">
                                            <?php echo $item['days_until_expiry']; ?> days
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Expiring Soon Items -->
                        <?php if (count($expiring_soon) > 0): ?>
                            <?php foreach ($expiring_soon as $item): ?>
                                <div class="p-3 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                                                <i class="fas fa-clock text-yellow-600 text-sm"></i>
                                            </div>
                                            <h4 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($item['medicine_name']); ?></h4>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-yellow-600"><?php echo $item['quantity']; ?></div>
                                            <div class="text-xs text-gray-500">in stock</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-600">
                                        <span>Expires: <?php echo date('M j', strtotime($item['expiry_date'])); ?></span>
                                        <span class="text-red-600 font-medium">
                                            <?php echo $item['days_until_expiry']; ?> days left
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (count($low_stock) == 0 && count($expiring_soon) == 0): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3 shadow">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                                <p class="text-gray-500">No inventory alerts</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Quick Actions</h3>
                        <span class="text-sm text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Daily Tasks</span>
                    </div>
                    <div class="space-y-4">
                        <a href="create_sale.php" class="p-4 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-yellow flex items-center justify-center">
                                <i class="fas fa-cash-register text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600">Create New Sale</h4>
                                <p class="text-xs text-gray-500">Process customer purchase</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-yellow-600"></i>
                        </a>

                        <a href="add_stock.php" class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-gray-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-gray flex items-center justify-center">
                                <i class="fas fa-plus-circle text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-gray-600">Add Stock</h4>
                                <p class="text-xs text-gray-500">Add new medicine batch</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-gray-600"></i>
                        </a>

                        <a href="medicines.php" class="p-4 bg-gradient-to-r from-yellow-50 to-white rounded-lg border border-yellow-100 hover:border-yellow-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-yellow flex items-center justify-center">
                                <i class="fas fa-pills text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-yellow-600">Browse Medicines</h4>
                                <p class="text-xs text-gray-500">View medicine catalog</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-yellow-600"></i>
                        </a>

                        <a href="stock.php" class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100 hover:border-gray-300 transition group flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-lg gradient-gray flex items-center justify-center">
                                <i class="fas fa-boxes text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm group-hover:text-gray-600">Check Stock</h4>
                                <p class="text-xs text-gray-500">Review inventory levels</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 group-hover:text-gray-600"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Sales Activity -->
            <div class="grid-col-4">
                <div class="chart-container rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 1.2s">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Recent Sales</h3>
                        <a href="sales.php" class="text-sm text-yellow-600 hover:text-yellow-800 font-medium flex items-center">
                            View All <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($recent_sales) > 0): ?>
                            <?php while ($sale = mysqli_fetch_assoc($recent_sales)): ?>
                                <div class="p-4 bg-gradient-to-r from-gray-50 to-white rounded-xl border border-gray-100 hover:border-yellow-200 transition">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-10 h-10 rounded-full gradient-yellow flex items-center justify-center">
                                                <i class="fas fa-shopping-cart text-white"></i>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-800">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xl font-bold text-yellow-600">Rs <?php echo number_format($sale['total_amount']); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-600">
                                        <span><?php echo date('M j', strtotime($sale['sale_date'])); ?></span>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                            Completed
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-shopping-cart text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No recent sales found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add JavaScript for dropdown functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown functionality
        const medicinesToggle = document.querySelector('.medicines-dropdown-toggle');
        const stockToggle = document.querySelector('.stock-dropdown-toggle');
        const saleToggle = document.querySelector('.sale-dropdown-toggle');

        const medicinesSubmenu = document.querySelector('.medicines-submenu');
        const stockSubmenu = document.querySelector('.stock-submenu');
        const saleSubmenu = document.querySelector('.sale-submenu');

        // Toggle dropdowns
        function toggleDropdown(toggleBtn, submenu, chevron) {
            toggleBtn.addEventListener('click', () => {
                const isExpanded = !submenu.classList.contains('hidden');
                
                // Close all other dropdowns
                [medicinesSubmenu, stockSubmenu, saleSubmenu].forEach(menu => {
                    if (menu !== submenu) {
                        menu.classList.add('hidden');
                        const otherBtn = menu.previousElementSibling;
                        if (otherBtn) {
                            otherBtn.querySelector('.fa-chevron-down').classList.remove('rotate-180');
                        }
                    }
                });

                // Toggle current dropdown
                submenu.classList.toggle('hidden');
                chevron.classList.toggle('rotate-180');
            });
        }

        if (medicinesToggle && medicinesSubmenu) {
            toggleDropdown(medicinesToggle, medicinesSubmenu, medicinesToggle.querySelector('.fa-chevron-down'));
        }

        if (stockToggle && stockSubmenu) {
            toggleDropdown(stockToggle, stockSubmenu, stockToggle.querySelector('.fa-chevron-down'));
        }

        if (saleToggle && saleSubmenu) {
            toggleDropdown(saleToggle, saleSubmenu, saleToggle.querySelector('.fa-chevron-down'));
        }

        // Mobile sidebar toggle
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
    });
</script>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <!-- Quick Stats Floating Button -->
    <div class="fixed bottom-6 right-6 z-40">
        <button id="quickStatsBtn" class="gradient-yellow text-white w-14 h-14 rounded-full shadow-2xl flex items-center justify-center hover:scale-110 transition-transform duration-300">
            <i class="fas fa-chart-pie text-xl"></i>
        </button>
    </div>

    <!-- Quick Stats Panel -->
    <div id="quickStatsPanel" class="fixed bottom-24 right-6 bg-white rounded-2xl shadow-2xl p-6 z-50 hidden w-80">
        <div class="flex items-center justify-between mb-4">
            <h4 class="font-bold text-gray-800">Quick Stats</h4>
            <button onclick="closeQuickStats()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <span class="text-sm text-gray-600">Today's Revenue</span>
                </div>
                <span class="font-bold text-gray-800">Rs <?php echo number_format($today_revenue); ?></span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-teal-500"></div>
                    <span class="text-sm text-gray-600">Total Sales</span>
                </div>
                <span class="font-bold text-gray-800"><?php echo number_format($sales); ?></span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <span class="text-sm text-gray-600">Low Stock</span>
                </div>
                <span class="font-bold text-gray-800"><?php echo count($low_stock); ?></span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                    <span class="text-sm text-gray-600">Active Medicines</span>
                </div>
                <span class="font-bold text-gray-800"><?php echo $active_medicines_count; ?></span>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        sidebarToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        });

        sidebarOverlay?.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });

        // Quick Stats Panel
        const quickStatsBtn = document.getElementById('quickStatsBtn');
        const quickStatsPanel = document.getElementById('quickStatsPanel');

        quickStatsBtn?.addEventListener('click', () => {
            quickStatsPanel.classList.toggle('hidden');
        });

        function closeQuickStats() {
            quickStatsPanel.classList.add('hidden');
        }

        // Update current time
        function updateCurrentTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            timeElement.textContent = `${hours}:${minutes}:${seconds}`;
        }

        setInterval(updateCurrentTime, 1000);
        updateCurrentTime();

        // Initialize Charts with Yellow/Gray Theme
        document.addEventListener('DOMContentLoaded', function() {
            // Yellow/Gray color palette
            const yellowColors = [
                'rgba(245, 158, 11, 0.8)', // Yellow 500
                'rgba(251, 191, 36, 0.8)', // Yellow 400
                'rgba(252, 211, 77, 0.8)', // Yellow 300
                'rgba(253, 230, 138, 0.8)', // Yellow 200
                'rgba(254, 243, 199, 0.8)', // Yellow 100
            ];

            const grayColors = [
                'rgba(107, 114, 128, 0.8)', // Gray 500
                'rgba(156, 163, 175, 0.8)', // Gray 400
                'rgba(209, 213, 219, 0.8)', // Gray 300
                'rgba(229, 231, 235, 0.8)', // Gray 200
                'rgba(243, 244, 246, 0.8)', // Gray 100
            ];

            // Combined Chart (Revenue & Sales)
            const combinedCtx = document.getElementById('combinedChart').getContext('2d');
            const combinedChart = new Chart(combinedCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_sales, 'month')); ?>,
                    datasets: [{
                        label: 'Revenue (Rs )',
                        data: <?php echo json_encode(array_column($monthly_sales, 'revenue')); ?>,
                        backgroundColor: yellowColors[0],
                        borderColor: '#f59e0b',
                        borderWidth: 2,
                        borderRadius: 6,
                        yAxisID: 'y',
                    }, {
                        label: 'Sales Count',
                        data: <?php echo json_encode(array_column($monthly_sales, 'total_sales')); ?>,
                        backgroundColor: grayColors[0],
                        borderColor: '#6b7280',
                        borderWidth: 2,
                        borderRadius: 6,
                        yAxisID: 'y1',
                        type: 'line',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#1f2937',
                            bodyColor: '#4b5563',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue (Rs )',
                                color: '#f59e0b'
                            },
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                color: '#f59e0b'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Sales Count',
                                color: '#6b7280'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                color: '#6b7280'
                            }
                        }
                    }
                }
            });

            // Stock Doughnut Chart
            const stockCtx = document.getElementById('stockChart').getContext('2d');
            const stockChart = new Chart(stockCtx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [<?php echo $active_medicines_count; ?>, <?php echo $medicines - $active_medicines_count; ?>],
                        backgroundColor: [
                            yellowColors[0],
                            grayColors[0]
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#1f2937',
                            bodyColor: '#4b5563',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12
                        }
                    }
                }
            });

            // Daily Line Chart
            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
            const dailyChart = new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($daily_sales, 'day')); ?>,
                    datasets: [{
                        label: 'Revenue (Rs )',
                        data: <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>,
                        borderColor: yellowColors[0],
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: yellowColors[0],
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#1f2937',
                            bodyColor: '#4b5563',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                color: '#6b7280',
                                callback: function(value) {
                                    return 'Rs ' + value;
                                }
                            }
                        }
                    }
                }
            });

            // Chart filter buttons
            document.querySelectorAll('.chart-filter').forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    document.querySelectorAll('.chart-filter').forEach(b => {
                        b.classList.remove('active', 'bg-yellow-50', 'text-yellow-600');
                        b.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    this.classList.remove('bg-gray-100', 'text-gray-600');
                    this.classList.add('active', 'bg-yellow-50', 'text-yellow-600');

                    if (filter === 'revenue') {
                        combinedChart.data.datasets[0].hidden = false;
                        combinedChart.data.datasets[1].hidden = true;
                    } else {
                        combinedChart.data.datasets[0].hidden = true;
                        combinedChart.data.datasets[1].hidden = false;
                    }
                    combinedChart.update();
                });
            });

            // Refresh dashboard function
            window.refreshDashboard = function() {
                const refreshBtn = document.querySelector('button[onclick="refreshDashboard()"]');
                const originalHtml = refreshBtn.innerHTML;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                refreshBtn.disabled = true;
                refreshBtn.classList.remove('pulse-glow');

                // Add refreshing animation to cards
                document.querySelectorAll('.animate-fade-in-up').forEach(card => {
                    card.style.animation = 'none';
                    setTimeout(() => {
                        card.style.animation = '';
                    }, 10);
                });

                setTimeout(() => {
                    refreshBtn.innerHTML = originalHtml;
                    refreshBtn.disabled = false;
                    refreshBtn.classList.add('pulse-glow');

                    showNotification('Dashboard data refreshed successfully!', 'success');
                }, 2000);
            };

            // Notification function
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

            // Add animations to cards on scroll
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

            document.querySelectorAll('.stat-card, .chart-container').forEach(card => {
                observer.observe(card);
            });

            // Auto-refresh every 60 seconds
            setInterval(() => {
                console.log('Auto-refreshing dashboard...');
            }, 60000);
        });
    </script>
</body>

</html>