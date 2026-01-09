<!-- Sidebar -->
<aside id="sidebar" class="bg-gradient-to-b from-gray-900 to-gray-800 text-white fixed lg:sticky top-12 h-full lg:h-[calc(100vh-4rem)] w-64 lg:w-72 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 z-40 overflow-y-auto custom-scrollbar shadow-2xl">
    <div class="p-6">
        <!-- User Info -->
        <div class="flex items-center space-x-3 mb-8 pb-6 border-b border-gray-700/50">
            <div class="w-12 h-12 rounded-full gradient-yellow flex items-center justify-center text-white font-bold text-xl shadow-lg">
                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
            </div>
            <div>
                <h3 class="font-bold text-white"><?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                <p class="text-xs text-gray-300">Administrator</p>
                <p class="text-xs text-green-400 mt-1 flex items-center">
                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                    Online
                </p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="space-y-2">
            <?php
            // Get counts for badges
            $pharmacists_count = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], "SELECT COUNT(*) as total FROM users WHERE role='pharmacist'"))['total'];
            $medicines_count = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], "SELECT COUNT(*) as total FROM medicines"))['total'];
            $stock_count = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], "SELECT SUM(quantity) as total FROM stock_batches WHERE is_expired = 0"))['total'] ?: 0;
            $sales_count = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], "SELECT COUNT(*) as total FROM sales WHERE DATE(sale_date) = CURDATE()"))['total'];
            $today_revenue = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], "SELECT SUM(total_amount) as total FROM sales WHERE DATE(sale_date) = CURDATE()"))['total'] ?: 0;

            // Get expenses data
            $monthly_expenses_query = "SELECT SUM(amount) as total FROM expenses 
                WHERE MONTH(expense_date) = MONTH(CURDATE()) 
                AND YEAR(expense_date) = YEAR(CURDATE())";
            $monthly_expenses_result = mysqli_query($GLOBALS['conn'], $monthly_expenses_query);
            $monthly_expenses = mysqli_fetch_assoc($monthly_expenses_result)['total'] ?? 0;

            // Calculate profit for current month
            $profit_query = mysqli_query($GLOBALS['conn'], "
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

            // Get low stock and expiring alerts
            $low_stock = mysqli_query(
                $GLOBALS['conn'],
                "SELECT COUNT(DISTINCT m.id) as count 
                 FROM medicines m 
                 JOIN stock_batches sb ON m.id = sb.medicine_id 
                 WHERE sb.quantity <= 50 AND sb.is_expired = 0"
            );
            $low_stock_count = mysqli_fetch_assoc($low_stock)['count'] ?: 0;

            $expiring_soon = mysqli_query(
                $GLOBALS['conn'],
                "SELECT COUNT(DISTINCT m.id) as count 
                 FROM medicines m 
                 JOIN stock_batches sb ON m.id = sb.medicine_id 
                 WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                 AND sb.is_expired = 0"
            );
            $expiring_count = mysqli_fetch_assoc($expiring_soon)['count'] ?: 0;

            // Get expired medicines count
            $expired_query = "SELECT COUNT(*) as count FROM stock_batches WHERE is_expired = 1";
            $expired_result = mysqli_query($GLOBALS['conn'], $expired_query);
            $expired_count = mysqli_fetch_assoc($expired_result)['count'] ?: 0;

            // Get returns count
            $returns_query = "SELECT COUNT(*) as count FROM returns_to_company WHERE DATE(returned_at) = CURDATE()";
            $returns_result = mysqli_query($GLOBALS['conn'], $returns_query);
            $returns_today = mysqli_fetch_assoc($returns_result)['count'] ?: 0;

            // Get payments count
            $payments_query = "SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = CURDATE()";
            $payments_result = mysqli_query($GLOBALS['conn'], $payments_query);
            $payments_today = mysqli_fetch_assoc($payments_result)['count'] ?: 0;

            // Get active users
            $active_query = mysqli_query(
                $GLOBALS['conn'],
                "SELECT COUNT(DISTINCT user_id) as active 
                 FROM user_sessions 
                 WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            $active_count = mysqli_fetch_assoc($active_query)['active'] ?: 0;

            // Current page for active styling
            $current_page = basename($_SERVER['PHP_SELF']);
            ?>

            <a href="dashboard.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'dashboard.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'dashboard.php' ? 'bg-white/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-tachometer-alt <?php echo $current_page == 'dashboard.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'dashboard.php' ? 'text-white' : 'text-gray-200'; ?>">Dashboard</span>
                <?php if ($current_page == 'dashboard.php'): ?>
                    <span class="ml-auto">
                        <i class="fas fa-chevron-right text-yellow-100 text-xs"></i>
                    </span>
                <?php endif; ?>
            </a>

            <a href="users.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'users.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'users.php' ? 'bg-yellow-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-user-md <?php echo $current_page == 'users.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'users.php' ? 'text-white' : 'text-gray-200'; ?>">Pharmacists</span>
                <span class="ml-auto <?php echo $current_page == 'users.php' ? 'bg-yellow-500/30 text-yellow-100' : 'bg-gray-600/50 text-gray-300'; ?> text-xs px-2 py-1 rounded-full font-medium"><?php echo $pharmacists_count; ?></span>
            </a>

            <a href="medicines.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'medicines.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'medicines.php' ? 'bg-yellow-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-pills <?php echo $current_page == 'medicines.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'medicines.php' ? 'text-white' : 'text-gray-200'; ?>">Medicines</span>
                <span class="ml-auto <?php echo $current_page == 'medicines.php' ? 'bg-yellow-500/30 text-yellow-100' : 'bg-gray-600/50 text-gray-300'; ?> text-xs px-2 py-1 rounded-full font-medium"><?php echo $medicines_count; ?></span>
            </a>

            <a href="inventory.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'inventory.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'inventory.php' ? 'bg-yellow-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-boxes <?php echo $current_page == 'inventory.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'inventory.php' ? 'text-white' : 'text-gray-200'; ?>">Inventory</span>
                <span class="ml-auto <?php echo $current_page == 'inventory.php' ? 'bg-yellow-500/30 text-yellow-100' : 'bg-gray-600/50 text-gray-300'; ?> text-xs px-2 py-1 rounded-full font-medium"><?php echo number_format($stock_count); ?></span>
            </a>

            <!-- Expired Medicines -->
            <a href="expired_medicines.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'expired_medicines.php' ? 'bg-red-500/20 border-l-4 border-red-500' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'expired_medicines.php' ? 'bg-red-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-skull-crossbones <?php echo $current_page == 'expired_medicines.php' ? 'text-red-400' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'expired_medicines.php' ? 'text-red-200' : 'text-gray-200'; ?>">Expired Medicines</span>
                <?php if ($expired_count > 0): ?>
                    <span class="ml-auto <?php echo $current_page == 'expired_medicines.php' ? 'bg-red-500/30 text-red-100' : 'bg-red-500/20 text-red-300'; ?> text-xs px-2 py-1 rounded-full font-medium <?php echo $current_page != 'expired_medicines.php' ? 'animate-pulse' : ''; ?>">
                        <?php echo $expired_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Return to Company -->
            <a href="return_to_company.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'return_to_company.php' ? 'bg-indigo-500/20 border-l-4 border-indigo-500' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'return_to_company.php' ? 'bg-indigo-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-undo-alt <?php echo $current_page == 'return_to_company.php' ? 'text-indigo-400' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'return_to_company.php' ? 'text-indigo-200' : 'text-gray-200'; ?>">Return to Company</span>
                <?php if ($returns_today > 0): ?>
                    <span class="ml-auto <?php echo $current_page == 'return_to_company.php' ? 'bg-indigo-500/30 text-indigo-100' : 'bg-indigo-500/20 text-indigo-300'; ?> text-xs px-2 py-1 rounded-full font-medium">
                        <?php echo $returns_today; ?> today
                    </span>
                <?php endif; ?>
            </a>

            <!-- Sales Section -->
            <div class="my-4 border-t border-gray-700/50"></div>
            <div class="px-4 py-2">
                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Sales Management</h4>
            </div>

            <a href="sales.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'sales.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'sales.php' ? 'bg-yellow-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-shopping-cart <?php echo $current_page == 'sales.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'sales.php' ? 'text-white' : 'text-gray-200'; ?>">All Sales</span>
                <span class="ml-auto <?php echo $current_page == 'sales.php' ? 'bg-yellow-500/30 text-yellow-100' : 'bg-gray-600/50 text-gray-300'; ?> text-xs px-2 py-1 rounded-full font-medium"><?php echo $sales_count; ?> today</span>
            </a>

            <!-- Payments -->
            <a href="payments.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'payments.php' ? 'bg-blue-500/20 border-l-4 border-blue-500' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'payments.php' ? 'bg-blue-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-credit-card <?php echo $current_page == 'payments.php' ? 'text-blue-400' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'payments.php' ? 'text-blue-200' : 'text-gray-200'; ?>">Payments</span>
                <?php if ($payments_today > 0): ?>
                    <span class="ml-auto <?php echo $current_page == 'payments.php' ? 'bg-blue-500/30 text-blue-100' : 'bg-blue-500/20 text-blue-300'; ?> text-xs px-2 py-1 rounded-full font-medium">
                        <?php echo $payments_today; ?> today
                    </span>
                <?php endif; ?>
            </a>

            <!-- Financial Management Section -->
            <div class="my-4 border-t border-gray-700/50"></div>
            <div class="px-4 py-2">
                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Financial Management</h4>
            </div>

            <!-- Expenses -->
            <a href="expenses.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'expenses.php' ? 'bg-red-500/20 border-l-4 border-red-500' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'expenses.php' ? 'bg-red-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar <?php echo $current_page == 'expenses.php' ? 'text-red-400' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'expenses.php' ? 'text-red-200' : 'text-gray-200'; ?>">Expenses</span>
                <?php if ($monthly_expenses > 0): ?>
                    <span class="ml-auto <?php echo $current_page == 'expenses.php' ? 'bg-red-500/30 text-red-100' : 'bg-red-500/20 text-red-300'; ?> text-xs px-2 py-1 rounded-full font-medium">
                        Rs <?php echo number_format($monthly_expenses, 0); ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Profit Analysis -->
            <a href="profit.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'profit.php' ? 'bg-green-500/20 border-l-4 border-green-500' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'profit.php' ? 'bg-green-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-chart-line <?php echo $current_page == 'profit.php' ? 'text-green-400' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'profit.php' ? 'text-green-200' : 'text-gray-200'; ?>">Profit Analysis</span>
                <?php if ($monthly_profit != 0): ?>
                    <span class="ml-auto <?php echo $current_page == 'profit.php' ? ($monthly_profit >= 0 ? 'bg-green-500/30 text-green-100' : 'bg-red-500/30 text-red-100') : ($monthly_profit >= 0 ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'); ?> text-xs px-2 py-1 rounded-full font-medium">
                        <?php echo $monthly_profit >= 0 ? '↗' : '↘'; ?> Rs <?php echo number_format(abs($monthly_profit), 0); ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Reports -->
            <a href="reports.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'reports.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'reports.php' ? 'bg-yellow-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-chart-bar <?php echo $current_page == 'reports.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'reports.php' ? 'text-white' : 'text-gray-200'; ?>">Reports</span>
            </a>

            <!-- Settings -->
            <a href="settings.php"
                class="flex items-center space-x-3 px-4 py-3 rounded-lg <?php echo $current_page == 'settings.php' ? 'gradient-yellow text-white shadow-lg' : 'hover:bg-gray-700/50 transition-all duration-200'; ?>">
                <div class="w-8 h-8 rounded-lg <?php echo $current_page == 'settings.php' ? 'bg-yellow-500/20' : 'bg-gray-700/50'; ?> flex items-center justify-center">
                    <i class="fas fa-cog <?php echo $current_page == 'settings.php' ? 'text-white' : 'text-gray-300'; ?> text-lg"></i>
                </div>
                <span class="font-medium <?php echo $current_page == 'settings.php' ? 'text-white' : 'text-gray-200'; ?>">Settings</span>
            </a>
        </nav>

        <!-- Divider -->
        <div class="my-6 border-t border-gray-700/50"></div>

        <!-- Quick Stats -->
        <div class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Today's Stats</h4>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <span class="text-sm text-gray-400">Revenue</span>
                    </div>
                    <span class="font-bold text-yellow-400">Rs <?php echo number_format($today_revenue); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                        <span class="text-sm text-gray-400">Active Users</span>
                    </div>
                    <span class="font-bold text-gray-300"><?php echo $active_count; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-sm text-gray-400">Alerts</span>
                    </div>
                    <span class="font-bold text-red-400"><?php echo $low_stock_count + $expiring_count; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-sm text-gray-400">Monthly Expenses</span>
                    </div>
                    <span class="font-bold text-red-400">Rs <?php echo number_format($monthly_expenses, 0); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full <?php echo $monthly_profit >= 0 ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                        <span class="text-sm text-gray-400">Monthly <?php echo $monthly_profit >= 0 ? 'Profit' : 'Loss'; ?></span>
                    </div>
                    <span class="font-bold <?php echo $monthly_profit >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                        <?php echo $monthly_profit >= 0 ? '↗' : '↘'; ?> Rs <?php echo number_format(abs($monthly_profit), 0); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <?php if ($monthly_expenses > 0 || $monthly_profit != 0): ?>
            <div class="mt-6 pt-4 border-t border-gray-700/50">
                <div class="bg-gradient-to-r <?php echo $monthly_profit >= 0 ? 'from-green-500/10 to-emerald-500/10 border border-green-500/20' : 'from-red-500/10 to-orange-500/10 border border-red-500/20'; ?> rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium <?php echo $monthly_profit >= 0 ? 'text-green-300' : 'text-red-300'; ?>">
                            <?php echo $monthly_profit >= 0 ? '📈 Financial Health' : '📉 Financial Alert'; ?>
                        </span>
                        <span class="text-xs <?php echo $monthly_profit >= 0 ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'; ?> px-2 py-1 rounded-full">
                            <?php echo $monthly_profit >= 0 ? 'Profitable' : 'Loss'; ?>
                        </span>
                    </div>
                    <div class="space-y-2 mb-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs <?php echo $monthly_profit >= 0 ? 'text-green-200/80' : 'text-red-200/80'; ?>">Monthly Expenses:</span>
                            <span class="text-sm font-bold text-red-300">
                                Rs <?php echo number_format($monthly_expenses, 2); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs <?php echo $monthly_profit >= 0 ? 'text-green-200/80' : 'text-red-200/80'; ?>">Net <?php echo $monthly_profit >= 0 ? 'Profit' : 'Loss'; ?>:</span>
                            <span class="text-sm font-bold <?php echo $monthly_profit >= 0 ? 'text-green-300' : 'text-red-300'; ?>">
                                <?php echo $monthly_profit >= 0 ? '↗' : '↘'; ?> Rs <?php echo number_format(abs($monthly_profit), 2); ?>
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

        // Highlight profit/loss with animation if significant
        const monthlyProfit = <?php echo $monthly_profit; ?>;
        const profitLink = document.querySelector('a[href="profit.php"]');

        if (Math.abs(monthlyProfit) > 10000) { // Highlight if profit/loss > 10,000
            if (monthlyProfit > 0) {
                profitLink.classList.add('animate-profit-pulse');
                const profitBadge = document.createElement('span');
                profitBadge.className = 'absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full flex items-center justify-center animate-pulse';
                profitBadge.innerHTML = '<i class="fas fa-arrow-up text-xs"></i>';
                profitLink.appendChild(profitBadge);
            } else {
                profitLink.classList.add('animate-warning-pulse');
                const lossBadge = document.createElement('span');
                lossBadge.className = 'absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center animate-pulse';
                lossBadge.innerHTML = '<i class="fas fa-exclamation text-xs"></i>';
                profitLink.appendChild(lossBadge);
            }
        }
    });
</script>