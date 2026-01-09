<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Check if user has access
$is_pharmacist = ($_SESSION['role'] === 'pharmacist');
$is_admin = ($_SESSION['role'] === 'admin');

if (!$is_pharmacist && !$is_admin) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle payment deletion with protection for auto-generated payments
if (isset($_GET['delete_id']) && $is_pharmacist) {
    $delete_id = intval($_GET['delete_id']);

    // Verify the payment belongs to pharmacist if they're not admin
    if ($is_pharmacist) {
        $check_query = "SELECT * FROM payments WHERE id = $delete_id AND created_by = $user_id";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) == 0) {
            $_SESSION['error'] = "You can only delete your own payments!";
            header("Location: payments.php");
            exit;
        }

        $payment_data = mysqli_fetch_assoc($check_result);

        // Prevent deletion of auto-generated payments
        if ($payment_data['is_auto_generated'] == 1) {
            $_SESSION['error'] = "Auto-generated payments cannot be deleted. They are linked to original transactions.";
            header("Location: payments.php");
            exit;
        }
    }

    $delete_query = "DELETE FROM payments WHERE id = $delete_id";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Payment deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting payment!";
    }
    header("Location: payments.php");
    exit;
}

// Fetch all payments with related data
$query = "SELECT 
            p.*,
            u.username as created_by_name,
            COALESCE(s.total_amount, 0) as sale_amount,
            COALESCE(s.discount, 0) as sale_discount,
            (COALESCE(s.total_amount, 0) - COALESCE(s.discount, 0)) as sale_net_amount,
            COALESCE(r.total_price, 0) as return_amount,
            CASE 
                WHEN p.payment_type = 'sale' THEN s.pharmacist_id 
                WHEN p.payment_type = 'return_to_company' THEN r.returned_by 
            END as original_creator_id,
            CASE 
                WHEN p.is_auto_generated = 1 AND p.payment_type = 'sale' THEN CONCAT('SALE-', p.reference_id)
                WHEN p.is_auto_generated = 1 AND p.payment_type = 'return_to_company' THEN CONCAT('RETURN-', p.reference_id)
                ELSE 'MANUAL'
            END as source_type
          FROM payments p
          LEFT JOIN users u ON p.created_by = u.id
          LEFT JOIN sales s ON p.payment_type = 'sale' AND p.reference_id = s.id
          LEFT JOIN returns_to_company r ON p.payment_type = 'return_to_company' AND p.reference_id = r.id
          WHERE 1=1";

// Filter by user if pharmacist
if ($is_pharmacist) {
    $query .= " AND p.created_by = $user_id";
}

$query .= " ORDER BY p.payment_date DESC";

$result = mysqli_query($conn, $query);
$total_payments = mysqli_num_rows($result);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(amount) as total_amount,
    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as completed_amount,
    SUM(CASE WHEN payment_status = 'cancelled' THEN amount ELSE 0 END) as cancelled_amount,
    COUNT(CASE WHEN payment_type = 'sale' THEN 1 END) as sale_payments,
    COUNT(CASE WHEN payment_type = 'return_to_company' THEN 1 END) as return_payments,
    COUNT(CASE WHEN payment_method = 'Cash' THEN 1 END) as cash_payments,
    COUNT(CASE WHEN payment_method = 'Online' THEN 1 END) as online_payments,
    COUNT(CASE WHEN is_auto_generated = 1 THEN 1 END) as auto_generated_payments,
    COUNT(CASE WHEN is_auto_generated = 0 THEN 1 END) as manual_payments
FROM payments";

