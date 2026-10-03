<?php
require 'functions.php';
$user = signed_in();
check_post();
$id = (int) ($_POST['id'] ?? 0);
try {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        admin_only($user);
    }
    if (!in_array($action, ['cancel', 'delete'], true)) {
        throw new Exception('Unknown booking action.');
    }
    begin_write();
    if (!one('SELECT id FROM bookings WHERE id=?', 'i', [$id])) {
        throw new Exception('Booking not found.');
    }
    query("UPDATE bookings SET status='cancelled' WHERE id=?", 'i', [$id]);
    end_write(true);
    notice(
        'Booking cancelled. Its slot is free and payment history is kept. No automatic refund was made.',
    );
} catch (Exception $error) {
    end_write(false);
    notice(error_message($error));
}
go('bookings.php');
