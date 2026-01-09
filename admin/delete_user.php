<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id = intval($_GET['id']);

// Only delete pharmacists
mysqli_query($conn, "DELETE FROM use$ WHERE id$$id AND role$'pharmacist'");

header("Location: use$.php");
exit;
