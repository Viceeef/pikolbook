<?php
require 'functions.php';
$user = signed_in();
check_post();
$id = (int) ($_POST['id'] ?? 0);
$return_page = ($_POST['return_page'] ?? '') === 'dashboard.php' ? 'dashboard.php' : 'bookings.php';
try {
    begin_write();
    $booking = $id ? one('SELECT * FROM bookings WHERE id=?', 'i', [$id]) : null;
    if ($id && !$booking) {
        throw new Exception('Booking not found.');
    }
    $date = $_POST['date'] ?? '';
    $hour_value = $_POST['hour'] ?? '';
    $court_id = (int) ($_POST['court_id'] ?? 0);
    if (
        !valid_date($date) ||
        !ctype_digit((string) $hour_value) ||
        (int) $hour_value < 9 ||
        (int) $hour_value > 23
    ) {
        throw new Exception('Choose a valid date and one-hour slot.');
    }
    $hour = (int) $hour_value;
    $start = sprintf('%02d:00:00', $hour);
    $end = sprintf('%02d:00:00', ($hour + 1) % 24);
    $changed =
        !$booking ||
        (int) $booking['court_id'] !== $court_id ||
        $booking['booking_date'] !== $date ||
        $booking['start_time'] !== $start;
    if ($booking && $changed && $user['role'] !== 'admin') {
        throw new Exception('Only Admin can change a booking schedule.');
    }
    if ($booking && $changed && $booking['status'] !== 'confirmed') {
        throw new Exception('Only confirmed bookings can be rescheduled.');
    }
    if ($changed && !available($court_id, $date, $hour, $id)) {
        throw new Exception(
            'This slot is booked, under maintenance, archived or in the past. Choose another slot.',
        );
    }
    $court = one('SELECT * FROM courts WHERE id=?', 'i', [$court_id]);
    if (!$court) {
        throw new Exception('Court not found.');
    }
    $payment = $_POST['payment_status'] ?? '';
    if (!in_array($payment, ['paid', 'unpaid', 'refunded'], true)) {
        throw new Exception('Choose a valid payment status.');
    }
    $amount_text = text_input('amount_paid', 20);
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount_text)) {
        throw new Exception('Enter a valid amount, with up to two decimal places.');
    }
    $amount = (float) $amount_text;
    $total = $booking ? (float) $booking['total_amount'] : (float) $court['hourly_rate'];
    if ($changed && $booking && (float) $court['hourly_rate'] !== $total) {
        throw new Exception('Choose a court with the same hourly rate.');
    }
    if (
        !$booking &&
        ($payment !== 'paid' || $amount !== $total || ($_POST['verified'] ?? '') !== '1')
    ) {
        throw new Exception(
            'New reservations require verified full payment of ' . money($total) . '.',
        );
    }
    if ($payment === 'paid' && ($amount !== $total || ($_POST['verified'] ?? '') !== '1')) {
        throw new Exception('Paid status requires full payment and manual verification.');
    }
    $method = text_input('payment_method', 50);
    if (!in_array($method, ['GCash QRPH', 'Bank QRPH'], true)) {
        throw new Exception('Choose GCash QRPH or Bank QRPH.');
    }
    $reference = text_input('payment_reference', 100);
    if (
        one('SELECT id FROM bookings WHERE payment_reference=? AND id<>? LIMIT 1', 'si', [
            $reference,
            $id,
        ])
    ) {
        throw new Exception('This payment reference is already recorded for another booking.');
    }
    $paid_value = $_POST['paid_at'] ?? '';
    $paid_date = DateTime::createFromFormat('!Y-m-d\TH:i', $paid_value);
    if (
        !$paid_date ||
        $paid_date->format('Y-m-d\TH:i') !== $paid_value ||
        $paid_date->format('Y-m-d H:i:s') > date('Y-m-d H:i:s')
    ) {
        throw new Exception('Choose a valid payment date and time that is not in the future.');
    }
    $paid_at = $paid_date->format('Y-m-d H:i:s');
    $payment_notes = text_input('payment_notes', 1000, false);
    $notes = text_input('notes', 1000, false);
    if ($booking) {
        query(
            'UPDATE bookings SET court_id=?,booking_date=?,start_time=?,end_time=?,payment_status=?,amount_paid=?,payment_method=?,payment_reference=?,paid_at=?,payment_notes=?,payment_updated_by=?,notes=? WHERE id=?',
            'issssdssssisi',
            [
                $court_id,
                $date,
                $start,
                $end,
                $payment,
                $amount,
                $method,
                $reference,
                $paid_at,
                $payment_notes,
                $user['id'],
                $notes,
                $id,
            ],
        );
    } else {
        $mode = $_POST['client_mode'] ?? '';
        if ($mode === 'new') {
            $name = text_input('full_name', 100);
            $phone = text_input('phone', 30);
            $email = email_input();
            query('INSERT INTO clients(full_name,phone,email) VALUES(?,?,?)', 'sss', [
                $name,
                $phone,
                $email,
            ]);
            $client_id = mysqli_insert_id($db);
        } elseif ($mode === 'existing') {
            $client_id = (int) ($_POST['client_id'] ?? 0);
            if (!one('SELECT id FROM clients WHERE id=? AND is_active=1', 'i', [$client_id])) {
                throw new Exception('Select an active existing client.');
            }
        } else {
            throw new Exception('Choose an existing or new client.');
        }
        query(
            "INSERT INTO bookings(court_id,client_id,created_by,booking_date,start_time,end_time,total_amount,payment_status,amount_paid,payment_method,payment_reference,paid_at,payment_notes,payment_updated_by,notes) VALUES(?,?,?,?,?,?,?,'paid',?,?,?,?,?,?,?)",
            'iiisssddssssis',
            [
                $court_id,
                $client_id,
                $user['id'],
                $date,
                $start,
                $end,
                $total,
                $amount,
                $method,
                $reference,
                $paid_at,
                $payment_notes,
                $user['id'],
                $notes,
            ],
        );
    }
    end_write(true);
    unset($_SESSION['form_draft']);
    notice('Booking saved to the database.');
    go($return_page);
} catch (Exception $error) {
    fail_form($error, $return_page, $id);
}