if ($is_pharmacist) {
    $stats_query .= " WHERE created_by = $user_id";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get today's payments
$today_query = "SELECT 
    COUNT(*) as today_count,
    SUM(amount) as today_amount,
    COUNT(CASE WHEN is_auto_generated = 1 THEN 1 END) as today_auto,
    COUNT(CASE WHEN is_auto_generated = 0 THEN 1 END) as today_manual
FROM payments 
WHERE DATE(payment_date) = CURDATE()";

if ($is_pharmacist) {
    $today_query .= " AND created_by = $user_id";
}

$today_result = mysqli_query($conn, $today_query);
$today = mysqli_fetch_assoc($today_result);

// Get recent payments
$recent_query = "SELECT p.*, u.username as created_by_name
                FROM payments p
                LEFT JOIN users u ON p.created_by = u.id";

if ($is_pharmacist) {
    $recent_query .= " WHERE p.created_by = $user_id";
}

$recent_query .= " ORDER BY p.payment_date DESC LIMIT 5";
$recent_payments = mysqli_query($conn, $recent_query);

// Get payment summary by type
$summary_query = "SELECT 
    payment_type,
    COUNT(*) as count,
    SUM(amount) as total_amount,
    AVG(amount) as avg_amount
FROM payments";

if ($is_pharmacist) {
    $summary_query .= " WHERE created_by = $user_id";
}

$summary_query .= " GROUP BY payment_type ORDER BY total_amount DESC";
$summary_result = mysqli_query($conn, $summary_query);
$payment_summary = [];
while ($row = mysqli_fetch_assoc($summary_result)) {
    $payment_summary[$row['payment_type']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - MediCare Pharma</title>
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

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
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

        .badge-sale {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-return {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-auto {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-manual {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 1rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="blue-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include $role === 'admin' ? "../includes/admin_sidebar.php" : "includes/pharmacist_sidebar.php"; ?>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            <span class="gradient-text">Payments</span> Management
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-credit-card text-blue-500"></i>
                            <span>Automatic & manual payments for sales and returns</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-shield text-green-500"></i>
                            <span><?php echo ucfirst($role); ?> Access Level</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <?php if ($is_pharmacist): ?>
                            <button onclick="showAddModal()"
                                class="gradient-blue text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-plus"></i>
                                <span>Add Manual Payment</span>
                                <i class="fas fa-arrow-right text-blue-100 text-sm"></i>
                            </button>
                        <?php endif; ?>
                        <button onclick="exportToExcel()"
                            class="border border-green-500 text-green-600 px-6 py-3 rounded-xl font-bold hover:bg-green-50 transition-all duration-300 flex items-center space-x-2">
                            <i class="fas fa-file-excel"></i>
                            <span>Export Excel</span>
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
                        <div class="flex flex-col space-y-1 text-right">
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Auto: <?php echo $stats['auto_generated_payments'] ?: 0; ?></span>
                            <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-full">Manual: <?php echo $stats['manual_payments'] ?: 0; ?></span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Rs <?php echo number_format($stats['total_amount'] ?: 0, 2); ?></h3>
                    <p class="text-gray-600 mb-3">Total Payments Amount</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-green h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-exchange-alt text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1 text-right">
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Sales: <?php echo $stats['sale_payments'] ?: 0; ?></span>
                            <span class="text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-full">Returns: <?php echo $stats['return_payments'] ?: 0; ?></span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_payments'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Total Payments</p>
                    <div class="flex items-center text-sm text-blue-500">
                        <i class="fas fa-rupee-sign mr-1"></i>
                        <span>Today: Rs <?php echo number_format($today['today_amount'] ?: 0, 2); ?></span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-chart-pie text-white text-xl"></i>
                        </div>
                        <div class="flex flex-col space-y-1 text-right">
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">Completed: Rs <?php echo number_format($stats['completed_amount'] ?: 0, 2); ?></span>
                            <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full">Pending: Rs <?php echo number_format($stats['pending_amount'] ?: 0, 2); ?></span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($today['today_count'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Today's Payments</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-green-500">
                            <i class="fas fa-robot mr-1"></i>Auto: <?php echo $today['today_auto'] ?: 0; ?>
                        </span>
                        <span class="text-purple-500">
                            <i class="fas fa-hand-paper mr-1"></i>Manual: <?php echo $today['today_manual'] ?: 0; ?>
                        </span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-teal-500 to-teal-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-cog text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Access</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo ucfirst($role); ?></h3>
                    <p class="text-gray-600 mb-3">Your Permission Level</p>
                    <div class="flex items-center text-sm text-teal-500">
                        <i class="fas fa-key mr-1"></i>
                        <span><?php echo $is_pharmacist ? 'Full Management' : 'View Only'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Type Summary -->
            <?php if (!empty($payment_summary)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mx-6 mb-6">
                    <?php foreach ($payment_summary as $type => $summary):
                        $is_sale = $type === 'sale';
                        $bg_color = $is_sale ? 'from-blue-500/10 to-blue-600/10' : 'from-purple-500/10 to-purple-600/10';
                        $text_color = $is_sale ? 'text-blue-600' : 'text-purple-600';
                        $icon = $is_sale ? 'fa-shopping-cart' : 'fa-undo-alt';
                        $type_name = $is_sale ? 'Sale Payments' : 'Return Payments';
                    ?>
                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r <?php echo $is_sale ? 'from-blue-500 to-blue-600' : 'from-purple-500 to-purple-600'; ?> flex items-center justify-center shadow-lg">
                                        <i class="fas <?php echo $icon; ?> text-white text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800"><?php echo $type_name; ?></h3>
                                        <p class="text-sm text-gray-500">Payment Summary</p>
                                    </div>
                                </div>
                                <span class="text-xs font-bold <?php echo $text_color; ?> bg-opacity-20 px-3 py-1 rounded-full">
                                    <?php echo $summary['count']; ?> payments
                                </span>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Total Amount:</span>
                                    <span class="font-bold <?php echo $text_color; ?>">
                                        Rs <?php echo number_format($summary['total_amount'], 2); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Average Payment:</span>
                                    <span class="font-bold text-gray-800">
                                        Rs <?php echo number_format($summary['avg_amount'], 2); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Percentage:</span>
                                    <span class="font-bold text-green-600">
                                        <?php echo $stats['total_amount'] > 0 ? number_format(($summary['total_amount'] / $stats['total_amount']) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full <?php echo $is_sale ? 'bg-gradient-to-r from-blue-500 to-blue-600' : 'bg-gradient-to-r from-purple-500 to-purple-600'; ?>"
                                        style="width: <?php echo $stats['total_amount'] > 0 ? ($summary['total_amount'] / $stats['total_amount']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Payments Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.6s">
                <div class="px-6 py-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-blue-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">All Payments</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo $total_payments; ?> payment records</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search by invoice or amount..."
                                class="pl-10 pr-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-blue-400"></i>
                        </div>

                        <select id="typeFilter"
                            class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="">All Types</option>
                            <option value="sale">Sale Payments</option>
                            <option value="return_to_company">Return Payments</option>
                        </select>

                        <select id="sourceFilter"
                            class="px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <option value="">All Sources</option>
                            <option value="auto">Auto-generated</option>
                            <option value="manual">Manual</option>
                        </select>

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
                                    Payment Details
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Transaction Info
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Amount Details
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Date & Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            <?php if ($total_payments > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    $payment_date = new DateTime($row['payment_date']);
                                    $created_date = new DateTime($row['created_at']);

                                    // Determine badge class based on status
                                    $status_badge = match ($row['payment_status']) {
                                        'completed' => 'badge-completed',
                                        'pending' => 'badge-pending',
                                        'cancelled' => 'badge-cancelled',
                                        default => 'badge-pending'
                                    };

                                    // Determine type badge
                                    $type_badge = $row['payment_type'] === 'sale' ? 'badge-sale' : 'badge-return';
                                    $type_text = $row['payment_type'] === 'sale' ? 'Sale Payment' : 'Return Payment';

                                    // Determine source badge
                                    $source_badge = $row['is_auto_generated'] == 1 ? 'badge-auto' : 'badge-manual';
                                    $source_text = $row['is_auto_generated'] == 1 ? 'Auto' : 'Manual';

                                    // Check if user can edit/delete this payment
                                    $can_edit = $is_pharmacist &&
                                        ($row['created_by'] == $user_id ||
                                            $row['original_creator_id'] == $user_id);

                                    // Auto-generated payments can only be viewed, not edited/deleted
                                    $is_editable = $can_edit && $row['is_auto_generated'] == 0;
                                ?>
                                    <tr class="table-row hover:bg-blue-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start space-x-4">
                                                <div class="w-12 h-12 rounded-xl <?php echo $row['payment_type'] === 'sale' ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-purple-500 to-purple-600'; ?> flex items-center justify-center text-white font-semibold shadow flex-shrink-0">
                                                    <i class="fas <?php echo $row['payment_type'] === 'sale' ? 'fa-shopping-cart' : 'fa-arrow-left'; ?> text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center space-x-2 mb-1">
                                                        <h4 class="font-semibold text-gray-800 text-lg">
                                                            <span class="<?php echo $type_badge; ?> mr-2">
                                                                <?php echo $type_text; ?>
                                                            </span>
                                                        </h4>
                                                        <span class="<?php echo $source_badge; ?>">
                                                            <?php echo $source_text; ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-sm text-gray-500 mb-2">
                                                        Invoice: <span class="font-mono text-blue-600"><?php echo htmlspecialchars($row['invoice_no']); ?></span>
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        Created by: <span class="font-medium"><?php echo htmlspecialchars($row['created_by_name']); ?></span>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Reference ID</span>
                                                    <span class="text-sm font-bold text-blue-600">
                                                        #<?php echo str_pad($row['reference_id'], 6, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Payment Method</span>
                                                    <div class="flex items-center space-x-2 text-sm <?php echo $row['payment_method'] === 'Cash' ? 'text-green-600' : 'text-blue-600'; ?>">
                                                        <i class="fas <?php echo $row['payment_method'] === 'Cash' ? 'fa-money-bill-wave' : 'fa-credit-card'; ?>"></i>
                                                        <span class="font-medium"><?php echo ucfirst($row['payment_method']); ?></span>
                                                    </div>
                                                </div>
                                                <?php if ($row['is_auto_generated'] == 1): ?>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Source</span>
                                                        <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">
                                                            <i class="fas fa-robot mr-1"></i>Auto-generated
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600">Amount Paid</span>
                                                    <span class="text-sm font-bold <?php echo $row['payment_type'] === 'sale' ? 'text-green-600' : 'text-purple-600'; ?>">
                                                        Rs <?php echo number_format($row['amount'], 2); ?>
                                                    </span>
                                                </div>
                                                <?php if ($row['payment_type'] === 'sale' && $row['sale_amount'] > 0): ?>
                                                    <div class="space-y-1">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-xs text-gray-500">Sale Amount:</span>
                                                            <span class="text-xs font-bold text-gray-600">
                                                                Rs <?php echo number_format($row['sale_amount'], 2); ?>
                                                            </span>
                                                        </div>
                                                        <?php if ($row['sale_discount'] > 0): ?>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Discount:</span>
                                                                <span class="text-xs font-bold text-red-600">
                                                                    -Rs <?php echo number_format($row['sale_discount'], 2); ?>
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Net Amount:</span>
                                                                <span class="text-xs font-bold text-blue-600">
                                                                    Rs <?php echo number_format($row['sale_net_amount'], 2); ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($row['payment_type'] === 'return_to_company' && $row['return_amount'] > 0): ?>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Return Amount</span>
                                                        <span class="text-sm font-bold text-gray-600">
                                                            Rs <?php echo number_format($row['return_amount'], 2); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['notes'])): ?>
                                                    <div class="text-xs text-gray-500 italic">
                                                        Note: <?php echo htmlspecialchars(substr($row['notes'], 0, 50)); ?><?php echo strlen($row['notes']) > 50 ? '...' : ''; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-3">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-800"><?php echo $payment_date->format('M d, Y'); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo $payment_date->format('h:i A'); ?></div>
                                                </div>
                                                <div>
                                                    <span class="<?php echo $status_badge; ?>">
                                                        <i class="fas <?php echo $row['payment_status'] === 'completed' ? 'fa-check' : ($row['payment_status'] === 'pending' ? 'fa-clock' : 'fa-times'); ?> mr-1 text-xs"></i>
                                                        <?php echo ucfirst($row['payment_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col space-y-2">
                                                <button onclick="showViewModal(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-eye text-sm"></i>
                                                    <span class="text-sm font-medium">View</span>
                                                </button>

                                                <?php if ($is_editable): ?>
                                                    <div class="flex space-x-2">
                                                        <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-edit text-xs"></i>
                                                            <span class="text-xs font-medium">Edit</span>
                                                        </button>

                                                        <button onclick="showDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['invoice_no']); ?>', <?php echo $row['is_auto_generated'] ? 'true' : 'false'; ?>)"
                                                            class="flex-1 inline-flex items-center justify-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                            <i class="fas fa-trash-alt text-xs"></i>
                                                            <span class="text-xs font-medium">Delete</span>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-credit-card text-blue-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Payments Found</h4>
                                            <p class="text-gray-600 mb-6">No payment records available yet.</p>
                                            <?php if ($is_pharmacist): ?>
                                                <button onclick="showAddModal()"
                                                    class="gradient-blue text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add First Payment</span>
                                                </button>
                                            <?php endif; ?>
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
                                Showing <?php echo $total_payments; ?> payments •
                                <span class="font-medium <?php echo $is_pharmacist ? 'text-green-600' : 'text-blue-600'; ?>">
                                    <?php echo $is_pharmacist ? 'Full Management Access' : 'View Only Access'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="exportToPDF()"
                                class="px-4 py-2 border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-pdf text-red-500"></i>
                                <span class="text-sm text-gray-700">Export PDF</span>
                            </button>
                            <button onclick="exportToExcel()"
                                class="px-4 py-2 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                <i class="fas fa-file-excel text-green-500"></i>
                                <span class="text-sm text-gray-700">Export Excel</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="glass-card mx-6 my-8 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                    <i class="fas fa-history text-blue-500"></i>
                    <span>Recent Payments</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <?php if (mysqli_num_rows($recent_payments) > 0): ?>
                        <?php mysqli_data_seek($recent_payments, 0); ?>
                        <?php while ($recent = mysqli_fetch_assoc($recent_payments)):
                            $recent_date = new DateTime($recent['payment_date']);
                        ?>
                            <div class="bg-gradient-to-r from-blue-50 to-white rounded-xl p-4 border border-blue-100 hover:border-blue-200 transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="w-10 h-10 rounded-lg <?php echo $recent['payment_type'] === 'sale' ? 'bg-green-100' : 'bg-purple-100'; ?> flex items-center justify-center">
                                        <i class="fas <?php echo $recent['payment_type'] === 'sale' ? 'fa-shopping-cart text-green-600' : 'fa-arrow-left text-purple-600'; ?>"></i>
                                    </div>
                                    <div class="flex flex-col items-end space-y-1">
                                        <span class="text-xs font-bold <?php echo $recent['payment_type'] === 'sale' ? 'text-green-600' : 'text-purple-600'; ?> bg-opacity-20 px-2 py-1 rounded-full">
                                            <?php echo $recent['payment_type'] === 'sale' ? 'Sale' : 'Return'; ?>
                                        </span>
                                        <span class="text-xs <?php echo $recent['is_auto_generated'] == 1 ? 'text-green-600 bg-green-50' : 'text-purple-600 bg-purple-50'; ?> px-2 py-1 rounded-full">
                                            <?php echo $recent['is_auto_generated'] == 1 ? 'Auto' : 'Manual'; ?>
                                        </span>
                                    </div>
                                </div>
                                <h4 class="font-medium text-gray-800 text-sm mb-1 truncate"><?php echo htmlspecialchars($recent['invoice_no']); ?></h4>
                                <p class="text-sm font-bold text-blue-600 mb-2">Rs <?php echo number_format($recent['amount'], 2); ?></p>
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span><?php echo $recent_date->format('M d, h:i A'); ?></span>
                                    <span><?php echo htmlspecialchars($recent['created_by_name']); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-5 text-center py-4">
                            <p class="text-gray-500">No recent payments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2" id="deleteModalTitle">Delete Payment</h3>
                <p class="text-gray-600 text-center mb-6" id="deleteModalMessage">
                    Are you sure you want to delete payment <span id="deletePaymentInvoice" class="font-semibold text-blue-600"></span>?
                    This action cannot be undone.
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a id="deleteConfirmLink"
                        href="#"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                        Delete Payment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- View Payment Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Payment Details</h3>
                    <button onclick="hideViewModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Invoice Number:</span>
                        <span id="viewInvoiceNo" class="font-bold text-blue-600"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Payment Type:</span>
                        <span id="viewPaymentType" class="font-bold"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Reference ID:</span>
                        <span id="viewReferenceId" class="font-bold text-gray-800"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Amount:</span>
                        <span id="viewAmount" class="text-xl font-bold text-green-600"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Payment Method:</span>
                        <span id="viewPaymentMethod" class="font-bold"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span id="viewStatus" class="font-bold"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Payment Date:</span>
                        <span id="viewPaymentDate" class="font-bold"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Created By:</span>
                        <span id="viewCreatedBy" class="font-bold"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Created At:</span>
                        <span id="viewCreatedAt" class="font-bold"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Payment Source:</span>
                        <span id="viewPaymentSource" class="font-bold"></span>
                    </div>

                    <div id="viewNotesSection" class="pt-4 border-t border-gray-200">
                        <h4 class="text-gray-600 mb-2">Notes:</h4>
                        <p id="viewNotes" class="text-gray-700 bg-gray-50 p-3 rounded-lg"></p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="hideViewModal()"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Payment Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-800">Add New Payment</h3>
                    <button onclick="hideEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <form id="paymentForm" onsubmit="return submitPaymentForm()">
                    <input type="hidden" id="paymentId" name="payment_id">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Payment Type *</label>
                            <select id="paymentType" name="payment_type" required
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                                <option value="">Select Type</option>
                                <option value="sale">Sale Payment</option>
                                <option value="return_to_company">Return to Company Payment</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Reference ID *</label>
                            <input type="number" id="referenceId" name="reference_id" required
                                placeholder="Enter sale ID or return ID"
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Invoice Number *</label>
                            <input type="text" id="invoiceNo" name="invoice_no" required
                                placeholder="Enter invoice number"
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Amount *</label>
                            <input type="number" step="0.01" id="amount" name="amount" required
                                placeholder="Enter amount"
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Payment Method *</label>
                            <select id="paymentMethod" name="payment_method" required
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                                <option value="Cash">Cash</option>
                                <option value="Online">Online</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Payment Status *</label>
                            <select id="paymentStatus" name="payment_status" required
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Payment Date *</label>
                            <input type="datetime-local" id="paymentDate" name="payment_date" required
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Notes</label>
                            <textarea id="notes" name="notes" rows="3"
                                placeholder="Add any notes about this payment"
                                class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500 focus:outline-none transition"></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex space-x-3">
                        <button type="button" onclick="hideEditModal()"
                            class="flex-1 px-4 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                            <span id="submitButtonText">Add Payment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "../includes/footer.php"; ?>

    <script>
        // Modal functions
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Manual Payment';
            document.getElementById('submitButtonText').textContent = 'Add Payment';
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentId').value = '';

            // Set current date/time
            const now = new Date();
            const localDateTime = now.toISOString().slice(0, 16);
            document.getElementById('paymentDate').value = localDateTime;

            document.getElementById('editModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function showEditModal(payment) {
            document.getElementById('modalTitle').textContent = 'Edit Payment';
            document.getElementById('submitButtonText').textContent = 'Update Payment';

            // Fill form with payment data
            document.getElementById('paymentId').value = payment.id;
            document.getElementById('paymentType').value = payment.payment_type;
            document.getElementById('referenceId').value = payment.reference_id;
            document.getElementById('invoiceNo').value = payment.invoice_no;
            document.getElementById('amount').value = payment.amount;
            document.getElementById('paymentMethod').value = payment.payment_method;
            document.getElementById('paymentStatus').value = payment.payment_status;
            document.getElementById('paymentDate').value = payment.payment_date.slice(0, 16);
            document.getElementById('notes').value = payment.notes || '';

            document.getElementById('editModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function showViewModal(payment) {
            // Fill view modal with data
            document.getElementById('viewInvoiceNo').textContent = payment.invoice_no;
            document.getElementById('viewPaymentType').innerHTML =
                `<span class="${payment.payment_type === 'sale' ? 'badge-sale' : 'badge-return'}">${
                    payment.payment_type === 'sale' ? 'Sale Payment' : 'Return Payment'
                }</span>`;
            document.getElementById('viewReferenceId').textContent = '#' + payment.reference_id.toString().padStart(6, '0');
            document.getElementById('viewAmount').textContent = 'Rs ' + parseFloat(payment.amount).toFixed(2);
            document.getElementById('viewPaymentMethod').innerHTML =
                `<span class="${payment.payment_method === 'Cash' ? 'text-green-600' : 'text-blue-600'}">
                    <i class="fas ${payment.payment_method === 'Cash' ? 'fa-money-bill-wave' : 'fa-credit-card'} mr-1"></i>
                    ${payment.payment_method}
                </span>`;

            const statusClass = {
                'completed': 'badge-completed',
                'pending': 'badge-pending',
                'cancelled': 'badge-cancelled'
            } [payment.payment_status] || 'badge-pending';

            document.getElementById('viewStatus').innerHTML =
                `<span class="${statusClass}">
                    <i class="fas ${payment.payment_status === 'completed' ? 'fa-check' : 
                                   payment.payment_status === 'pending' ? 'fa-clock' : 'fa-times'} mr-1"></i>
                    ${payment.payment_status.charAt(0).toUpperCase() + payment.payment_status.slice(1)}
                </span>`;

            const paymentDate = new Date(payment.payment_date);
            document.getElementById('viewPaymentDate').textContent =
                paymentDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

            document.getElementById('viewCreatedBy').textContent = payment.created_by_name;

            const createdAt = new Date(payment.created_at);
            document.getElementById('viewCreatedAt').textContent =
                createdAt.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

            document.getElementById('viewPaymentSource').innerHTML =
                `<span class="${payment.is_auto_generated == 1 ? 'badge-auto' : 'badge-manual'}">
                    ${payment.is_auto_generated == 1 ? 'Auto-generated' : 'Manual Entry'}
                </span>`;

            document.getElementById('viewNotes').textContent = payment.notes || 'No notes available';

            if (!payment.notes) {
                document.getElementById('viewNotesSection').classList.add('hidden');
            } else {
                document.getElementById('viewNotesSection').classList.remove('hidden');
            }

            document.getElementById('viewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function showDeleteModal(id, invoiceNo, isAutoGenerated = false) {
            document.getElementById('deleteModal').classList.add('active');
            document.getElementById('deletePaymentInvoice').textContent = invoiceNo;
            document.getElementById('deleteConfirmLink').href = `payments.php?delete_id=${id}`;

            if (isAutoGenerated) {
                document.getElementById('deleteModalTitle').textContent = 'Cannot Delete Payment';
                document.getElementById('deleteModalMessage').innerHTML =
                    `Payment <span class="font-semibold text-blue-600">${invoiceNo}</span> is auto-generated and cannot be deleted.<br>
                     Auto-generated payments are linked to original transactions.`;
                document.getElementById('deleteConfirmLink').style.display = 'none';
                document.querySelector('#deleteModal .flex-1:nth-child(1)').classList.replace('flex-1', 'w-full');
            } else {
                document.getElementById('deleteModalTitle').textContent = 'Delete Payment';
                document.getElementById('deleteModalMessage').innerHTML =
                    `Are you sure you want to delete payment <span class="font-semibold text-blue-600">${invoiceNo}</span>?<br>
                     This action cannot be undone.`;
                document.getElementById('deleteConfirmLink').style.display = 'block';
                document.querySelector('#deleteModal .flex-1:nth-child(1)').classList.replace('w-full', 'flex-1');
            }

            document.body.style.overflow = 'hidden';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Submit payment form
        function submitPaymentForm() {
            const form = document.getElementById('paymentForm');
            const formData = new FormData(form);
            const paymentId = formData.get('payment_id');

            // Add AJAX request to save/update payment
            fetch('save_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        hideEditModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                });

            return false;
        }

        // Export functions
        function exportToExcel() {
            try {
                const rows = [];
                rows.push(['Invoice No', 'Payment Type', 'Reference ID', 'Amount', 'Payment Method', 'Status', 'Source', 'Payment Date', 'Created By']);

                <?php
                mysqli_data_seek($result, 0);
                while ($row = mysqli_fetch_assoc($result)):
                ?>
                    rows.push([
                        '<?php echo $row['invoice_no']; ?>',
                        '<?php echo $row['payment_type'] === 'sale' ? 'Sale Payment' : 'Return Payment'; ?>',
                        <?php echo $row['reference_id']; ?>,
                        <?php echo $row['amount']; ?>,
                        '<?php echo $row['payment_method']; ?>',
                        '<?php echo ucfirst($row['payment_status']); ?>',
                        '<?php echo $row['is_auto_generated'] == 1 ? 'Auto-generated' : 'Manual'; ?>',
                        '<?php echo $row['payment_date']; ?>',
                        '<?php echo addslashes($row['created_by_name']); ?>'
                    ]);
                <?php endwhile; ?>

                const ws = XLSX.utils.aoa_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Payments Data');

                const today = new Date().toISOString().slice(0, 10);
                XLSX.writeFile(wb, `Payments_${today}.xlsx`);

                showNotification('Excel file exported successfully!', 'success');
            } catch (error) {
                console.error('Excel export error:', error);
                showNotification('Error exporting Excel file', 'error');
            }
        }

        function exportToPDF() {
            showNotification('PDF export feature coming soon!', 'info');
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

        // Filter functionality
        const typeFilter = document.getElementById('typeFilter');
        const sourceFilter = document.getElementById('sourceFilter');
        const dateFilter = document.getElementById('dateFilter');

        function applyFilters() {
            const selectedType = typeFilter?.value;
            const selectedSource = sourceFilter?.value;
            const selectedDate = dateFilter?.value;

            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const typeText = row.cells[0]?.textContent.toLowerCase();
                const sourceText = row.cells[0]?.textContent.toLowerCase();
                const dateText = row.cells[3]?.textContent.toLowerCase();

                let typeMatch = true;
                let sourceMatch = true;
                let dateMatch = true;

                if (selectedType) {
                    typeMatch = selectedType === 'sale' ?
                        typeText.includes('sale payment') :
                        typeText.includes('return payment');
                }

                if (selectedSource) {
                    if (selectedSource === 'auto') {
                        sourceMatch = sourceText.includes('auto');
                    } else if (selectedSource === 'manual') {
                        sourceMatch = sourceText.includes('manual') ||
                            (!sourceText.includes('auto') && row.cells[0]?.querySelector('.badge-auto') === null);
                    }
                }

                if (selectedDate) {
                    dateMatch = dateText.includes(selectedDate);
                }

                row.style.display = typeMatch && sourceMatch && dateMatch ? '' : 'none';
            });
        }

        if (typeFilter) typeFilter.addEventListener('change', applyFilters);
        if (sourceFilter) sourceFilter.addEventListener('change', applyFilters);
        if (dateFilter) dateFilter.addEventListener('change', applyFilters);

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

            // Show success/error messages from PHP session
            <?php if (isset($_SESSION['success'])): ?>
                showNotification('<?php echo $_SESSION['success']; ?>', 'success');
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                showNotification('<?php echo $_SESSION['error']; ?>', 'error');
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>