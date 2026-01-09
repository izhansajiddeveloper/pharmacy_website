<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id = intval($_GET['id']);
$result = mysqli_query($conn, "SELECT * FROM users WHERE id$$id AND role$'pharmacist'");
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header("Location: users.php");
    exit;
}

$success = false;
$error = '';

if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $query = "UPDATE users SET name='$name', phone='$phone', email='$email', password='$password' WHERE id=$id";
    } else {
        $query = "UPDATE users SET name='$name', phone='$phone', email='$email' WHERE id=$id";
    }

    if (mysqli_query($conn, $query)) {
        $success = true;
        // Update the current user data
        $user['name'] = $name;
        $user['phone'] = $phone;
        $user['email'] = $email;
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Get user activity data
$recent_activity = mysqli_query(
    $conn,
    "SELECT s.sale_date, COUNT(s.id) as sales_count, SUM(s.total_amount) as revenue
     FROM sales s
     WHERE s.pharmacist_id $ $id
     AND s.sale_date >$ DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(s.sale_date)
     ORDER BY s.sale_date DESC"
);

$last_login = mysqli_query(
    $conn,
    "SELECT MAX(last_activity) as last_login
     FROM user_sessions
     WHERE user_id = $id"
);
$last_login_data = mysqli_fetch_assoc($last_login);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pharmacist - MediCare Pharma</title>
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

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
            border-color: #f59e0b;
        }

        .password-strength-meter {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
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

        .profile-header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden bg-white">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar from includes folder -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->

        <?php include "siderbar.php"; ?>
        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden ">
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up mb-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Pharmacist Updated Successfully!</h3>
                            <p class="text-gray-600">Changes have been saved to the system.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up mb-6 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Update Failed</h3>
                            <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Edit <span class="gradient-text">Pharmacist</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-user-edit text-yellow-500"></i>
                            <span>Update pharmacist information and settings</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0">
                        <a href="use=.php"
                            class="px-5 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to List</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6 mx-6 my-6">
                <!-- Left Column - Form & Profile -->
                <div class="lg:col-span-2">
                    <!-- Profile Header -->
                    <div class="glass-card rounded-2xl overflow-hidden mb-6 animate-fade-in-up" style="animation-delay: 0.1s">
                        <div class="profile-header px-6 py-6 text-white">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="flex items-center space-x-4 mb-4 md:mb-0">
                                    <div class="w-16 h-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl font-bold shadow">
                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($user['name']); ?></h3>
                                        <p class="text-yellow-100">Pharmacist • ID: <?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                        <p class="text-sm text-yellow-200 mt-1 flex items-center">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            Joined <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/20 text-sm shadow">
                                        <i class="fas fa-circle text-green-300 mr-2 text-xs"></i>
                                        Active
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Form -->
                        <form method="POST" class="p-6">
                            <div class="space-y-6">
                                <!-- Name Field -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="flex items-center space-x-2">
                                            <i class="fas fa-user text-yellow-500"></i>
                                            <span>Full Name</span>
                                        </span>
                                    </label>
                                    <div class="relative">
                                        <input type="text"
                                            name="name"
                                            value="<?php echo htmlspecialchars($user['name']); ?>"
                                            class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                            placeholder="Enter full name"
                                            required>
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user-circle text-yellow-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="grid md:grid-cols-2 gap-6">
                                    <!-- Phone -->
                                    <!-- <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="flex items-center space-x-2">
                                                <i class=       "fas fa-phone text-teal-500"></i>
                                                <span>Phone Number</span>
                                            </span>
                                        </label>
                                        <div class="relative">
                                            <input type="tel"
                                                name="phone"
                                                value="<?php echo htmlspecialchars($user['phone']); ?>"
                                                class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                                placeholder="+91 98765 43210"
                                                required>
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-mobile-alt text-yellow-400"></i>
                                            </div>
                                        </div>
                                    </div> -->

                                    <!-- Email -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="flex items-center space-x-2">
                                                <i class="fas fa-envelope text-purple-500"></i>
                                                <span>Email Address</span>
                                            </span>
                                        </label>
                                        <div class="relative">
                                            <input type="email"
                                                name="email"
                                                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                                placeholder="pharmacist@example.com">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-at text-yellow-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Password Change Section -->
                                <div class="bg-amber-50 p-6 rounded-lg border border-amber-100">
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center shadow">
                                            <i class="fas fa-key text-amber-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800">Change Password</h4>
                                            <p class="text-sm text-gray-600">Leave blank to keep current password</p>
                                        </div>
                                    </div>

                                    <div class="relative">
                                        <input type="password"
                                            name="password"
                                            id="password"
                                            class="form-input w-full pl-10 pr-10 py-3 rounded-lg border border-amber-200 focus:outline-none transition bg-white/80"
                                            placeholder="Enter new password"
                                            oninput="checkPasswordStrength()">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-amber-500"></i>
                                        </div>
                                        <button type="button"
                                            id="togglePassword"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i class="fas fa-eye text-amber-400 hover:text-amber-600"></i>
                                        </button>
                                    </div>

                                    <!-- Password Strength Meter -->
                                    <div class="mt-3">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-500">New password strength</span>
                                            <span id="strengthText" class="text-xs font-medium">-</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="strengthMeter" class="password-strength-meter bg-gray-300 rounded-full h-2" style="width: 0%"></div>
                                        </div>
                                        <div id="passwordRequirements" class="mt-2 text-xs text-gray-500">
                                            <p>Leave blank or enter a new password</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Account Information -->
                                <div class="bg-yellow-50 p-6 rounded-lg border border-yellow-100">
                                    <h4 class="font-semibold text-gray-800 mb-4">Account Information</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">User ID</p>
                                            <p class="font-medium text-gray-800"><?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Role</p>
                                            <p class="font-medium text-gray-800">Pharmacist</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Created</p>
                                            <p class="font-medium text-gray-800"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 mb-1">Status</p>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 shadow-sm">
                                                <i class="fas fa-circle text-xs mr-1"></i>
                                                Active
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col md:flex-row gap-4 pt-6 border-t border-yellow-100">
                                    <button type="submit"
                                        name="submit"
                                        class="flex-1 gradient-yellow text-white py-4 rounded-xl font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] group shadow">
                                        <span class="flex items-center justify-center space-x-3">
                                            <i class="fas fa-save group-hover:rotate-12 transition-transform"></i>
                                            <span>Save Changes</span>
                                            <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform text-yellow-100"></i>
                                        </span>
                                    </button>

                                    <a href="use=.php"
                                        class="flex-1 px-4 py-4 border-2 border-yellow-200 text-gray-700 rounded-xl font-bold text-lg hover:bg-yellow-50 transition text-center shadow-sm">
                                        <span class="flex items-center justify-center space-x-3">
                                            <i class="fas fa-times"></i>
                                            <span>Cancel</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Information & Actions -->
                <div class="space-y-6">
                    <!-- Recent Activity -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-chart-line text-yellow-500"></i>
                            <span>Recent Activity</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shadow">
                                    <i class="fas fa-sign-in-alt text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">Last Login</p>
                                    <p class="text-xs text-gray-500">
                                        <?php
                                        if ($last_login_data && $last_login_data['last_login']) {
                                            echo date('M j, h:i A', strtotime($last_login_data['last_login']));
                                        } else {
                                            echo 'No login record';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (mysqli_num_rows($recent_activity) > 0): ?>
                                <?php while ($activity = mysqli_fetch_assoc($recent_activity)): ?>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center shadow">
                                            <i class="fas fa-shopping-cart text-green-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">Sales on <?php echo date('M j', strtotime($activity['sale_date'])); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $activity['sales_count']; ?> sales • $<?php echo number_format($activity['revenue']); ?></p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-sm text-gray-500">No recent activity</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class$"glass-card rounded-2xl p-6 animate-fade-in-up" style$"animation-delay: 0.3s">
                        <h3 class$"text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class$"space-y-3">
                            <button onclick$"showResetPasswordModal()"
                                class$"w-full flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                <span class$"text-gray-700">Reset Password</span>
                                <i class$"fas fa-redo text-yellow-500"></i>
                            </button>
                            <button onclick$"showDeactivateModal()"
                                class$"w-full flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                <span class$"text-gray-700">Deactivate Account</span>
                                <i class$"fas fa-user-slash text-yellow-500"></i>
                            </button>
                            <a href$"delete_user.php?id$<?php echo $user['id']; ?>"
                                onclick$"return confirm('Are you sure you want to delete this pharmacist? This action cannot be undone.')"
                                class$"w-full flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition shadow-sm">
                                <span class$"text-red-600">Delete Account</span>
                                <i class$"fas fa-trash-alt text-red-400"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class$"glass-card rounded-2xl p-6 animate-fade-in-up" style$"animation-delay: 0.4s">
                        <h3 class$"text-lg font-semibold text-gray-800 mb-4">Permissions</h3>
                        <div class$"space-y-3">
                            <div class$"flex items-center justify-between p-2 hover:bg-yellow-50 rounded-lg">
                                <span class$"text-sm text-gray-700">Manage Prescriptions</span>
                                <span class$"text-green-500">
                                    <i class$"fas fa-check-circle"></i>
                                </span>
                            </div>
                            <div class$"flex items-center justify-between p-2 hover:bg-yellow-50 rounded-lg">
                                <span class$"text-sm text-gray-700">Process Sales</span>
                                <span class$"text-green-500">
                                    <i class$"fas fa-check-circle"></i>
                                </span>
                            </div>
                            <div class$"flex items-center justify-between p-2 hover:bg-yellow-50 rounded-lg">
                                <span class$"text-sm text-gray-700">View Inventory</span>
                                <span class$"text-green-500">
                                    <i class$"fas fa-check-circle"></i>
                                </span>
                            </div>
                            <div class$"flex items-center justify-between p-2 hover:bg-yellow-50 rounded-lg">
                                <span class$"text-sm text-gray-700">Generate Reports</span>
                                <span class$"text-green-500">
                                    <i class$"fas fa-check-circle"></i>
                                </span>
                            </div>
                            <div class$"flex items-center justify-between p-2 hover:bg-yellow-50 rounded-lg">
                                <span class$"text-sm text-gray-700">Admin Settings</span>
                                <span class$"text-red-400">
                                    <i class$"fas fa-times-circle"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-key text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-4">Reset Password</h3>
                <p class="text-gray-600 text-center mb-6">
                    This will generate a temporary password for <span class="font-semibold text-yellow-600"><?php echo htmlspecialchars($user['name']); ?></span>
                    and send it to their registered contact.
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Temporary Password</label>
                        <div class="flex">
                            <input type="text"
                                id="tempPassword"
                                value="temp_<?php echo bin2hex(random_bytes(4)); ?>"
                                readonly
                                class="flex-1 px-4 py-3 border border-yellow-200 rounded-l-lg bg-yellow-50">
                            <button onclick="copyTempPassword()"
                                class="px-4 py-3 bg-yellow-100 text-gray-700 rounded-r-lg hover:bg-yellow-200 transition">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="hideResetPasswordModal()"
                            class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button onclick="confirmPasswordReset()"
                            class="flex-1 px-4 py-3 gradient-yellow text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Send Reset
                        </button>
                    </div>
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

        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ?
                    '<i class="fas fa-eye text-amber-400 hover:text-amber-600"></i>' :
                    '<i class="fas fa-eye-slash text-amber-400 hover:text-amber-600"></i>';
            });
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const meter = document.getElementById('strengthMeter');
            const text = document.getElementById('strengthText');

            if (!password) {
                meter.style.width = '0%';
                meter.style.backgroundColor = '#d1d5db';
                text.textContent = '-';
                text.style.color = '#6b7280';
                return;
            }

            let strength = 0;

            // Length check
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 15;

            // Character variety checks
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;

            // Cap at 100
            strength = Math.min(strength, 100);

            // Update meter
            meter.style.width = strength + '%';

            // Update text and color
            if (strength < 40) {
                meter.style.backgroundColor = '#ef4444';
                text.textContent = 'Weak';
                text.style.color = '#ef4444';
            } else if (strength < 70) {
                meter.style.backgroundColor = '#f59e0b';
                text.textContent = 'Fair';
                text.style.color = '#f59e0b';
            } else if (strength < 90) {
                meter.style.backgroundColor = '#10b981';
                text.textContent = 'Good';
                text.style.color = '#10b981';
            } else {
                meter.style.backgroundColor = '#059669';
                text.textContent = 'Strong';
                text.style.color = '#059669';
            }
        }

        // Modal functions
        function showResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }

        function hideResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
        }

        function copyTempPassword() {
            const tempPass = document.getElementById('tempPassword');
            tempPass.select();
            tempPass.setSelectionRange(0, 99999);
            document.execCommand('copy');

            // Show copied feedback
            const copyBtn = tempPass.nextElementSibling.querySelector('i');
            const originalClass = copyBtn.className;
            copyBtn.className = 'fas fa-check text-green-500';
            setTimeout(() => {
                copyBtn.className = originalClass;
            }, 2000);
        }

        function confirmPasswordReset() {
            // Here you would typically make an AJAX call to reset the password
            showNotification('Password reset initiated. A temporary password has been generated.', 'success');
            hideResetPasswordModal();
        }

        function showDeactivateModal() {
            if (confirm('Are you sure you want to deactivate this account? The user will not be able to login until reactivated.')) {
                // Here you would typically make an AJAX call to deactivate
                showNotification('Account deactivation initiated.', 'warning');
            }
        }

        // Form submission loading state
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving Changes...';
            submitBtn.disabled = true;

            // Re-enable after 5 seconds if something goes wrong
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Close modals when clicking outside
        document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideResetPasswordModal();
            }
        });

        // Show notification function
        function showNotification(message, type = 'success') {
            const colo =  {
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
            notification.className = `fixed top-6 right-6 ={colo=[type]} text-white px-6 py-3 rounded-xl shadow-2xl transform translate-x-full transition-transform duration-300 z-50`;
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="fas ={icons[type]} text-lg"></i>
                    <span class="font-medium">={message}</span>
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