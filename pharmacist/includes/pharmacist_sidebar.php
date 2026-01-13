<?php
// This file should be included in pharmacist pages
// It requires the database connection and auth check to be done in the main file

$user_name = $_SESSION['name'];
?>

<!-- Sidebar -->
<aside id="sidebar" class="bg-gradient-to-b from-gray-900 to-gray-800 text-white fixed lg:sticky top-12 h-full lg:h-[calc(100vh-4rem)] w-64 lg:w-72 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 z-40 overflow-y-auto custom-scrollbar shadow-2xl">
    <div class="p-6">
        <!-- User Info -->
        <div class="flex items-center space-x-3 mb-8 pb-6 border-b border-gray-700/50">
            <div class="w-12 h-12 rounded-full gradient-yellow flex items-center justify-center text-white font-bold text-xl shadow-lg">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div>
                <h3 class="font-bold text-white"><?php echo htmlspecialchars($user_name); ?></h3>
                <p class="text-xs text-gray-300">Pharmacist</p>
                <p class="text-xs text-green-400 mt-1 flex items-center">
                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                    Online
                </p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="space-y-1">
            <!-- Dashboard -->
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'gradient-yellow text-white shadow-lg' : ''; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-white/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-tachometer-alt <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-white' : 'text-gray-200'; ?>">Dashboard</span>
                <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
                    <span class="ml-auto">
                        <i class="fas fa-chevron-right text-yellow-100 text-xs"></i>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Medicines Dropdown -->
            <div class="space-y-1">
                <button type="button" class="medicines-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php', 'search_brand.php', 'search_generic.php', 'return_to_company.php', 'expired_medicines.php']) ? 'bg-blue-500/10 border-l-4 border-blue-500' : ''; ?>">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php', 'search_brand.php', 'search_generic.php', 'return_to_company.php', 'expired_medicines.php']) ? 'bg-blue-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                            <i class="fas fa-pills <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php', 'search_brand.php', 'search_generic.php', 'return_to_company.php', 'expired_medicines.php']) ? 'text-blue-400' : 'text-gray-300'; ?> text-lg"></i>
                        </div>
                        <span class="font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php', 'search_brand.php', 'search_generic.php', 'return_to_company.php', 'expired_medicines.php']) ? 'text-blue-200' : 'text-gray-200'; ?>">Medicines</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                </button>

                <!-- Medicines Submenu -->
                <div class="medicines-submenu pl-4 ml-4 border-l border-gray-700/50 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php', 'search_brand.php', 'search_generic.php', 'return_to_company.php', 'expired_medicines.php']) ? '' : 'hidden'; ?>">
                    <!-- All Medicines -->
                    <a href="medicines.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php']) ? 'bg-blue-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php']) ? 'bg-blue-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['medicines.php', 'add_medicine.php', 'edit_medicine.php']) ? 'text-blue-200' : 'text-gray-300'; ?>">All Medicines</span>
                    </a>

                    <!-- Search Brand -->
                    <a href="search_brand.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'search_brand.php' ? 'bg-blue-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'search_brand.php' ? 'bg-blue-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'search_brand.php' ? 'text-blue-200' : 'text-gray-300'; ?>">Search Brand</span>
                    </a>

                    <!-- Search Generic -->
                    <a href="search_generic.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'search_generic.php' ? 'bg-blue-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'search_generic.php' ? 'bg-blue-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'search_generic.php' ? 'text-blue-200' : 'text-gray-300'; ?>">Search Generic</span>
                    </a>

                    <!-- Return to Company -->
                    <a href="return_to_company.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'return_to_company.php' ? 'bg-blue-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'return_to_company.php' ? 'bg-blue-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'return_to_company.php' ? 'text-blue-200' : 'text-gray-300'; ?>">Return to Company</span>
                    </a>

                    <!-- Expired Medicines -->
                    <a href="expired_medicines.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'expired_medicines.php' ? 'bg-red-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'expired_medicines.php' ? 'bg-red-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'expired_medicines.php' ? 'text-red-200' : 'text-gray-300'; ?>">Expired Medicines</span>
                        <?php if (basename($_SERVER['PHP_SELF']) != 'expired_medicines.php'): ?>
                            <?php
                            // Get expired medicines count
                            $expired_query = "SELECT COUNT(*) as count FROM stock_batches WHERE is_expired = 1";
                            $expired_result = mysqli_query($conn, $expired_query);
                            $expired_count = mysqli_fetch_assoc($expired_result)['count'];
                            ?>
                            <?php if ($expired_count > 0): ?>
                                <span class="ml-auto bg-red-500/20 text-red-300 text-xs px-2 py-1 rounded-full font-medium animate-pulse">
                                    <?php echo $expired_count; ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Stock Dropdown -->
            <div class="space-y-1">
                <button type="button" class="stock-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stock.php', 'add_stock.php', 'edit_stock.php']) ? 'bg-green-500/10 border-l-4 border-green-500' : ''; ?>">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stock.php', 'add_stock.php', 'edit_stock.php']) ? 'bg-green-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                            <i class="fas fa-boxes <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stock.php', 'add_stock.php', 'edit_stock.php']) ? 'text-green-400' : 'text-gray-300'; ?> text-lg"></i>
                        </div>
                        <span class="font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stock.php', 'add_stock.php', 'edit_stock.php']) ? 'text-green-200' : 'text-gray-200'; ?>">Stock</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                </button>

                <!-- Stock Submenu -->
                <div class="stock-submenu pl-4 ml-4 border-l border-gray-700/50 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stock.php', 'add_stock.php', 'edit_stock.php']) ? '' : 'hidden'; ?>">
                    <!-- View Stock -->
                    <a href="stock.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'bg-green-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'bg-green-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'text-green-200' : 'text-gray-300'; ?>">View Stock</span>
                    </a>

                    <!-- Add Stock -->
                    <a href="add_stock.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'add_stock.php' ? 'bg-green-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'add_stock.php' ? 'bg-green-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'add_stock.php' ? 'text-green-200' : 'text-gray-300'; ?>">Add Stock</span>
                    </a>
                </div>
            </div>

            <!-- Sale Dropdown -->
            <div class="space-y-1">
                <button type="button" class="sale-dropdown-toggle flex items-center justify-between w-full px-4 py-3 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php', 'create_sale.php', 'payments.php', 'expenses.php', 'profit.php']) ? 'bg-purple-500/10 border-l-4 border-purple-500' : ''; ?>">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php', 'create_sale.php', 'payments.php', 'expenses.php', 'profit.php']) ? 'bg-purple-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                            <i class="fas fa-shopping-cart <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php', 'create_sale.php', 'payments.php', 'expenses.php', 'profit.php']) ? 'text-purple-400' : 'text-gray-300'; ?> text-lg"></i>
                        </div>
                        <span class="font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php', 'create_sale.php', 'payments.php', 'expenses.php', 'profit.php']) ? 'text-purple-200' : 'text-gray-200'; ?>">Sale</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200"></i>
                </button>

                <!-- Sale Submenu -->
                <div class="sale-submenu pl-4 ml-4 border-l border-gray-700/50 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php', 'create_sale.php', 'payments.php', 'expenses.php', 'profit.php']) ? '' : 'hidden'; ?>">
                    <!-- Create Sale -->
                    <a href="create_sale.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'create_sale.php' ? 'bg-purple-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'create_sale.php' ? 'bg-purple-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'create_sale.php' ? 'text-purple-200' : 'text-gray-300'; ?>">Create Sale</span>
                        <span class="ml-auto bg-green-500/20 text-green-300 text-xs px-2 py-1 rounded-full font-medium">
                            <i class="fas fa-bolt"></i>
                        </span>
                    </a>
                     <a href="create_regular_sale.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'create_sale.php' ? 'bg-purple-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'create_regular__sale.php' ? 'bg-purple-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'create_regular_sale.php' ? 'text-purple-200' : 'text-gray-300'; ?>">Create  Regular Sale</span>
                        <span class="ml-auto bg-green-500/20 text-green-300 text-xs px-2 py-1 rounded-full font-medium">
                            <i class="fas fa-bolt"></i>
                        </span>
                    </a>


                    <!-- View Sales -->
                    <a href="sales.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php']) ? 'bg-purple-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php']) ? 'bg-purple-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['sales.php', 'view_sale.php']) ? 'text-purple-200' : 'text-gray-300'; ?>">View Sales</span>
                    </a>

                    <!-- Payments -->
                    <a href="payments.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'bg-blue-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'bg-blue-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'text-blue-200' : 'text-gray-300'; ?>">Payments</span>
                        <?php if (basename($_SERVER['PHP_SELF']) != 'payments.php'): ?>
                            <?php
                            // Get today's payments count
                            $payments_query = "SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = CURDATE() AND created_by = ?";
                            $stmt = mysqli_prepare($conn, $payments_query);
                            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                            mysqli_stmt_execute($stmt);
                            $payments_result = mysqli_stmt_get_result($stmt);
                            $payments_today = mysqli_fetch_assoc($payments_result)['count'];
                            mysqli_stmt_close($stmt);
                            ?>
                            <?php if ($payments_today > 0): ?>
                                <span class="ml-auto bg-blue-500/20 text-blue-300 text-xs px-2 py-1 rounded-full font-medium">
                                    <?php echo $payments_today; ?> today
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>

                    <!-- Expenses -->
                    <a href="expenses.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'bg-red-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'bg-red-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'text-red-200' : 'text-gray-300'; ?>">Expenses</span>
                        <?php if (basename($_SERVER['PHP_SELF']) != 'expenses.php'): ?>
                            <?php
                            // Get total expenses for current month
                            $monthly_expenses_query = "SELECT SUM(amount) as total FROM expenses 
                                WHERE MONTH(expense_date) = MONTH(CURDATE()) 
                                AND YEAR(expense_date) = YEAR(CURDATE())";
                            $monthly_expenses_result = mysqli_query($conn, $monthly_expenses_query);
                            $monthly_expenses = mysqli_fetch_assoc($monthly_expenses_result)['total'] ?? 0;
                            ?>
                            <?php if ($monthly_expenses > 0): ?>
                                <span class="ml-auto bg-red-500/20 text-red-300 text-xs px-2 py-1 rounded-full font-medium">
                                    Rs <?php echo number_format($monthly_expenses, 0); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>

                    <!-- Profit Analysis -->
                    <a href="profit.php" class="flex items-center space-x-3 px-4 py-2 rounded-lg hover:bg-gray-700/50 transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'profit.php' ? 'bg-green-500/10' : ''; ?>">
                        <div class="w-2 h-2 rounded-full <?php echo basename($_SERVER['PHP_SELF']) == 'profit.php' ? 'bg-green-400' : 'bg-gray-500'; ?>"></div>
                        <span class="text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'profit.php' ? 'text-green-200' : 'text-gray-300'; ?>">Profit Analysis</span>
                        <?php if (basename($_SERVER['PHP_SELF']) != 'profit.php'): ?>
                            <?php
                            // Calculate current month profit/loss
                            $profit_query = mysqli_query($conn, "
                                SELECT 
                                    (SELECT COALESCE(SUM(CASE WHEN payment_type = 'sale' AND payment_status = 'completed' 
                                        THEN transaction_net_amount ELSE 0 END), 0) FROM payments 
                                        WHERE MONTH(payment_date) = MONTH(CURDATE()) 
                                        AND YEAR(payment_date) = YEAR(CURDATE())) as revenue,
                                    (SELECT COALESCE(SUM(CASE WHEN payment_type = 'return_to_company' AND payment_status = 'completed' 
                                        THEN amount ELSE 0 END), 0) FROM payments 
                                        WHERE MONTH(payment_date) = MONTH(CURDATE()) 
                                        AND YEAR(payment_date) = YEAR(CURDATE())) as returns_cost,
                                    (SELECT COALESCE(SUM(amount), 0) FROM expenses 
                                        WHERE MONTH(expense_date) = MONTH(CURDATE()) 
                                        AND YEAR(expense_date) = YEAR(CURDATE())) as expenses
                            ");
                            $profit_data = mysqli_fetch_assoc($profit_query);
                            $monthly_profit = ($profit_data['revenue'] - $profit_data['returns_cost'] - $profit_data['expenses']);
                            ?>
                            <?php if ($monthly_profit != 0): ?>
                                <span class="ml-auto <?php echo $monthly_profit >= 0 ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'; ?> text-xs px-2 py-1 rounded-full font-medium">
                                    <?php echo $monthly_profit >= 0 ? '↗' : '↘'; ?> Rs <?php echo number_format(abs($monthly_profit), 0); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

        </nav>

        <!-- Divider -->
        <div class="my-6 border-t border-gray-700/50"></div>

        <!-- Quick Stats -->
        <div class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Quick Stats</h4>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <span class="text-sm text-gray-400">Medicines</span>
                    </div>
                    <span class="font-bold text-yellow-400">
                        <?php
                        if (!isset($medicine_count)) {
                            $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM medicines");
                            $medicine_count = mysqli_fetch_assoc($count_query)['total'];
                        }
                        echo $medicine_count;
                        ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                        <span class="text-sm text-gray-400">Active Stock</span>
                    </div>
                    <span class="font-bold text-gray-300">
                        <?php
                        if (!isset($active_stock_count)) {
                            $stock_query = mysqli_query($conn, "SELECT COUNT(DISTINCT medicine_id) as count FROM stock_batches WHERE quantity > 0 AND is_expired = 0");
                            $active_stock_count = mysqli_fetch_assoc($stock_query)['count'];
                        }
                        echo $active_stock_count;
                        ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-sm text-gray-400">Expired Stock</span>
                    </div>
                    <span class="font-bold text-red-400">
                        <?php
                        if (!isset($expired_stock_count)) {
                            $expired_query = mysqli_query($conn, "SELECT COUNT(DISTINCT medicine_id) as count FROM stock_batches WHERE is_expired = 1");
                            $expired_stock_count = mysqli_fetch_assoc($expired_query)['count'] ?? 0;
                        }
                        echo $expired_stock_count;
                        ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-indigo-500"></div>
                        <span class="text-sm text-gray-400">Returns Today</span>
                    </div>
                    <span class="font-bold text-indigo-400">
                        <?php
                        if (!isset($returns_today_count)) {
                            $returns_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM returns_to_company WHERE DATE(returned_at) = CURDATE()");
                            $returns_today_count = mysqli_fetch_assoc($returns_query)['count'] ?? 0;
                        }
                        echo $returns_today_count;
                        ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                        <span class="text-sm text-gray-400">Payments Today</span>
                    </div>
                    <span class="font-bold text-blue-400">
                        <?php
                        if (!isset($payments_today_count)) {
                            $payments_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = CURDATE() AND created_by = " . $_SESSION['user_id']);
                            $payments_today_count = mysqli_fetch_assoc($payments_query)['count'] ?? 0;
                        }
                        echo $payments_today_count;
                        ?>
                    </span>
                </div>
                <!-- Add Monthly Expenses and Profit to Quick Stats -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-sm text-gray-400">Monthly Expenses</span>
                    </div>
                    <span class="font-bold text-red-400">
                        Rs <?php echo number_format($monthly_expenses ?? 0, 0); ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full <?php echo ($monthly_profit ?? 0) >= 0 ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                        <span class="text-sm text-gray-400">Monthly <?php echo ($monthly_profit ?? 0) >= 0 ? 'Profit' : 'Loss'; ?></span>
                    </div>
                    <span class="font-bold <?php echo ($monthly_profit ?? 0) >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                        <?php echo ($monthly_profit ?? 0) >= 0 ? '↗' : '↘'; ?> Rs <?php echo number_format(abs($monthly_profit ?? 0), 0); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Payment Stats -->
        <?php
        // Get payment stats
        $payment_stats_query = "SELECT 
            SUM(amount) as total_amount,
            COUNT(*) as total_payments,
            SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount
        FROM payments 
        WHERE created_by = ?";

        $stmt = mysqli_prepare($conn, $payment_stats_query);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $payment_stats_result = mysqli_stmt_get_result($stmt);
        $payment_stats = mysqli_fetch_assoc($payment_stats_result);
        mysqli_stmt_close($stmt);

        if ($payment_stats['total_payments'] > 0):
        ?>
            <div class="mt-6 pt-4 border-t border-gray-700/50">
                <div class="bg-gradient-to-r from-blue-500/10 to-indigo-500/10 border border-blue-500/20 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-blue-300">💰 Payment Summary</span>
                        <span class="text-xs bg-blue-500/20 text-blue-300 px-2 py-1 rounded-full">
                            <?php echo $payment_stats['total_payments']; ?> payments
                        </span>
                    </div>
                    <div class="space-y-2 mb-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-blue-200/80">Total Amount:</span>
                            <span class="text-sm font-bold text-green-300">
                                Rs <?php echo number_format($payment_stats['total_amount'], 2); ?>
                            </span>
                        </div>
                        <?php if ($payment_stats['pending_amount'] > 0): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-yellow-200/80">Pending Amount:</span>
                                <span class="text-sm font-bold text-yellow-300">
                                    Rs <?php echo number_format($payment_stats['pending_amount'], 2); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="payments.php" class="block text-center mt-3 text-xs bg-gradient-to-r from-blue-500 to-indigo-500 text-white px-3 py-2 rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-eye mr-1"></i> View Payments
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Financial Summary -->
        <?php if (($monthly_expenses ?? 0) > 0 || ($monthly_profit ?? 0) != 0): ?>
            <div class="mt-6 pt-4 border-t border-gray-700/50">
                <div class="bg-gradient-to-r <?php echo ($monthly_profit ?? 0) >= 0 ? 'from-green-500/10 to-emerald-500/10 border border-green-500/20' : 'from-red-500/10 to-orange-500/10 border border-red-500/20'; ?> rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium <?php echo ($monthly_profit ?? 0) >= 0 ? 'text-green-300' : 'text-red-300'; ?>">
                            <?php echo ($monthly_profit ?? 0) >= 0 ? '📈 Financial Health' : '📉 Financial Alert'; ?>
                        </span>
                        <span class="text-xs <?php echo ($monthly_profit ?? 0) >= 0 ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'; ?> px-2 py-1 rounded-full">
                            <?php echo ($monthly_profit ?? 0) >= 0 ? 'Profitable' : 'Loss'; ?>
                        </span>
                    </div>
                    <div class="space-y-2 mb-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs <?php echo ($monthly_profit ?? 0) >= 0 ? 'text-green-200/80' : 'text-red-200/80'; ?>">Monthly Expenses:</span>
                            <span class="text-sm font-bold text-red-300">
                                Rs <?php echo number_format($monthly_expenses ?? 0, 2); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs <?php echo ($monthly_profit ?? 0) >= 0 ? 'text-green-200/80' : 'text-red-200/80'; ?>">Net <?php echo ($monthly_profit ?? 0) >= 0 ? 'Profit' : 'Loss'; ?>:</span>
                            <span class="text-sm font-bold <?php echo ($monthly_profit ?? 0) >= 0 ? 'text-green-300' : 'text-red-300'; ?>">
                                <?php echo ($monthly_profit ?? 0) >= 0 ? '↗' : '↘'; ?> Rs <?php echo number_format(abs($monthly_profit ?? 0), 2); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="expenses.php" class="flex-1 text-center text-xs bg-gradient-to-r from-red-500 to-orange-500 text-white px-2 py-2 rounded-lg hover:shadow-lg transition">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Expenses
                        </a>
                        <a href="profit.php" class="flex-1 text-center text-xs bg-gradient-to-r from-green-500 to-emerald-500 text-white px-2 py-2 rounded-lg hover:shadow-lg transition">
                            <i class="fas fa-chart-line mr-1"></i> Profit
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Expiry Alerts -->
        <?php
        // Get medicines expiring in next 30 days
        $expiring_query = mysqli_query(
            $conn,
            "SELECT COUNT(DISTINCT m.id) as count 
             FROM medicines m
             JOIN stock_batches sb ON m.id = sb.medicine_id
             WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             AND sb.is_expired = 0"
        );
        $expiring_count = mysqli_fetch_assoc($expiring_query)['count'] ?? 0;

        if ($expiring_count > 0):
        ?>
            <div class="mt-6 pt-4 border-t border-gray-700/50">
                <div class="bg-gradient-to-r from-orange-500/10 to-red-500/10 border border-orange-500/20 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-orange-300">⚠️ Expiring Soon</span>
                        <span class="text-xs bg-orange-500/20 text-orange-300 px-2 py-1 rounded-full">
                            <?php echo $expiring_count; ?> medicines
                        </span>
                    </div>
                    <p class="text-xs text-orange-200/80">Medicines expiring within 30 days</p>
                    <a href="stock.php?filter=expiring_soon" class="block text-center mt-3 text-xs bg-gradient-to-r from-orange-500 to-red-500 text-white px-3 py-2 rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Logout -->
        <div class="mt-8 pt-6 border-t border-gray-700/50">
            <a href="../auth/logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-red-500/10 to-red-600/10 hover:from-red-500/20 hover:to-red-600/20 transition-all duration-200 group">
                <i class="fas fa-sign-out-alt text-red-400 group-hover:text-red-300"></i>
                <span class="text-red-300 group-hover:text-red-200">Logout</span>
            </a>
        </div>
    </div>
</aside>

<!-- Overlay for mobile -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

<!-- Mobile Menu Button (to be placed in navbar) -->
<button id="sidebar-toggle" class="lg:hidden text-gray-600 hover:text-yellow-600 ml-4">
    <i class="fas fa-bars text-xl"></i>
</button>

<style>
    /* Add these styles to your main CSS or include here */
    .gradient-yellow {
        background: linear-gradient(135deg, #f59e0b, #fbbf24);
    }

    .gradient-red {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    .gradient-indigo {
        background: linear-gradient(135deg, #4f46e5, #3730a3);
    }

    .gradient-blue {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .gradient-green {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .gradient-purple {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .gradient-gray {
        background: linear-gradient(135deg, #6b7280, #9ca3af);
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
        background: #f59e0b;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #d97706;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes warning-pulse {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.05);
            opacity: 0.8;
        }
    }

    .animate-warning-pulse {
        animation: warning-pulse 1.5s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-3px);
        }
    }

    .animate-float {
        animation: float 2s ease-in-out infinite;
    }

    @keyframes profit-pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    .animate-profit-pulse {
        animation: profit-pulse 1s ease-in-out infinite;
    }
</style>

<script>
    // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
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

        // Dropdown functionality
        const medicinesToggle = document.querySelector('.medicines-dropdown-toggle');
        const stockToggle = document.querySelector('.stock-dropdown-toggle');
        const saleToggle = document.querySelector('.sale-dropdown-toggle');

        const medicinesSubmenu = document.querySelector('.medicines-submenu');
        const stockSubmenu = document.querySelector('.stock-submenu');
        const saleSubmenu = document.querySelector('.sale-submenu');

        // Toggle dropdowns
        function toggleDropdown(toggleBtn, submenu, chevron) {
            toggleBtn.addEventListener('click', () => {
                const isExpanded = !submenu.classList.contains('hidden');

                // Close all other dropdowns
                [medicinesSubmenu, stockSubmenu, saleSubmenu].forEach(menu => {
                    if (menu !== submenu) {
                        menu.classList.add('hidden');
                        const otherBtn = menu.previousElementSibling;
                        if (otherBtn) {
                            otherBtn.querySelector('.fa-chevron-down').classList.remove('rotate-180');
                        }
                    }
                });

                // Toggle current dropdown
                submenu.classList.toggle('hidden');
                chevron.classList.toggle('rotate-180');
            });
        }

        if (medicinesToggle && medicinesSubmenu) {
            toggleDropdown(medicinesToggle, medicinesSubmenu, medicinesToggle.querySelector('.fa-chevron-down'));
        }

        if (stockToggle && stockSubmenu) {
            toggleDropdown(stockToggle, stockSubmenu, stockToggle.querySelector('.fa-chevron-down'));
        }

        if (saleToggle && saleSubmenu) {
            toggleDropdown(saleToggle, saleSubmenu, saleToggle.querySelector('.fa-chevron-down'));
        }

        // Open dropdown based on current page
        const currentPage = window.location.pathname.split('/').pop();

        // Define which pages belong to which dropdown
        const medicinesPages = ['medicines.php', 'add_medicine.php', 'edit_medicine.php', 'search_brand.php', 'search_generic.php', 'return_to_company.php', 'expired_medicines.php'];
        const stockPages = ['stock.php', 'add_stock.php', 'edit_stock.php'];
        const salePages = ['sales.php', 'view_sale.php', 'create_sale.php', 'payments.php', 'expenses.php', 'profit.php'];

        if (medicinesPages.includes(currentPage)) {
            if (medicinesSubmenu) {
                medicinesSubmenu.classList.remove('hidden');
                const chevron = medicinesToggle.querySelector('.fa-chevron-down');
                if (chevron) chevron.classList.add('rotate-180');
            }
        } else if (stockPages.includes(currentPage)) {
            if (stockSubmenu) {
                stockSubmenu.classList.remove('hidden');
                const chevron = stockToggle.querySelector('.fa-chevron-down');
                if (chevron) chevron.classList.add('rotate-180');
            }
        } else if (salePages.includes(currentPage)) {
            if (saleSubmenu) {
                saleSubmenu.classList.remove('hidden');
                const chevron = saleToggle.querySelector('.fa-chevron-down');
                if (chevron) chevron.classList.add('rotate-180');
            }
        }

        // Highlight active page
        const navLinks = document.querySelectorAll('#sidebar nav a');
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                // Remove all active classes
                link.classList.remove('bg-gray-700/50');

                // Add appropriate active class based on section
                if (medicinesPages.includes(currentPage)) {
                    link.classList.add('bg-blue-500/20');
                } else if (stockPages.includes(currentPage)) {
                    link.classList.add('bg-green-500/20');
                } else if (salePages.includes(currentPage)) {
                    if (currentPage === 'payments.php') {
                        link.classList.add('bg-blue-500/20');
                    } else if (currentPage === 'expenses.php') {
                        link.classList.add('bg-red-500/20');
                    } else if (currentPage === 'profit.php') {
                        link.classList.add('bg-green-500/20');
                    } else {
                        link.classList.add('bg-purple-500/20');
                    }
                }
            }
        });

        // Highlight expired medicines if there are any
        const expiredCount = <?php echo $expired_stock_count ?? 0; ?>;
        const expiredLink = document.querySelector('a[href="expired_medicines.php"]');

        if (expiredCount > 0 && currentPage !== 'expired_medicines.php') {
            if (expiredLink) {
                expiredLink.classList.add('animate-warning-pulse');
            }
        }

        // Highlight payments link if there are pending payments
        const pendingAmount = <?php echo $payment_stats['pending_amount'] ?? 0; ?>;
        const paymentsLink = document.querySelector('a[href="payments.php"]');

        if (pendingAmount > 0 && currentPage !== 'payments.php') {
            if (paymentsLink) {
                paymentsLink.classList.add('animate-float');
            }
        }

        // Highlight profit link if there's significant profit or loss
        const monthlyProfit = <?php echo $monthly_profit ?? 0; ?>;
        const profitLink = document.querySelector('a[href="profit.php"]');

        if (Math.abs(monthlyProfit) > 10000 && currentPage !== 'profit.php') {
            if (profitLink) {
                profitLink.classList.add('animate-profit-pulse');
            }
        }

        // Auto-refresh quick stats every 60 seconds
        setInterval(() => {
            fetch('ajax/get_quick_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update expired count badge
                        const expiredBadge = document.querySelector('a[href="expired_medicines.php"] span.bg-red-500\\/20');
                        if (expiredBadge && data.expired_count > 0) {
                            expiredBadge.textContent = data.expired_count;
                        } else if (data.expired_count > 0) {
                            // Create badge if doesn't exist
                            const expiredLink = document.querySelector('a[href="expired_medicines.php"]');
                            if (expiredLink) {
                                const badge = document.createElement('span');
                                badge.className = 'ml-auto bg-red-500/20 text-red-300 text-xs px-2 py-1 rounded-full font-medium animate-pulse';
                                badge.textContent = data.expired_count;
                                expiredLink.appendChild(badge);
                            }
                        }

                        // Update returns count
                        const returnsBadge = document.querySelector('a[href="return_to_company.php"] span.bg-indigo-500\\/20');
                        if (returnsBadge && data.returns_today > 0) {
                            returnsBadge.textContent = data.returns_today + ' today';
                        }

                        // Update payments count
                        const paymentsBadge = document.querySelector('a[href="payments.php"] span.bg-blue-500\\/20');
                        if (paymentsBadge && data.payments_today > 0) {
                            paymentsBadge.textContent = data.payments_today + ' today';
                        }

                        // Update expenses badge
                        const expensesBadge = document.querySelector('a[href="expenses.php"] span.bg-red-500\\/20');
                        if (expensesBadge && data.monthly_expenses > 0) {
                            expensesBadge.textContent = 'Rs ' + data.monthly_expenses.toLocaleString('en-IN', {
                                minimumFractionDigits: 0
                            });
                        }

                        // Update profit badge
                        const profitBadge = document.querySelector('a[href="profit.php"] span.ml-auto');
                        if (profitBadge && data.monthly_profit != 0) {
                            if (data.monthly_profit >= 0) {
                                profitBadge.className = 'ml-auto bg-green-500/20 text-green-300 text-xs px-2 py-1 rounded-full font-medium';
                                profitBadge.innerHTML = '↗ Rs ' + Math.abs(data.monthly_profit).toLocaleString('en-IN', {
                                    minimumFractionDigits: 0
                                });
                            } else {
                                profitBadge.className = 'ml-auto bg-red-500/20 text-red-300 text-xs px-2 py-1 rounded-full font-medium';
                                profitBadge.innerHTML = '↘ Rs ' + Math.abs(data.monthly_profit).toLocaleString('en-IN', {
                                    minimumFractionDigits: 0
                                });
                            }
                        }

                        // Update quick stats numbers
                        const statValues = document.querySelectorAll('#sidebar .space-y-3 > div:last-child span.font-bold');
                        const values = [
                            data.medicines_count,
                            data.active_stock_count,
                            data.expired_stock_count,
                            data.returns_today_count,
                            data.payments_today_count,
                            data.low_stock_count,
                            data.monthly_expenses,
                            Math.abs(data.monthly_profit)
                        ];

                        statValues.forEach((el, index) => {
                            if (values[index] !== undefined) {
                                if (index === 6) { // Monthly expenses
                                    el.textContent = 'Rs ' + values[index].toLocaleString('en-IN', {
                                        minimumFractionDigits: 0
                                    });
                                } else if (index === 7) { // Monthly profit/loss
                                    const profitLabel = document.querySelectorAll('#sidebar .space-y-3 > div:last-child span.text-gray-400')[7];
                                    if (profitLabel) {
                                        profitLabel.textContent = 'Monthly ' + (data.monthly_profit >= 0 ? 'Profit' : 'Loss');
                                    }
                                    const profitIndicator = data.monthly_profit >= 0 ? '↗' : '↘';
                                    el.textContent = profitIndicator + ' Rs ' + values[index].toLocaleString('en-IN', {
                                        minimumFractionDigits: 0
                                    });
                                } else {
                                    el.textContent = values[index];
                                }
                            }
                        });

                        // Update payment stats if exists
                        const paymentStatsDiv = document.querySelector('#sidebar .from-blue-500\\/10');
                        if (paymentStatsDiv && data.payment_stats) {
                            const totalAmountSpan = paymentStatsDiv.querySelector('span.text-green-300');
                            const pendingAmountSpan = paymentStatsDiv.querySelector('span.text-yellow-300');
                            const countSpan = paymentStatsDiv.querySelector('span.bg-blue-500\\/20');

                            if (totalAmountSpan) {
                                totalAmountSpan.textContent = 'Rs ' + data.payment_stats.total_amount.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                });
                            }

                            if (pendingAmountSpan && data.payment_stats.pending_amount > 0) {
                                pendingAmountSpan.textContent = 'Rs ' + data.payment_stats.pending_amount.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                });
                                pendingAmountSpan.parentElement.style.display = 'flex';
                            }

                            if (countSpan) {
                                countSpan.textContent = data.payment_stats.total_payments + ' payments';
                            }
                        }

                        // Update expiring soon alert
                        if (data.expiring_soon > 0) {
                            const alertDiv = document.querySelector('#sidebar .from-orange-500\\/10');
                            if (alertDiv) {
                                const countSpan = alertDiv.querySelector('span.bg-orange-500\\/20');
                                if (countSpan) {
                                    countSpan.textContent = data.expiring_soon + ' medicines';
                                }
                            }
                        }

                        // Update financial summary
                        const financialDiv = document.querySelector('#sidebar .from-green-500\\/10, #sidebar .from-red-500\\/10');
                        if (financialDiv && data.monthly_profit != 0) {
                            const profitSpan = financialDiv.querySelector('span.text-sm.font-bold');
                            if (profitSpan) {
                                const profitIndicator = data.monthly_profit >= 0 ? '↗' : '↘';
                                profitSpan.innerHTML = profitIndicator + ' Rs ' + Math.abs(data.monthly_profit).toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                });
                            }
                        }
                    }
                })
                .catch(error => console.error('Error refreshing stats:', error));
        }, 60000); // Refresh every 60 seconds
    });
</script>