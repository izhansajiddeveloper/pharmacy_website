<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: ../index.php");
    exit;
}

header('Content-Type: application/json');

if (isset($_GET['medicine_id'])) {
    $medicine_id = intval($_GET['medicine_id']);
    
    $query = "
        SELECT sb.*, 
               m.name as medicine_name
        FROM stock_batches sb
        JOIN medicines m ON sb.medicine_id = m.id
        WHERE sb.medicine_id = $medicine_id
        AND sb.quantity > 0
        AND sb.expiry_date > CURDATE()
        AND sb.is_expired = 0
        ORDER BY sb.expiry_date ASC
    ";
    
    $result = mysqli_query($conn, $query);
    $batches = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $batches[] = [
            'id' => $row['id'],
            'batch_no' => $row['batch_no'],
            'quantity' => $row['quantity'],
            'selling_price' => $row['selling_price'],
            'expiry_date' => $row['expiry_date'],
            'units_per_packet' => $row['units_per_packet'],
            'packets_per_box' => $row['packets_per_box']
        ];
    }
    
    echo json_encode($batches);
} else {
    echo json_encode([]);
}
?>