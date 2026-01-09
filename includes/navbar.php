<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Current path
$current_path = $_SERVER['REQUEST_URI'];

// Check if user is logged in
$isLoggedIn = isset($_SESSION['role']);
$userRole = $isLoggedIn ? $_SESSION['role'] : null;

// ALWAYS point Home / Logo to public index.php
$homePath = "/pharmacy_system/index.php";

// Dashboard path (only used for Dashboard button)
$dashboardPath = $isLoggedIn
    ? "/pharmacy_system/{$userRole}/dashboard.php"
    : "/pharmacy_system/auth/login.php"; // fallback if somehow used when not logged in
?>

<!-- Navbar -->
<nav class="bg-white shadow-lg border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <!-- Logo: ALWAYS goes to public home -->
            <div class="flex items-center space-x-3">
                <a href="<?php echo htmlspecialchars($homePath); ?>" class="flex items-center no-underline">
                    <img src="https://cdn-icons-png.flaticon.com/512/206/206853.png" alt="Pharmacy Logo" class="h-8 w-8">
                    <h1 class="ml-2 text-xl font-bold text-blue-700">
                        MediCare<span class="text-green-600">Pharma</span>
                    </h1>
                </a>
            </div>

            <!-- Center Nav Links (Desktop) -->
            <div class="hidden md:flex items-center space-x-6">
                <?php
                $navLinks = [
                    'Home' => $homePath,
                    'About' => '/pharmacy_system/about.php',
                    'Services' => '/pharmacy_system/services.php',
                    'Contact' => '/pharmacy_system/contact.php'
                ];
                foreach ($navLinks as $name => $href):
                ?>
                    <a href="<?php echo htmlspecialchars($href); ?>"
                        class="text-gray-700 hover:text-blue-600 font-medium transition-colors duration-200 flex items-center space-x-1 group <?php echo strpos($current_path, basename($href)) !== false ? 'nav-active text-blue-600' : ''; ?>">
                        <i class="fas <?php
                                        echo $name === 'Home' ? 'fa-home' : ($name === 'About' ? 'fa-info-circle' : ($name === 'Services' ? 'fa-pills' : 'fa-phone-alt'));
                                        ?> text-gray-400 group-hover:text-blue-600 <?php echo strpos($current_path, basename($href)) !== false ? 'text-blue-600' : ''; ?>"></i>
                        <span><?php echo htmlspecialchars($name); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Right Section -->
            <div class="flex items-center space-x-4">
                <?php if ($isLoggedIn): ?>
                    <!-- Dashboard Button -->
                    <?php
                    // Check if the current URL does NOT contain the dashboard path
                    if (strpos($_SERVER['REQUEST_URI'], $dashboardPath) === false):
                    ?>
                        <a href="<?php echo htmlspecialchars($dashboardPath); ?>"
                            class="hidden md:flex items-center space-x-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:shadow-lg transition-all duration-200 hover:from-blue-700 hover:to-blue-800">
                            <i class="fas fa-tachometer-alt text-sm"></i>
                            <span class="font-medium">Dashboard</span>
                            <span class="text-xs bg-white/20 px-2 py-0.5 rounded"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span>
                        </a>
                    <?php endif; ?>


                    <!-- Logout -->
                    <a href="/pharmacy_system/auth/logout.php"
                        class="flex items-center space-x-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200">
                        <i class="fas fa-sign-out-alt text-sm"></i>
                        <span class="font-medium">Logout</span>
                    </a>

                    <!-- Mobile Menu Button -->
                    <div class="md:hidden">
                        <button id="mobile-menu-button" class="text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Login for Guests -->
                    <a href="/pharmacy_system/auth/login.php"
                        class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-5 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 flex items-center space-x-2">
                        <i class="fas fa-sign-in-alt text-sm"></i>
                        <span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Menu (only shown if logged in) -->
    <?php if ($isLoggedIn): ?>
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 shadow-lg">
            <div class="px-4 py-3 space-y-2">
                <?php foreach ($navLinks as $name => $href): ?>
                    <a href="<?php echo htmlspecialchars($href); ?>"
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-700 <?php echo strpos($current_path, basename($href)) !== false ? 'bg-blue-50 text-blue-600' : ''; ?>">
                        <i class="fas <?php
                                        echo $name === 'Home' ? 'fa-home' : ($name === 'About' ? 'fa-info-circle' : ($name === 'Services' ? 'fa-pills' : 'fa-phone-alt'));
                                        ?> text-gray-400 w-5 <?php echo strpos($current_path, basename($href)) !== false ? 'text-blue-600' : ''; ?>"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($name); ?></span>
                    </a>
                <?php endforeach; ?>

                <!-- Mobile Dashboard -->

                <a href="<?php echo htmlspecialchars($dashboardPath); ?>"
                    class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-50 text-blue-700 border border-blue-100">
                    <i class="fas fa-tachometer-alt text-blue-600 w-5"></i>
                    <span class="font-medium">Dashboard</span>
                    <span class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded ml-auto"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span>
                </a>

                <!-- Mobile Logout -->
                <a href="/pharmacy_system/auth/logout.php"
                    class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-red-50 text-red-600 border border-red-100">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span class="font-medium">Logout</span>
                </a>
            </div>
        </div>
    <?php endif; ?>
</nav>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .nav-active {
        color: #2563eb;
        font-weight: 600;
    }

    .nav-active i {
        color: #2563eb;
    }

    #mobile-menu {
        transition: all 0.3s ease;
    }

    a:hover i {
        transform: translateX(2px);
        transition: transform 0.2s ease;
    }

    nav a {
        text-decoration: none;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        if (menuButton && mobileMenu) {
            menuButton.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
            document.addEventListener('click', e => {
                if (!menuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.add('hidden');
                }
            });
        }

        // Highlight active link based on current path
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('nav a[href]:not([href="#"])');
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && currentPath.endsWith(href.split('/').pop())) {
                link.classList.add('nav-active');
            }
        });
    });
</script>