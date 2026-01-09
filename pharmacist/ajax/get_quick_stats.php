<?php
// ajax/get_quick_stats.php
require_once "../../config/db.php";
require_once "../../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get all quick stats
$stats = [];

// Medicines count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM medicines");
$stats['medicines_count'] = mysqli_fetch_assoc($result)['count'];

// Active stock count
$result = mysqli_query($conn, "SELECT COUNT(DISTINCT medicine_id) as count FROM stock_batches WHERE quantity > 0 AND is_expired = 0");
$stats['active_stock_count'] = mysqli_fetch_assoc($result)['count'];

// Expired stock count
$result = mysqli_query($conn, "SELECT COUNT(DISTINCT medicine_id) as count FROM stock_batches WHERE is_expired = 1");
$stats['expired_stock_count'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Returns today count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM returns_to_company WHERE DATE(returned_at) = CURDATE()");
$stats['returns_today_count'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Low stock count
$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT m.id) as count
     FROM medicines m
     JOIN stock_batches sb ON m.id = sb.medicine_id
     WHERE sb.is_expired = 0
     GROUP BY m.id
     HAVING SUM(sb.quantity) <= 50"
);
$stats['low_stock_count'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Expiring soon count
$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT m.id) as count 
     FROM medicines m
     JOIN stock_batches sb ON m.id = sb.medicine_id
     WHERE sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND sb.is_expired = 0"
);
$stats['expiring_soon'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Expired batches count (for badge)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_batches WHERE is_expired = 1");
$stats['expired_count'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Returns today (for badge)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM returns_to_company WHERE DATE(returned_at) = CURDATE()");
$stats['returns_today'] = mysqli_fetch_assoc($result)['count'] ?? 0;

header('Content-Type: application/json');
echo json_encode(['success' => true, ...$stats]);
