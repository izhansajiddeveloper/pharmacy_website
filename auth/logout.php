<?php
session_start();

// Store role for potential custom logout message
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';

// Clear all session data
$_SESSION = array();

// If using session cookies, delete them
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600, // Set to past time to delete
        $params["path"],
        $params["domain"],
        $params["secure"],
        isset($params["httponly"])
    );
}

// Finally, destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Prevent caching of this page
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

// Set logout message in URL if needed
$logout_message = "logged_out";
if ($user_role === 'admin') {
    $logout_message = "admin_logged_out";
} elseif ($user_role === 'pharmacist') {
    $logout_message = "pharmacist_logged_out";
}

// Redirect to login page with logout message
header("Location: ../index.php?message=" . urlencode($logout_message));
exit();
