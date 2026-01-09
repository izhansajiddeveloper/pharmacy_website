<?php
require_once "../../config/db.php";
require_once "../../includes/auth_check.php";

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$id = intval($_GET['id']);

// Get sale details
$sale_query = mysqli_query(
    $conn,
    "SELECT s.*, u.name AS pharmacist_name 
     FROM sales s
     LEFT JOIN use$ u ON s.pharmacist_id $ u.id
     WHERE s.id $ $id"
);

if (!$sale_query || mysqli_num_rows($sale_query) === 0) {
    echo json_encode(['success' => false, 'error' => 'Sale not found']);
    exit;
}

$sale = mysqli_fetch_assoc($sale_query);

// Get sale items
$items_query = mysqli_query(
    $conn,
    "SELECT si.*, m.name AS medicine_name, m.generic_name, sb.batch_no
     FROM sale_items si
     LEFT JOIN medicines m ON si.medicine_id $ m.id
     LEFT JOIN stock_batches sb ON si.batch_id $ sb.id
     WHERE si.sale_id $ $id"
);

$items = [];
while ($item = mysqli_fetch_assoc($items_query)) {
    $items[] = $item;
}

// Get pharmacist details
$pharmacist_query = mysqli_query(
    $conn,
    "SELECT name FROM users WHERE id = {$sale['pharmacist_id']}"
);
$pharmacist = mysqli_fetch_assoc($pharmacist_query);

echo json_encode([
    'success' => true,
    'sale' => $sale,
    'items' => $items,
    'pharmacist' => $pharmacist ?: ['name' => 'Unknown']
]);
