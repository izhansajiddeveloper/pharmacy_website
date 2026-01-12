<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacists can edit stock
if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

// Check if medicine_id is provided
if (!isset($_GET['medicine_id']) || empty($_GET['medicine_id'])) {
    header("Location: stock.php");
    exit;
}

$medicine_id = intval($_GET['medicine_id']);

// Get medicine details
$medicine_query = "SELECT m.*, mg.name as generic_name, c.name as category_name, t.name as type_name
                   FROM medicines m
                   LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
                   LEFT JOIN medicine_categories c ON m.category_id = c.id
                   LEFT JOIN medicine_types t ON m.type_id = t.id
                   WHERE m.id = ?";
$medicine_stmt = mysqli_prepare($conn, $medicine_query);
mysqli_stmt_bind_param($medicine_stmt, 'i', $medicine_id);
mysqli_stmt_execute($medicine_stmt);
$medicine_result = mysqli_stmt_get_result($medicine_stmt);
$medicine = mysqli_fetch_assoc($medicine_result);

if (!$medicine) {
    header("Location: stock.php");
    exit;
}

// Get active stock batches for this medicine
$batches_query = "SELECT sb.*, s.name as supplier_name
                  FROM stock_batches sb
                  LEFT JOIN suppliers s ON sb.supplier_id = s.id
                  WHERE sb.medicine_id = ? 
                  AND sb.quantity > 0
                  AND sb.is_expired = 0
                  AND sb.is_returned = 0
                  AND sb.is_disposed = 0
                  ORDER BY sb.expiry_date ASC";
$batches_stmt = mysqli_prepare($conn, $batches_query);
mysqli_stmt_bind_param($batches_stmt, 'i', $medicine_id);
mysqli_stmt_execute($batches_stmt);
$batches_result = mysqli_stmt_get_result($batches_stmt);

// Get suppliers for dropdown
$suppliers_query = "SELECT id, name FROM suppliers ORDER BY name";
$suppliers_result = mysqli_query($conn, $suppliers_query);


// Handle form submission for updating batches
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_batches'])) {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($_POST['batches'] as $batch_id => $batch_data) {
            $batch_id = intval($batch_id);
            
            // Validate and sanitize inputs
            $quantity = max(0, intval($batch_data['quantity']));
            $units_per_packet = max(1, intval($batch_data['units_per_packet']));
            $packets_per_box = max(1, intval($batch_data['packets_per_box']));
            $purchase_price = max(0, floatval($batch_data['purchase_price']));
            $selling_price = max(0, floatval($batch_data['selling_price']));
            $mrp = max(0, floatval($batch_data['mrp']));
            $location = mysqli_real_escape_string($conn, $batch_data['location']);
            
            // Check if batch exists and belongs to this medicine
            $check_query = "SELECT id FROM stock_batches WHERE id = ? AND medicine_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'ii', $batch_id, $medicine_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update the batch
                $update_query = "UPDATE stock_batches 
                                SET quantity = ?, 
                                    units_per_packet = ?,
                                    packets_per_box = ?,
                                    purchase_price = ?,
                                    selling_price = ?,
                                    mrp = ?,
                                    location = ?,
                                    updated_at = NOW()
                                WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, 'iiidddsi', 
                    $quantity, $units_per_packet, $packets_per_box,
                    $purchase_price, $selling_price, $mrp, $location,
                    $batch_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                mysqli_stmt_close($update_stmt);
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_message'] = "Successfully updated $success_count batch(es).";
        }
        if ($error_count > 0) {
            $_SESSION['error_message'] = "Failed to update $error_count batch(es).";
        }
        
        // Refresh batches data
        mysqli_stmt_execute($batches_stmt);
        $batches_result = mysqli_stmt_get_result($batches_stmt);
    }
}

// Calculate total stock
$total_stock_query = "SELECT COALESCE(SUM(quantity), 0) as total_stock 
                      FROM stock_batches 
                      WHERE medicine_id = ? 
                      AND quantity > 0
                      AND is_expired = 0
                      AND is_returned = 0
                      AND is_disposed = 0";
