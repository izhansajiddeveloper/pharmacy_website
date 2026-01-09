<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Handle form submissions
$message = '';
$message_type = '';

// Update pharmacy settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // In a real app, you would update settings in database
    $message = "Settings updated successfully!";
    $message_type = "success";
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all password fields!";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match!";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters long!";
        $message_type = "error";
    } else {
        // In a real app, verify current password and update in database
        $message = "Password changed successfully!";
        $message_type = "success";
    }
}

// Get current user info
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($user_query);

// Get system stats
$stats_query = mysqli_query(
    $conn,
    "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM medicines) as total_medicines,
        (SELECT COUNT(*) FROM stock_batches) as total_batches,
        (SELECT COUNT(*) FROM sales) as total_sales"
);
$stats = mysqli_fetch_assoc($stats_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        .gradient-red {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
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

        .teal-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-teal), #0d9488);
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

        .setting-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .setting-card:hover {
            border-left-color: var(--primary-yellow);
            transform: translateX(5px);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: var(--accent-green);
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary-gray-dark);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-yellow);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-gray-dark);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            border: 1px solid #d1d5db;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background-color: #f9fafb;
            border-color: var(--primary-yellow);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="teal-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

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
                            System <span class="gradient-text">Settings</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-cogs text-teal-500"></i>
                            <span>Manage system preferences, security, and configurations</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-shield text-blue-500"></i>
                            <span>Admin Access Only</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex items-center space-x-3">
                        <div class="flex items-center space-x-2 px-4 py-2 bg-blue-50 rounded-lg">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo ucfirst($user['role']); ?></p>
                            </div>
                        </div>
                        <button onclick="showSystemInfo()"
                            class="px-4 py-2 border border-yellow-200 text-gray-700 rounded-lg hover:bg-yellow-50 transition font-semibold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-info-circle text-yellow-500"></i>
                            <span>System Info</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notification -->
            <?php if ($message): ?>
                <div class="mx-6 mt-6 animate-fade-in-up">
                    <div class="rounded-xl p-4 <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                        <div class="flex items-center space-x-3">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-lg"></i>
                            <span class="font-medium"><?php echo $message; ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- System Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-blue flex items-center justify-center shadow-lg">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Users</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_users'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Total System Users</p>
                    <div class="flex items-center text-sm text-blue-500">
                        <i class="fas fa-user-shield mr-1"></i>
                        <span>Admins & Pharmacists</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-green flex items-center justify-center shadow-lg">
                            <i class="fas fa-pills text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Medicines</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_medicines'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Registered Medicines</p>
                    <div class="flex items-center text-sm text-green-500">
                        <i class="fas fa-tags mr-1"></i>
                        <span>Various categories & types</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-purple flex items-center justify-center shadow-lg">
                            <i class="fas fa-boxes text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Batches</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_batches'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Stock Batches</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-layer-group mr-1"></i>
                        <span>Active inventory batches</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-receipt text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Transactions</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo number_format($stats['total_sales'] ?: 0); ?></h3>
                    <p class="text-gray-600 mb-3">Total Sales</p>
                    <div class="flex items-center text-sm text-yellow-500">
                        <i class="fas fa-history mr-1"></i>
                        <span>All time transactions</span>
                    </div>
                </div>
            </div>

            <!-- Settings Sections -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mx-6 my-8">
                <!-- General Settings -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                        <i class="fas fa-cog text-blue-500"></i>
                        <span>General Settings</span>
                    </h3>
                    <form method="POST" action="">
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="form-label">Pharmacy Name</label>
                                <input type="text" name="pharmacy_name" value="MediCare Pharma"
                                    class="form-input" placeholder="Enter pharmacy name">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Contact Email</label>
                                <input type="email" name="contact_email" value="contact@medicarepharma.com"
                                    class="form-input" placeholder="Enter contact email">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone_number" value="+91 9876543210"
                                    class="form-input" placeholder="Enter phone number">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="address" rows="3"
                                    class="form-input" placeholder="Enter pharmacy address">123 Medical Street, Health City, HC 123456</textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" name="update_settings" class="btn-primary">
                                    <i class="fas fa-save mr-2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Security Settings -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                        <i class="fas fa-shield-alt text-green-500"></i>
                        <span>Security Settings</span>
                    </h3>
                    <form method="POST" action="">
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password"
                                    class="form-input" placeholder="Enter current password" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password"
                                    class="form-input" placeholder="Enter new password" required>
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password"
                                    class="form-input" placeholder="Confirm new password" required>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" name="change_password" class="btn-primary">
                                    <i class="fas fa-key mr-2"></i> Change Password
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h4 class="font-medium text-gray-800 mb-4">Two-Factor Authentication</h4>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-mobile-alt text-gray-500"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">SMS Verification</p>
                                    <p class="text-xs text-gray-500">Receive OTP via SMS</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                        <i class="fas fa-bell text-yellow-500"></i>
                        <span>Notification Settings</span>
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 setting-card bg-white rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">Low Stock Alerts</p>
                                    <p class="text-sm text-gray-500">Notify when stock is low</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-3 setting-card bg-white rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                    <i class="fas fa-clock text-orange-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">Expiry Alerts</p>
                                    <p class="text-sm text-gray-500">Notify before medicines expire</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-3 setting-card bg-white rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">Sales Reports</p>
                                    <p class="text-sm text-gray-500">Daily sales summary</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-3 setting-card bg-white rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <i class="fas fa-user-plus text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">New User Alerts</p>
                                    <p class="text-sm text-gray-500">Notify when new user registers</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="font-medium text-gray-800 mb-3">Notification Methods</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center space-x-2 p-3 bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" class="rounded text-blue-600" checked>
                                <span class="text-sm text-gray-700">Email</span>
                            </label>
                            <label class="flex items-center space-x-2 p-3 bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" class="rounded text-blue-600">
                                <span class="text-sm text-gray-700">SMS</span>
                            </label>
                            <label class="flex items-center space-x-2 p-3 bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" class="rounded text-blue-600" checked>
                                <span class="text-sm text-gray-700">Push</span>
                            </label>
                            <label class="flex items-center space-x-2 p-3 bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" class="rounded text-blue-600">
                                <span class="text-sm text-gray-700">Browser</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Preferences -->
            <div class="glass-card mx-6 rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center space-x-2">
                    <i class="fas fa-sliders-h text-teal-500"></i>
                    <span>System Preferences</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-800 mb-4">Business Hours</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Opening Time</span>
                                <input type="time" value="09:00" class="px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Closing Time</span>
                                <input type="time" value="21:00" class="px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium text-gray-800 mb-4">Invoice Settings</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Invoice Prefix</span>
                                <input type="text" value="INV-" class="px-3 py-2 border border-gray-300 rounded-lg w-32">
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Tax Rate (%)</span>
                                <input type="number" value="18" min="0" max="100" class="px-3 py-2 border border-gray-300 rounded-lg w-32">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium text-gray-800 mb-2">Data Retention Policy</h4>
                            <p class="text-sm text-gray-500">Automatically delete sales records older than:</p>
                        </div>
                        <select class="px-4 py-2 border border-gray-300 rounded-lg">
                            <option>6 months</option>
                            <option selected>1 year</option>
                            <option>2 years</option>
                            <option>5 years</option>
                            <option>Never</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium text-gray-800 mb-2">Auto Backup</h4>
                            <p class="text-sm text-gray-500">Automatically backup database daily</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button class="btn-secondary">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button class="btn-primary">
                        <i class="fas fa-save mr-2"></i> Save All Preferences
                    </button>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="glass-card mx-6 my-8 rounded-2xl p-6 border border-red-200 bg-red-50 animate-fade-in-up" style="animation-delay: 0.9s">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span>Danger Zone</span>
                </h3>
                <p class="text-gray-600 mb-6">These actions are irreversible. Please proceed with caution.</p>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-red-200">
                        <div>
                            <h4 class="font-medium text-gray-800">Clear All Sales Data</h4>
                            <p class="text-sm text-gray-500">Permanently delete all sales records</p>
                        </div>
                        <button onclick="confirmClearSales()"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                            <i class="fas fa-trash mr-2"></i> Clear Data
                        </button>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-red-200">
                        <div>
                            <h4 class="font-medium text-gray-800">Reset All Settings</h4>
                            <p class="text-sm text-gray-500">Restore all settings to default values</p>
                        </div>
                        <button onclick="confirmResetSettings()"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                            <i class="fas fa-redo mr-2"></i> Reset Settings
                        </button>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-red-200">
                        <div>
                            <h4 class="font-medium text-gray-800">Export Database</h4>
                            <p class="text-sm text-gray-500">Download complete database backup</p>
                        </div>
                        <button onclick="exportDatabase()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            <i class="fas fa-download mr-2"></i> Export Database
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <!-- System Info Modal -->
    <div id="systemInfoModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full animate-fade-in-up">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800">System Information</h3>
                    <button onclick="hideSystemInfo()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">PHP Version</p>
                            <p class="font-medium text-gray-800"><?php echo phpversion(); ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">MySQL Version</p>
                            <p class="font-medium text-gray-800"><?php echo mysqli_get_server_info($conn); ?></p>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Server Software</p>
                        <p class="font-medium text-gray-800"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Database Size</p>
                            <p class="font-medium text-gray-800">Calculating...</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-500">Last Backup</p>
                            <p class="font-medium text-gray-800"><?php echo date('M d, Y H:i'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="hideSystemInfo()"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

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

        // System Info Modal
        function showSystemInfo() {
            document.getElementById('systemInfoModal').classList.remove('hidden');
        }

        function hideSystemInfo() {
            document.getElementById('systemInfoModal').classList.add('hidden');
        }

        // Danger zone actions
        function confirmClearSales() {
            if (confirm('Are you sure you want to clear all sales data? This action cannot be undone.')) {
                alert('Sales data cleared successfully!');
                // In real app, make AJAX call to clear sales
            }
        }

        function confirmResetSettings() {
            if (confirm('Are you sure you want to reset all settings to default values?')) {
                alert('Settings reset successfully!');
                // In real app, make AJAX call to reset settings
            }
        }

        function exportDatabase() {
            alert('Preparing database export...');
            // In real app, trigger database export
            window.location.href = 'export_database.php';
        }

        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const passwordInputs = this.querySelectorAll('input[type="password"]');
                passwordInputs.forEach(input => {
                    if (input.value.length < 6 && input.value.length > 0) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        input.focus();
                        return false;
                    }
                });
            });
        });

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
        };
    </script>
</body>

</html>