<?php
require 'functions.php';
$user = signed_in();
// POST forms for creating/editing, cancelling or deleting bookings.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    if (in_array($_POST['action'] ?? '', ['cancel', 'delete'], true)) {
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
    } else {
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
            $duration_value = $_POST['duration'] ?? '1';
            $court_id = (int) ($_POST['court_id'] ?? 0);
            if (
                !valid_date($date) ||
                !ctype_digit((string) $hour_value) ||
                (int) $hour_value < 9 ||
                (int) $hour_value > 23 ||
                !ctype_digit((string) $duration_value) ||
                (int) $duration_value < 1 ||
                (int) $hour_value + (int) $duration_value > 24
            ) {
                throw new Exception('Choose a valid start time and duration that ends by midnight.');
            }
            $hour = (int) $hour_value;
            $duration = (int) $duration_value;
            $start = sprintf('%02d:00:00', $hour);
            $end = sprintf('%02d:00:00', ($hour + $duration) % 24);
            $changed =
                !$booking ||
                (int) $booking['court_id'] !== $court_id ||
                $booking['booking_date'] !== $date ||
                $booking['start_time'] !== $start ||
                $booking['end_time'] !== $end;
            if ($booking && $changed && $user['role'] !== 'admin') {
                throw new Exception('Only Admin can change a booking schedule.');
            }
            if ($booking && $changed && $booking['status'] !== 'confirmed') {
                throw new Exception('Only confirmed bookings can be rescheduled.');
            }
            if ($changed && !available($court_id, $date, $hour, $duration, $id)) {
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
            // Existing unchanged bookings keep their recorded price.
            // A new or resized booking costs the court's hourly rate times its hours.
            $total =
                $booking && !$changed
                    ? (float) $booking['total_amount']
                    : (float) $court['hourly_rate'] * $duration;
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
                    'UPDATE bookings SET court_id=?,booking_date=?,start_time=?,end_time=?,total_amount=?,payment_status=?,amount_paid=?,payment_method=?,payment_reference=?,paid_at=?,payment_notes=?,payment_updated_by=?,notes=? WHERE id=?',
                    'isssdsdssssisi',
                    [
                        $court_id,
                        $date,
                        $start,
                        $end,
                        $total,
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
    }
}

$title = 'Bookings & schedule';
$page = 'bookings.php';
$date = $_GET['date'] ?? '';
$court_id = (int) ($_GET['court_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$bookings = rows(
    'SELECT b.*,c.name AS court_name,cl.full_name AS client_name FROM bookings b JOIN courts c ON c.id=b.court_id JOIN clients cl ON cl.id=b.client_id ORDER BY b.booking_date DESC,b.start_time',
);
$courts = rows('SELECT id,name FROM courts ORDER BY id');
require_once 'layout.php';
page_header($title, $page, $user);
?>
<div class="heading"><div><h1>Bookings & schedule</h1><p class="muted">Reservations and payment details.</p></div><button data-open="booking-dialog">+ New booking</button></div>
<form method="get" class="toolbar">
<?php field('Client name', 'search', $search, 'search', false); ?>
<div class="field"><label for="filter-date">Date</label><input id="filter-date" name="date" type="date" value="<?= e(
    $date,
) ?>"></div>
<?php  ?>
<div class="field"><label for="filter-court">Court</label><select name="court_id" id="filter-court"><option value="0">All courts</option><?php foreach (
    $courts
    as $court
): ?><option value="<?= (int) $court['id'] ?>" <?= $court_id === (int) $court['id']
    ? 'selected'
    : '' ?>><?= e($court['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="filter-status">Booking status</label><select name="status" id="filter-status"><option value="">All statuses</option><?php foreach (
    ['confirmed', 'completed', 'cancelled']
    as $s
): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(
    ucfirst($s),
) ?></option><?php endforeach; ?></select></div>
<button>Filter</button><a class="button light" href="bookings.php">Clear</a>
</form>
<section class="card"><div class="table-wrap"><table><thead><tr><th>ID</th><th>Date / Time</th><th>Court</th><th>Client</th><th>Booking</th><th>Payment details</th><th>Actions</th></tr></thead><tbody>
<?php
$shown = 0;
foreach ($bookings as $b):

    if (
        ($date && $date !== $b['booking_date']) ||
        ($court_id && $court_id !== (int) $b['court_id']) ||
        ($search && stripos($b['client_name'], $search) === false) ||
        ($status && $status !== $b['status'])
    ) {
        continue;
    }
    $shown++;
    ?><tr><td><?= (int) $b['id'] ?></td><td><?= e($b['booking_date']) ?><br><?= e(
    slot_label($b['start_time'], $b['end_time']),
) ?></td><td><?= e($b['court_name']) ?></td><td><?= e($b['client_name']) ?></td><td><?= e(
    ucfirst($b['status']),
) ?></td><td><?= e(ucfirst($b['payment_status'])) ?> · <?= e(money($b['amount_paid'])) ?><br><?= e(
    $b['payment_method'],
) ?><br><?= e($b['payment_reference']) ?><br><?= e($b['paid_at']) ?><br><?= e(
    $b['payment_notes'],
) ?></td><td><div class="row-actions">
<a class="button light" href="bookings.php?edit=<?= (int) $b['id'] ?>"><?= $user['role'] === 'admin'
    ? 'Master override'
    : 'Payment details' ?></a>
<?php if (
    $b['status'] === 'confirmed'
): ?><form method="post" action="bookings.php" data-confirm="Cancel this booking and release the slot? Payment is not automatically refunded."><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $b[
    'id'
] ?>"><input type="hidden" name="action" value="cancel"><button class="light">Cancel</button></form><?php endif; ?>
<?php if ($user['role'] === 'admin'):
    delete_button(
        'bookings.php',
        $b['id'],
        'Delete this booking from the active schedule? It will be cancelled and its payment history kept.',
    );
endif; ?>
</div></td></tr><?php
endforeach;
?>
<?php if (!$shown): ?><tr><td colspan="7">No bookings found.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php
booking_modal($page, $user);
page_footer();
 ?>
