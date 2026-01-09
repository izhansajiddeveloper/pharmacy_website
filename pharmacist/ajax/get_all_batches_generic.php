<?php
require_once "../../config/db.php";
require_once "../../includes/auth_check.php";

// Only pharmacist allowed
if ($_SESSION['role'] !== 'pharmacist') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set header for JSON response
header('Content-Type: application/json');

// Check if generic_name is provided
if (!isset($_GET['generic_name']) || empty($_GET['generic_name'])) {
    echo json_encode(['success' => false, 'message' => 'Generic name is required']);
    exit;
}

$generic_name = trim($_GET['generic_name']);

// Fetch all batches for this generic medicine (across all brands)
$query = "
    SELECT 
        sb.*,
        m.name AS brand_name,
        mg.name AS generic_name,
        s.name AS supplier_name,
        DATEDIFF(sb.expiry_date, CURDATE()) AS days_until_expiry
    FROM stock_batches sb
    INNER JOIN medicines m ON sb.medicine_id = m.id
    LEFT JOIN medicine_generics mg ON m.generic_id = mg.id
    LEFT JOIN suppliers s ON sb.supplier_id = s.id
    WHERE mg.name LIKE CONCAT('%', ?, '%')
    ORDER BY 
        CASE 
            WHEN sb.expiry_date < CURDATE() OR sb.is_expired = 1 THEN 3
            WHEN DATEDIFF(sb.expiry_date, CURDATE()) <= 30 THEN 2
            ELSE 1
        END,
        sb.expiry_date ASC,
        sb.added_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $generic_name);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
$summary = [
    'total_batches' => 0,
    'valid_batches' => 0,
    'near_expiry' => 0,
    'expired_batches' => 0,
    'total_quantity' => 0,
    'brand_count' => 0
];

$brands_seen = [];

while ($row = $result->fetch_assoc()) {
    $batches[] = $row;

    // Update summary
    $summary['total_batches']++;
    $summary['total_quantity'] += $row['quantity'] ?? 0;

    // Track unique brands
    if (!in_array($row['brand_name'], $brands_seen)) {
        $brands_seen[] = $row['brand_name'];
        $summary['brand_count']++;
    }

    // Expiry logic
    $days_until_expiry = isset($row['days_until_expiry']) ? (int)$row['days_until_expiry'] : null;
    if ($row['is_expired'] == 1 || $days_until_expiry < 0) {
        $summary['expired_batches']++;
    } elseif ($days_until_expiry !== null && $days_until_expiry <= 30) {
        $summary['near_expiry']++;
    } else {
        $summary['valid_batches']++;
    }
}

echo json_encode([
    'success' => true,
    'batches' => $batches,
    'summary' => $summary
]);
