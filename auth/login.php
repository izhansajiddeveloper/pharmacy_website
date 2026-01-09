<?php
// session_start(); // Make sure session is started
require_once "../config/db.php";

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    header("Location: /" . $_SESSION['role'] . "/dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    // Check user
    $query = "SELECT * FROM users WHERE username='$username' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        // Verify password (plain text now)
        if ($password === $user['password']) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];

            // Redirect to role-based dashboard
            header("Location: /pharmacy_system/" . $user['role'] . "/dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta cha=et="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediCare Pharma Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-yellow: #704a08ff;
            --primary-yellow-light: #d49b0aff;
            --primary-yellow-dark: #d97706;
            --primary-gray: #6b7280;
            --primary-gray-light: #9ca3af;
            --primary-gray-dark: #4b5563;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 50%, #fef3c7 100%);
            min-height: 100vh;
        }

        .login-gradient {
            background: linear-gradient(135deg, var(--primary-yellow) 0%, var(--primary-yellow-dark) 100%);
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

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
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

        .gradient-text {
            background: linear-gradient(45deg, var(--primary-yellow), var(--primary-yellow-dark), var(--primary-gray));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradient 3s ease infinite;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .error-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
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

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            border-color: var(--primary-yellow);
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>
    <div class="yellow-blob bottom-40 right-40 animate-float" style="animation-delay: 2s; width: 200px; height: 200px;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <!-- Login Section -->
    <section class="flex-1 flex items-center justify-center py-12 px-4">
        <div class="max-w-6xl mx-auto w-full">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left Column - Illustration & Info -->
                <div class="hidden lg:block">
                    <div class="relative">
                        <!-- Content -->
                        <div class="relative z-10">
                            <div class="mb-10 animate-fade-in-up">
                                <h1 class="text-5xl font-bold mb-4 text-gray-800">
                                    Welcome to <span class="gradient-text">MediCare Pharma</span>
                                </h1>
                                <p class="text-xl text-gray-600">
                                    Professional pharmacy management with our black & yellow themed dashboard.
                                    Secure access to manage inventory, sales, and prescriptions.
                                </p>
                            </div>

                            <!-- Features List -->
                            <div class="space-y-6">
                                <div class="stat-card p-5 rounded-xl animate-fade-in-up" style="animation-delay: 0.1s">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg gradient-yellow flex items-center justify-center">
                                            <i class="fas fa-shield-alt text-white text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-800 mb-1">Secure Access</h3>
                                            <p class="text-gray-600 text-sm">Role-based authentication and encryption</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="stat-card p-5 rounded-xl animate-fade-in-up" style="animation-delay: 0.2s">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg gradient-gray flex items-center justify-center">
                                            <i class="fas fa-chart-line text-white text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-800 mb-1">Real-time Analytics</h3>
                                            <p class="text-gray-600 text-sm">Live charts and business insights</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="stat-card p-5 rounded-xl animate-fade-in-up" style="animation-delay: 0.3s">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg gradient-yellow flex items-center justify-center">
                                            <i class="fas fa-boxes text-white text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-800 mb-1">Smart Inventory</h3>
                                            <p class="text-gray-600 text-sm">Track stock with expiry alerts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="mt-12 grid grid-cols-3 gap-6">
                                <div class="stat-card text-center p-6 rounded-2xl">
                                    <div class="text-3xl font-bold text-yellow-600 mb-1">500+</div>
                                    <div class="text-sm text-gray-600">Pharmacies</div>
                                </div>
                                <div class="stat-card text-center p-6 rounded-2xl">
                                    <div class="text-3xl font-bold text-gray-600 mb-1">99.9%</div>
                                    <div class="text-sm text-gray-600">Uptime</div>
                                </div>
                                <div class="stat-card text-center p-6 rounded-2xl">
                                    <div class="text-3xl font-bold text-yellow-600 mb-1">24/7</div>
                                    <div class="text-sm text-gray-600">Support</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Login Form -->
                <div>
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <!-- Form Header -->
                        <div class="login-gradient text-white p-8">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                        <i class="fas fa-lock text-xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold">Secure Login</h2>
                                        <p class="text-yellow-100 text-sm">Access your pharmacy dashboard</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="w-3 h-3 bg-green-400 rounded-full pulse-animation"></div>
                                    <p class="text-xs text-yellow-200">System Active</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-center space-x-2 mb-2">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-user-md text-sm"></i>
                                </div>
                                <span class="text-sm">For Pharmacists & Administrato=</span>
                            </div>
                        </div>

                        <!-- Login Form -->
                        <div class="p-8">
                            <?php if ($error): ?>
                                <div id="error-message" class="error-shake mb-6 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 p-4 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-red-800">Login Failed</h4>
                                            <p class="text-red-600 text-sm"><?php echo htmlspecialchars($error); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" id="loginForm" class="space-y-6">
                                <!-- Username Field -->
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium">
                                        <i class="fas fa-user-circle text-yellow-500 mr-2"></i>
                                        Username
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                        <input type="text"
                                            name="username"
                                            class="form-input w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                                            placeholder="Enter your username"
                                            required
                                            autocomplete="username">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Use your registered username</p>
                                </div>

                                <!-- Password Field -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-gray-700 font-medium">
                                            <i class="fas fa-key text-yellow-500 mr-2"></i>
                                            Password
                                        </label>
                                        <a href="/auth/forgot-password.php" class="text-sm text-yellow-600 hover:text-yellow-800 transition">
                                            <i class="fas fa-question-circle mr-1"></i>
                                            Forgot Password?
                                        </a>
                                    </div>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input type="password"
                                            name="password"
                                            id="password"
                                            class="form-input w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                                            placeholder="Enter your password"
                                            required
                                            autocomplete="current-password">
                                        <button type="button"
                                            id="togglePassword"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Minimum 8 characters with letters & numbers</p>
                                </div>

                                <!-- Remember Me & Role Selection -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center">
                                        <input type="checkbox"
                                            id="remember"
                                            name="remember"
                                            class="w-4 h-4 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500">
                                        <label for="remember" class="ml-2 text-gray-700 text-sm">
                                            Remember me
                                        </label>
                                    </div>

                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Login as</label>
                                        <div class="flex space-x-2">
                                            <label class="flex-1">
                                                <input type="radio"
                                                    name="role_type"
                                                    value="pharmacist"
                                                    class="sr-only peer"
                                                    checked>
                                                <div class="p-2 text-center border border-gray-300 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-50 cu=or-pointer transition">
                                                    <i class="fas fa-user-md text-yellow-600 mb-1"></i>
                                                    <div class="text-xs text-gray-700">Pharmacist</div>
                                                </div>
                                            </label>
                                            <label class="flex-1">
                                                <input type="radio"
                                                    name="role_type"
                                                    value="admin"
                                                    class="sr-only peer">
                                                <div class="p-2 text-center border border-gray-300 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-50 cu=or-pointer transition">
                                                    <i class="fas fa-cog text-yellow-600 mb-1"></i>
                                                    <div class="text-xs text-gray-700">Admin</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit"
                                    id="loginButton"
                                    class="w-full gradient-yellow text-white py-3.5 rounded-lg font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] group pulse-glow">
                                    <span class="flex items-center justify-center">
                                        <i class="fas fa-sign-in-alt mr-3 group-hover:rotate-12 transition-transform"></i>
                                        <span>Login to Dashboard</span>
                                        <i class="fas fa-arrow-right ml-3 group-hover:translate-x-2 transition-transform"></i>
                                    </span>
                                </button>

                                <!-- Demo Credentials -->
                                <div class="mt-4 bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                                    <h4 class="text-sm font-semibold text-yellow-800 mb-2 flex items-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Demo Credentials
                                    </h4>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <span class="text-gray-600">Admin:</span>
                                            <div class="text-gray-800 font-mono">admin / admin123</div>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Pharmacist:</span>
                                            <div class="text-gray-800 font-mono">pharmacist / pharma123</div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <!-- Divider -->
                            <!-- <div class="relative my-8">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-gray-200"></div>
                                </div>
                                <div class="relative flex justify-center text-sm">
                                    <span class="px-4 bg-white text-gray-500">Or continue with</span>
                                </div>
                            </div> -->

                            <!-- Alternative Login Options
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button"
                                    class="flex items-center justify-center space-x-2 p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fab fa-google text-gray-600"></i>
                                    <span class="text-sm font-medium text-gray-700">Google</span>
                                </button>
                                <button type="button"
                                    class="flex items-center justify-center space-x-2 p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-fingerprint text-gray-600"></i>
                                    <span class="text-sm font-medium text-gray-700">Biometric</span>
                                </button>
                            </div> -->

                            <!-- Registration Link -->
                            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                                <p class="text-gray-600">
                                    Need access?
                                    <a href="/auth/register.php" class="text-yellow-600 font-semibold hover:text-yellow-800 ml-1">
                                        <i class="fas fa-user-plus mr-1"></i>
                                        Request Access
                                    </a>
                                </p>
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-shield-alt text-yellow-500 mr-1"></i>
                                    Your data is protected with secure authentication
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile View Quick Stats -->
                    <div class="lg:hidden mt-8 grid grid-cols-3 gap-4">
                        <div class="stat-card p-4 rounded-xl text-center">
                            <div class="text-xl font-bold text-yellow-600 mb-1">500+</div>
                            <div class="text-xs text-gray-600">Pharmacies</div>
                        </div>
                        <div class="stat-card p-4 rounded-xl text-center">
                            <div class="text-xl font-bold text-gray-600 mb-1">99.9%</div>
                            <div class="text-xs text-gray-600">Uptime</div>
                        </div>
                        <div class="stat-card p-4 rounded-xl text-center">
                            <div class="text-xl font-bold text-yellow-600 mb-1">24/7</div>
                            <div class="text-xs text-gray-600">Support</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>' : '<i class="fas fa-eye-slash text-gray-400 hover:text-gray-600"></i>';
            });
        }

        // Form validation and interaction
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');

        if (loginForm) {
            // Real-time password strength indicator
            const passwordField = document.getElementById('password');
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    const password = this.value;
                    const strengthText = document.getElementById('password-strength');
                    if (!strengthText) {
                        const strengthDiv = document.createElement('div');
                        strengthDiv.id = 'password-strength';
                        strengthDiv.className = 'text-xs mt-1';
                        this.parentNode.parentNode.appendChild(strengthDiv);
                    }

                    const strength = checkPasswordStrength(password);
                    const strengthDiv = document.getElementById('password-strength');

                    switch (strength) {
                        case 'weak':
                            strengthDiv.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i> Weak password</span>';
                            break;
                        case 'medium':
                            strengthDiv.innerHTML = '<span class="text-yellow-600"><i class="fas fa-exclamation-circle mr-1"></i> Medium strength</span>';
                            break;
                        case 'strong':
                            strengthDiv.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i> Strong password</span>';
                            break;
                    }
                });
            }

            // Form submission animation
            loginForm.addEventListener('submit', function(e) {
                const button = loginButton;
                const originalText = button.innerHTML;

                // Show loading state
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Authenticating...';
                button.classList.remove('hover:scale-[1.02]', 'pulse-glow');

                // Re-enable after 3 seconds (in case of error)
                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    button.classList.add('hover:scale-[1.02]', 'pulse-glow');
                }, 3000);
            });

            // Input focus effects
            const inputs = loginForm.querySelectorAll('input[type="text"], input[type="password"], input[type="email"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-yellow-200');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-yellow-200');
                });
            });
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            // Strong password: at least 8 characters, with lowercase, uppercase, number, and special character
            const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/;

            // Medium password: at least 6 characters, with any two of lowercase, uppercase, or number
            const mediumRegex = /^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9]))).{6,}$/;


            if (strongRegex.test(password)) {
                return 'strong';
            } else if (mediumRegex.test(password)) {
                return 'medium';
            } else {
                return 'weak';
            }
        }

        // Remove error message after 5 seconds
        const errorMessage = document.getElementById('error-message');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0';
                errorMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    errorMessage.remove();
                }, 500);
            }, 5000);
        }

        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField) {
                usernameField.focus();
            }

            // Add floating label effect
            const floatLabels = document.querySelectorAll('input[type="text"], input[type="password"]');
            floatLabels.forEach(input => {
                input.addEventListener('focus', function() {
                    const label = this.previousElementSibling;
                    if (label && label.tagName === 'LABEL') {
                        label.classList.add('text-yellow-600');
                    }
                });

                input.addEventListener('blur', function() {
                    const label = this.previousElementSibling;
                    if (label && label.tagName === 'LABEL') {
                        label.classList.remove('text-yellow-600');
                    }
                });
            });

            // Add fade-in animation to form elements
            const formElements = document.querySelectorAll('#loginForm > div');
            formElements.forEach((element, index) => {
                element.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s forwards`;
                element.style.opacity = '0';
            });
        });

        // CSS Animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>