<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only admin can run sync
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once "functions/auto_payment_generator.php";

$total_created = syncAllAutoPayments($conn);

echo "<h1>Payment Sync Completed</h1>";
echo "<p>Successfully created $total_created auto-generated payments for existing sales and returns.</p>";
echo "<a href='payments.php'>View Payments</a>";
