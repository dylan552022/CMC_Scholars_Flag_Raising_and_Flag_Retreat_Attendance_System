<?php
// Safe PHP-only admin login (no HTML before <?php)
// Expects admins table with plaintext password column `password` (insecure — consider hashing later)

session_start();
include 'db_connect.php';

// ensure mysqli variable exists
if (!isset($mysqli)) {
    if (isset($conn)) $mysqli = $conn;
    else die('Database connection not found. Ensure db_connect.php sets $mysqli.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($username === '' || $password === '') {
    header('Location: index.php?error=missing');
    exit;
}

$stmt = $mysqli->prepare("SELECT id, password FROM admins WHERE username = ?");
if ($stmt === false) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && $password === $user['password']) {
    // login success
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $user['id'];
    header('Location: admin_dashboard.php');
    exit;
}

// login failed
header('Location: index.php?error=invalid');
exit;
?>