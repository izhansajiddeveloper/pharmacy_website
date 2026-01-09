<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Set header for JSON response
header('Content-Type: application/json');

// Check permissions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$name = isset($_POST['name']) ? mysqli_real_escape_string($conn, trim($_POST['name'])) : '';
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
$generic_id = isset($_POST['generic_id']) ? intval($_POST['generic_id']) : 0;
$description = isset($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';

// Validate required fields
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Medicine name is required']);
    exit;
}

if ($category_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Category is required']);
    exit;
}

if ($type_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Type is required']);
    exit;
}

// Check if medicine exists
$check_query = "SELECT id FROM medicines WHERE id = $id";
$check_result = mysqli_query($conn, $check_query);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Medicine not found']);
    exit;
}

// Prepare generic_id value for SQL (NULL if 0)
$generic_sql = ($generic_id > 0) ? $generic_id : "NULL";

// Update medicine
$update_query = "UPDATE medicines SET 
    name = '$name',
    category_id = $category_id,
    type_id = $type_id,
    generic_id = $generic_sql,
    description = '$description',
    updated_at = NOW()
    WHERE id = $id";

if (mysqli_query($conn, $update_query)) {
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['success' => true, 'message' => 'Medicine updated successfully']);
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes were made']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
exit;