$total_stmt = mysqli_prepare($conn, $total_stock_query);
mysqli_stmt_bind_param($total_stmt, 'i', $medicine_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_stock = mysqli_fetch_assoc($total_result)['total_stock'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stock - <?php echo htmlspecialchars($medicine['name']); ?> | MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --accent-green: #10b981;
            --accent-red: #ef4444;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .gradient-yellow {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
        }

        .gradient-green {
            background: linear-gradient(135deg, var(--accent-green), #059669);
        }

        .gradient-red {
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
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

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .input-field {
            transition: all 0.2s ease;
        }

        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .expired-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <!-- Page Header -->
            <div class="glass-card mx-6 mt-6 rounded-2xl p-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-4 mb-4">
                            <a href="stock.php" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-3xl font-bold text-gray-800">
                                Edit <span class="text-yellow-600">Stock</span>
                            </h1>
                        </div>
                        
                        <!-- Medicine Info -->
                        <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-4 rounded-xl border border-yellow-200">
                            <div class="flex items-start space-x-4">
                                <div class="w-16 h-16 rounded-xl gradient-yellow flex items-center justify-center text-white font-bold shadow-lg">
                                    <i class="fas fa-capsules text-2xl"></i>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-bold text-gray-800 mb-1">
                                        <?php echo htmlspecialchars($medicine['name']); ?>
                                    </h2>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <?php if (!empty($medicine['generic_name'])): ?>
                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 text-sm rounded-full">
                                                <i class="fas fa-dna mr-1"></i>
                                                <?php echo htmlspecialchars($medicine['generic_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($medicine['category_name'])): ?>
                                            <span class="px-3 py-1 bg-green-100 text-green-700 text-sm rounded-full">
                                                <i class="fas fa-tag mr-1"></i>
                                                <?php echo htmlspecialchars($medicine['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($medicine['type_name'])): ?>
                                            <span class="px-3 py-1 bg-purple-100 text-purple-700 text-sm rounded-full">
                                                <i class="fas fa-pills mr-1"></i>
                                                <?php echo htmlspecialchars($medicine['type_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <span class="font-semibold">Total Current Stock:</span>
                                        <span class="ml-2 px-3 py-1 bg-gray-800 text-white rounded-lg font-bold">
                                            <?php echo number_format($total_stock); ?> units
                                        </span>
                                        <span class="ml-4">
                                            <i class="fas fa-boxes text-gray-400 mr-1"></i>
                                            MED-<?php echo str_pad($medicine['id'], 6, '0', STR_PAD_LEFT); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="mx-6 mt-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl animate-fade-in-up">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="mx-6 mt-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl animate-fade-in-up">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stock Batches Form -->
            <form method="POST" class="glass-card mx-6 my-6 p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-boxes text-yellow-500 mr-2"></i>
                        Active Stock Batches
                    </h3>
                    <button type="submit" name="update_batches"
                        class="gradient-green text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-200 flex items-center space-x-2">
                        <i class="fas fa-save"></i>
                        <span>Save All Changes</span>
                    </button>
                </div>

                <?php if (mysqli_num_rows($batches_result) > 0): ?>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Batch No
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Expiry Date
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Quantity
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Units/Packet
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Packets/Box
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Prices (Rs)
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Location
                                    </th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                        Supplier
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($batch = mysqli_fetch_assoc($batches_result)): 
                                    $is_near_expiry = strtotime($batch['expiry_date']) < strtotime('+30 days');
                                    $is_expired = strtotime($batch['expiry_date']) < time();
                                ?>
                                    <tr class="hover:bg-yellow-50 transition-colors <?php echo $is_near_expiry ? 'bg-red-50' : ''; ?>">
                                        <td class="px-4 py-4">
                                            <div class="font-mono font-bold text-gray-800">
                                                <?php echo htmlspecialchars($batch['batch_no']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Received: <?php echo date('d/m/Y', strtotime($batch['received_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium <?php echo $is_expired ? 'text-red-600' : 'text-gray-700'; ?>">
                                                    <?php echo date('d/m/Y', strtotime($batch['expiry_date'])); ?>
                                                </span>
                                                <?php if ($is_expired): ?>
                                                    <span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full font-bold expired-badge">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Expired
                                                    </span>
                                                <?php elseif ($is_near_expiry): ?>
                                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full font-bold">
                                                        <i class="fas fa-clock mr-1"></i>Near Expiry
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <input type="number" 
                                                name="batches[<?php echo $batch['id']; ?>][quantity]"
                                                value="<?php echo $batch['quantity']; ?>"
                                                min="0"
                                                step="1"
                                                class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 input-field"
                                                required>
                                        </td>
                                        <td class="px-4 py-4">
                                            <input type="number" 
                                                name="batches[<?php echo $batch['id']; ?>][units_per_packet]"
                                                value="<?php echo $batch['units_per_packet']; ?>"
                                                min="1"
                                                step="1"
                                                class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 input-field"
                                                required>
                                        </td>
                                        <td class="px-4 py-4">
                                            <input type="number" 
                                                name="batches[<?php echo $batch['id']; ?>][packets_per_box]"
                                                value="<?php echo $batch['packets_per_box']; ?>"
                                                min="1"
                                                step="1"
                                                class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 input-field"
                                                required>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center">
                                                    <span class="text-xs text-gray-500 w-20">Purchase:</span>
                                                    <input type="number" 
                                                        name="batches[<?php echo $batch['id']; ?>][purchase_price]"
                                                        value="<?php echo number_format($batch['purchase_price'], 2); ?>"
                                                        min="0"
                                                        step="0.01"
                                                        class="flex-1 px-3 py-1 border border-gray-300 rounded text-sm"
                                                        required>
                                                </div>
                                                <div class="flex items-center">
                                                    <span class="text-xs text-gray-500 w-20">Selling:</span>
                                                    <input type="number" 
                                                        name="batches[<?php echo $batch['id']; ?>][selling_price]"
                                                        value="<?php echo number_format($batch['selling_price'], 2); ?>"
                                                        min="0"
                                                        step="0.01"
                                                        class="flex-1 px-3 py-1 border border-gray-300 rounded text-sm"
                                                        required>
                                                </div>
                                                <div class="flex items-center">
                                                    <span class="text-xs text-gray-500 w-20">MRP:</span>
                                                    <input type="number" 
                                                        name="batches[<?php echo $batch['id']; ?>][mrp]"
                                                        value="<?php echo number_format($batch['mrp'], 2); ?>"
                                                        min="0"
                                                        step="0.01"
                                                        class="flex-1 px-3 py-1 border border-gray-300 rounded text-sm"
                                                        required>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <input type="text" 
                                                name="batches[<?php echo $batch['id']; ?>][location]"
                                                value="<?php echo htmlspecialchars($batch['location']); ?>"
                                                class="w-48 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 input-field"
                                                placeholder="e.g., Shelf A5">
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm">
                                                <div class="font-medium text-gray-800">
                                                    <?php echo htmlspecialchars($batch['supplier_name'] ?: 'N/A'); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Batch ID: <?php echo $batch['id']; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box-open text-gray-400 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-800 mb-2">No Active Stock Batches</h4>
                        <p class="text-gray-600 mb-6">This medicine has no active stock batches.</p>
                        <a href="add_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                            class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-200 inline-flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add New Batch</span>
                        </a>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Action Buttons -->
            <div class="glass-card mx-6 mb-6 p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.2s">
                <div class="flex flex-wrap gap-4 justify-between">
                    <div class="flex flex-wrap gap-3">
                        <a href="add_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                            class="gradient-yellow text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-200 flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add New Batch</span>
                        </a>
                        <a href="view_stock.php?medicine_id=<?php echo $medicine_id; ?>"
                            class="px-6 py-3 border-2 border-blue-200 text-blue-600 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2">
                            <i class="fas fa-eye"></i>
                            <span>View Details</span>
                        </a>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="stock.php"
                            class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Stock</span>
                        </a>
                        <button onclick="printPage()"
                            class="px-6 py-3 border-2 border-green-200 text-green-600 rounded-xl hover:bg-green-50 transition font-bold flex items-center space-x-2">
                            <i class="fas fa-print"></i>
                            <span>Print Report</span>
                        </button>
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

        // Auto-calculate total units
        document.querySelectorAll('input[name*="[quantity]"]').forEach(input => {
            input.addEventListener('change', function() {
                const row = this.closest('tr');
                const quantity = parseInt(this.value) || 0;
                const unitsPerPacket = parseInt(row.querySelector('input[name*="[units_per_packet]"]').value) || 0;
                const packetsPerBox = parseInt(row.querySelector('input[name*="[packets_per_box]"]').value) || 0;
                
                // You can add calculation logic here if needed
                console.log(`Total units: ${quantity * unitsPerPacket * packetsPerBox}`);
            });
        });

        // Print function
        function printPage() {
            window.print();
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            const inputs = this.querySelectorAll('input[required]');
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[name="update_batches"]').click();
            }
            
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printPage();
            }
            
            // Esc to go back
            if (e.key === 'Escape') {
                window.location.href = 'stock.php';
            }
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>