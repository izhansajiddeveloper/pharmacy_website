<?php
require_once "../../config/db.php";
require_once "../../includes/auth_check.php";

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit;
}

$saleId = intval($_GET['id']);

// Fetch sale details
$saleQuery = mysqli_query(
    $conn,
    "SELECT s.*, u.name AS pharmacist_name
     FROM sales s
     LEFT JOIN users u ON s.pharmacist_id = u.id
     WHERE s.id = $saleId"
);

if (mysqli_num_rows($saleQuery) === 0) {
    echo json_encode(['success' => false, 'message' => 'Sale not found']);
    exit;
}

$sale = mysqli_fetch_assoc($saleQuery);

// Fetch sale items with medicine details
$itemsQuery = mysqli_query(
    $conn,
    "SELECT si.*, m.name AS medicine_name, m.generic_name, sb.batch_no
     FROM sale_items si
     LEFT JOIN medicines m ON si.medicine_id = m.id
     LEFT JOIN stock_batches sb ON si.batch_id = sb.id
     WHERE si.sale_id = $saleId
     ORDER BY si.id"
);

$items = [];
while ($item = mysqli_fetch_assoc($itemsQuery)) {
    $items[] = $item;
}

// Fetch pharmacist details
$pharmacistQuery = mysqli_query(
    $conn,
    "SELECT name, username, created_at 
     FROM users 
     WHERE id = {$sale['pharmacist_id']}"
);
$pharmacist = mysqli_fetch_assoc($pharmacistQuery);

echo json_encode([
    'success' => true,
    'sale' => $sale,
    'items' => $items,
    'pharmacist' => $pharmacist
]);
