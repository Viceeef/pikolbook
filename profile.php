<?php
require 'functions.php';
$user = signed_in();
// POST: validate and save this page's form. GET: show the page below.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    try {
        $name = text_input('full_name', 100);
        $email = email_input(true);
        $phone = text_input('phone', 30);
        $password = $_POST['new_password'] ?? '';
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new Exception('A new password must contain 8–72 characters.');
        }
        begin_write();
        $current = one('SELECT * FROM users WHERE id = ?', 'i', [$user['id']]);
        if (!password_verify($_POST['current_password'] ?? '', $current['password_hash'])) {
            throw new Exception('Your current password is incorrect.');
        }
        query('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?', 'sssi', [
            $name,
            $email,
            $phone,
            $user['id'],
        ]);
        if ($password !== '') {
            query('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [
                password_hash($password, PASSWORD_DEFAULT),
                $user['id'],
            ]);
        }
        end_write(true);
        session_regenerate_id(true);
        notice('Your profile has been updated.');
    } catch (Exception $error) {
        end_write(false);
        $_SESSION['error_message'] = error_message($error);
    }
    go('profile.php');
}

$title = 'Edit profile';
$page = 'profile.php';
require_once 'layout.php';
page_header($title, $page, $user);
?>
<h1>Edit profile</h1><p class="muted">Update your own account.</p>
<section class="card"><form method="post" action="profile.php"><?php csrf_field(); ?>
<div class="form-grid">
<?php
field('Full name', 'full_name', $user['full_name']);
field('Email', 'email', $user['email'], 'email', true, 150);
field('Phone', 'phone', $user['phone'], 'tel', true, 30);
field('Current password', 'current_password', '', 'password', true, 72);
field('New password (8–72 characters)', 'new_password', '', 'password', true, 72);
?>
</div><button>Save profile</button></form></section>
<?php page_footer(); ?>
