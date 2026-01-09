<?php
// get_expense.php - Simple version
require_once "../../config/db.php";

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Expense ID required']);
    exit;
}

$id = intval($_GET['id']); // Use intval for safety

$query = "SELECT e.*, u.name as created_by_name 
          FROM expenses e 
          LEFT JOIN users u ON e.created_by = u.id 
          WHERE e.id = $id";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

if (mysqli_num_rows($result) > 0) {
    $expense = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'data' => $expense]);
} else {
    echo json_encode(['success' => false, 'message' => 'Expense not found']);
}
