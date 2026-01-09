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

// Update expired batches on page load (or run via cron job)
$expired_updated = updateExpiredBatches($conn);

$search_term = '';
$results = [];

if (isset($_GET['q'])) {
    $search_term = trim($_GET['q']);

    if ($search_term !== '') {

        // Modified query to get batch-wise information
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
                        END
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
                ) AS min_selling_price

            FROM medicines m

            LEFT JOIN medicine_categories mc 
                ON m.category_id = mc.id

            LEFT JOIN medicine_types mt 
                ON m.type_id = mt.id

            LEFT JOIN stock_batches sb 
                ON m.id = sb.medicine_id

            WHERE m.name LIKE CONCAT('%', ?, '%')
                AND (sb.id IS NULL OR sb.quantity > 0)

            GROUP BY m.id
            ORDER BY m.name ASC
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
    <title>Search by Brand - MediCare Pharma</title>
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

        .gradient-blue {
            background: linear-gradient(135deg, var(--accent-blue), #2563eb);
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            background: white;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .medicine-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(139, 92, 246, 0.15);
            transition: all 0.3s ease;
        }

        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(139, 92, 246, 0.2);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-brand {
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .badge-generic {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-stock {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .highlight {
            background-color: rgba(253, 224, 71, 0.3);
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
        #viewMedicineModal,
        #viewAllBatchesModal {
            transition: opacity 0.3s ease;
        }

        #viewMedicineModal>div:first-child,
        #viewAllBatchesModal>div:first-child {
            transition: transform 0.3s ease-out;
        }

        #viewMedicineModal:not(.hidden)>div:first-child,
        #viewAllBatchesModal:not(.hidden)>div:first-child {
            transform: translateY(0);
        }

        #viewMedicineModal.hidden>div:first-child,
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

        .medicine-info-grid {
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

        .batch-table-container {
            max-height: 300px;
            overflow-y: auto;
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
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <main class="flex-1 p-6">
            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Search by <span class="gradient-text">Brand Name</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-search text-blue-500"></i>
                            <span>Search medicines using brand/trade names</span>
                            <?php if ($expired_updated > 0): ?>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    Updated <?php echo $expired_updated; ?> expired batch<?php echo $expired_updated != 1 ? 'es' : ''; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="medicines.php"
                            class="px-6 py-3 border border-blue-200 text-gray-700 rounded-xl hover:bg-blue-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left text-blue-500"></i>
                            <span>Back to Medicines</span>
                        </a>
                        <a href="search_generic.php"
                            class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:shadow-lg transition font-bold flex items-center space-x-2 shadow">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Search by Generic</span>
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
                                    <i class="fas fa-pills text-blue-400"></i>
                                </div>
                                <input type="text" name="q" value="<?php echo htmlspecialchars($search_term); ?>"
                                    placeholder="Enter brand name (e.g., Paracetamol, Amoxicillin, etc.)"
                                    class="search-input w-full pl-10 pr-4 py-4 rounded-xl text-lg">
                            </div>
                        </div>
                        <div>
                            <button type="submit"
                                class="gradient-blue text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition-all duration-300 hover:scale-105 flex items-center space-x-2 shadow">
                                <i class="fas fa-search"></i>
                                <span>Search Brand</span>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Search Suggestions -->
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="text-sm text-gray-500 mr-2">Try:</span>
                        <?php
                        $quick_searches = ['Panadol', 'Calpol', 'CoughClear', 'Amoxil', 'Allergex'];
                        foreach ($quick_searches as $quick):
                        ?>
                            <a href="?q=<?php echo urlencode($quick); ?>"
                                class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm">
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
                                <i class="fas fa-list text-purple-500"></i>
                                <span>Search Results for
                                    <span class="text-blue-600">"<?php echo htmlspecialchars($search_term); ?>"</span>
                                </span>
                            </h3>
                            <p class="text-gray-500 text-sm mt-1">
                                Found <?php echo count($results); ?> medicine<?php echo count($results) !== 1 ? 's' : ''; ?>
                                (showing batch-wise stock)
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
                                // Highlight search term in medicine name
                                $highlighted_name = preg_replace(
                                    "/($search_term)/i",
                                    '<span class="highlight">$1</span>',
                                    htmlspecialchars($medicine['name'])
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
                            ?>
                                <div class="medicine-card rounded-xl p-5">
                                    <!-- Header -->
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center space-x-2">
                                                    <span class="badge badge-brand">
                                                        <i class="fas fa-tag mr-1"></i>Brand
                                                    </span>
                                                    <?php if (!empty($medicine['generic_name'])): ?>
                                                        <span class="badge badge-generic">
                                                            <i class="fas fa-dna mr-1"></i>Generic
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
                                            <h4 class="font-bold text-gray-800 text-lg mb-2" style="word-break: break-word;">
                                                <?php echo $highlighted_name; ?>
                                            </h4>
                                            <?php if (!empty($medicine['generic_name'])): ?>
                                                <p class="text-gray-600 text-sm mb-3">
                                                    <i class="fas fa-dna text-green-500 mr-1"></i>
                                                    <?php echo htmlspecialchars($medicine['generic_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Stock Summary -->
                                    <div class="mb-4 bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl p-4">
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-gray-800"><?php echo (int)$total_valid_stock; ?></div>
                                                <div class="text-xs text-gray-600">Available Stock</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-red-600"><?php echo (int)$total_expired_stock; ?></div>
                                                <div class="text-xs text-gray-600">Expired Stock</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-purple-600"><?php echo $batch_count; ?></div>
                                                <div class="text-xs text-gray-600">Total Batches</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-lg font-bold <?php echo $is_near_expiry ? 'text-orange-600' : 'text-green-600'; ?>">
                                                    <?php echo $expiry_display; ?>
                                                </div>
                                                <div class="text-xs text-gray-600">Earliest Expiry</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Batch Details Table -->
                                    <?php if (count($batch_details) > 0): ?>
                                        <div class="mt-6">
                                            <h5 class="font-bold text-gray-700 mb-3 flex items-center">
                                                <i class="fas fa-boxes text-purple-500 mr-2"></i>
                                                Batch-wise Stock Details
                                            </h5>
                                            <div class="batch-table-container modal-scroll">
                                                <table class="batch-table">
                                                    <thead>
                                                        <tr>
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
                                                                <td class="font-medium"><?php echo htmlspecialchars($batch['BatchNo']); ?></td>
                                                                <td>
                                                                    <span class="font-bold text-gray-800"><?php echo (int)$batch['Qty']; ?></span>
                                                                    <span class="text-xs text-gray-500"> units</span>
                                                                </td>
                                                                <td class="font-bold text-green-600">Rs <?php echo number_format($batch['Price'], 2); ?></td>
                                                                <td class="text-gray-700"><?php echo htmlspecialchars($batch['Exp']); ?></td>
                                                                <td>
                                                                    <span class="batch-badge <?php echo $status_class; ?>">
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
                                        <div class="text-center py-4 text-gray-500">
                                            <i class="fas fa-box-open text-2xl mb-2"></i>
                                            <p>No batch information available</p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Medicine Information -->
                                    <div class="medicine-info-grid mt-6">
                                        <div class="info-item">
                                            <i class="fas fa-layer-group text-purple-500"></i>
                                            <span>
                                                <?php echo htmlspecialchars($medicine['category_name'] ?? 'N/A'); ?>
                                            </span>
                                        </div>

                                        <div class="info-item">
                                            <i class="fas fa-pills text-green-500"></i>
                                            <span>
                                                <?php echo htmlspecialchars($medicine['type_name'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (!empty($medicine['description'])): ?>
                                        <div class="mt-4 mb-4">
                                            <p class="text-gray-500 text-sm line-clamp-2">
                                                <i class="fas fa-align-left text-gray-400 mr-1"></i>
                                                <?php echo htmlspecialchars(substr($medicine['description'], 0, 120)); ?>
                                                <?php if (strlen($medicine['description']) > 120): ?>...<?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                                        <div class="text-xs text-gray-500">
                                            <i class="fas fa-hashtag mr-1"></i>ID: <?php echo $medicine['id']; ?>
                                            <?php if ($batch_count > 0): ?>
                                                <span class="ml-2">
                                                    <i class="fas fa-box mr-1"></i><?php echo $batch_count; ?> batches
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex space-x-2">
                                            <a href="edit_medicine.php?id=<?php echo $medicine['id']; ?>"
                                                class="px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition font-medium flex items-center space-x-2">
                                                <i class="fas fa-edit"></i>
                                                <span>Edit</span>
                                            </a>
                                            <button type="button" onclick="openAllBatchesModal(<?php echo $medicine['id']; ?>, '<?php echo addslashes($medicine['name']); ?>')"
                                                class="px-4 py-2 bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100 transition font-medium flex items-center space-x-2">
                                                <i class="fas fa-boxes"></i>
                                                <span>All Batches</span>
                                            </button>
                                            <?php
                                            $medicine_id = $medicine['id'] ?? '';
                                            $medicine_name = $medicine['name'] ?? 'N/A';
                                            $generic_name = $medicine['generic_name'] ?? 'N/A';
                                            $category_name = $medicine['category_name'] ?? 'N/A';
                                            $type_name = $medicine['type_name'] ?? 'N/A';
                                            $min_price_modal = $medicine['min_selling_price'] ?? '0.00';
                                            $total_valid_stock_modal = $medicine['total_valid_stock'] ?? '0';
                                            $total_expired_stock_modal = $medicine['total_expired_stock'] ?? '0';
                                            $description = $medicine['description'] ?? 'No description available.';
                                            $created_at = $medicine['created_at'] ?? '';
                                            $earliest_expiry_modal = $medicine['earliest_valid_expiry'] ?? '';
                                            $batch_count_modal = $medicine['batch_count'] ?? '0';
                                            $valid_batch_count_modal = $medicine['valid_batch_count'] ?? '0';
                                            $expired_batch_count_modal = $medicine['expired_batch_count'] ?? '0';
                                            $batch_details_modal = $medicine['batch_details'] ?? '';
                                            ?>
                                            <button type="button"
                                                onclick="openMedicineModal(
                                                    '<?php echo addslashes($medicine_id); ?>',
                                                    '<?php echo addslashes($medicine_name); ?>',
                                                    '<?php echo addslashes($generic_name); ?>',
                                                    '<?php echo addslashes($category_name); ?>',
                                                    '<?php echo addslashes($type_name); ?>',
                                                    '<?php echo addslashes($min_price_modal); ?>',
                                                    '<?php echo addslashes($total_valid_stock_modal); ?>',
                                                    '<?php echo addslashes($total_expired_stock_modal); ?>',
                                                    '<?php echo addslashes($earliest_expiry_modal); ?>',
                                                    '<?php echo addslashes($batch_count_modal); ?>',
                                                    '<?php echo addslashes($valid_batch_count_modal); ?>',
                                                    '<?php echo addslashes($expired_batch_count_modal); ?>',
                                                    '<?php echo addslashes($batch_details_modal); ?>',
                                                    '<?php echo addslashes($description); ?>',
                                                    '<?php echo addslashes($created_at); ?>'
                                                )"
                                                class="view-btn px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition font-medium flex items-center space-x-2">
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
                            <h4 class="text-xl font-bold text-gray-800 mb-2">No Medicines Found</h4>
                            <p class="text-gray-600 mb-6">No medicines found for brand name "<?php echo htmlspecialchars($search_term); ?>"</p>
                            <div class="space-x-3">
                                <a href="search_brand.php"
                                    class="px-6 py-3 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition font-medium inline-flex items-center space-x-2">
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
                    <div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-pills text-blue-500 text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-3">Search Medicines by Brand</h3>
                    <p class="text-gray-600 max-w-2xl mx-auto mb-8">
                        Enter a brand/trade name in the search box above to find medicines with batch-wise stock details.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                        <div class="bg-blue-50 p-5 rounded-xl">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-3 mx-auto">
                                <i class="fas fa-tag text-blue-600"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 mb-2">Brand Names</h4>
                            <p class="text-sm text-gray-600">Search using pharmaceutical brand names</p>
                        </div>
                        <div class="bg-purple-50 p-5 rounded-xl">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-3 mx-auto">
                                <i class="fas fa-boxes text-purple-600"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 mb-2">Batch-wise View</h4>
                            <p class="text-sm text-gray-600">See detailed batch information</p>
                        </div>
                        <div class="bg-green-50 p-5 rounded-xl">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-3 mx-auto">
                                <i class="fas fa-exchange-alt text-green-600"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 mb-2">Stock Tracking</h4>
                            <p class="text-sm text-gray-600">Track valid vs expired batches</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- View Details Modal -->
            <div id="viewMedicineModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                    <!-- Modal panel -->
                    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                        <!-- Modal header -->
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-pills text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-white" id="modal-title">Medicine Details</h3>
                                        <p class="text-blue-100 text-sm">Complete information with batch details</p>
                                    </div>
                                </div>
                                <button type="button" onclick="closeMedicineModal()" class="text-white hover:text-blue-200 transition">
                                    <i class="fas fa-times text-2xl"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Modal body -->
                        <div class="bg-white px-6 py-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Left Column -->
                                <div class="space-y-6">
                                    <!-- Medicine Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Medicine Name</label>
                                        <div class="text-xl font-bold text-gray-800" id="modal-name"></div>
                                    </div>

                                    <!-- Generic Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">Generic Name</label>
                                        <div class="text-lg text-gray-700" id="modal-generic-name"></div>
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
                                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl p-4">
                                        <h4 class="font-bold text-gray-700 mb-3">Stock Summary</h4>
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
                                            Batch Details (<span id="modal-total-batches"></span>)
                                        </h4>
                                        <div id="modal-batch-table-container" class="batch-table-container modal-scroll"></div>
                                    </div>

                                    <!-- ID & Date -->
                                    <div class="pt-4 border-t border-gray-200">
                                        <div class="flex justify-between text-sm text-gray-500">
                                            <div>
                                                <i class="fas fa-hashtag mr-1"></i>
                                                <span>ID: <span id="modal-id"></span></span>
                                            </div>
                                            <div>
                                                <i class="far fa-calendar-alt mr-1"></i>
                                                <span>Added: <span id="modal-date"></span></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal footer -->
                        <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                            <button type="button" onclick="closeMedicineModal()" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                Close
                            </button>
                            <a href="#" id="modal-edit-link" class="px-5 py-2.5 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-xl hover:shadow-lg transition font-medium flex items-center space-x-2">
                                <i class="fas fa-edit"></i>
                                <span>Edit Medicine</span>
                            </a>
                            <button type="button" onclick="openAllBatchesModalFromViewDetails()" class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl hover:shadow-lg transition font-medium flex items-center space-x-2">
                                <i class="fas fa-boxes"></i>
                                <span>Manage Batches</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New: All Batches Modal -->
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
                                        <p class="text-purple-100 text-sm" id="batches-medicine-name"></p>
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
                                <p class="text-gray-600 mb-6">No stock batches found for this medicine.</p>
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
                        window.location.href = 'search_brand.php';
                    }
                }

                // Ctrl/Cmd + G to switch to generic search
                if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                    e.preventDefault();
                    window.location.href = 'search_generic.php';
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

        // All Batches Modal Functions
        let currentMedicineId = null;
        let currentMedicineName = null;

        function openAllBatchesModal(medicineId, medicineName) {
            currentMedicineId = medicineId;
            currentMedicineName = medicineName;

            // Set modal title
            document.getElementById('batches-medicine-name').textContent = medicineName;
            document.getElementById('batches-modal-title').textContent = 'All Batches - ' + medicineName;

            // Show loading state
            document.getElementById('batches-table-body').innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-8">
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

            // Load batches data via AJAX
            loadBatchesData(medicineId);
        }

        // Function to open All Batches modal from View Details modal
        function openAllBatchesModalFromViewDetails() {
            // Get medicine ID and name from the View Details modal
            const medicineId = document.getElementById('modal-id').textContent;
            const medicineName = document.getElementById('modal-name').textContent;

            // Close the View Details modal
            closeMedicineModal();

            // Open the All Batches modal after a short delay
            setTimeout(() => {
                openAllBatchesModal(medicineId, medicineName);
            }, 300);
        }

        async function loadBatchesData(medicineId) {
            try {
                const response = await fetch(`ajax/get_all_batches.php?medicine_id=${medicineId}`);
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

            const summaryText = `${summary.total_batches || 0} batches • ${summary.total_quantity || 0} total units`;
            document.getElementById('batches-summary').textContent = summaryText;
        }

        function showErrorMessage(message) {
            document.getElementById('batches-table-body').innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-8">
                        <div class="w-12 h-12 mx-auto bg-red-50 rounded-full flex items-center justify-center mb-3">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                        </div>
                        <p class="text-red-600 font-medium">${message}</p>
                        <button onclick="loadBatchesData(currentMedicineId)" class="mt-3 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm">
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

        // Medicine Modal Functions
        function openMedicineModal(id, name, genericName, category, type, minPrice, validStock, expiredStock, earliestExpiry, totalBatches, validBatches, expiredBatches, batchDetails, description, date) {
            // Populate modal with data
            document.getElementById('modal-id').textContent = id;
            document.getElementById('modal-name').textContent = name;
            document.getElementById('modal-generic-name').textContent = genericName !== 'N/A' ? genericName : 'Not specified';
            document.getElementById('modal-category').textContent = category !== 'N/A' ? category : 'Not specified';
            document.getElementById('modal-type').textContent = type !== 'N/A' ? type : 'Not specified';

            // Stock information
            document.getElementById('modal-valid-stock').textContent = validStock + ' units';
            document.getElementById('modal-expired-stock').textContent = expiredStock + ' units';
            document.getElementById('modal-valid-batches').textContent = validBatches + ' batches';
            document.getElementById('modal-expired-batches').textContent = expiredBatches + ' batches';
            document.getElementById('modal-total-batches').textContent = totalBatches + ' batches';

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
                    expiryElement.innerHTML += ' <i class="fas fa-exclamation-triangle ml-1"></i>';
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

            // Format date
            const dateObj = new Date(date);
            if (!isNaN(dateObj.getTime())) {
                document.getElementById('modal-date').textContent = dateObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            } else {
                document.getElementById('modal-date').textContent = date;
            }

            // Generate batch table
            const batchTableContainer = document.getElementById('modal-batch-table-container');
            batchTableContainer.innerHTML = '';

            if (batchDetails && batchDetails !== '') {
                const batches = batchDetails.split('||');
                let tableHTML = `
                    <table class="batch-table">
                        <thead>
                            <tr>
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

                    if (statusText === 'Expired') {
                        statusClass = 'batch-expired';
                    } else if (statusText === 'Near Expiry') {
                        statusClass = 'batch-near-expiry';
                    } else {
                        statusClass = 'batch-valid';
                    }

                    tableHTML += `
                        <tr>
                            <td class="font-medium">${batchData['BatchNo'] || 'N/A'}</td>
                            <td>
                                <span class="font-bold text-gray-800">${batchData['Qty'] || '0'}</span>
                                <span class="text-xs text-gray-500"> units</span>
                            </td>
                            <td class="font-bold text-green-600">Rs ${parseFloat(batchData['Price'] || 0).toFixed(2)}</td>
                            <td class="text-gray-700">${batchData['Exp'] || 'N/A'}</td>
                            <td>
                                <span class="batch-badge ${statusClass}">
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
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-box-open text-3xl mb-2"></i>
                        <p>No batch information available</p>
                    </div>
                `;
            }

            // Set edit link
            document.getElementById('modal-edit-link').href = `edit_medicine.php?id=${id}`;

            // Show modal
            const modal = document.getElementById('viewMedicineModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMedicineModal() {
            const modal = document.getElementById('viewMedicineModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside or pressing Escape
        document.addEventListener('DOMContentLoaded', function() {
            const medicineModal = document.getElementById('viewMedicineModal');
            const batchesModal = document.getElementById('viewAllBatchesModal');

            // Close modals when clicking outside
            medicineModal.addEventListener('click', function(e) {
                if (e.target === medicineModal) {
                    closeMedicineModal();
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
                    if (!medicineModal.classList.contains('hidden')) {
                        closeMedicineModal();
                    }
                    if (!batchesModal.classList.contains('hidden')) {
                        closeAllBatchesModal();
                    }
                }
            });
        });
    </script>
</body>

</html>