<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

function e($s){ return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }

if (!empty($_POST['username'])) {
    // Admin forgot-password flow (username + optional email)
    $username = e($_POST['username']);
    $email = e($_POST['email'] ?? '');

    // TODO: replace debug output with secure DB lookup + reset-token/email flow.
    $title = 'Admin Password Recovery';
    $body = "<p><strong>Username:</strong> {$username}</p>";
    if ($email !== '') $body .= "<p><strong>Email (if provided):</strong> {$email}</p>";
    $body .= "<p>Next: implement secure lookup and send a single-use reset link to the admin email.</p>";

} else {
    // Student / scholar flow (existing form)
    $student = e($_POST['student_id'] ?? '');
    $scholar = e($_POST['scholar_type'] ?? '');

    $title = 'Student Password Recovery (received)';
    $body = "<p><strong>Student ID / Fullname:</strong> {$student}</p>";
    $body .= "<p><strong>Scholar Type:</strong> {$scholar}</p>";
    $body .= "<p>Next: implement secure DB lookup / reset-token flow. Do not reveal plain passwords.</p>";
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $title; ?></title>
<link rel="icon" type="image/jpg" href="images/favicon.jpg"/>
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f4f6f8;padding:28px;color:#222}
.card{max-width:720px;margin:24px auto;padding:20px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.06)}
.button{display:inline-block;padding:10px 14px;background:#0aa;border-radius:6px;color:#fff;text-decoration:none}
</style>
</head>
<body>
<div class="card">
    <h2><?php echo $title; ?></h2>
    <?php echo $body; ?>
    <hr>
    <p>If you want this handler to perform a database lookup and send a reset link, tell me:
    the DB config filename (example: db_connect.php), whether you use mysqli or PDO, and your users table columns (username, email, id).</p>
    <p><a class="button" href="forgot_password.php">Back</a></p>
</div>
</body>
</html>
?>