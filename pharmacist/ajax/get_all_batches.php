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

// Check if medicine_id is provided
if (!isset($_GET['medicine_id']) || !is_numeric($_GET['medicine_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
    exit;
}

$medicine_id = intval($_GET['medicine_id']);

// Fetch all batches for this medicine
$query = "
    SELECT 
        sb.*,
        s.name AS supplier_name,
        DATEDIFF(sb.expiry_date, CURDATE()) AS days_until_expiry
    FROM stock_batches sb
    LEFT JOIN suppliers s ON sb.supplier_id = s.id
    WHERE sb.medicine_id = ?
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
$stmt->bind_param("i", $medicine_id);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
$summary = [
    'total_batches' => 0,
    'valid_batches' => 0,
    'near_expiry' => 0,
    'expired_batches' => 0,
    'total_quantity' => 0
];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;

        // Update summary
        $summary['total_batches']++;
        $summary['total_quantity'] += $row['quantity'];

        if ($row['is_expired'] == 1 || $row['days_until_expiry'] < 0) {
            $summary['expired_batches']++;
        } elseif ($row['days_until_expiry'] <= 30) {
            $summary['near_expiry']++;
        } else {
            $summary['valid_batches']++;
        }
    }
}

echo json_encode([
    'success' => true,
    'batches' => $batches,
    'summary' => $summary
]);
