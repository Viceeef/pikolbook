<?php
require 'functions.php';
$user = signed_in();
admin_only($user);
check_post();
$id = (int) ($_POST['id'] ?? 0);
try {
    begin_write();
    if ($id && !one("SELECT id FROM users WHERE id=? AND role='staff'", 'i', [$id])) {
        throw new Exception('Staff account not found.');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        if (!$id) {
            throw new Exception('Choose a staff account.');
        }
        if (
            one(
                'SELECT id FROM bookings WHERE created_by=? OR payment_updated_by=? LIMIT 1',
                'ii',
                [$id, $id],
            )
        ) {
            query('UPDATE users SET is_active=0 WHERE id=?', 'i', [$id]);
        } else {
            query('DELETE FROM users WHERE id=?', 'i', [$id]);
        }
    } else {
        $name = text_input('full_name', 100);
        $email = email_input(true);
        $phone = text_input('phone', 30, false);
        $password = $_POST['password'] ?? '';
        if ((!$id || $password !== '') && (strlen($password) < 8 || strlen($password) > 72)) {
            throw new Exception('Password must contain 8–72 characters.');
        }
        $active = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;
        if ($id) {
            query('UPDATE users SET full_name=?,email=?,phone=?,is_active=? WHERE id=?', 'sssii', [
                $name,
                $email,
                $phone,
                $active,
                $id,
            ]);
            if ($password !== '') {
                query('UPDATE users SET password_hash=? WHERE id=?', 'si', [
                    password_hash($password, PASSWORD_DEFAULT),
                    $id,
                ]);
            }
        } else {
            query(
                "INSERT INTO users(full_name,email,phone,password_hash,role,is_active) VALUES(?,?,?,?,'staff',?)",
                'ssssi',
                [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $active],
            );
        }
    }
    end_write(true);
    notice('Staff account updated.');
    go('staff.php');
} catch (Exception $error) {
    fail_form($error, 'staff.php', $id);
}
