<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

$id = intval($_GET['id']);
$stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_batches WHERE id=$id"));

if (!$stock) {
    header("Location: stock.php");
    exit;
}

// Get medicine name for display
$medicine_info = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT m.name, m.generic_name FROM medicines m WHERE m.id={$stock['medicine_id']}"
));

// Fetch all medicines for dropdown
$medicines = mysqli_query($conn, "SELECT id, name, generic_name FROM medicines ORDER BY name");
// Fetch suppliers
$suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name");

$success = false;
$error = '';

if (isset($_POST['submit'])) {
    $medicine_id = intval($_POST['medicine_id']);
    $batch_no = mysqli_real_escape_string($conn, $_POST['batch_no']);
    $quantity = intval($_POST['quantity']);
    $purchase_price = floatval($_POST['purchase_price']);
    $selling_price = floatval($_POST['selling_price']);
    $mrp = floatval($_POST['mrp']);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 'NULL';
    $received_date = $_POST['received_date'];
    $expiry_date = $_POST['expiry_date'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);

    $query = "UPDATE stock_batches SET 
        medicine_id=$medicine_id, 
        batch_no='$batch_no', 
        quantity=$quantity, 
        purchase_price=$purchase_price, 
        selling_price=$selling_price, 
        mrp=$mrp, 
        supplier_id=$supplier_id, 
        received_date='$received_date', 
        expiry_date='$expiry_date', 
        location='$location'
        WHERE id=$id";

    if (mysqli_query($conn, $query)) {
        $success = true;
        $stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_batches WHERE id=$id"));
        // Re-fetch medicine info
        $medicine_info = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT m.name, m.generic_name FROM medicines m WHERE m.id={$stock['medicine_id']}"
        ));
    } else {
        $error = mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stock Batch - MediCare Pharma</title>
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

        .gradient-blue {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
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

        .form-input {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(209, 213, 219, 0.5);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            background: white;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .price-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.1);
        }

        .batch-info {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-left: 4px solid var(--accent-blue);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">

    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="blue-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>

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
                            Edit <span class="gradient-text">Stock Batch</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-edit text-blue-500"></i>
                            <span>Update stock batch information</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span><?php echo ucfirst($_SESSION['role']); ?> Access</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-hashtag text-purple-500"></i>
                            <span class="font-medium">Batch: <?php echo htmlspecialchars($stock['batch_no']); ?></span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="stock.php"
                            class="px-6 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-blue-500"></i>
                            <span>Back to Stock</span>
                        </a>
                        <a href="view_stock.php?id=<?php echo $id; ?>"
                            class="px-6 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-eye text-blue-500"></i>
                            <span>View Details</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Current Batch Info -->
            <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up batch-info">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    <span>Current Batch Information</span>
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Batch ID</p>
                        <p class="font-semibold text-gray-800">#<?php echo str_pad($stock['id'], 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Medicine</p>
                        <p class="font-semibold text-gray-800">
                            <?php echo htmlspecialchars($medicine_info['name'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Current Quantity</p>
                        <p class="font-semibold text-gray-800"><?php echo number_format($stock['quantity']); ?> units</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Added Date</p>
                        <p class="font-semibold text-gray-800"><?php echo date('M d, Y', strtotime($stock['received_date'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <?php if ($success): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-green-50 to-green-25 border border-green-200 animate-fade-in-up">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-green-800">Success!</h3>
                            <p class="text-green-600">Stock batch updated successfully. <a href="view_stock.php?id=<?php echo $id; ?>" class="font-medium underline">View updated details</a> or <a href="stock.php" class="font-medium underline">return to stock list</a>.</p>
                        </div>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 bg-gradient-to-r from-red-50 to-red-25 border border-red-200 animate-fade-in-up">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-red-800">Error!</h3>
                            <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Form -->
                <div class="lg:col-span-2">
                    <form method="POST" class="space-y-6">
                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.1s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-pills text-blue-500"></i>
                                <span>Medicine Information</span>
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-capsules text-gray-400 mr-1"></i>
                                        Select Medicine
                                    </label>
                                    <select name="medicine_id" required
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                        <?php
                                        mysqli_data_seek($medicines, 0); // Reset pointer
                                        while ($med = mysqli_fetch_assoc($medicines)): ?>
                                            <option value="<?php echo $med['id']; ?>" <?php echo ($stock['medicine_id'] == $med['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($med['name']); ?>
                                                <?php if (!empty($med['generic_name'])): ?>
                                                    (<?php echo htmlspecialchars($med['generic_name']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-box text-purple-500"></i>
                                <span>Batch Details</span>
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-hashtag text-gray-400 mr-1"></i>
                                        Batch Number
                                    </label>
                                    <input type="text" name="batch_no" required
                                        placeholder="Enter batch number"
                                        value="<?php echo htmlspecialchars($stock['batch_no']); ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-boxes text-gray-400 mr-1"></i>
                                            Quantity
                                        </label>
                                        <input type="number" name="quantity" min="1" required
                                            placeholder="Enter quantity"
                                            value="<?php echo $stock['quantity']; ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                            Storage Location
                                        </label>
                                        <input type="text" name="location"
                                            placeholder="e.g., Shelf A1, Refrigerator"
                                            value="<?php echo htmlspecialchars($stock['location']); ?>"
                                            class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-calendar-alt text-yellow-500"></i>
                                <span>Date Information</span>
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-calendar-plus text-gray-400 mr-1"></i>
                                        Received Date
                                    </label>
                                    <input type="date" name="received_date" required
                                        value="<?php echo $stock['received_date']; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-calendar-times text-gray-400 mr-1"></i>
                                        Expiry Date
                                    </label>
                                    <input type="date" name="expiry_date" required
                                        value="<?php echo $stock['expiry_date']; ?>"
                                        class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <i class="fas fa-truck text-teal-500"></i>
                                <span>Supplier Information</span>
                            </h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-building text-gray-400 mr-1"></i>
                                    Select Supplier (Optional)
                                </label>
                                <select name="supplier_id"
                                    class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                                    <option value="">No supplier selected</option>
                                    <?php
                                    mysqli_data_seek($suppliers, 0); // Reset pointer
                                    while ($supp = mysqli_fetch_assoc($suppliers)): ?>
                                        <option value="<?php echo $supp['id']; ?>" <?php echo ($stock['supplier_id'] == $supp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supp['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" name="submit"
                                class="gradient-blue text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-save"></i>
                                <span>Update Stock Batch</span>
                                <i class="fas fa-arrow-right text-blue-100 text-sm"></i>
                            </button>

                            <a href="stock.php"
                                class="px-8 py-4 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm">
                                <i class="fas fa-times"></i>
                                <span>Cancel</span>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Right Column - Price Information -->
                <div class="space-y-6">
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-tag text-green-500"></i>
                            <span>Price Information</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="price-card rounded-xl p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-shopping-cart text-blue-500 mr-1"></i>
                                    Purchase Price (Rs)
                                </label>
                                <input type="number" name="purchase_price" step="0.01" min="0" required
                                    placeholder="0.00"
                                    value="<?php echo number_format((float)$stock['purchase_price'], 2, '.', ''); ?>"
                                    class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                            </div>

                            <div class="price-card rounded-xl p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-cash-register text-green-500 mr-1"></i>
                                    Selling Price (Rs)
                                </label>
                                <input type="number" name="selling_price" step="0.01" min="0" required
                                    placeholder="0.00"
                                    value="<?php echo number_format((float)$stock['selling_price'], 2, '.', ''); ?>"
                                    class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                            </div>

                            <div class="price-card rounded-xl p-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-tags text-purple-500 mr-1"></i>
                                    MRP (Rs)
                                </label>
                                <input type="number" name="mrp" step="0.01" min="0" required
                                    placeholder="0.00"
                                    value="<?php echo number_format((float)$stock['mrp'], 2, '.', ''); ?>"
                                    class="w-full form-input px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-200 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.6s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-lightbulb text-yellow-500"></i>
                            <span>Editing Tips</span>
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg">
                                <i class="fas fa-exclamation-circle text-blue-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-blue-800">Quantity Changes</p>
                                    <p class="text-xs text-blue-600">Adjust stock levels carefully</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-lg">
                                <i class="fas fa-calendar-exclamation text-yellow-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-yellow-800">Expiry Updates</p>
                                    <p class="text-xs text-yellow-600">Update expiry dates for accurate tracking</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-lg">
                                <i class="fas fa-chart-line text-green-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-green-800">Pricing Updates</p>
                                    <p class="text-xs text-green-600">Review pricing to maintain profitability</p>
                                </div>
                            </div>
                        </div>
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

        // Validate expiry date is after received date
        const receivedDateInput = document.querySelector('input[name="received_date"]');
        const expiryDateInput = document.querySelector('input[name="expiry_date"]');

        if (receivedDateInput && expiryDateInput) {
            expiryDateInput.addEventListener('change', function() {
                const receivedDate = new Date(receivedDateInput.value);
                const expiryDate = new Date(this.value);

                if (expiryDate <= receivedDate) {
                    alert('Expiry date must be after the received date!');
                    this.value = '';
                    this.focus();
                }
            });
        }

        // Show notification on successful update
        <?php if ($success): ?>
            setTimeout(() => {
                showNotification('Stock batch updated successfully!', 'success');
            }, 100);
        <?php endif; ?>

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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[type="submit"]').click();
            }
            if (e.key === 'Escape') {
                window.location.href = 'stock.php';
            }
        });
    </script>
</body>

</html>