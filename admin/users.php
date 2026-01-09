<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch all pharmacists with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Total count for pagination
$total_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role='pharmacist'");
$total_row = mysqli_fetch_assoc($total_result);
$total_users = $total_row['total'];
$total_pages = ceil($total_users / $limit);

// Fetch users with limit
$result = mysqli_query($conn, "SELECT * FROM users WHERE role='pharmacist' ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// Get active pharmacists (last login within 15 minutes)
$active_result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT u.id) as active_count 
     FROM users u 
     LEFT JOIN user_sessions us ON u.id = us.user_id 
     WHERE (us.last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) OR us.last_activity IS NULL) 
     AND u.role = 'pharmacist'"
);
$active_count = mysqli_fetch_assoc($active_result)['active_count'] ?: 0;

// Get new pharmacists this month
$new_this_month = mysqli_query(
    $conn,
    "SELECT COUNT(*) as new_count 
     FROM users 
     WHERE role='pharmacist' 
     AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
     AND YEAR(created_at) = YEAR(CURRENT_DATE())"
);
$new_count = mysqli_fetch_assoc($new_this_month)['new_count'] ?: 0;

// Get inactive pharmacists (last login > 30 days)
$inactive_result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT u.id) as inactive_count 
     FROM users u 
     LEFT JOIN user_sessions us ON u.id = us.user_id 
     WHERE (us.last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY) OR us.last_activity IS NULL) 
     AND u.role = 'pharmacist'"
);
$inactive_count = mysqli_fetch_assoc($inactive_result)['inactive_count'] ?: 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacists Management - MediCare Pharma</title>
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
            --accent-blue: #3b82f6;
            --accent-teal: #14b8a6;
            --accent-purple: #8b5cf6;
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

        .gradient-gray {
            background: linear-gradient(135deg, var(--primary-gray), var(--primary-gray-light));
        }

        .gradient-mixed {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-gray));
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
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

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
                            Pharmacists <span class="gradient-text">Management</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-users text-yellow-500"></i>
                            <span>Manage all registered pharmacists in the system</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-calendar-alt text-gray-500"></i>
                            <span><?php echo date('F j, Y'); ?></span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0">
                        <a href="add_user.php"
                            class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-user-plus"></i>
                            <span>Add New Pharmacist</span>
                            <i class="fas fa-arrow-right text-yellow-100 text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mx-6 my-6">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl gradient-yellow flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-md text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-full">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $total_users; ?></h3>
                    <p class="text-gray-600 mb-3">Registered Pharmacists</p>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="gradient-yellow h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-check text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Active</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $active_count; ?></h3>
                    <p class="text-gray-600 mb-3">Currently Active</p>
                    <div class="flex items-center text-sm text-green-500">
                        <i class="fas fa-signal mr-1"></i>
                        <span>Online now</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-plus text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">New</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $new_count; ?></h3>
                    <p class="text-gray-600 mb-3">This Month</p>
                    <div class="flex items-center text-sm text-purple-500">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>Monthly growth</span>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-clock text-white text-xl"></i>
                        </div>
                        <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1 rounded-full">Inactive</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $inactive_count; ?></h3>
                    <p class="text-gray-600 mb-3">Need Attention</p>
                    <div class="flex items-center text-sm text-amber-500">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span>Last login > 30 days</span>
                    </div>
                </div>
            </div>

            <!-- Pharmacists Table -->
            <div class="glass-card mx-6 rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.5s">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25 flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">All Pharmacists</h3>
                        <p class="text-sm text-gray-600">Showing <?php echo min($limit, $total_users - $offset); ?> of <?php echo $total_users; ?> pharmacists</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative">
                            <input type="text"
                                id="searchInput"
                                placeholder="Search pharmacists..."
                                class="pl-10 pr-4 py-2 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition bg-white/80 shadow-sm">
                            <i class="fas fa-search absolute left-3 top-3 text-yellow-400"></i>
                        </div>

                        <!-- Filter Button -->
                        <button class="flex items-center space-x-2 px-4 py-2 border border-yellow-200 rounded-lg hover:bg-yellow-50 transition bg-white/80 shadow-sm">
                            <i class="fas fa-filter text-yellow-500"></i>
                            <span class="text-sm text-gray-700">Filter</span>
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-yellow-50 to-yellow-25">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center space-x-2">
                                        <span>Pharmacist</span>
                                        <i class="fas fa-sort text-yellow-400 cursor-pointer hover:text-yellow-600"></i>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Contact Info
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Joined Date
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-yellow-50">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr class="table-row hover:bg-yellow-25 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-4">
                                                <div class="w-10 h-10 rounded-full gradient-yellow flex items-center justify-center text-white font-semibold shadow">
                                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></h4>
                                                    <p class="text-sm text-gray-500">ID: <?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <?php if (!empty($row['phone'])): ?>
                                                    <div class="flex items-center space-x-2 text-gray-700">
                                                        <i class="fas fa-phone text-sm text-yellow-400"></i>
                                                        <span class="text-sm"><?php echo htmlspecialchars($row['phone']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['email'])): ?>
                                                    <div class="flex items-center space-x-2 text-gray-700">
                                                        <i class="fas fa-envelope text-sm text-yellow-400"></i>
                                                        <span class="text-sm"><?php echo htmlspecialchars($row['email']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="inline-flex items-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 shadow-sm">
                                                    <i class="fas fa-circle text-xs mr-1"></i>
                                                    Active
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <div class="text-gray-800 font-medium"><?php echo date('M j, Y', strtotime($row['created_at'])); ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <!-- Edit Button -->
                                                <a href="edit_user.php?id=<?php echo $row['id']; ?>"
                                                    class="inline-flex items-center space-x-1 px-3 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-edit text-sm group-hover:rotate-12 transition-transform"></i>
                                                    <span class="text-sm font-medium">Edit</span>
                                                </a>

                                                <!-- Delete Button with Modal Trigger -->
                                                <button onclick="showDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')"
                                                    class="inline-flex items-center space-x-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors group shadow-sm">
                                                    <i class="fas fa-trash-alt text-sm"></i>
                                                    <span class="text-sm font-medium">Delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto">
                                            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                                                <i class="fas fa-users text-yellow-400 text-2xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">No Pharmacists Found</h4>
                                            <p class="text-gray-600 mb-6">Get started by adding your first pharmacist to the system.</p>
                                            <a href="add_user.php"
                                                class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 inline-flex items-center space-x-2 shadow">
                                                <i class="fas fa-user-plus"></i>
                                                <span>Add First Pharmacist</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>"
                                        class="px-4 py-2 border border-yellow-200 rounded-lg hover:bg-yellow-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                        <i class="fas fa-chevron-left text-sm text-yellow-500"></i>
                                        <span class="text-sm text-gray-700">Previous</span>
                                    </a>
                                <?php endif; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>"
                                        class="px-4 py-2 border border-yellow-200 rounded-lg hover:bg-yellow-50 transition flex items-center space-x-2 bg-white/80 shadow-sm">
                                        <span class="text-sm text-gray-700">Next</span>
                                        <i class="fas fa-chevron-right text-sm text-yellow-500"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mx-6 my-8">
                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center shadow">
                            <i class="fas fa-file-export text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-1">Export Data</h4>
                            <p class="text-sm text-gray-600">Export pharmacists list as CSV or PDF</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.7s">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-xl bg-green-100 flex items-center justify-center shadow">
                            <i class="fas fa-bell text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-1">Notifications</h4>
                            <p class="text-sm text-gray-600">Set up alerts for new registrations</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.8s">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-xl bg-purple-100 flex items-center justify-center shadow">
                            <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-1">Activity Reports</h4>
                            <p class="text-sm text-gray-600">View pharmacist activity analytics</p>
                        </div>
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
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Pharmacist</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete <span id="deleteUserName" class="font-semibold text-yellow-600"></span>?
                    This action cannot be undone.
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a id="deleteConfirmLink"
                        href="#"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                        Delete
                    </a>
                </div>
            </div>
        </div>
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

        // Delete modal functions
        function showDeleteModal(id, name) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteConfirmLink').href = `delete_user.php?id=${id}`;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });

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

        // Sort functionality
        document.querySelectorAll('th .fa-sort').forEach(sortIcon => {
            sortIcon.addEventListener('click', function() {
                const th = this.closest('th');
                const colIndex = Array.from(th.parentNode.children).indexOf(th);
                const tbody = th.closest('table').querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr:not([style*="display: none"])'));

                const isAsc = !this.classList.contains('fa-sort-up');
                this.classList.toggle('fa-sort-up', isAsc);
                this.classList.toggle('fa-sort-down', !isAsc);

                rows.sort((a, b) => {
                    const aText = a.children[colIndex].textContent.trim();
                    const bText = b.children[colIndex].textContent.trim();

                    if (isAsc) {
                        return aText.localeCompare(bText);
                    } else {
                        return bText.localeCompare(aText);
                    }
                });

                rows.forEach(row => tbody.appendChild(row));
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
    </script>
</body>

</html>