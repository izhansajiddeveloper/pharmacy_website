<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacist allowed
if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit();
}

// Function to automatically update expired batches
function updateExpiredBatches($conn)
{
    $update_stmt = $conn->prepare("
        UPDATE stock_batches 
        SET is_expired = 1 
        WHERE expiry_date < CURDATE() 
            AND is_expired = 0
            AND quantity > 0
    ");

    if ($update_stmt->execute()) {
        return $update_stmt->affected_rows;
    }
    return 0;
}

// Update expired batches on page load
$expired_updated = updateExpiredBatches($conn);

$search_term = '';
$results = [];

if (isset($_GET['q'])) {
    $search_term = trim($_GET['q']);

    if ($search_term !== '') {
        // Modified query to get batch-wise information grouped by generic name
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.name,
                m.generic_name,
                m.description,
                m.created_at,
                
                mc.name AS category_name,
                mt.name AS type_name,
                
                -- Batch details (grouped by batch)
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        'Batch:', 
                        sb.id, 
                        '|Qty:', 
                        sb.quantity, 
                        '|Exp:', 
                        DATE_FORMAT(sb.expiry_date, '%M %Y'),
                        '|Price:', 
                        sb.selling_price,
                        '|BatchNo:', 
                        IFNULL(sb.batch_no, 'N/A'),
                        '|Status:',
                        CASE 
                            WHEN sb.expiry_date < CURDATE() OR sb.is_expired = 1 THEN 'Expired'
                            WHEN DATEDIFF(sb.expiry_date, CURDATE()) <= 30 THEN 'Near Expiry'
                            ELSE 'Valid'
                        END,
                        '|Brand:', 
                        m.name
                    ) SEPARATOR '||'
                ) AS batch_details,
                
                -- Get total valid stock
                COALESCE(SUM(
                    CASE 
                        WHEN sb.expiry_date >= CURDATE() AND sb.is_expired = 0 
                        THEN sb.quantity 
                        ELSE 0 
                    END
                ), 0) AS total_valid_stock,
                
                -- Get total expired stock
                COALESCE(SUM(
                    CASE 
                        WHEN sb.expiry_date < CURDATE() OR sb.is_expired = 1 
                        THEN sb.quantity 
                        ELSE 0 
                    END
                ), 0) AS total_expired_stock,
                
                -- Count of batches
                COUNT(DISTINCT sb.id) AS batch_count,
                
                -- Get valid batches count
                COUNT(DISTINCT CASE 
                    WHEN sb.expiry_date >= CURDATE() AND sb.is_expired = 0 
                    THEN sb.id 
                END) AS valid_batch_count,
                
                -- Get expired batches count
                COUNT(DISTINCT CASE 
                    WHEN sb.expiry_date < CURDATE() OR sb.is_expired = 1 
                    THEN sb.id 
                END) AS expired_batch_count,
                
                -- Earliest expiry among valid batches
                MIN(
                    CASE 
                        WHEN sb.expiry_date >= CURDATE() AND sb.is_expired = 0 
                        THEN sb.expiry_date 
                        ELSE NULL 
                    END
                ) AS earliest_valid_expiry,
                
                -- Lowest selling price among valid batches
                MIN(
                    CASE 
                        WHEN sb.expiry_date >= CURDATE() AND sb.is_expired = 0 
                        THEN sb.selling_price 
                        ELSE NULL 
                    END
                ) AS min_selling_price,
                
                -- Count of different brands for this generic
                COUNT(DISTINCT m.id) AS brand_count

            FROM medicines m

            LEFT JOIN medicine_categories mc 
                ON m.category_id = mc.id

            LEFT JOIN medicine_types mt 
                ON m.type_id = mt.id

            LEFT JOIN stock_batches sb 
                ON m.id = sb.medicine_id

            WHERE m.generic_name LIKE CONCAT('%', ?, '%')
                AND (sb.id IS NULL OR sb.quantity > 0)

            GROUP BY m.generic_name
            ORDER BY m.generic_name ASC
        ");

        $stmt->bind_param("s", $search_term);
        $stmt->execute();

        $res = $stmt->get_result();
        $results = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search by Generic Name - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --primary-yellow-dark: #d97706;
            --accent-purple: #8b5cf6;
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
            --accent-teal: #14b8a6;
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

        .gradient-teal {
            background: linear-gradient(135deg, var(--accent-teal), #0d9488);
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(20, 184, 166, 0.3);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            background: white;
            border-color: #14b8a6;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
            outline: none;
        }

        .generic-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(20, 184, 166, 0.15);
            transition: all 0.3s ease;
        }

        .generic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(20, 184, 166, 0.2);
            border-color: rgba(20, 184, 166, 0.3);
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-generic {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #86efac;
        }

        .badge-brand {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .badge-stock {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .highlight {
            background-color: rgba(134, 239, 172, 0.3);
            padding: 2px 4px;
            border-radius: 4px;
        }

        /* Batch Table */
        .batch-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .batch-table th {
            background: #f8fafc;
            padding: 8px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        .batch-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .batch-table tr:hover {
            background-color: #f8fafc;
        }

        .batch-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .batch-valid {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .batch-near-expiry {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .batch-expired {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        /* Modal Animation */
        #viewGenericModal,
        #viewAllBatchesModal {
            transition: opacity 0.3s ease;
        }

        #viewGenericModal>div:first-child,
        #viewAllBatchesModal>div:first-child {
            transition: transform 0.3s ease-out;
        }

        #viewGenericModal:not(.hidden)>div:first-child,
        #viewAllBatchesModal:not(.hidden)>div:first-child {
            transform: translateY(0);
        }

        #viewGenericModal.hidden>div:first-child,
        #viewAllBatchesModal.hidden>div:first-child {
            transform: translateY(4px);
        }

        /* Smooth transitions */
        .bg-gray-500 {
            transition: opacity 0.3s ease;
        }

        .bg-white {
            transition: all 0.3s ease-out;
        }

        /* Scrollbar styling */
        .modal-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .modal-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-scroll::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .modal-scroll::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .line-clamp-2 {
            display: block;
            overflow: hidden;
            max-height: 3em;
            line-height: 1.5em;
        }

        .generic-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 12px;
        }

        .info-item {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #4b5563;
        }

        .info-item i {
            margin-right: 4px;
            width: 16px;
            text-align: center;
        }

        .price-tag {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .stock-low {
            color: #dc2626;
            background: #fee2e2;
        }

        .stock-medium {
            color: #d97706;
            background: #fef3c7;
        }

        .stock-high {
            color: #059669;
            background: #d1fae5;
        }

        .expired-badge {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .near-expiry {
            color: #ea580c;
            background: #ffedd5;
        }

        .batch-count-badge {
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            color: #7c3aed;
            border: 1px solid #d8b4fe;
        }

        .brand-count-badge {
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            color: #0c4a6e;
            border: 1px solid #7dd3fc;
        }

        .batch-table-container {
            max-height: 300px;
            overflow-y: auto;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8));
            border: 1px solid rgba(209, 213, 219, 0.3);
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .generic-highlight {
            background: linear-gradient(135deg, #a7f3d0, #5eead4);
            color: #064e3b;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        .warning-card {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fbbf24;
            border-left: 4px solid #f59e0b;
        }

        .danger-card {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 1px solid #fca5a5;
            border-left: 4px solid #ef4444;
        }

        .success-card {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #86efac;
            border-left: 4px solid #22c55e;
        }

        /* Enhanced batch table for all batches modal */
        .detailed-batch-table th {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            padding: 12px;
            font-size: 13px;
            color: #374151;
        }

        .detailed-batch-table td {
            padding: 12px;
            font-size: 13px;
        }

        .detailed-batch-table tr:hover {
            background-color: #f9fafb;
        }

        .batch-status-cell {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .purchase-price {
            color: #3b82f6;
            font-weight: 600;
        }

        .selling-price {
            color: #10b981;
            font-weight: 600;
        }

        .mrp-price {
            color: #8b5cf6;
            font-weight: 600;
        }

        .location-badge {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
        }

        .blob-green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .blob-teal {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
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
    <div class="blob blob-green w-72 h-72 -top-20 -right-20 animate-float"></div>
    <div class="blob blob-teal w-64 h-64 bottom-20 -left-20 animate-float" style="animation-delay: 1s;"></div>

    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <main class="flex-1 p-6">
            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Search by <span class="gradient-text">Generic Name</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-dna text-teal-500"></i>
                            <span>Search medicines by generic/chemical names with batch-wise expiry tracking</span>
                            <?php if ($expired_updated > 0): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Updated <?php echo $expired_updated; ?> expired batch<?php echo $expired_updated != 1 ? 'es' : ''; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="medicines.php"
                            class="px-6 py-3 border border-teal-200 text-gray-700 rounded-xl hover:bg-teal-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-teal-500"></i>
                            <span>Back to Medicines</span>
                        </a>
                        <a href="search_brand.php"
                            class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Search by Brand</span>
                        </a>
                        <a href="expired_medicines.php"
                            class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-skull-crossbones"></i>
                            <span>View Expired</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="glass-card rounded-2xl p-6 mb-6">
                <form method="GET" class="relative">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-flask text-teal-400"></i>
                                </div>
                                <input type="text" name="q" value="<?php echo htmlspecialchars($search_term); ?>"
                                    placeholder="Enter generic name (e.g., Paracetamol, Amoxicillin, etc.)"
                                    class="search-input w-full pl-10 pr-4 py-4 rounded-xl text-lg">
                            </div>
                        </div>
                        <div>
                            <button type="submit"
                                class="gradient-teal text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-search"></i>
                                <span>Search Generic</span>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Search Suggestions -->
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="text-sm text-gray-500 mr-2">Try:</span>
                        <?php
                        $quick_searches = ['Paracetamol', 'Amoxicillin', 'Ibuprofen', 'Cetirizine', 'Dextromethorphan'];
                        foreach ($quick_searches as $quick):
                        ?>
                            <a href="?q=<?php echo urlencode($quick); ?>"
                                class="px-3 py-1 bg-teal-50 text-teal-600 rounded-lg hover:bg-teal-100 transition text-sm">
                                <?php echo htmlspecialchars($quick); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>

            <?php if ($search_term !== ''): ?>
                <!-- Results Section -->
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 flex items-center space-x-3">
                                <i class="fas fa-list text-teal-500"></i>
                                <span>Search Results for
                                    <span class="text-teal-600">"<?php echo htmlspecialchars($search_term); ?>"</span>
                                </span>
                            </h3>
                            <p class="text-gray-500 text-sm mt-1">
                                Found <?php echo count($results); ?> generic medicine<?php echo count($results) !== 1 ? 's' : ''; ?>
                                (showing batch-wise stock with expiry tracking)
                            </p>
                        </div>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-clock mr-1"></i>
                            Search completed in <?php echo number_format(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3); ?>s
                        </div>
                    </div>

                    <?php if (count($results) > 0): ?>
                        <div class="grid grid-cols-1 gap-6">
                            <?php foreach ($results as $medicine):
                                // Highlight search term in generic name
                                $highlighted_generic = preg_replace(
                                    "/($search_term)/i",
                                    '<span class="highlight generic-highlight">$1</span>',
                                    htmlspecialchars($medicine['generic_name'])
                                );

                                // Parse batch details
                                $batch_details = [];
                                if (!empty($medicine['batch_details'])) {
                                    $batch_parts = explode('||', $medicine['batch_details']);
                                    foreach ($batch_parts as $batch) {
                                        $batch_data = [];
                                        $pairs = explode('|', $batch);
                                        foreach ($pairs as $pair) {
                                            list($key, $value) = explode(':', $pair, 2);
                                            $batch_data[$key] = $value;
                                        }
                                        $batch_details[] = $batch_data;
                                    }
                                }

                                // Stock totals
                                $total_valid_stock = $medicine['total_valid_stock'] ?? 0;
                                $total_expired_stock = $medicine['total_expired_stock'] ?? 0;
                                $batch_count = $medicine['batch_count'] ?? 0;
                                $valid_batch_count = $medicine['valid_batch_count'] ?? 0;
                                $expired_batch_count = $medicine['expired_batch_count'] ?? 0;
                                $brand_count = $medicine['brand_count'] ?? 0;

                                // Determine overall stock status
                                $stock_status_class = 'stock-high';
                                $stock_icon = 'fa-check-circle';
                                if ($total_valid_stock <= 10) {
                                    $stock_status_class = 'stock-low';
                                    $stock_icon = 'fa-exclamation-circle';
                                } elseif ($total_valid_stock <= 50) {
                                    $stock_status_class = 'stock-medium';
                                    $stock_icon = 'fa-exclamation-triangle';
                                }

                                // Get minimum selling price
                                $min_price = $medicine['min_selling_price'] ?? 0;
                                $display_price = ($min_price > 0) ? number_format($min_price, 2) : 'Not set';

                                // Format expiry date
                                $earliest_expiry = $medicine['earliest_valid_expiry'] ?? null;
                                $expiry_display = $earliest_expiry ? date('M Y', strtotime($earliest_expiry)) : 'No valid batches';

                                // Check if expiry is near (within 30 days)
                                $is_near_expiry = false;
                                if ($earliest_expiry) {
                                    $expiry_date = new DateTime($earliest_expiry);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiry_date);
                                    $is_near_expiry = $interval->days <= 30;
                                }

                                // Calculate expiry risk
                                $expiry_risk = 'none';
                                if ($expired_batch_count > 0 && $valid_batch_count == 0) {
                                    $expiry_risk = 'high';
                                } elseif ($is_near_expiry) {
                                    $expiry_risk = 'medium';
                                } elseif ($expired_batch_count > 0) {
                                    $expiry_risk = 'low';
                                }
                            ?>
                                <div class="generic-card rounded-xl p-5">
                                    <!-- Header -->
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center space-x-2">
                                                    <span class="badge badge-generic">
                                                        <i class="fas fa-dna mr-1"></i>Generic Name
                                                    </span>
                                                    <?php if ($brand_count > 0): ?>
                                                        <span class="badge brand-count-badge">
                                                            <i class="fas fa-tags mr-1"></i>
                                                            <?php echo $brand_count; ?> brand<?php echo $brand_count != 1 ? 's' : ''; ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($batch_count > 0): ?>
                                                        <span class="badge batch-count-badge">
                                                            <i class="fas fa-boxes mr-1"></i>
                                                            <?php echo $valid_batch_count; ?> valid, <?php echo $expired_batch_count; ?> expired
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="price-tag">
                                                    <?php echo $min_price > 0 ? 'From Rs ' . $display_price : 'Price N/A'; ?>
                                                </span>
                                            </div>
                                            <h4 class="font-bold text-gray-800 text-xl mb-2" style="word-break: break-word;">
                                                <?php echo $highlighted_generic; ?>
                                            </h4>
                                            <p class="text-gray-600 text-sm mb-3">
                                                <i class="fas fa-info-circle text-teal-500 mr-1"></i>
                                                Active ingredient - Found in multiple brands
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Stock Summary Cards -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                        <div class="stat-card">
                                            <div class="stat-value text-teal-600"><?php echo (int)$total_valid_stock; ?></div>
                                            <div class="stat-label">Available Stock</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                                Across all batches
                                            </div>
                                        </div>
                                        <div class="stat-card <?php echo $expired_batch_count > 0 ? 'danger-card' : ''; ?>">
                                            <div class="stat-value <?php echo $expired_batch_count > 0 ? 'text-red-600' : 'text-gray-600'; ?>">
                                                <?php echo (int)$total_expired_stock; ?>
                                            </div>
                                            <div class="stat-label">Expired Stock</div>
                                            <div class="text-xs <?php echo $expired_batch_count > 0 ? 'text-red-500' : 'text-gray-500'; ?> mt-1">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <?php echo $expired_batch_count; ?> expired batch<?php echo $expired_batch_count != 1 ? 'es' : ''; ?>
                                            </div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value text-purple-600"><?php echo $batch_count; ?></div>
                                            <div class="stat-label">Total Batches</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-box mr-1"></i>
                                                Across all brands
                                            </div>
                                        </div>
                                        <div class="stat-card <?php echo $is_near_expiry ? 'warning-card' : 'success-card'; ?>">
                                            <div class="stat-value <?php echo $is_near_expiry ? 'text-orange-600' : 'text-green-600'; ?>">
                                                <?php echo $expiry_display; ?>
                                            </div>
                                            <div class="stat-label">Earliest Expiry</div>
                                            <div class="text-xs <?php echo $is_near_expiry ? 'text-orange-500' : 'text-green-500'; ?> mt-1">
                                                <i class="fas <?php echo $is_near_expiry ? 'fa-exclamation-triangle' : 'fa-calendar-check'; ?> mr-1"></i>
                                                <?php echo $is_near_expiry ? 'Near Expiry' : 'Within Shelf Life'; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Batch Details Table -->
                                    <?php if (count($batch_details) > 0): ?>
                                        <div class="mt-6">
                                            <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                                                <i class="fas fa-boxes text-purple-500 mr-2"></i>
                                                Batch-wise Stock Details (Across All Brands)
                                            </h5>
                                            <div class="batch-table-container modal-scroll">
                                                <table class="batch-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Brand</th>
                                                            <th>Batch No</th>
                                                            <th>Quantity</th>
                                                            <th>Price</th>
                                                            <th>Expiry</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($batch_details as $batch):
                                                            $status_class = '';
                                                            if ($batch['Status'] === 'Expired') {
                                                                $status_class = 'batch-expired';
                                                            } elseif ($batch['Status'] === 'Near Expiry') {
                                                                $status_class = 'batch-near-expiry';
                                                            } else {
                                                                $status_class = 'batch-valid';
                                                            }
                                                        ?>
                                                            <tr>
                                                                <td class="font-medium text-blue-600"><?php echo htmlspecialchars($batch['Brand']); ?></td>
                                                                <td class="font-medium"><?php echo htmlspecialchars($batch['BatchNo']); ?></td>
                                                                <td>
                                                                    <span class="font-bold text-gray-800"><?php echo (int)$batch['Qty']; ?></span>
                                                                    <span class="text-xs text-gray-500"> units</span>
                                                                </td>
                                                                <td class="font-bold text-green-600">Rs <?php echo number_format($batch['Price'], 2); ?></td>
                                                                <td class="text-gray-700"><?php echo htmlspecialchars($batch['Exp']); ?></td>
                                                                <td>
                                                                    <span class="batch-badge <?php echo $status_class; ?>">
                                                                        <i class="fas <?php echo $batch['Status'] === 'Expired' ? 'fa-skull-crossbones' : ($batch['Status'] === 'Near Expiry' ? 'fa-exclamation-triangle' : 'fa-check-circle'); ?> mr-1"></i>
                                                                        <?php echo htmlspecialchars($batch['Status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8 text-gray-500 bg-gray-50 rounded-xl">
                                            <i class="fas fa-box-open text-4xl mb-3 text-gray-400"></i>
                                            <p class="text-lg font-medium text-gray-600">No batch information available</p>
                                            <p class="text-sm text-gray-500 mt-2">This generic doesn't have any stock batches yet</p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Medicine Information -->
                                    <div class="generic-info-grid mt-6">
                                        <div class="info-item">
                                            <i class="fas fa-layer-group text-purple-500"></i>
                                            <span>
                                                Category: <?php echo htmlspecialchars($medicine['category_name'] ?? 'N/A'); ?>
                                            </span>
                                        </div>

                                        <div class="info-item">
                                            <i class="fas fa-pills text-green-500"></i>
                                            <span>
                                                Type: <?php echo htmlspecialchars($medicine['type_name'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (!empty($medicine['description'])): ?>
                                        <div class="mt-4 mb-4">
                                            <div class="bg-gradient-to-r from-gray-50 to-teal-50 rounded-xl p-4">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-align-left text-teal-500 mr-2"></i>
                                                    <span class="font-medium text-gray-700">Description</span>
                                                </div>
                                                <p class="text-gray-600 text-sm line-clamp-2">
                                                    <?php echo htmlspecialchars(substr($medicine['description'], 0, 150)); ?>
                                                    <?php if (strlen($medicine['description']) > 150): ?>...<?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                                        <div class="text-xs text-gray-500">
                                            <i class="fas fa-dna mr-1 text-teal-500"></i>
                                            Generic Entry
                                            <?php if ($batch_count > 0): ?>
                                                <span class="ml-3">
                                                    <i class="fas fa-box mr-1"></i><?php echo $batch_count; ?> batch<?php echo $batch_count != 1 ? 'es' : ''; ?> across <?php echo $brand_count; ?> brand<?php echo $brand_count != 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="search_brand.php?q=<?php echo urlencode($medicine['generic_name']); ?>"
                                                class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition font-medium flex items-center space-x-2">
                                                <i class="fas fa-search"></i>
                                                <span>Find Brands</span>
                                            </a>
                                            <button type="button" onclick="openAllBatchesModalForGeneric('<?php echo addslashes($medicine['generic_name']); ?>')"
                                                class="px-4 py-2 bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100 transition font-medium flex items-center space-x-2">
                                                <i class="fas fa-boxes"></i>
                                                <span>All Batches</span>
                                            </button>
                                            <?php
                                            $generic_name = addslashes($medicine['generic_name'] ?? 'N/A');
                                            $category_name = addslashes($medicine['category_name'] ?? 'N/A');
                                            $type_name = addslashes($medicine['type_name'] ?? 'N/A');
                                            $min_price_modal = addslashes($medicine['min_selling_price'] ?? '0.00');
                                            $total_valid_stock_modal = addslashes($medicine['total_valid_stock'] ?? '0');
                                            $total_expired_stock_modal = addslashes($medicine['total_expired_stock'] ?? '0');
                                            $description = addslashes($medicine['description'] ?? 'No description available.');
                                            $created_at = addslashes($medicine['created_at'] ?? '');
                                            $earliest_expiry_modal = addslashes($medicine['earliest_valid_expiry'] ?? '');
                                            $batch_count_modal = addslashes($medicine['batch_count'] ?? '0');
                                            $valid_batch_count_modal = addslashes($medicine['valid_batch_count'] ?? '0');
                                            $expired_batch_count_modal = addslashes($medicine['expired_batch_count'] ?? '0');
                                            $batch_details_modal = addslashes($medicine['batch_details'] ?? '');
                                            $brand_count_modal = addslashes($medicine['brand_count'] ?? '0');
                                            ?>
                                            <button type="button"
                                                onclick="openGenericModal(
                                                    '<?php echo $generic_name; ?>',
                                                    '<?php echo $category_name; ?>',
                                                    '<?php echo $type_name; ?>',
                                                    '<?php echo $min_price_modal; ?>',
                                                    '<?php echo $total_valid_stock_modal; ?>',
                                                    '<?php echo $total_expired_stock_modal; ?>',
                                                    '<?php echo $earliest_expiry_modal; ?>',
                                                    '<?php echo $batch_count_modal; ?>',
                                                    '<?php echo $valid_batch_count_modal; ?>',
                                                    '<?php echo $expired_batch_count_modal; ?>',
                                                    '<?php echo $batch_details_modal; ?>',
                                                    '<?php echo $brand_count_modal; ?>',
                                                    '<?php echo $description; ?>',
                                                    '<?php echo $created_at; ?>'
                                                )"
                                                class="px-4 py-2 bg-teal-50 text-teal-600 rounded-lg hover:bg-teal-100 transition font-medium flex items-center space-x-2">
                                                <i class="fas fa-eye"></i>
                                                <span>View Details</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-20 h-20 mx-auto bg-red-50 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                            </div>
                            <h4 class="text-xl font-bold text-gray-800 mb-2">No Generic Medicines Found</h4>
                            <p class="text-gray-600 mb-6">No medicines found for generic name "<?php echo htmlspecialchars($search_term); ?>"</p>
                            <div class="space-x-3">
                                <a href="search_generic.php"
                                    class="px-6 py-3 bg-teal-50 text-teal-600 rounded-xl hover:bg-teal-100 transition font-medium inline-flex items-center space-x-2">
                                    <i class="fas fa-redo"></i>
                                    <span>Clear Search</span>
                                </a>
                                <a href="medicines.php"
                                    class="px-6 py-3 bg-green-50 text-green-600 rounded-xl hover:bg-green-100 transition font-medium inline-flex items-center space-x-2">
                                    <i class="fas fa-plus"></i>
                                    <span>Add New Medicine</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="glass-card rounded-2xl p-12 text-center">
                    <div class="w-24 h-24 mx-auto bg-teal-50 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-flask text-teal-500 text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-3">Search Medicines by Generic Name</h3>
                    <p class="text-gray-600 max-w-2xl mx-auto mb-8">
                        Enter a generic/chemical name to find all brands containing the same active ingredient with detailed batch-wise expiry tracking.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                        <div class="bg-teal-50 p-5 rounded-xl">
                            <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center mb-3 mx-auto">
                                <i class="fas fa-dna text-teal-600"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 mb-2">Generic Names</h4>
                            <p class="text-sm text-gray-600">Search by chemical/generic names</p>
                        </div>
                        <div class="bg-yellow-50 p-5 rounded-xl">
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-3 mx-auto">
                                <i class="fas fa-calendar-exclamation text-yellow-600"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 mb-2">Expiry Tracking</h4>
                            <p class="text-sm text-gray-600">Track batch expiry across brands</p>
                        </div>
                        <div class="bg-blue-50 p-5 rounded-xl">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-3 mx-auto">
                                <i class="fas fa-exchange-alt text-blue-600"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 mb-2">Cross-Brand View</h4>
                            <p class="text-sm text-gray-600">See all brands of same generic</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- View Details Modal (Original) -->
            <div id="viewGenericModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                    <!-- Modal panel -->
                    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                        <!-- Modal header -->
                        <div class="bg-gradient-to-r from-teal-600 to-green-600 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-flask text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white" id="modal-title">Generic Medicine Details</h3>
                                        <p class="text-teal-100 text-sm">Complete information with cross-brand expiry tracking</p>
                                    </div>
                                </div>
                                <button type="button" onclick="closeGenericModal()" class="text-white hover:text-teal-200 transition">
                                    <i class="fas fa-times text-2xl"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Modal body -->
                        <div class="bg-white px-6 py-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Left Column -->
                                <div class="space-y-6">
                                    <!-- Generic Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Generic Name</label>
                                        <div class="text-2xl font-bold text-teal-800" id="modal-generic-name"></div>
                                    </div>

                                    <!-- Category & Type -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Category</label>
                                            <div class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm inline-block" id="modal-category"></div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Medicine Type</label>
                                            <div class="px-3 py-1 bg-green-50 text-green-700 rounded-full text-sm inline-block" id="modal-type"></div>
                                        </div>
                                    </div>

                                    <!-- Stock Summary -->
                                    <div class="bg-gradient-to-r from-gray-50 to-teal-50 rounded-xl p-4">
                                        <h4 class="font-bold text-gray-700 mb-3">Stock Summary (Across All Brands)</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Available Stock</label>
                                                <div class="text-2xl font-bold text-gray-800" id="modal-valid-stock"></div>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Expired Stock</label>
                                                <div class="text-2xl font-bold text-red-600" id="modal-expired-stock"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Brands</label>
                                                <div class="text-sm font-medium text-blue-600" id="modal-brand-count"></div>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Batches</label>
                                                <div class="text-sm font-medium text-purple-600" id="modal-total-batches"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Valid Batches</label>
                                                <div class="text-sm font-medium text-green-600" id="modal-valid-batches"></div>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Expired Batches</label>
                                                <div class="text-sm font-medium text-red-600" id="modal-expired-batches"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Earliest Expiry</label>
                                                <div class="text-sm font-medium" id="modal-expiry-date"></div>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Lowest Price</label>
                                                <div class="text-lg font-bold text-green-600" id="modal-price"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Description -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-2">Description</label>
                                        <div class="bg-gray-50 rounded-xl p-4 max-h-32 overflow-y-auto modal-scroll" id="modal-description"></div>
                                    </div>
                                </div>

                                <!-- Right Column - Batch Details -->
                                <div class="space-y-6">
                                    <div>
                                        <h4 class="font-bold text-gray-700 mb-3 flex items-center">
                                            <i class="fas fa-boxes text-purple-500 mr-2"></i>
                                            Batch Details Across Brands (<span id="modal-table-total-batches"></span> batches)
                                        </h4>
                                        <div id="modal-batch-table-container" class="batch-table-container modal-scroll"></div>
                                    </div>

                                    <!-- ID & Date -->
                                    <div class="pt-4 border-t border-gray-200">
                                        <div class="flex justify-between text-sm text-gray-500">
                                            <div>
                                                <i class="fas fa-dna mr-1 text-teal-500"></i>
                                                <span>Generic Entry</span>
                                            </div>
                                            <div>
                                                <i class="far fa-calendar-alt mr-1"></i>
                                                <span>Search Time: <?php echo date('h:i A'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal footer -->
                        <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                            <button type="button" onclick="closeGenericModal()" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Close
                            </button>
                            <a href="#" id="modal-search-brands-link" class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:shadow-lg transition font-medium flex items-center space-x-2">
                                <i class="fas fa-search"></i>
                                <span>Find Brands</span>
                            </a>
                            <button type="button" onclick="openAllBatchesModalFromGenericModal()" class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl hover:shadow-lg transition font-medium flex items-center space-x-2">
                                <i class="fas fa-boxes"></i>
                                <span>All Batches</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New: All Batches Modal for Generic -->
            <div id="viewAllBatchesModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="batches-modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                    <!-- Modal panel -->
                    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full">
                        <!-- Modal header -->
                        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-boxes text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white" id="batches-modal-title">All Stock Batches</h3>
                                        <p class="text-purple-100 text-sm" id="batches-generic-name"></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="text-white text-sm" id="batches-summary"></div>
                                    <button type="button" onclick="closeAllBatchesModal()" class="text-white hover:text-purple-200 transition">
                                        <i class="fas fa-times text-2xl"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Modal body -->
                        <div class="bg-white px-6 py-6">
                            <div class="mb-6">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div class="bg-blue-50 p-4 rounded-xl">
                                        <div class="text-2xl font-bold text-blue-700" id="total-batches-count">0</div>
                                        <div class="text-sm text-blue-600">Total Batches</div>
                                    </div>
                                    <div class="bg-green-50 p-4 rounded-xl">
                                        <div class="text-2xl font-bold text-green-700" id="valid-batches-count">0</div>
                                        <div class="text-sm text-green-600">Valid Batches</div>
                                    </div>
                                    <div class="bg-yellow-50 p-4 rounded-xl">
                                        <div class="text-2xl font-bold text-yellow-700" id="near-expiry-count">0</div>
                                        <div class="text-sm text-yellow-600">Near Expiry</div>
                                    </div>
                                    <div class="bg-red-50 p-4 rounded-xl">
                                        <div class="text-2xl font-bold text-red-700" id="expired-batches-count">0</div>
                                        <div class="text-sm text-red-600">Expired Batches</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Batch Table -->
                            <div class="overflow-x-auto">
                                <table class="detailed-batch-table w-full">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Brand</th>
                                            <th class="text-left">Batch No</th>
                                            <th class="text-left">Quantity</th>
                                            <th class="text-left">Purchase Price</th>
                                            <th class="text-left">Selling Price</th>
                                            <th class="text-left">MRP</th>
                                            <th class="text-left">Received Date</th>
                                            <th class="text-left">Expiry Date</th>
                                            <th class="text-left">Location</th>
                                            <th class="text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="batches-table-body">
                                        <!-- Batches will be loaded here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <div id="no-batches-message" class="text-center py-12 hidden">
                                <div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-box-open text-gray-400 text-3xl"></i>
                                </div>
                                <h4 class="text-xl font-bold text-gray-800 mb-2">No Batches Found</h4>
                                <p class="text-gray-600 mb-6">No stock batches found for this generic medicine.</p>
                            </div>
                        </div>

                        <!-- Modal footer -->
                        <div class="bg-gray-50 px-6 py-4 flex justify-end">
                            <button type="button" onclick="closeAllBatchesModal()" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include "../includes/footer.php"; ?>

    <script>
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="q"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="q"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }

                // Escape to clear search
                if (e.key === 'Escape') {
                    const searchInput = document.querySelector('input[name="q"]');
                    if (searchInput && searchInput.value) {
                        window.location.href = 'search_generic.php';
                    }
                }

                // Ctrl/Cmd + B to switch to brand search
                if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                    e.preventDefault();
                    window.location.href = 'search_brand.php';
                }
            });
        });

        // Show loading state on form submit
        document.querySelector('form')?.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Searching...</span>';
                submitBtn.disabled = true;
            }
        });

        // All Batches Modal Functions for Generic Search
        let currentGenericName = null;

        function openAllBatchesModalForGeneric(genericName) {
            currentGenericName = genericName;

            // Set modal title
            document.getElementById('batches-generic-name').textContent = genericName;
            document.getElementById('batches-modal-title').textContent = 'All Batches - ' + genericName;

            // Show loading state
            document.getElementById('batches-table-body').innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-8">
                        <div class="flex justify-center">
                            <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                        </div>
                        <p class="text-gray-600 mt-2">Loading batches...</p>
                    </td>
                </tr>
            `;

            // Show modal
            const modal = document.getElementById('viewAllBatchesModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Load batches data via AJAX for generic
            loadBatchesDataForGeneric(genericName);
        }

        // Function to open All Batches modal from View Details modal
        function openAllBatchesModalFromGenericModal() {
            // Get generic name from the View Details modal
            const genericName = document.getElementById('modal-generic-name').textContent;

            // Close the View Details modal
            closeGenericModal();

            // Open the All Batches modal after a short delay
            setTimeout(() => {
                openAllBatchesModalForGeneric(genericName);
            }, 300);
        }

        async function loadBatchesDataForGeneric(genericName) {
            try {
                const response = await fetch(`ajax/get_all_batches_generic.php?generic_name=${encodeURIComponent(genericName)}`);
                const data = await response.json();

                if (data.success) {
                    updateBatchesTable(data.batches);
                    updateBatchesSummary(data.summary);
                    document.getElementById('no-batches-message').classList.add('hidden');
                } else {
                    showErrorMessage(data.message || 'Failed to load batches');
                }
            } catch (error) {
                console.error('Error loading batches:', error);
                showErrorMessage('Failed to load batches. Please try again.');
            }
        }

        function updateBatchesTable(batches) {
            const tableBody = document.getElementById('batches-table-body');

            if (!batches || batches.length === 0) {
                tableBody.innerHTML = '';
                document.getElementById('no-batches-message').classList.remove('hidden');
                return;
            }

            let html = '';
            batches.forEach(batch => {
                // Determine status and styling
                let statusClass = '';
                let statusText = '';
                let statusIcon = '';

                if (batch.is_expired == 1 || new Date(batch.expiry_date) < new Date()) {
                    statusClass = 'batch-expired';
                    statusText = 'Expired';
                    statusIcon = 'fa-skull-crossbones';
                } else {
                    const expiryDate = new Date(batch.expiry_date);
                    const today = new Date();
                    const daysDiff = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));

                    if (daysDiff <= 30) {
                        statusClass = 'batch-near-expiry';
                        statusText = 'Near Expiry';
                        statusIcon = 'fa-exclamation-triangle';
                    } else {
                        statusClass = 'batch-valid';
                        statusText = 'Valid';
                        statusIcon = 'fa-check-circle';
                    }
                }

                // Format dates
                const receivedDate = new Date(batch.received_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                const expiryDate = new Date(batch.expiry_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                html += `
                    <tr class="hover:bg-gray-50">
                        <td class="py-3">
                            <div class="font-medium text-blue-600">${batch.brand_name || 'N/A'}</div>
                        </td>
                        <td class="py-3">
                            <div class="font-medium text-gray-900">${batch.batch_no || 'N/A'}</div>
                        </td>
                        <td class="py-3">
                            <div class="font-bold text-gray-800">${batch.quantity}</div>
                            <div class="text-xs text-gray-500">units</div>
                        </td>
                        <td class="py-3">
                            <span class="purchase-price">Rs ${parseFloat(batch.purchase_price || 0).toFixed(2)}</span>
                        </td>
                        <td class="py-3">
                            <span class="selling-price">Rs ${parseFloat(batch.selling_price || 0).toFixed(2)}</span>
                        </td>
                        <td class="py-3">
                            <span class="mrp-price">Rs ${parseFloat(batch.mrp || 0).toFixed(2)}</span>
                        </td>
                        <td class="py-3 text-gray-700">${receivedDate}</td>
                        <td class="py-3">
                            <div class="text-gray-700">${expiryDate}</div>
                            ${new Date(batch.expiry_date) < new Date() ? 
                                '<div class="text-xs text-red-500">Expired</div>' : 
                                ''}
                        </td>
                        <td class="py-3">
                            ${batch.location ? 
                                `<span class="location-badge">${batch.location}</span>` : 
                                '<span class="text-gray-400">-</span>'}
                        </td>
                        <td class="py-3">
                            <div class="batch-status-cell">
                                <span class="batch-badge ${statusClass}">
                                    <i class="fas ${statusIcon} mr-1"></i>
                                    ${statusText}
                                </span>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = html;
        }

        function updateBatchesSummary(summary) {
            document.getElementById('total-batches-count').textContent = summary.total_batches || 0;
            document.getElementById('valid-batches-count').textContent = summary.valid_batches || 0;
            document.getElementById('near-expiry-count').textContent = summary.near_expiry || 0;
            document.getElementById('expired-batches-count').textContent = summary.expired_batches || 0;

            const summaryText = `${summary.total_batches || 0} batches • ${summary.total_quantity || 0} total units • ${summary.brand_count || 0} brands`;
            document.getElementById('batches-summary').textContent = summaryText;
        }

        function showErrorMessage(message) {
            document.getElementById('batches-table-body').innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-8">
                        <div class="w-12 h-12 mx-auto bg-red-50 rounded-full flex items-center justify-center mb-3">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                        </div>
                        <p class="text-red-600 font-medium">${message}</p>
                        <button onclick="loadBatchesDataForGeneric(currentGenericName)" class="mt-3 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm">
                            <i class="fas fa-redo mr-1"></i> Retry
                        </button>
                    </td>
                </tr>
            `;
        }

        function closeAllBatchesModal() {
            const modal = document.getElementById('viewAllBatchesModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Original Generic Modal Functions (keep these unchanged)
        function openGenericModal(genericName, category, type, minPrice, validStock, expiredStock, earliestExpiry, totalBatches, validBatches, expiredBatches, batchDetails, brandCount, description, date) {
            // Populate modal with data
            document.getElementById('modal-generic-name').textContent = genericName;
            document.getElementById('modal-category').textContent = category !== 'N/A' ? category : 'Not specified';
            document.getElementById('modal-type').textContent = type !== 'N/A' ? type : 'Not specified';

            // Stock information
            document.getElementById('modal-valid-stock').textContent = validStock + ' units';
            document.getElementById('modal-expired-stock').textContent = expiredStock + ' units';
            document.getElementById('modal-brand-count').textContent = brandCount + ' brand' + (brandCount != 1 ? 's' : '');
            document.getElementById('modal-total-batches').textContent = totalBatches + ' batch' + (totalBatches != 1 ? 'es' : '');
            document.getElementById('modal-valid-batches').textContent = validBatches + ' valid';
            document.getElementById('modal-expired-batches').textContent = expiredBatches + ' expired';
            document.getElementById('modal-table-total-batches').textContent = totalBatches;

            // Expiry date
            const expiryElement = document.getElementById('modal-expiry-date');
            if (earliestExpiry && earliestExpiry !== '') {
                const expiryDate = new Date(earliestExpiry);
                const today = new Date();
                const daysDiff = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));

                expiryElement.textContent = expiryDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                if (daysDiff <= 30) {
                    expiryElement.className = 'text-sm font-medium text-orange-600';
                    expiryElement.innerHTML += ' <i class="fas fa-exclamation-triangle ml-1"></i> (Near Expiry)';
                } else if (daysDiff <= 90) {
                    expiryElement.className = 'text-sm font-medium text-yellow-600';
                } else {
                    expiryElement.className = 'text-sm font-medium text-green-600';
                }
            } else {
                expiryElement.textContent = 'No expiry date set';
                expiryElement.className = 'text-sm font-medium text-gray-500';
            }

            // Price
            const priceValue = parseFloat(minPrice) || 0;
            document.getElementById('modal-price').textContent = priceValue > 0 ? 'Rs ' + priceValue.toFixed(2) : 'Price not set';

            // Description
            document.getElementById('modal-description').textContent = description !== 'No description available.' ? description : 'No description available.';

            // Set search brands link
            document.getElementById('modal-search-brands-link').href = `search_brand.php?q=${encodeURIComponent(genericName)}`;

            // Generate batch table
            const batchTableContainer = document.getElementById('modal-batch-table-container');
            batchTableContainer.innerHTML = '';

            if (batchDetails && batchDetails !== '') {
                const batches = batchDetails.split('||');
                let tableHTML = `
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Batch No</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Expiry</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                batches.forEach(batch => {
                    const batchData = {};
                    batch.split('|').forEach(pair => {
                        const [key, value] = pair.split(':');
                        if (key && value) batchData[key] = value;
                    });

                    let statusClass = '';
                    let statusText = batchData['Status'] || 'Unknown';
                    let statusIcon = 'fa-question-circle';

                    if (statusText === 'Expired') {
                        statusClass = 'batch-expired';
                        statusIcon = 'fa-skull-crossbones';
                    } else if (statusText === 'Near Expiry') {
                        statusClass = 'batch-near-expiry';
                        statusIcon = 'fa-exclamation-triangle';
                    } else {
                        statusClass = 'batch-valid';
                        statusIcon = 'fa-check-circle';
                    }

                    tableHTML += `
                        <tr>
                            <td class="font-medium text-blue-600">${batchData['Brand'] || 'N/A'}</td>
                            <td class="font-medium">${batchData['BatchNo'] || 'N/A'}</td>
                            <td>
                                <span class="font-bold text-gray-800">${batchData['Qty'] || '0'}</span>
                                <span class="text-xs text-gray-500"> units</span>
                            </td>
                            <td class="font-bold text-green-600">Rs ${parseFloat(batchData['Price'] || 0).toFixed(2)}</td>
                            <td class="text-gray-700">${batchData['Exp'] || 'N/A'}</td>
                            <td>
                                <span class="batch-badge ${statusClass}">
                                    <i class="fas ${statusIcon} mr-1"></i>
                                    ${statusText}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                tableHTML += '</tbody></table>';
                batchTableContainer.innerHTML = tableHTML;
            } else {
                batchTableContainer.innerHTML = `
                    <div class="text-center py-8 text-gray-500 bg-gray-50 rounded-xl">
                        <i class="fas fa-box-open text-3xl mb-2 text-gray-400"></i>
                        <p class="text-gray-600">No batch information available</p>
                        <p class="text-sm text-gray-500 mt-1">This generic doesn't have any stock batches yet</p>
                    </div>
                `;
            }

            // Show modal
            const modal = document.getElementById('viewGenericModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeGenericModal() {
            const modal = document.getElementById('viewGenericModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside or pressing Escape
        document.addEventListener('DOMContentLoaded', function() {
            const genericModal = document.getElementById('viewGenericModal');
            const batchesModal = document.getElementById('viewAllBatchesModal');

            // Close modals when clicking outside
            genericModal.addEventListener('click', function(e) {
                if (e.target === genericModal) {
                    closeGenericModal();
                }
            });

            batchesModal.addEventListener('click', function(e) {
                if (e.target === batchesModal) {
                    closeAllBatchesModal();
                }
            });

            // Close modals with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (!genericModal.classList.contains('hidden')) {
                        closeGenericModal();
                    }
                    if (!batchesModal.classList.contains('hidden')) {
                        closeAllBatchesModal();
                    }
                }
            });
        });

        // Highlight near expiry and expired batches
        document.addEventListener('DOMContentLoaded', function() {
            // Check for near expiry batches and show warning
            const nearExpiryCount = document.querySelectorAll('.batch-near-expiry').length;
            const expiredCount = document.querySelectorAll('.batch-expired').length;

            if (nearExpiryCount > 0 || expiredCount > 0) {
                console.log(`Warning: ${nearExpiryCount} near expiry and ${expiredCount} expired batches found`);
            }
        });
    </script>
</body>

</html>