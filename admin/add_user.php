<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";


if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = false;
$error = '';

if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'pharmacist';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';

    $query = "INSERT INTO users (name, phone, email, password, role, created_at) 
              VALUES ('$name','$phone','$email','$password','$role',NOW())";

    if (mysqli_query($conn, $query)) {
        $success = true;
        $new_user_id = mysqli_insert_id($conn);

        // Reset form fields
        $_POST = array();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta cha=et="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Pharmacist - MediCare Pharma</title>
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
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

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
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Pharmacist Added Successfully!</h3>
                            <p class="text-gray-600 mb-3">New pharmacist has been added to the system with ID: <span class="font-semibold text-yellow-600"><?php echo $new_user_id; ?></span></p>
                            <div class="flex space-x-3">
                                <a href="use=.php" class="inline-flex items-center space-x-2 text-yellow-600 hover:text-yellow-800 font-medium">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Pharmacists</span>
                                </a>
                                <a href="add_user.php" class="inline-flex items-center space-x-2 text-teal-600 hover:text-teal-800 font-medium">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Add Another</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up mb-6 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Error Adding Pharmacist</h3>
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
                            Add New <span class="gradient-text">Pharmacist</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-user-plus text-yellow-500"></i>
                            <span>Register a new pharmacist to access the pharmacy management system</span>
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
                <!-- Left Column - Form -->
                <div class="lg:col-span-2">
                    <div class="glass-card rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s">
                        <div class="px-6 py-4 border-b border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25">
                            <h3 class="text-lg font-semibold text-gray-800">Pharmacist Details</h3>
                            <p class="text-sm text-gray-600">Fill in the required information</p>
                        </div>

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
                                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                            class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                            placeholder="Enter full name"
                                            required>
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user-circle text-yellow-400"></i>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">Enter the pharmacist's full legal name</p>
                                </div>

                                <!-- Contact Information -->
                                <div class="grid md:grid-cols-2 gap-6">
                                    <!-- Phone -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="flex items-center space-x-2">
                                                <i class="fas fa-phone text-teal-500"></i>
                                                <span>Phone Number</span>
                                            </span>
                                        </label>
                                        <div class="relative">
                                            <input type="tel"
                                                name="phone"
                                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                                class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                                placeholder="+91 98765 43210"
                                                required>
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-mobile-alt text-yellow-400"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Primary contact number</p>
                                    </div>

                                    <!-- Email (Optional) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="flex items-center space-x-2">
                                                <i class="fas fa-envelope text-purple-500"></i>
                                                <span>Email Address</span>
                                                <span class="text-xs text-gray-500 font-normal">(Optional)</span>
                                            </span>
                                        </label>
                                        <div class="relative">
                                            <input type="email"
                                                name="email"
                                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                                class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                                placeholder="pharmacist@example.com">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-at text-yellow-400"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">For notifications and recovery</p>
                                    </div>
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="flex items-center space-x-2">
                                            <i class="fas fa-key text-green-500"></i>
                                            <span>Password</span>
                                        </span>
                                    </label>
                                    <div class="relative">
                                        <input type="password"
                                            name="password"
                                            id="password"
                                            class="form-input w-full pl-10 pr-10 py-3 rounded-lg border border-yellow-200 focus:outline-none transition bg-white/80"
                                            placeholder="Create a secure password"
                                            required
                                            oninput="checkPasswordStrength()">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-yellow-400"></i>
                                        </div>
                                        <button type="button"
                                            id="togglePassword"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i class="fas fa-eye text-yellow-400 hover:text-yellow-600"></i>
                                        </button>
                                    </div>

                                    <!-- Password Strength Meter -->
                                    <div class="mt-2">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-500">Password strength</span>
                                            <span id="strengthText" class="text-xs font-medium">Weak</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="strengthMeter" class="password-strength-meter bg-red-500 rounded-full h-2" style="width: 25%"></div>
                                        </div>
                                        <div id="passwordRequirements" class="mt-2 text-xs text-gray-500">
                                            <p>Must contain at least 8 characters with letters and numbers</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Role Information -->
                                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center shadow">
                                            <i class="fas fa-user-md text-yellow-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800">Pharmacist Role</h4>
                                            <p class="text-sm text-gray-600">This user will be added as a pharmacist</p>
                                        </div>
                                    </div>
                                    <div class="space-y-2 text-sm text-gray-600">
                                        <p class="flex items-center">
                                            <i class="fas fa-check-circle text-green-500 mr-2 text-xs"></i>
                                            Can manage prescriptions and inventory
                                        </p>
                                        <p class="flex items-center">
                                            <i class="fas fa-check-circle text-green-500 mr-2 text-xs"></i>
                                            Can process sales and view reports
                                        </p>
                                        <p class="flex items-center">
                                            <i class="fas fa-times-circle text-red-500 mr-2 text-xs"></i>
                                            Cannot access admin settings
                                        </p>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-6 border-t border-yellow-100">
                                    <button type="submit"
                                        name="submit"
                                        class="w-full gradient-yellow text-white py-4 rounded-xl font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] group shadow">
                                        <span class="flex items-center justify-center space-x-3">
                                            <i class="fas fa-user-plus group-hover:rotate-12 transition-transform"></i>
                                            <span>Add Pharmacist</span>
                                            <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform text-yellow-100"></i>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Information -->
                <div class="space-y-6">
                    <!-- Guidelines -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-info-circle text-yellow-500"></i>
                            <span>Guidelines</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mt-0.5 shadow">
                                    <i class="fas fa-check text-yellow-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">Verify phone number format before submission</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mt-0.5 shadow">
                                    <i class="fas fa-check text-yellow-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">Use strong passwords with mixed characte=</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mt-0.5 shadow">
                                    <i class="fas fa-check text-yellow-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">Email is optional but recommended</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mt-0.5 shadow">
                                    <i class="fas fa-check text-yellow-600 text-xs"></i>
                                </div>
                                <p class="text-sm text-gray-600">New use= will receive login credentials</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <a href="use=.php"
                                class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                <span class="text-gray-700">View All Pharmacists</span>
                                <i class="fas fa-arrow-right text-yellow-500"></i>
                            </a>
                            <a href="#"
                                class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                <span class="text-gray-700">Generate Report</span>
                                <i class="fas fa-file-export text-yellow-500"></i>
                            </a>
                            <a href="#"
                                class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition shadow-sm">
                                <span class="text-gray-700">Bulk Import</span>
                                <i class="fas fa-upload text-yellow-500"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center shadow">
                                <i class="fas fa-headset text-green-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Need Help?</h4>
                                <p class="text-sm text-gray-600">Contact support</p>
                            </div>
                        </div>
                        <a href="mailto:support@medicarepharma.com"
                            class="inline-flex items-center space-x-2 text-green-600 hover:text-green-800">
                            <i class="fas fa-envelope"></i>
                            <span>support@medicarepharma.com</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
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

        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ?
                    '<i class="fas fa-eye text-yellow-400 hover:text-yellow-600"></i>' :
                    '<i class="fas fa-eye-slash text-yellow-400 hover:text-yellow-600"></i>';
            });
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const meter = document.getElementById('strengthMeter');
            const text = document.getElementById('strengthText');
            const requirements = document.getElementById('passwordRequirements');

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

            // Update requirements
            const reqChecks = [{
                    test: password.length >= 8,
                    text: 'At least 8 characte='
                },
                {
                    test: /[A-Z]/.test(password),
                    text: 'One uppercase letter'
                },
                {
                    test: /[0-9]/.test(password),
                    text: 'One number'
                },
                {
                    test: /[^A-Za-z0-9]/.test(password),
                    text: 'One special character'
                }
            ];

            const reqHtml = reqChecks.map(req => `
                <p class="flex items-center ={req.test ? 'text-green-500' : 'text-gray-400'}">
                    <i class="fas fa-={req.test ? 'check-circle' : 'circle'} mr-2 text-xs"></i>
                    ={req.text}
                </p>
            `).join('');

            requirements.innerHTML = reqHtml;
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const phone = document.querySelector('input[name="phone"]').value;

            // Simple phone validation
            const phoneRegex = /^[\+]?[1-9][\d\s\-\(\)\.]{8,}=/;
            if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
                e.preventDefault();
                showNotification('Please enter a valid phone number', 'error');
                return;
            }

            // Password length check
            if (password.length < 8) {
                e.preventDefault();
                showNotification('Password must be at least 8 characte= long', 'error');
                return;
            }

            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding Pharmacist...';
            submitBtn.disabled = true;

            // Re-enable after 5 seconds if something goes wrong
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Initialize password strength check
        checkPasswordStrength();

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
                notification.style.transform ='translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay =`${index * 0.1}s`;
            });
        });
    </script>
</body>

</html>