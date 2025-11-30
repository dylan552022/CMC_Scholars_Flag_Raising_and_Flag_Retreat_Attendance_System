<?php
// Safe admin logout: clears session and redirects to index.
// Make sure this file contains only PHP and is saved as UTF-8 without BOM.

session_start();

// Clear session data
$_SESSION = [];

// Remove session cookie if present
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy session
session_destroy();

// Redirect back to login page
header('Location: index.php');
exit;