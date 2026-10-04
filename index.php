<?php
require 'functions.php';
// Log out is a POST action in the sidebar.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    check_post();
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    go('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    signed_in();
    go('dashboard.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    $account = one('SELECT * FROM users WHERE email = ? AND is_active = 1', 's', [
        trim($_POST['email'] ?? ''),
    ]);
    if ($account && password_verify($_POST['password'] ?? '', $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        go('dashboard.php');
    }
    $error = 'Email or password is incorrect, or this account is inactive.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login | Pikolbook</title><link rel="stylesheet" href="style.css"></head>
<body>
<main class="login-layout">
    <section class="login-brand"><img src="img/login_logo.png" alt="Pikolbook"><h1>Book a court.<br>Enjoy the game.</h1><p>Your bookings, clients and daily schedule in one place.</p></section>
    <section class="login-panel"><div class="login-box">
        <h1>Welcome back.</h1><p class="muted">Sign in with your assigned account.</p>
        <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if (!empty($_SESSION['message'])): ?><p class="notice"><?= e(
    $_SESSION['message'],
) ?></p><?php unset($_SESSION['message']);endif; ?>
        <form method="post">
            <?php csrf_field(); ?>
            <?php field('Email address', 'email', $_POST['email'] ?? '', 'email', true, 150); ?>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" maxlength="72" required autocomplete="current-password">
            </div>
            <button class="wide">Log in</button>
        </form>
    </div></section>
</main>
</body>
</html>
