<?php
// api/get_batch_details.php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if (!isset($_GET['batch_id'])) {
    echo json_encode(['success' => false, 'message' => 'Batch ID required']);
    exit;
}

$batch_id = mysqli_real_escape_string($conn, $_GET['batch_id']);

$query = "
    SELECT 
        m.*,
        sb.*,
        mc.name AS category_name,
        mt.name AS type_name,
        s.name AS supplier_name,
        s.contact_person,
        s.phone,
        s.email
    FROM stock_batches sb
    JOIN medicines m ON sb.medicine_id = m.id
    LEFT JOIN medicine_categories mc ON m.category_id = mc.id
    LEFT JOIN medicine_types mt ON m.type_id = mt.id
    LEFT JOIN suppliers s ON sb.supplier_id = s.id
    WHERE sb.id = '$batch_id'
";

$result = mysqli_query($conn, $query);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['success' => true, 'batch' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
}
