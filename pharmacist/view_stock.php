<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$id = intval($_GET['id']);
$stock = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT sb.*, 
                                 m.name AS medicine_name,
                                 c.name AS category_name, 
                                 t.name AS type_name,
                                 s.name AS supplier_name
                          FROM stock_batches sb
                          JOIN medicines m ON sb.medicine_id = m.id
                          LEFT JOIN medicine_categories c ON m.category_id = c.id
                          LEFT JOIN medicine_types t ON m.type_id = t.id
                          LEFT JOIN suppliers s ON sb.supplier_id = s.id
                          WHERE sb.id=$id")
);


if (!$stock) {
    header("Location: stock.php");
    exit;
}

// Calculate expiry status
$expiry_date = new DateTime($stock['expiry_date']);
$today = new DateTime();
$days_until_expiry = $today->diff($expiry_date)->days;
$is_expired = $expiry_date < $today;
$is_expiring_soon = !$is_expired && $days_until_expiry <= 30;

// Calculate batch value
$batch_value = $stock['quantity'] * $stock['purchase_price'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Stock Batch - MediCare Pharma</title>
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
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
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

        .purple-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
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

        .info-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: 0 4px 20px rgba(139, 92, 246, 0.1);
        }

        .status-expired {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .status-expiring {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .status-good {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .badge-batch {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-category {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-type {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .stock-indicator {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: #e5e7eb;
        }

        .stock-fill {
            height: 100%;
            border-radius: 4px;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="purple-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Stock Batch <span class="gradient-text">Details</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-eye text-purple-500"></i>
                            <span>View detailed information about stock batch</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span><?php echo ucfirst($_SESSION['role']); ?> Access</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <span class="badge-batch">
                                <i class="fas fa-hashtag mr-1 text-xs"></i>
                                <?php echo htmlspecialchars($stock['batch_no']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="stock.php"
                            class="px-6 py-3 border border-purple-200 text-gray-700 rounded-xl hover:bg-purple-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-purple-500"></i>
                            <span>Back to Stock</span>
                        </a>
                        <a href="edit_stock.php?id=<?php echo $id; ?>"
                            class="gradient-purple text-white px-6 py-3 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                            <i class="fas fa-edit"></i>
                            <span>Edit Batch</span>
                            <i class="fas fa-arrow-right text-purple-100 text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Medicine & Batch Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Medicine Information Card -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-pills text-blue-500"></i>
                            <span>Medicine Information</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Medicine Name</p>
                                    <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($stock['medicine_name']); ?></p>
                                    <?php if (!empty($stock['generic_name'])): ?>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($stock['generic_name']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <?php if (!empty($stock['category_name'])): ?>
                                        <span class="badge-category">
                                            <i class="fas fa-tag mr-1 text-xs"></i>
                                            <?php echo htmlspecialchars($stock['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($stock['type_name'])): ?>
                                        <span class="badge-type">
                                            <i class="fas fa-prescription-bottle mr-1 text-xs"></i>
                                            <?php echo htmlspecialchars($stock['type_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Batch Details</p>
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="badge-batch">
                                                <i class="fas fa-hashtag mr-1 text-xs"></i>
                                                <?php echo htmlspecialchars($stock['batch_no']); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">Batch ID: #<?php echo str_pad($stock['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            Location: <?php echo htmlspecialchars($stock['location'] ?: 'Main Store'); ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($stock['supplier_name'])): ?>
                                    <div>
                                        <p class="text-sm text-gray-600 mb-1">Supplier</p>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($stock['supplier_name']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Status Card -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-boxes text-green-500"></i>
                            <span>Stock Status</span>
                        </h3>
                        <div class="space-y-6">
                            <!-- Quantity & Stock Level -->
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <p class="text-sm text-gray-600">Current Quantity</p>
                                        <p class="text-2xl font-bold <?php echo $stock['quantity'] <= 50 ? 'text-red-600' : ($stock['quantity'] <= 100 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                            <?php echo number_format($stock['quantity']); ?> units
                                        </p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium 
                                        <?php echo $stock['quantity'] <= 50 ? 'bg-red-100 text-red-800' : ($stock['quantity'] <= 100 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                        <?php echo $stock['quantity'] <= 50 ? 'Low Stock' : ($stock['quantity'] <= 100 ? 'Medium Stock' : 'Good Stock'); ?>
                                    </span>
                                </div>
                                <div class="stock-indicator">
                                    <div class="stock-fill <?php echo $stock['quantity'] <= 50 ? 'status-expired' : ($stock['quantity'] <= 100 ? 'status-expiring' : 'status-good'); ?>"
                                        style="width: <?php echo min(100, ($stock['quantity'] / 200) * 100); ?>%;"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>0</span>
                                    <span>Stock Level</span>
                                    <span>200+</span>
                                </div>
                            </div>

                            <!-- Expiry Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="info-card rounded-xl p-4">
                                    <p class="text-sm text-gray-600 mb-1">Expiry Date</p>
                                    <p class="text-lg font-bold <?php echo $is_expired ? 'text-red-600' : ($is_expiring_soon ? 'text-yellow-600' : 'text-gray-800'); ?>">
                                        <?php echo date('M d, Y', strtotime($stock['expiry_date'])); ?>
                                    </p>
                                    <p class="text-xs <?php echo $is_expired ? 'text-red-500' : ($is_expiring_soon ? 'text-yellow-500' : 'text-gray-500'); ?>">
                                        <?php if ($is_expired): ?>
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Expired <?php echo abs($days_until_expiry); ?> days ago
                                        <?php elseif ($is_expiring_soon): ?>
                                            <i class="fas fa-clock mr-1"></i>Expiring in <?php echo $days_until_expiry; ?> days
                                        <?php else: ?>
                                            <i class="fas fa-check-circle mr-1"></i>Valid for <?php echo $days_until_expiry; ?> more days
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="info-card rounded-xl p-4">
                                    <p class="text-sm text-gray-600 mb-1">Received Date</p>
                                    <p class="text-lg font-bold text-gray-800">
                                        <?php echo date('M d, Y', strtotime($stock['received_date'])); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-calendar-plus mr-1"></i>
                                        <?php
                                        $received_date = new DateTime($stock['received_date']);
                                        $days_since_received = $today->diff($received_date)->days;
                                        echo $days_since_received . ' days in inventory';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Price Information & Actions -->
                <div class="space-y-6">
                    <!-- Price Information Card -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-tag text-yellow-500"></i>
                            <span>Price Information</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="info-card rounded-xl p-4">
                                <p class="text-sm text-gray-600 mb-1">Purchase Price</p>
                                <p class="text-2xl font-bold text-blue-600">Rs <?php echo number_format($stock['purchase_price'], 2); ?></p>
                                <p class="text-xs text-gray-500">Cost per unit</p>
                            </div>

                            <div class="info-card rounded-xl p-4">
                                <p class="text-sm text-gray-600 mb-1">Selling Price</p>
                                <p class="text-2xl font-bold text-green-600">Rs <?php echo number_format($stock['selling_price'], 2); ?></p>
                                <p class="text-xs text-gray-500">Selling price per unit</p>
                            </div>

                            <div class="info-card rounded-xl p-4">
                                <p class="text-sm text-gray-600 mb-1">MRP</p>
                                <p class="text-2xl font-bold text-purple-600">Rs <?php echo number_format($stock['mrp'], 2); ?></p>
                                <p class="text-xs text-gray-500">Maximum retail price</p>
                            </div>

                            <div class="info-card rounded-xl p-4 bg-gradient-to-r from-green-50 to-green-25 border border-green-200">
                                <p class="text-sm text-gray-600 mb-1">Batch Value</p>
                                <p class="text-2xl font-bold text-green-600">Rs <?php echo number_format($batch_value, 2); ?></p>
                                <p class="text-xs text-gray-500">Total value (Quantity × Purchase Price)</p>
                            </div>

                            <!-- Profit Margin -->
                            <div class="info-card rounded-xl p-4 bg-gradient-to-r from-blue-50 to-blue-25 border border-blue-200">
                                <p class="text-sm text-gray-600 mb-1">Profit Margin</p>
                                <?php
                                $profit_per_unit = $stock['selling_price'] - $stock['purchase_price'];
                                $margin_percentage = $stock['purchase_price'] > 0 ? ($profit_per_unit / $stock['purchase_price'] * 100) : 0;
                                $margin_class = $margin_percentage >= 20 ? 'text-green-600' : ($margin_percentage >= 10 ? 'text-yellow-600' : 'text-red-600');
                                ?>
                                <p class="text-2xl font-bold <?php echo $margin_class; ?>"><?php echo number_format($margin_percentage, 1); ?>%</p>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Profit/Unit: Rs <?php echo number_format($profit_per_unit, 2); ?></span>
                                    <span>Total: Rs <?php echo number_format($profit_per_unit * $stock['quantity'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-bolt text-red-500"></i>
                            <span>Quick Actions</span>
                        </h3>
                        <div class="space-y-3">
                            <a href="edit_stock.php?id=<?php echo $id; ?>"
                                class="w-full flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-edit text-blue-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Edit Batch</span>
                                </div>
                                <i class="fas fa-chevron-right text-blue-400"></i>
                            </a>

                            <button onclick="adjustStock(<?php echo $id; ?>, '<?php echo addslashes($stock['medicine_name']); ?>')"
                                class="w-full flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                        <i class="fas fa-exchange-alt text-green-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Adjust Stock</span>
                                </div>
                                <i class="fas fa-chevron-right text-green-400"></i>
                            </button>

                            <a href="medicines.php?id=<?php echo $stock['medicine_id']; ?>"
                                class="w-full flex items-center justify-between p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <i class="fas fa-pills text-purple-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">View Medicine</span>
                                </div>
                                <i class="fas fa-chevron-right text-purple-400"></i>
                            </a>

                            <button onclick="showDeleteModal(<?php echo $id; ?>, '<?php echo addslashes($stock['medicine_name'] . ' - Batch: ' . $stock['batch_no']); ?>')"
                                class="w-full flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-trash-alt text-red-600"></i>
                                    </div>
                                    <span class="font-medium text-gray-700">Delete Batch</span>
                                </div>
                                <i class="fas fa-chevron-right text-red-400"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <!-- Batch History -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-history text-gray-500"></i>
                        <span>Batch Information</span>
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600">Batch ID</span>
                            <span class="font-medium text-gray-800">#<?php echo str_pad($stock['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600">Stock Status</span>
                            <span class="<?php echo $stock['is_expired'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?> px-3 py-1 rounded-full text-xs font-medium">
                                <?php echo $stock['is_expired'] ? 'Marked as Expired' : 'Active'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600">Last Updated</span>
                            <span class="font-medium text-gray-800"><?php echo date('M d, Y H:i', strtotime($stock['received_date'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Stock Alerts -->
                <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                        <i class="fas fa-bell text-yellow-500"></i>
                        <span>Stock Alerts</span>
                    </h3>
                    <div class="space-y-3">
                        <?php if ($is_expired): ?>
                            <div class="flex items-center space-x-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-800">Expired Stock</p>
                                    <p class="text-xs text-red-600">This batch has expired and should not be sold</p>
                                </div>
                            </div>
                        <?php elseif ($is_expiring_soon): ?>
                            <div class="flex items-center space-x-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                                    <i class="fas fa-clock text-yellow-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-yellow-800">Expiring Soon</p>
                                    <p class="text-xs text-yellow-600">This batch expires in <?php echo $days_until_expiry; ?> days</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($stock['quantity'] <= 50): ?>
                            <div class="flex items-center space-x-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-exclamation text-red-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-800">Low Stock</p>
                                    <p class="text-xs text-red-600">Consider restocking this medicine</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$is_expired && !$is_expiring_soon && $stock['quantity'] > 50): ?>
                            <div class="flex items-center space-x-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-800">Stock Status: Good</p>
                                    <p class="text-xs text-green-600">No active alerts for this batch</p>
                                </div>
                            </div>
                        <?php endif; ?>
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
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Delete Stock Batch</h3>
                <p class="text-gray-600 text-center mb-6">
                    Are you sure you want to delete <span id="deleteBatchName" class="font-semibold text-purple-600"></span>?
                    This will remove the stock batch and its quantity from inventory. This action cannot be undone.
                </p>
                <div class="flex space-x-3">
                    <button onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 border border-purple-200 text-gray-700 rounded-xl hover:bg-purple-50 transition font-medium shadow-sm">
                        Cancel
                    </button>
                    <a id="deleteConfirmLink"
                        href="delete_stock.php?id=<?php echo $id; ?>"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:shadow-lg transition text-center font-medium shadow">
                        Delete Batch
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">
            <div class="p-6">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow">
                    <i class="fas fa-edit text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 text-center mb-2">Adjust Stock</h3>
                <p class="text-gray-600 text-center mb-4">
                    Adjust quantity for <span id="adjustMedicineName" class="font-semibold text-purple-600"></span>
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adjustment Type</label>
                        <select id="adjustmentType" class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition">
                            <option value="add">Add Stock</option>
                            <option value="remove">Remove Stock</option>
                            <option value="set">Set New Quantity</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" id="adjustQuantity" min="1" value="1"
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason (Optional)</label>
                        <textarea id="adjustReason" rows="2"
                            class="w-full px-4 py-3 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-200 focus:border-yellow-500 focus:outline-none transition"
                            placeholder="e.g., Damaged goods, recount, etc."></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="hideAdjustModal()"
                            class="flex-1 px-4 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-medium shadow-sm">
                            Cancel
                        </button>
                        <button onclick="submitAdjustment()"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white rounded-xl hover:shadow-lg transition font-medium shadow">
                            Apply Adjustment
                        </button>
                    </div>
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
            document.getElementById('deleteBatchName').textContent = name;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Adjust stock modal functions
        let currentBatchId = null;

        function adjustStock(id, name) {
            currentBatchId = id;
            document.getElementById('adjustModal').classList.remove('hidden');
            document.getElementById('adjustMedicineName').textContent = name;
        }

        function hideAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
            currentBatchId = null;
        }

        function submitAdjustment() {
            const type = document.getElementById('adjustmentType').value;
            const quantity = document.getElementById('adjustQuantity').value;
            const reason = document.getElementById('adjustReason').value;

            if (!quantity || quantity <= 0) {
                alert('Please enter a valid quantity');
                return;
            }

            // Submit adjustment (this would typically be an AJAX call)
            alert(`Adjusting stock for batch ${currentBatchId}: ${type} ${quantity} units`);
            hideAdjustModal();

            // Reload the page to see changes
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // Close modals when clicking outside
        [document.getElementById('deleteModal'), document.getElementById('adjustModal')].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (this.id === 'deleteModal') hideDeleteModal();
                        if (this.id === 'adjustModal') hideAdjustModal();
                    }
                });
            }
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Export to PDF function
        function exportToPDF() {
            // This function would generate a PDF of the stock batch details
            alert('PDF export functionality would be implemented here');
        }

        // Print batch details
        function printBatch() {
            window.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printBatch();
            }

            // Escape to go back
            if (e.key === 'Escape') {
                window.location.href = 'stock.php';
            }
        });
    </script>
</body>

</html>