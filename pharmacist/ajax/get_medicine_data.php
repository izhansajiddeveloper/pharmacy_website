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

// Get medicine ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid medicine ID']);
    exit;
}

// Fetch medicine details
$medicine_query = mysqli_query(
    $conn,
    "SELECT m.*, c.name AS category_name, t.name AS type_name, g.name AS generic_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     LEFT JOIN medicine_types t ON m.type_id = t.id
     LEFT JOIN medicine_generics g ON m.generic_id = g.id
     WHERE m.id = $id"
);

if ($medicine = mysqli_fetch_assoc($medicine_query)) {
    // Fetch categories, types, generics for dropdowns
    $categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
    $types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
    $generics = mysqli_query($conn, "SELECT * FROM medicine_generics ORDER BY name");

    $categories_data = [];
    while ($cat = mysqli_fetch_assoc($categories)) {
        $categories_data[] = $cat;
    }

    $types_data = [];
    while ($type = mysqli_fetch_assoc($types)) {
        $types_data[] = $type;
    }

    $generics_data = [];
    while ($generic = mysqli_fetch_assoc($generics)) {
        $generics_data[] = $generic;
    }

    echo json_encode([
        'success' => true,
        'medicine' => $medicine,
        'categories' => $categories_data,
        'types' => $types_data,
        'generics' => $generics_data
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Medicine not found']);
}
exit;
