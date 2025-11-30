<?php
// Safe student logout — no HTML or stray characters before <?php
session_start();

// Unset all session variables
$_SESSION = [];

// If session cookie exists, remove it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session and redirect to index
session_destroy();
header('Location: index.php');
exit;
?>