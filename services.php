<?php
// session_start();
require_once "config/db.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - MediCare Pharma Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(251, 191, 36, 0.3);
            box-shadow: 0 4px 20px rgba(251, 191, 36, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(251, 191, 36, 0.2);
            transition: all 0.3s ease;
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

        .service-tab {
            transition: all 0.3s ease;
        }

        .service-tab.active {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
            color: white;
            transform: scale(1.05);
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
    <section class="gradient-yellow text-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center relative z-10">
            <h1 class="text-5xl md:text-6xl font-bold mb-6">Our Services</h1>
            <p class="text-xl text-yellow-100 max-w-3xl mx-auto mb-8">
                Comprehensive pharmacy management solutions designed to streamline your operations and boost efficiency.
            </p>
            <div class="flex justify-center space-x-4">
                <a href="/auth/login.php" class="bg-white text-yellow-600 px-8 py-3 rounded-xl font-semibold hover:shadow-2xl transition-all duration-300 hover:scale-105">
                    <i class="fas fa-play-circle mr-2"></i>Start Free Trial
                </a>
                <a href="#all-services" class="border-2 border-white text-white px-8 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all duration-300">
                    <i class="fas fa-arrow-down mr-2"></i>Explore Services
                </a>
            </div>
        </div>
    </section>

    <!-- Service Categories -->
    <section id="all-services" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Complete <span class="gradient-text">Pharmacy Solutions</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Everything you need to manage your pharmacy efficiently in one platform.
                </p>
            </div>

            <!-- Service Tabs -->
            <div class="flex flex-wrap justify-center gap-4 mb-12">
                <button class="service-tab active px-6 py-3 rounded-xl font-medium bg-yellow-50 text-yellow-600" data-tab="inventory">
                    <i class="fas fa-boxes mr-2"></i>Inventory
                </button>
                <button class="service-tab px-6 py-3 rounded-xl font-medium bg-gray-100 text-gray-600" data-tab="sales">
                    <i class="fas fa-shopping-cart mr-2"></i>Sales
                </button>
                <button class="service-tab px-6 py-3 rounded-xl font-medium bg-gray-100 text-gray-600" data-tab="prescription">
                    <i class="fas fa-file-prescription mr-2"></i>Prescription
                </button>
                <button class="service-tab px-6 py-3 rounded-xl font-medium bg-gray-100 text-gray-600" data-tab="reports">
                    <i class="fas fa-chart-bar mr-2"></i>Reports
                </button>
                <button class="service-tab px-6 py-3 rounded-xl font-medium bg-gray-100 text-gray-600" data-tab="support">
                    <i class="fas fa-headset mr-2"></i>Support
                </button>
            </div>

            <!-- Service Content -->
            <div class="space-y-16">
                <!-- Inventory Management -->
                <div id="inventory" class="service-content active">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div class="animate-fade-in-up">
                            <div class="inline-flex items-center space-x-2 bg-yellow-100 text-yellow-600 px-4 py-2 rounded-full mb-6">
                                <i class="fas fa-boxes"></i>
                                <span class="font-medium">Core Service</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-800 mb-6">Smart Inventory Management</h3>
                            <p class="text-gray-600 mb-6 leading-relaxed">
                                Keep track of your medicine stock with real-time updates, automatic expiry alerts,
                                and intelligent reorder suggestions. Manage multiple supplie= and batches efficiently.
                            </p>
                            <div class="space-y-4 mb-8">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Real-time Stock Tracking</h4>
                                        <p class="text-gray-600 text-sm">Monitor stock levels with live updates</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Expiry Date Management</h4>
                                        <p class="text-gray-600 text-sm">Automatic alerts for expiring medicines</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Batch Tracking</h4>
                                        <p class="text-gray-600 text-sm">Track medicine batches from purchase to sale</p>
                                    </div>
                                </div>
                            </div>
                            <a href="/auth/login.php" class="gradient-yellow text-white px-8 py-3 rounded-xl font-semibold inline-flex items-center hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <i class="fas fa-play mr-2"></i> Try Inventory Demo
                            </a>
                        </div>
                        <div class="relative animate-fade-in-up" style="animation-delay: 0.2s">
                            <div class="stat-card p-6 rounded-2xl">
                                <div class="aspect-video rounded-xl overflow-hidden bg-gradient-to-br from-yellow-50 to-gray-50 flex items-center justify-center">
                                    <div class="text-center p-8">
                                        <div class="w-20 h-20 gradient-yellow rounded-2xl flex items-center justify-center mx-auto mb-6">
                                            <i class="fas fa-boxes text-white text-3xl"></i>
                                        </div>
                                        <h4 class="text-xl font-bold text-gray-800 mb-3">Inventory Dashboard</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="bg-white p-3 rounded-lg">
                                                <div class="text-2xl font-bold text-yellow-600">2,458</div>
                                                <div class="text-sm text-gray-600">Total Items</div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg">
                                                <div class="text-2xl font-bold text-red-600">45</div>
                                                <div class="text-sm text-gray-600">Low Stock</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Management -->
                <div id="sales" class="service-content hidden">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div class="animate-fade-in-up">
                            <div class="inline-flex items-center space-x-2 bg-gray-100 text-gray-600 px-4 py-2 rounded-full mb-6">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="font-medium">Revenue Generator</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-800 mb-6">Sales & Billing System</h3>
                            <p class="text-gray-600 mb-6 leading-relaxed">
                                Process sales quickly with our intuitive billing system. Generate invoices,
                                handle multiple payment methods, and track sales performance in real-time.
                            </p>
                            <div class="space-y-4 mb-8">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Quick Billing</h4>
                                        <p class="text-gray-600 text-sm">Fast invoice generation with templates</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Multi-payment Support</h4>
                                        <p class="text-gray-600 text-sm">Cash, card, digital payments, and insurance</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Tax Calculation</h4>
                                        <p class="text-gray-600 text-sm">Automatic tax calculation and reporting</p>
                                    </div>
                                </div>
                            </div>
                            <a href="/auth/login.php" class="gradient-yellow text-white px-8 py-3 rounded-xl font-semibold inline-flex items-center hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <i class="fas fa-play mr-2"></i> Try Sales Demo
                            </a>
                        </div>
                        <div class="relative animate-fade-in-up" style="animation-delay: 0.2s">
                            <div class="stat-card p-6 rounded-2xl">
                                <div class="aspect-video rounded-xl overflow-hidden bg-gradient-to-br from-gray-50 to-yellow-50 flex items-center justify-center">
                                    <div class="text-center p-8">
                                        <div class="w-20 h-20 gradient-gray rounded-2xl flex items-center justify-center mx-auto mb-6">
                                            <i class="fas fa-shopping-cart text-white text-3xl"></i>
                                        </div>
                                        <h4 class="text-xl font-bold text-gray-800 mb-3">Sales Dashboard</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="bg-white p-3 rounded-lg">
                                                <div class="text-2xl font-bold text-green-600">=45,820</div>
                                                <div class="text-sm text-gray-600">Today's Sales</div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg">
                                                <div class="text-2xl font-bold text-blue-600">287</div>
                                                <div class="text-sm text-gray-600">Transactions</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prescription Management -->
                <div id="prescription" class="service-content hidden">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div class="animate-fade-in-up">
                            <div class="inline-flex items-center space-x-2 bg-yellow-100 text-yellow-600 px-4 py-2 rounded-full mb-6">
                                <i class="fas fa-file-prescription"></i>
                                <span class="font-medium">Patient Care</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-800 mb-6">Prescription Management</h3>
                            <p class="text-gray-600 mb-6 leading-relaxed">
                                Handle prescriptions digitally with patient history tracking,
                                drug interaction checks, and refill management. Ensure patient safety and compliance.
                            </p>
                            <div class="space-y-4 mb-8">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Digital Records</h4>
                                        <p class="text-gray-600 text-sm">Secure digital prescription storage</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Interaction Alerts</h4>
                                        <p class="text-gray-600 text-sm">Automatic drug interaction warnings</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-semibold text-gray-800">Refill Management</h4>
                                        <p class="text-gray-600 text-sm">Track and manage prescription refills</p>
                                    </div>
                                </div>
                            </div>
                            <a href="/auth/login.php" class="gradient-yellow text-white px-8 py-3 rounded-xl font-semibold inline-flex items-center hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <i class="fas fa-play mr-2"></i> Try Prescription Demo
                            </a>
                        </div>
                        <div class="relative animate-fade-in-up" style="animation-delay: 0.2s">
                            <div class="stat-card p-6 rounded-2xl">
                                <div class="aspect-video rounded-xl overflow-hidden bg-gradient-to-br from-yellow-50 to-gray-50 flex items-center justify-center">
                                    <div class="text-center p-8">
                                        <div class="w-20 h-20 gradient-yellow rounded-2xl flex items-center justify-center mx-auto mb-6">
                                            <i class="fas fa-file-prescription text-white text-3xl"></i>
                                        </div>
                                        <h4 class="text-xl font-bold text-gray-800 mb-3">Prescription Dashboard</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="bg-white p-3 rounded-lg">
                                                <div class="text-2xl font-bold text-purple-600">156</div>
                                                <div class="text-sm text-gray-600">Today's Rx</div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg">
                                                <div class="text-2xl font-bold text-teal-600">89</div>
                                                <div class="text-sm text-gray-600">Pending Refills</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- All Services Grid -->
    <section class="py-24 bg-gradient-to-b from-yellow-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    All <span class="gradient-text">Features</span> Included
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Every tool you need to run a successful pharmacy practice.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-chart-line text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Advanced Analytics</h3>
                    <p class="text-gray-600 mb-4">
                        Get insights with detailed reports on sales, inventory turnover, and business performance.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Sales trend analysis
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Profit margin tracking
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Custom report builder
                        </li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-use= text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">User Management</h3>
                    <p class="text-gray-600 mb-4">
                        Control access with role-based permissions for pharmacists, assistants, and administrato=.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Role-based access control
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Activity logging
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Multi-user support
                        </li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-bell text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Smart Alerts</h3>
                    <p class="text-gray-600 mb-4">
                        Stay informed with automated notifications for low stock, expiring medicines, and important updates.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Low stock alerts
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Expiry warnings
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Sales notifications
                        </li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-mobile-alt text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Mobile Access</h3>
                    <p class="text-gray-600 mb-4">
                        Access your pharmacy data on the go with our mobile-responsive design and dedicated apps.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Mobile-responsive
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            iOS & Android apps
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Offline capabilities
                        </li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-database text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Data Security</h3>
                    <p class="text-gray-600 mb-4">
                        Enterprise-grade security with regular backups, encryption, and compliance features.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Automatic backups
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Data encryption
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-yellow-400 text-xs mr-2"></i>
                            Audit trails
                        </li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-headset text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">24/7 Support</h3>
                    <p class="text-gray-600 mb-4">
                        Get help whenever you need it with our dedicated support team and comprehensive documentation.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Live chat support
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Phone & email
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-circle text-gray-400 text-xs mr-2"></i>
                            Training resources
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Simple <span class="gradient-text">Pricing</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Choose the plan that works best for your pharmacy.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Basic Plan -->
                <div class="stat-card rounded-2xl p-8 text-center">
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Basic</h3>
                        <div class="text-5xl font-bold text-gray-800 mb-4">
                            =999<span class="text-xl text-gray-600">/month</span>
                        </div>
                        <p class="text-gray-600">For small pharmacies just starting out</p>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Up to 500 medicines
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Basic inventory
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Sales management
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-times text-gray-300 mr-2"></i>
                            No analytics
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-times text-gray-300 mr-2"></i>
                            Email support only
                        </li>
                    </ul>
                    <a href="/auth/login.php" class="block gradient-gray text-white py-3 rounded-xl font-semibold hover:shadow-xl transition-all duration-300">
                        Start Free Trial
                    </a>
                </div>

                <!-- Professional Plan -->
                <div class="stat-card rounded-2xl p-8 text-center relative border-2 border-yellow-500 transform scale-105">
                    <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                        <span class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-4 py-1 rounded-full text-sm font-semibold">
                            Most Popular
                        </span>
                    </div>
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Professional</h3>
                        <div class="text-5xl font-bold text-yellow-600 mb-4">
                            =2,499<span class="text-xl text-gray-600">/month</span>
                        </div>
                        <p class="text-gray-600">For growing pharmacies</p>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Unlimited medicines
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Advanced inventory
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Full sales system
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Basic analytics
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Priority support
                        </li>
                    </ul>
                    <a href="/auth/login.php" class="block gradient-yellow text-white py-3 rounded-xl font-semibold hover:shadow-xl transition-all duration-300">
                        Start Free Trial
                    </a>
                </div>

                <!-- Enterprise Plan -->
                <div class="stat-card rounded-2xl p-8 text-center">
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Enterprise</h3>
                        <div class="text-5xl font-bold text-gray-800 mb-4">
                            =4,999<span class="text-xl text-gray-600">/month</span>
                        </div>
                        <p class="text-gray-600">For large pharmacy chains</p>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Multi-location support
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Advanced analytics
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Custom integrations
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            API access
                        </li>
                        <li class="flex items-center justify-center text-gray-600">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            24/7 dedicated support
                        </li>
                    </ul>
                    <a href="/contact.php" class="block gradient-gray text-white py-3 rounded-xl font-semibold hover:shadow-xl transition-all duration-300">
                        Contact Sales
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 gradient-yellow text-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">
                Start Your Free 14-Day Trial
            </h2>
            <p class="text-xl text-yellow-100 mb-10 max-w-2xl mx-auto">
                No credit card required. Experience all features with full access.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                <a href="/auth/login.php"
                    class="bg-white text-yellow-600 px-10 py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 flex items-center space-x-3">
                    <i class="fas fa-rocket"></i>
                    <span>Start Free Trial</span>
                </a>
                <a href="/contact.php"
                    class="border-2 border-white text-white px-10 py-4 rounded-xl font-bold text-lg hover:bg-white/10 transition-all duration-300 flex items-center space-x-3">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule Demo</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include "includes/footer.php"; ?>

    <script>
        // Service Tab Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.service-tab');
            const contents = document.querySelectorAll('.service-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.dataset.tab;

                    // Update active tab
                    tabs.forEach(t => {
                        t.classList.remove('active', 'bg-yellow-50', 'text-yellow-600');
                        t.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    this.classList.remove('bg-gray-100', 'text-gray-600');
                    this.classList.add('active', 'bg-yellow-50', 'text-yellow-600');

                    // Show active content
                    contents.forEach(content => {
                        content.classList.remove('active');
                        content.classList.add('hidden');
                    });

                    const activeContent = document.getElementById(tabId);
                    activeContent.classList.remove('hidden');
                    activeContent.classList.add('active');

                    // Trigger animations
                    const elements = activeContent.querySelectorAll('.animate-fade-in-up');
                    elements.forEach((el, index) => {
                        el.style.animationDelay = `${index * 0.1}s`;
                    });
                });
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

            // Observe all stat cards
            document.querySelectorAll('.stat-card').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>

</html>