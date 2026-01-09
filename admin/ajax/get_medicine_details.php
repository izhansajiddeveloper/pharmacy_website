<?php
require_once "../../config/db.php";
require_once "../../includes/auth_check.php";

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
    exit;
}

$medicineId = intval($_GET['id']);

// Fetch medicine details
$medicineQuery = mysqli_query(
    $conn,
    "SELECT m.*, c.name AS category_name, t.name AS type_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     LEFT JOIN medicine_types t ON m.type_id = t.id
     WHERE m.id = $medicineId"
);

if (mysqli_num_rows($medicineQuery) === 0) {
    echo json_encode(['success' => false, 'message' => 'Medicine not found']);
    exit;
}

$medicine = mysqli_fetch_assoc($medicineQuery);

// Fetch stock information
$stockQuery = mysqli_query(
    $conn,
    "SELECT 
        COALESCE(SUM(sb.quantity), 0) as total_stock,
        MIN(CASE WHEN sb.expiry_date >= CURDATE() THEN sb.expiry_date END) as next_expiry,
        SUM(CASE WHEN sb.quantity <= 10 THEN 1 ELSE 0 END) as low_stock_batches,
        SUM(CASE WHEN sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon
     FROM stock_batches sb
     WHERE sb.medicine_id = $medicineId AND sb.quantity > 0"
);
$stockInfo = mysqli_fetch_assoc($stockQuery);

// Fetch price information
$priceQuery = mysqli_query(
    $conn,
    "SELECT 
        MIN(purchase_price) as min_purchase,
        MAX(purchase_price) as max_purchase,
        AVG(purchase_price) as avg_purchase,
        MIN(selling_price) as min_selling,
        MAX(selling_price) as max_selling,
        AVG(selling_price) as avg_selling,
        MIN(mrp) as min_mrp,
        MAX(mrp) as max_mrp,
        AVG(mrp) as avg_mrp
     FROM stock_batches 
     WHERE medicine_id = $medicineId AND quantity > 0"
);
$priceInfo = mysqli_fetch_assoc($priceQuery);

// Fetch stock batches
$batchesQuery = mysqli_query(
    $conn,
    "SELECT sb.*, s.name as supplier_name
     FROM stock_batches sb
     LEFT JOIN suppliers s ON sb.supplier_id = s.id
     WHERE sb.medicine_id = $medicineId AND sb.quantity > 0
     ORDER BY sb.expiry_date ASC, sb.received_date DESC"
);

$batches = [];
while ($batch = mysqli_fetch_assoc($batchesQuery)) {
    $batches[] = $batch;
}

echo json_encode([
    'success' => true,
    'medicine' => $medicine,
    'stockInfo' => $stockInfo,
    'priceInfo' => $priceInfo,
    'batches' => $batches
]);
