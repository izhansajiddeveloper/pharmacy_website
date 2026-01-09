<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Set role-based permissions
$can_edit = ($_SESSION['role'] === 'pharmacist');
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle form submissions (Pharmacist only)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_expense'])) {
        $expense_type = mysqli_real_escape_string($conn, $_POST['expense_type']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);

        $query = "INSERT INTO expenses (expense_type, description, amount, expense_date, payment_method, category, created_by, notes) 
                  VALUES ('$expense_type', '$description', '$amount', '$expense_date', '$payment_method', '$category', '$user_id', '$notes')";

        if (mysqli_query($conn, $query)) {
            $success_message = "Expense added successfully!";
        } else {
            $error_message = "Error adding expense: " . mysqli_error($conn);
        }
    }

    if (isset($_POST['update_expense'])) {
        $id = mysqli_real_escape_string($conn, $_POST['expense_id']);
        $expense_type = mysqli_real_escape_string($conn, $_POST['expense_type']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);

        $query = "UPDATE expenses SET 
                  expense_type = '$expense_type',
                  description = '$description',
                  amount = '$amount',
                  expense_date = '$expense_date',
                  payment_method = '$payment_method',
                  category = '$category',
                  notes = '$notes'
                  WHERE id = '$id'";

        if (mysqli_query($conn, $query)) {
            $success_message = "Expense updated successfully!";
        } else {
            $error_message = "Error updating expense: " . mysqli_error($conn);
        }
    }

    if (isset($_POST['delete_expense'])) {
        $id = mysqli_real_escape_string($conn, $_POST['expense_id']);

        $query = "DELETE FROM expenses WHERE id = '$id'";

        if (mysqli_query($conn, $query)) {
            $success_message = "Expense deleted successfully!";
        } else {
            $error_message = "Error deleting expense: " . mysqli_error($conn);
        }
    }
}

// Fetch expenses with filters
$where_clause = "1=1";
if (isset($_GET['type']) && $_GET['type'] !== 'all') {
    $type = mysqli_real_escape_string($conn, $_GET['type']);
    $where_clause .= " AND expense_type = '$type'";
}
if (isset($_GET['category']) && $_GET['category'] !== 'all') {
    $category = mysqli_real_escape_string($conn, $_GET['category']);
    $where_clause .= " AND category = '$category'";
}
if (isset($_GET['month']) && $_GET['month'] !== 'all') {
    $month = mysqli_real_escape_string($conn, $_GET['month']);
    $where_clause .= " AND DATE_FORMAT(expense_date, '%Y-%m') = '$month'";
}

$query = "SELECT e.*, u.name as created_by_name 
          FROM expenses e 
          LEFT JOIN users u ON e.created_by = u.id 
          WHERE $where_clause 
          ORDER BY expense_date DESC";
$result = mysqli_query($conn, $query);
$total_expenses = mysqli_num_rows($result);

// Calculate statistics
$stats_query = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_expenses,
        SUM(amount) as total_amount,
        AVG(amount) as avg_expense,
        MIN(expense_date) as first_expense,
        MAX(expense_date) as last_expense,
        SUM(CASE WHEN expense_type = 'salary' THEN amount ELSE 0 END) as salary_total,
        SUM(CASE WHEN expense_type = 'utility' THEN amount ELSE 0 END) as utility_total,
        SUM(CASE WHEN expense_type = 'transport' THEN amount ELSE 0 END) as transport_total,
        SUM(CASE WHEN expense_type = 'store_expense' THEN amount ELSE 0 END) as store_total,
        SUM(CASE WHEN expense_type = 'internet' THEN amount ELSE 0 END) as internet_total,
        SUM(CASE WHEN expense_type = 'other' THEN amount ELSE 0 END) as other_total
    FROM expenses
");
$stats = mysqli_fetch_assoc($stats_query);

// Get monthly expenses
$monthly_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(expense_date, '%Y-%m') as month, 
           SUM(amount) as total_amount,
           COUNT(*) as expense_count
    FROM expenses
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

// Get expense types for filter
$expense_types = ['salary', 'utility', 'transport', 'store_expense', 'internet', 'other'];

// Get unique categories
$categories_query = mysqli_query($conn, "SELECT DISTINCT category FROM expenses ORDER BY category");
$categories = [];
while ($row = mysqli_fetch_assoc($categories_query)) {
    $categories[] = $row['category'];
}

