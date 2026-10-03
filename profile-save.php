<?php
require 'functions.php';
$user = signed_in();
check_post();
try {
    $name = text_input('full_name', 100);
    $email = email_input(true);
    $phone = text_input('phone', 30, false);
    $password = $_POST['new_password'] ?? '';
    if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
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
    notice(error_message($error));
}
go('profile.php');
