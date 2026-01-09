<?php
// session_start();
require_once "config/db.php";

// Redirect logged-in users to their dashboard
if (isset($_SESSION['role'])) {
    header("Location: " . $_SESSION['role'] . "/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Pharma - Pharmacy Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for stats -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-yellow: #704a08ff;
            --primary-yellow-light: #d49b0aff;
            --primary-yellow-dark: #d97706;
            --primary-gray: #6b7280;
            --primary-gray-light: #9ca3af;
            --primary-gray-dark: #4b5563;
            --accent-teal: #14b8a6;
            --accent-purple: #8b5cf6;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 50%, #fef3c7 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .gradient-bg {
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

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(245, 158, 11, 0.15);
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

        .feature-icon {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(251, 191, 36, 0.1));
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>
    <div class="yellow-blob bottom-40 right-40 animate-float" style="animation-delay: 2s; width: 200px; height: 200px;"></div>

    <!-- Navbar -->
    <?php include "includes/navbar.php"; ?>

    <!-- Hero Section -->
    <section class="gradient-bg text-white relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-32 -translate-y-32"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-48 translate-y-48"></div>
            <div class="absolute top-1/2 left-1/2 w-80 h-80 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="animate-fade-in-up">
                    <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full mb-6">
                        <span class="text-sm font-medium">🏥 Trusted by Pharmacies Nationwide</span>
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                    </div>

                    <h1 class="text-5xl lg:text-6xl font-bold mb-6 leading-tight">
                        Professional
                        <span class="text-yellow-300">Pharmacy</span><br>
                        Management System
                    </h1>

                    <p class="text-xl text-yellow-100 mb-8 leading-relaxed">
                        Streamline your pharmacy operations with our powerful black & yellow themed solution.
                        Manage inventory, sales, and prescriptions with unprecedented efficiency.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 mb-10">
                        <a href="/auth/login.php"
                            class="group bg-white text-yellow-600 px-8 py-4 rounded-xl font-semibold text-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 flex items-center justify-center space-x-3">
                            <span>Login to Dashboard</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform"></i>
                        </a>
                        <a href="#features"
                            class="group border-2 border-white text-white px-8 py-4 rounded-xl font-semibold text-lg hover:bg-white/10 transition-all duration-300 flex items-center justify-center space-x-3">
                            <i class="fas fa-star text-xl"></i>
                            <span>View Features</span>
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-sm">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-shield-alt text-yellow-300"></i>
                            <span>Secure & Reliable</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-bolt text-yellow-300"></i>
                            <span>Fast Performance</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-headset text-yellow-300"></i>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                </div>

                <div class="relative animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="glass-card rounded-2xl p-2 animate-float">
                        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 shadow-2xl">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 gradient-yellow rounded-lg flex items-center justify-center">
                                        <i class="fas fa-pills text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-white">MediCare Dashboard</h3>
                                        <p class="text-xs text-gray-300">Live Preview</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                                    <p class="text-xs text-gray-300">Online</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-gray-800 p-4 rounded-lg">
                                    <p class="text-sm text-gray-300">Total Medicines</p>
                                    <p class="text-2xl font-bold text-yellow-400">2,458</p>
                                </div>
                                <div class="bg-gray-800 p-4 rounded-lg">
                                    <p class="text-sm text-gray-300">Today's Sales</p>
                                    <p class="text-2xl font-bold text-yellow-400">=45,820</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-shopping-cart text-yellow-400 text-sm"></i>
                                        </div>
                                        <span class="text-sm font-medium text-gray-200">New Order Received</span>
                                    </div>
                                    <span class="text-xs text-gray-400">2 min ago</span>
                                </div>

                                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-prescription text-yellow-400 text-sm"></i>
                                        </div>
                                        <span class="text-sm font-medium text-gray-200">Prescription Processed</span>
                                    </div>
                                    <span class="text-xs text-gray-400">5 min ago</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Elements -->
                    <div class="absolute -top-6 -left-6 w-24 h-24 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full opacity-20 blur-xl"></div>
                    <div class="absolute -bottom-6 -right-6 w-32 h-32 bg-gradient-to-r from-gray-600 to-gray-800 rounded-full opacity-20 blur-xl"></div>
                </div>
            </div>
        </div>

        <!-- Wave Divider -->
        <div class="absolute bottom-0 left-0 right-0">
            <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 60L60 50C120 40 240 20 360 20C480 20 600 40 720 50C840 60 960 60 1080 50C1200 40 1320 20 1380 10L1440 0V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0V60Z" fill="#fef3c7" />
            </svg>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-16 bg-gradient-to-b from-yellow-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="stat-card rounded-2xl p-8 text-center animate-fade-in-up">
                    <div class="text-4xl font-bold text-yellow-600 mb-2">500+</div>
                    <div class="text-gray-600">Pharmacies Trust Us</div>
                    <div class="w-16 h-1 gradient-yellow mx-auto mt-4 rounded-full"></div>
                </div>

                <div class="stat-card rounded-2xl p-8 text-center animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="text-4xl font-bold text-gray-600 mb-2">24/7</div>
                    <div class="text-gray-600">Support Available</div>
                    <div class="w-16 h-1 gradient-gray mx-auto mt-4 rounded-full"></div>
                </div>

                <div class="stat-card rounded-2xl p-8 text-center animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="text-4xl font-bold text-yellow-600 mb-2">99.9%</div>
                    <div class="text-gray-600">Uptime Guarantee</div>
                    <div class="w-16 h-1 gradient-yellow mx-auto mt-4 rounded-full"></div>
                </div>

                <div class="stat-card rounded-2xl p-8 text-center animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="text-4xl font-bold text-gray-600 mb-2">10M+</div>
                    <div class="text-gray-600">Transactions Processed</div>
                    <div class="w-16 h-1 gradient-mixed mx-auto mt-4 rounded-full"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section id="features" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Core <span class="gradient-text">Features</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Everything you need to manage your pharmacy efficiently with our black & yellow themed interface.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="card-hover bg-gradient-to-br from-yellow-50 to-white p-8 rounded-2xl border border-yellow-100 animate-fade-in-up">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-boxes text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Inventory Management</h3>
                    <p class="text-gray-600 mb-6">
                        Track stock levels, expiry dates, and batch numbe= with real-time updates.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Automatic expiry alerts
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Low stock notifications
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Batch tracking
                        </li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="card-hover bg-gradient-to-br from-gray-50 to-white p-8 rounded-2xl border border-gray-100 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-shopping-cart text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Sales Management</h3>
                    <p class="text-gray-600 mb-6">
                        Process sales quickly with automatic billing and receipt generation.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Quick billing
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Tax calculation
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Sales reports
                        </li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="card-hover bg-gradient-to-br from-yellow-50 to-white p-8 rounded-2xl border border-yellow-100 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-chart-line text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Analytics Dashboard</h3>
                    <p class="text-gray-600 mb-6">
                        Get insights with beautiful charts and reports in black & yellow theme.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Sales analytics
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Revenue reports
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Inventory insights
                        </li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="card-hover bg-gradient-to-br from-gray-50 to-white p-8 rounded-2xl border border-gray-100 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-use= text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">User Management</h3>
                    <p class="text-gray-600 mb-6">
                        Manage pharmacists and administrato= with role-based access control.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Role-based access
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Activity logging
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Secure authentication
                        </li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="card-hover bg-gradient-to-br from-yellow-50 to-white p-8 rounded-2xl border border-yellow-100 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-file-prescription text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Prescription Management</h3>
                    <p class="text-gray-600 mb-6">
                        Handle prescriptions efficiently with digital records and tracking.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Digital records
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Patient history
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Refill tracking
                        </li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="card-hover bg-gradient-to-br from-gray-50 to-white p-8 rounded-2xl border border-gray-100 animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-bell text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Smart Alerts</h3>
                    <p class="text-gray-600 mb-6">
                        Stay informed with automated alerts for low stock and expiry dates.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Low stock alerts
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Expiry warnings
                        </li>
                        <li class="flex items-center text-gray-700">
                            <i class="fas fa-check-circle text-yellow-500 mr-3"></i>
                            Sales notifications
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Preview -->
    <section class="py-24 bg-gradient-to-b from-gray-900 to-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    <span class="text-yellow-400">Professional</span> Dashboard
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    Experience our intuitive black & yellow themed dashboard with real-time data visualization.
                </p>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-6 border-b border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 gradient-yellow rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-pie text-white"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-white">Live Dashboard Preview</h3>
                                <p class="text-sm text-gray-300">Interactive charts & statistics</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                            <span class="text-sm text-gray-300">Live Data</span>
                        </div>
                    </div>
                </div>

                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-gray-800/50 p-6 rounded-xl">
                            <div class="text-2xl font-bold text-yellow-400">=12,450</div>
                            <div class="text-sm text-gray-300">Today's Revenue</div>
                        </div>
                        <div class="bg-gray-800/50 p-6 rounded-xl">
                            <div class="text-2xl font-bold text-yellow-400">45</div>
                            <div class="text-sm text-gray-300">Today's Sales</div>
                        </div>
                        <div class="bg-gray-800/50 p-6 rounded-xl">
                            <div class="text-2xl font-bold text-yellow-400">287</div>
                            <div class="text-sm text-gray-300">Low Stock Items</div>
                        </div>
                        <div class="bg-gray-800/50 p-6 rounded-xl">
                            <div class="text-2xl font-bold text-yellow-400">15</div>
                            <div class="text-sm text-gray-300">Expiring Soon</div>
                        </div>
                    </div>

                    <div class="bg-gray-800/30 rounded-xl p-6">
                        <canvas id="previewChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Setup -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Get Started in <span class="gradient-text">Minutes</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Simple setup process to get your pharmacy management system running quickly.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 relative">
                <!-- Connecting Line -->
                <div class="hidden md:block absolute top-16 left-1/4 right-1/4 h-1 bg-gradient-to-r from-yellow-200 to-gray-200"></div>

                <!-- Step 1 -->
                <div class="relative z-10 animate-fade-in-up">
                    <div class="w-20 h-20 rounded-full gradient-yellow flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold shadow-lg">
                        1
                    </div>
                    <div class="stat-card p-8 rounded-2xl">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Login</h3>
                        <p class="text-gray-600 mb-6">
                            Access your account with secure authentication.
                        </p>
                        <div class="flex justify-center">
                            <i class="fas fa-sign-in-alt text-4xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="relative z-10 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="w-20 h-20 rounded-full gradient-gray flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold shadow-lg">
                        2
                    </div>
                    <div class="stat-card p-8 rounded-2xl">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Setup</h3>
                        <p class="text-gray-600 mb-6">
                            Configure your pharmacy settings and inventory.
                        </p>
                        <div class="flex justify-center">
                            <i class="fas fa-cog text-4xl text-gray-600"></i>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="relative z-10 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="w-20 h-20 rounded-full gradient-mixed flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold shadow-lg">
                        3
                    </div>
                    <div class="stat-card p-8 rounded-2xl">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Manage</h3>
                        <p class="text-gray-600 mb-6">
                            Start managing your pharmacy operations efficiently.
                        </p>
                        <div class="flex justify-center">
                            <i class="fas fa-rocket text-4xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 gradient-bg text-white relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 left-10 w-64 h-64 bg-white rounded-full"></div>
            <div class="absolute bottom-10 right-10 w-80 h-80 bg-white rounded-full"></div>
        </div>

        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">
                Ready to Transform Your Pharmacy?
            </h2>
            <p class="text-xl text-yellow-100 mb-10 max-w-2xl mx-auto">
                Experience the power of our black & yellow themed pharmacy management system.
                Login now to access your dashboard.
            </p>

            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                <a href="/auth/login.php"
                    class="bg-white text-yellow-600 px-10 py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 flex items-center space-x-3 pulse-glow">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login to Dashboard</span>
                </a>
            </div>

            <div class="mt-10 grid grid-cols-3 gap-8 max-w-xl mx-auto">
                <div class="text-center">
                    <div class="text-3xl font-bold mb-2">Secure</div>
                    <div class="text-sm text-yellow-200">Authentication</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold mb-2">Real-time</div>
                    <div class="text-sm text-yellow-200">Updates</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold mb-2">Professional</div>
                    <div class="text-sm text-yellow-200">Interface</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include "includes/footer.php"; ?>

    <!-- Scroll to top button -->
    <button id="scrollToTop" class="fixed bottom-8 right-8 w-12 h-12 gradient-yellow text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110 hidden z-50">
        <i class="fas fa-arrow-up text-lg"></i>
    </button>

    <script>
        // Scroll to top button
        const scrollToTopBtn = document.getElementById('scrollToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.remove('hidden');
            } else {
                scrollToTopBtn.classList.add('hidden');
            }
        });

        scrollToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Initialize preview chart
        document.addEventListener('DOMContentLoaded', function() {
            const previewCtx = document.getElementById('previewChart').getContext('2d');
            new Chart(previewCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Daily Revenue (=)',
                        data: [8500, 10200, 12450, 9800, 15600, 14200, 11800],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#9ca3af',
                                font: {
                                    size: 14
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(156, 163, 175, 0.2)'
                            },
                            ticks: {
                                color: '#9ca3af'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(156, 163, 175, 0.2)'
                            },
                            ticks: {
                                color: '#9ca3af',
                                callback: function(value) {
                                    return '=' + value;
                                }
                            }
                        }
                    }
                }
            });

            // Add scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            document.querySelectorAll('.card-hover, .stat-card').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>

</html>