// Get months for filter
$months_query = mysqli_query($conn, "
    SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m') as month
    FROM expenses
    ORDER BY month DESC
");
$months = [];
while ($row = mysqli_fetch_assoc($months_query)) {
    $months[] = $row['month'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

        .red-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
        }

        .badge-salary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-utility {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-transport {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-store {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-internet {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-other {
            background: linear-gradient(135deg, #6b7280, #4b5563);
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
    <div class="red-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include ($role === 'admin') ? "siderbar.php" : "includes/pharmacist_sidebar.php"; ?>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Pharmacy <span class="gradient-text">Expenses</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-money-bill-wave <?php echo $can_edit ? 'text-green-500' : 'text-blue-500'; ?>"></i>
                            <span><?php echo $can_edit ? 'Manage all pharmacy expenses' : 'View pharmacy expenses'; ?></span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-chart-bar text-purple-500"></i>
                            <span>Total: Rs <?php echo number_format($stats['total_amount'] ?: 0, 2); ?></span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <?php if ($can_edit): ?>
                            <button onclick="showAddModal()"
                                class="gradient-red text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-plus"></i>
                                <span>Add New Expense</span>
                                <i class="fas fa-arrow-right text-red-100 text-sm"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-red flex items-center justify-center shadow-lg">
                            <i class="fas fa-indian-rupee-sign text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-red-600 bg-red-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['total_amount'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Expenses</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-red h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Salaries</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['salary_total'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Salary Expenses</p>
                    <div class="flex items-center text-sm text-blue-500">
                        <i class="fas fa-percentage mr-1"></i>
                        <span><?php echo $stats['total_amount'] > 0 ? number_format(($stats['salary_total'] / $stats['total_amount']) * 100, 1) : 0; ?>% of total</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-bolt text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1 text-right">
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Utilities</span>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Internet</span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format(($stats['utility_total'] + $stats['internet_total']) ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Utilities & Internet</p>
                    <div class="flex items-center text-sm text-green-500">
                        <i class="fas fa-home mr-1"></i>
                        <span>Store: Rs <?php echo number_format($stats['store_total'] ?: 0, 2); ?></span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Average</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['avg_expense'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Average Expense</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-receipt mr-1"></i>
                        <span><?php echo number_format($stats['total_expenses'] ?: 0); ?> total expenses</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="glass-card mx-6 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
                    <h3 class="text-lg font-semibold text-gray-800">Filter Expenses</h3>
                    <div class="flex flex-wrap gap-4">
                        <select id="typeFilter" class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none bg-white/80 shadow-sm">
                            <option value="all">All Types</option>
                            <?php foreach ($expense_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo (isset($_GET['type']) && $_GET['type'] === $type) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="categoryFilter" class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none bg-white/80 shadow-sm">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] === $category) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="monthFilter" class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none bg-white/80 shadow-sm">
                            <option value="all">All Months</option>
                            <?php foreach ($months as $month):
                                $date = DateTime::createFromFormat('Y-m', $month);
                            ?>
                                <option value="<?php echo $month; ?>" <?php echo (isset($_GET['month']) && $_GET['month'] === $month) ? 'selected' : ''; ?>>
                                    <?php echo $date ? $date->format('F Y') : $month; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button onclick="applyFilters()" class="px-4 py-2 gradient-blue text-white rounded-lg hover:shadow-lg transition shadow">
                            Apply Filters
                        </button>
                        <button onclick="resetFilters()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition shadow-sm">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Expenses Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.6s">
                <div class="px-6 py-4 border-b border-red-100 bg-gradient-to-r from-red-50 to-red-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">All Expenses</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo $total_expenses; ?> expense records</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search by description or amount..."
                                class="pl-10 pr-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-red-400"></i>
                        </div>

                        <button onclick="exportToExcel()"
                            class="px-4 py-2 border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                            <i class="fas fa-file-excel text-green-500"></i>
                            <span class="text-sm text-gray-700">Export Excel</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar max-h-[600px]">
                    <table class="w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gradient-to-r from-red-50 to-red-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Expense Details
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Type & Category
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Amount Details
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Date & Payment
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <?php echo $can_edit ? 'Actions' : 'Created By'; ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-red-50">
                            <?php if ($total_expenses > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    $expense_date = new DateTime($row['expense_date']);
                                    $badge_class = 'badge-' . $row['expense_type'];
                                ?>
                                    <tr class="table-row hover:bg-red-25 transition-colors" id="expense-row-<?php echo $row['id']; ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl gradient-red flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas fa-receipt text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-800 text-lg mb-1">
                                                        <?php echo htmlspecialchars($row['description']); ?>
                                                    </h4>
                                                    <p class="text-sm text-gray-500 mb-2">
                                                        ID: <span class="font-mono"><?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                    </p>
                                                    <?php if (!empty($row['notes'])): ?>
                                                        <p class="text-sm text-gray-600 italic">
                                                            <i class="fas fa-sticky-note text-gray-400 mr-1"></i>
                                                            <?php echo htmlspecialchars(substr($row['notes'], 0, 50)); ?>
                                                            <?php echo strlen($row['notes']) > 50 ? '...' : ''; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <span class="<?php echo $badge_class; ?>">
                                                    <i class="fas 
                                                        <?php
                                                        switch ($row['expense_type']) {
                                                            case 'salary':
                                                                echo 'fa-users';
                                                                break;
                                                            case 'utility':
                                                                echo 'fa-bolt';
                                                                break;
                                                            case 'transport':
                                                                echo 'fa-car';
                                                                break;
                                                            case 'store_expense':
                                                                echo 'fa-store';
                                                                break;
                                                            case 'internet':
                                                                echo 'fa-wifi';
                                                                break;
                                                            default:
                                                                echo 'fa-file-invoice-dollar';
                                                        }
                                                        ?> 
                                                        mr-1 text-xs">
                                                    </i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $row['expense_type'])); ?>
                                                </span>
                                                <div>
                                                    <p class="text-sm text-gray-600">Category</p>
                                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($row['category']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Amount</span>
                                                    <span class="text-lg font-bold text-red-600">
                                                        Rs <?php echo number_format($row['amount'], 2); ?>
                                                    </span>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-calendar-day mr-1"></i>
                                                    <?php echo $expense_date->format('F'); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="text-sm font-medium text-gray-800">
                                                    <?php echo $expense_date->format('M d, Y'); ?>
                                                </div>
                                                <div class="flex items-center space-x-2 text-sm <?php echo $row['payment_method'] === 'cash' ? 'text-green-600' : 'text-blue-600'; ?>">
                                                    <i class="fas 
                                                        <?php
                                                        switch ($row['payment_method']) {
                                                            case 'cash':
                                                                echo 'fa-money-bill-wave';
                                                                break;
                                                            case 'online':
                                                                echo 'fa-credit-card';
                                                                break;
                                                            case 'cheque':
                                                                echo 'fa-file-signature';
                                                                break;
                                                            default:
                                                                echo 'fa-money-bill-wave';
                                                        }
                                                        ?>">
                                                    </i>
                                                    <span><?php echo ucfirst($row['payment_method']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($can_edit): ?>
                                                <div class="flex flex-col space-y-2">
                                                    <button onclick="showViewModal(<?php echo $row['id']; ?>)"
                                                        class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                        <i class="fas fa-eye text-sm"></i>
                                                        <span class="text-sm font-medium">View</span>
                                                    </button>

                                                    <div class="flex space-x-2">
                                                        <button onclick="showEditModal(<?php echo $row['id']; ?>)"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-edit text-xs"></i>
                                                            <span class="text-xs font-medium">Edit</span>
                                                        </button>

                                                        <button onclick="showDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['description']); ?>')"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-trash-alt text-xs"></i>
                                                            <span class="text-xs font-medium">Delete</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-sm text-gray-600">
                                                    <i class="fas fa-user-circle mr-1"></i>
                                                    <?php echo htmlspecialchars($row['created_by_name'] ?: 'System'); ?>
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $can_edit ? '5' : '5'; ?>" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-file-invoice-dollar text-red-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Expenses Found</h4>
                                            <p class="text-gray-600 mb-6">No expense records found matching your criteria.</p>
                                            <?php if ($can_edit): ?>
                                                <button onclick="showAddModal()"
                                                    class="gradient-red text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add First Expense</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-red-100 bg-gradient-to-r from-red-50 to-red-25">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="text-sm text-gray-500">
                                Showing <?php echo $total_expenses; ?> expenses •
                                <span class="font-medium <?php echo $can_edit ? 'text-green-600' : 'text-blue-600'; ?>">
                                    <?php echo $can_edit ? 'Full Management Access' : 'View Only Access'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            Last updated: <?php echo date('M d, Y h:i A'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Expense Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full transform transition-all animate-fade-in-up">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">Add New Expense</h3>
                        <p class="text-gray-600">Record a new pharmacy expense</p>
                    </div>
                    <button onclick="hideAddModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <form method="POST" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expense Type *</label>
                            <select name="expense_type" required
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="">Select Type</option>
                                <option value="salary">Salary</option>
                                <option value="utility">Utility Bills</option>
                                <option value="transport">Transport</option>
                                <option value="store_expense">Store Expense</option>
                                <option value="internet">Internet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                            <input type="text" name="category" required
                                placeholder="e.g., Pharmacist Salary, Electricity, etc."
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <input type="text" name="description" required
                            placeholder="Enter expense description"
                            class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs) *</label>
                            <input type="number" name="amount" required step="0.01" min="0"
                                placeholder="0.00"
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expense Date *</label>
                            <input type="date" name="expense_date" required
                                value="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select name="payment_method" required
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="cash">Cash</option>
                                <option value="online">Online</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea name="notes" rows="3"
                            placeholder="Add any additional notes..."
                            class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition"></textarea>
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button type="button" onclick="hideAddModal()"
                            class="flex-1 px-4 py-3 border border-red-200 text-gray-700 rounded-xl hover:bg-red-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" name="add_expense"
                            class="flex-1 px-4 py-3 gradient-red text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Add Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full transform transition-all animate-fade-in-up">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">Edit Expense</h3>
                        <p class="text-gray-600">Update expense details</p>
                    </div>
                    <button onclick="hideEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="expense_id" id="edit_expense_id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expense Type *</label>
                            <select name="expense_type" id="edit_expense_type" required
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="">Select Type</option>
                                <option value="salary">Salary</option>
                                <option value="utility">Utility Bills</option>
                                <option value="transport">Transport</option>
                                <option value="store_expense">Store Expense</option>
                                <option value="internet">Internet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                            <input type="text" name="category" id="edit_category" required
                                placeholder="e.g., Pharmacist Salary, Electricity, etc."
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <input type="text" name="description" id="edit_description" required
                            placeholder="Enter expense description"
                            class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs) *</label>
                            <input type="number" name="amount" id="edit_amount" required step="0.01" min="0"
                                placeholder="0.00"
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expense Date *</label>
                            <input type="date" name="expense_date" id="edit_expense_date" required
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select name="payment_method" id="edit_payment_method" required
                                class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition">
                                <option value="cash">Cash</option>
                                <option value="online">Online</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea name="notes" id="edit_notes" rows="3"
                            placeholder="Add any additional notes..."
                            class="w-full px-4 py-2 border border-red-200 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-500 focus:outline-none transition"></textarea>
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button type="button" onclick="hideEditModal()"
                            class="flex-1 px-4 py-3 border border-red-200 text-gray-700 rounded-xl hover:bg-red-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" name="update_expense"
                            class="flex-1 px-4 py-3 gradient-blue text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Update Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Expense Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full transform transition-all animate-fade-in-up">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">Expense Details</h3>
                        <p class="text-gray-600">View complete expense information</p>
                    </div>
                    <button onclick="hideViewModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Expense Type</label>
                            <div id="view_expense_type" class="text-lg font-medium text-gray-800"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Category</label>
                            <div id="view_category" class="text-lg font-medium text-gray-800"></div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Description</label>
                        <div id="view_description" class="text-lg font-medium text-gray-800"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Amount</label>
                            <div class="text-2xl font-bold text-red-600">
                                Rs <span id="view_amount"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Date</label>
                            <div id="view_expense_date" class="text-lg font-medium text-gray-800"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Payment Method</label>
                            <div id="view_payment_method" class="text-lg font-medium text-gray-800"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Created By</label>
                            <div id="view_created_by" class="text-lg font-medium text-gray-800"></div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Notes</label>
                        <div id="view_notes" class="text-gray-600 bg-gray-50 p-3 rounded-lg"></div>
                    </div>

                    <div class="pt-4 border-t">
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-clock mr-1"></i>
                            Created: <span id="view_created_at"></span>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Last Updated: <span id="view_updated_at"></span>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 pt-6">
                    <button onclick="hideViewModal()"
                        class="flex-1 px-4 py-3 gradient-blue text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Expense</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete expense "<span id="deleteExpenseDescription" class="font-semibold text-red-600"></span>"?
                    This action cannot be undone.
                </p>
                <form method="POST" action="" class="flex space-x-3">
                    <input type="hidden" name="expense_id" id="delete_expense_id">
                    <button type="button" onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-red-200 text-gray-700 rounded-xl hover:bg-red-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <button type="submit" name="delete_expense"
                        class="flex-1 px-4 py-3 gradient-red text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                        Delete Expense
                    </button>
                </form>
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

        // Modal functions
        function showAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function hideAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function showEditModal(id) {
            // Fetch expense details via AJAX
            fetch(`ajax/get_expense.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_expense_id').value = data.id;
                        document.getElementById('edit_expense_type').value = data.expense_type;
                        document.getElementById('edit_category').value = data.category;
                        document.getElementById('edit_description').value = data.description;
                        document.getElementById('edit_amount').value = data.amount;
                        document.getElementById('edit_expense_date').value = data.expense_date;
                        document.getElementById('edit_payment_method').value = data.payment_method;
                        document.getElementById('edit_notes').value = data.notes;
                        document.getElementById('editModal').classList.remove('hidden');
                    } else {
                        showNotification('Error loading expense details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading expense details', 'error');
                });
        }

        function hideEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        async function showViewModal(id) {
            // 1. Added 'return' to fetch and fixed the promise chain
            fetch(`ajax/get_expense.php?id=${id}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json(); // Explicitly return the json promise
                })
                .then(res => {
                    // 2. PHP sends data wrapped in 'res.data'
                    if (res.success && res.data) {
                        const item = res.data; // Create a reference to the nested object

                        document.getElementById('view_expense_type').textContent =
                            item.expense_type?.charAt(0).toUpperCase() + item.expense_type?.slice(1).replace('_', ' ');

                        document.getElementById('view_category').textContent = item.category;
                        document.getElementById('view_description').textContent = item.description;
                        document.getElementById('view_amount').textContent = parseFloat(item.amount).toFixed(2);

                        document.getElementById('view_expense_date').textContent =
                            new Date(item.expense_date).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });

                        document.getElementById('view_payment_method').textContent =
                            item.payment_method?.charAt(0).toUpperCase() + item.payment_method?.slice(1);

                        document.getElementById('view_created_by').textContent = item.created_by_name || 'System';
                        document.getElementById('view_notes').textContent = item.notes || 'No notes provided';

                        document.getElementById('view_created_at').textContent = new Date(item.created_at).toLocaleString('en-US');
                        document.getElementById('view_updated_at').textContent = new Date(item.updated_at).toLocaleString('en-US');

                        document.getElementById('viewModal').classList.remove('hidden');
                    } else {
                        showNotification(res.message || 'Error loading details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Could not connect to server', 'error');
                });
        }

        function hideViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function showDeleteModal(id, description) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteExpenseDescription').textContent = description;
            document.getElementById('delete_expense_id').value = id;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.fixed.inset-0').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'addModal') hideAddModal();
                    if (this.id === 'editModal') hideEditModal();
                    if (this.id === 'viewModal') hideViewModal();
                    if (this.id === 'deleteModal') hideDeleteModal();
                }
            });
        });

        // Export to Excel
        function exportToExcel() {
            try {
                const rows = [];
                rows.push(['ID', 'Description', 'Type', 'Category', 'Amount', 'Date', 'Payment Method', 'Notes', 'Created By', 'Created At']);

                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                ?>
                    rows.push([
                        '<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>',
                        '<?php echo addslashes($row['description']); ?>',
                        '<?php echo ucfirst(str_replace('_', ' ', $row['expense_type'])); ?>',
                        '<?php echo addslashes($row['category']); ?>',
                        <?php echo $row['amount']; ?>,
                        '<?php echo $row['expense_date']; ?>',
                        '<?php echo ucfirst($row['payment_method']); ?>',
                        '<?php echo addslashes($row['notes'] ?: ''); ?>',
                        '<?php echo addslashes($row['created_by_name'] ?: 'System'); ?>',
                        '<?php echo $row['created_at']; ?>'
                    ]);
                <?php endwhile; ?>

                const ws = XLSX.utils.aoa_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Expenses Data');

                const today = new Date().toISOString().slice(0, 10);
                XLSX.writeFile(wb, `Pharmacy_Expenses_${today}.xlsx`);

                showNotification('Excel file exported successfully!', 'success');
            } catch (error) {
                console.error('Excel export error:', error);
                showNotification('Error exporting Excel file', 'error');
            }
        }

        // Filter functions
        function applyFilters() {
            const type = document.getElementById('typeFilter').value;
            const category = document.getElementById('categoryFilter').value;
            const month = document.getElementById('monthFilter').value;

            let url = 'expenses.php?';
            if (type !== 'all') url += `type=${type}&`;
            if (category !== 'all') url += `category=${encodeURIComponent(category)}&`;
            if (month !== 'all') url += `month=${month}&`;

            window.location.href = url.slice(0, -1);
        }

        function resetFilters() {
            window.location.href = 'expenses.php';
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

        // Initialize date pickers
        flatpickr("input[type='date']", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });

        // Display messages
        <?php if (isset($success_message)): ?>
            showNotification('<?php echo $success_message; ?>', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            showNotification('<?php echo $error_message; ?>', 'error');
        <?php endif; ?>

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            <?php if ($can_edit): ?>
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    showAddModal();
                }

                if (e.key === 'Escape') {
                    hideAddModal();
                    hideEditModal();
                    hideViewModal();
                    hideDeleteModal();
                }
            <?php endif; ?>
        });
    </script>
</body>

</html>