<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * Redirect to login page if not
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

/**
 * Optional: Check role
 * Usage: pass allowed role(s) as array
 * Example: checkRole(['admin']); OR checkRole(['pharmacist']);
 */
function checkRole($allowedRoles = [])
{
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Redirect to dashboard if role not allowed
        header("Location: /" . $_SESSION['role'] . "/dashboard.php");
        exit();
    }
}
