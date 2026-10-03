<?php
require 'functions.php';
$user = signed_in();
check_post();
$id = (int) ($_POST['id'] ?? 0);
try {
    begin_write();
    if (!$id || !one('SELECT id FROM clients WHERE id=?', 'i', [$id])) {
        throw new Exception(
            'Choose an existing client. New clients can only be added with a booking.',
        );
    }
    if (($_POST['action'] ?? '') === 'delete') {
        if (one('SELECT id FROM bookings WHERE client_id=? LIMIT 1', 'i', [$id])) {
            query('UPDATE clients SET is_active=0 WHERE id=?', 'i', [$id]);
        } else {
            query('DELETE FROM clients WHERE id=?', 'i', [$id]);
        }
    } else {
        $name = text_input('full_name', 100);
        $phone = text_input('phone', 30);
        $email = email_input();
        query('UPDATE clients SET full_name=?,phone=?,email=?,is_active=? WHERE id=?', 'sssii', [
            $name,
            $phone,
            $email,
            ($_POST['is_active'] ?? '1') === '1' ? 1 : 0,
            $id,
        ]);
    }
    end_write(true);
    notice('Client updated or removed.');
    go('clients.php');
} catch (Exception $error) {
    fail_form($error, 'clients.php', $id);
}
