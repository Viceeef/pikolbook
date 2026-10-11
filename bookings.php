<?php
require 'functions.php';
$user = signed_in();
// All writes use the same lock and transaction, including every date in a monthly block.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? 'save';
    $return_page = ($_POST['return_page'] ?? '') === 'dashboard.php' ? 'dashboard.php' : 'bookings.php';
    try {
        begin_write();
        $booking = $id ? booking_record($id) : null;
        if ($id && !$booking) throw new Exception('Booking not found.');
        if (in_array($action, ['cancel', 'complete', 'refund'], true)) {
            if (!$booking) throw new Exception('Choose a booking.');
            if ($action === 'complete') {
                if ($booking['status'] !== 'confirmed') throw new Exception('Only confirmed bookings can be completed.');
                if (booking_end($booking) > date('Y-m-d H:i:s')) throw new Exception('Complete a booking only after its reserved time has ended.');
                query("UPDATE bookings SET status='completed' WHERE id=?", 'i', [$id]);
                $message = 'Booking marked as completed.';
            } elseif ($action === 'cancel') {
                if ($booking['status'] !== 'confirmed') throw new Exception('Only confirmed bookings can be cancelled.');
                $scope = $_POST['scope'] ?? 'one';
                if (!in_array($scope, ['one', 'series'], true) || ($scope === 'series' && empty($booking['series_code']))) {
                    throw new Exception('Choose a valid cancellation scope.');
                }
                $reason = text_input('cancel_reason', 500);
                $targets = cancellation_targets($booking, $scope);
                if (!$targets) throw new Exception('There are no future confirmed dates left in this monthly block.');
                // Recalculate at submission, and ask for review if a refund cutoff has passed.
                $cancellation_time = time();
                $due = 0;
                foreach ($targets as $target) $due += refund_quote($target, $cancellation_time)['amount'];
                if (!isset($_POST['quoted_refund']) || abs((float) $_POST['quoted_refund'] - $due) > 0.001 ||
                    (int) ($_POST['quoted_count'] ?? 0) !== count($targets)) {
                    throw new Exception('The cancellation amount or dates changed. Review the updated preview and confirm again.');
                }
                foreach ($targets as $target) {
                    $quote = refund_quote($target, $cancellation_time);
                    query("UPDATE bookings SET status='cancelled' WHERE id=?", 'i', [$target['id']]);
                    query('INSERT INTO booking_details(booking_id,cancelled_at,cancel_reason,refund_due,handled_by)
                        VALUES(?,NOW(),?,?,?) ON DUPLICATE KEY UPDATE cancelled_at=NOW(),cancel_reason=VALUES(cancel_reason),
                        refund_due=VALUES(refund_due),handled_by=VALUES(handled_by)', 'isdi',
                        [$target['id'], $reason, $quote['amount'], $user['id']]);
                }
                $message = count($targets) . ' booking(s) cancelled. Slots released. Refund due: ' . money($due) . '. Record each refund after it is paid.';
            } else {
                if ($booking['status'] !== 'cancelled' || empty($booking['cancelled_at']) ||
                    (float) $booking['refund_due'] <= 0 || (float) $booking['refunded_amount'] > 0) {
                    throw new Exception('This booking has no pending refund.');
                }
                if (($_POST['refund_verified'] ?? '') !== '1') throw new Exception('Confirm that the refund was actually sent.');
                $reference = text_input('refund_reference', 100);
                if (one('SELECT booking_id FROM booking_details WHERE refund_reference=? AND refunded_amount>0', 's', [$reference])) {
                    throw new Exception('This refund reference has already been recorded.');
                }
                query('UPDATE booking_details SET refunded_amount=refund_due,refunded_at=NOW(),refund_reference=?,handled_by=? WHERE booking_id=?',
                    'sii', [$reference, $user['id'], $id]);
                $message = 'Refund of ' . money($booking['refund_due']) . ' recorded. Reports now deduct this amount.';
            }
            end_write(true);
            $_SESSION['feedback'] = $message;
            unset($_SESSION['form_draft']);
            go('bookings.php');
        }
        if ($action !== 'save') throw new Exception('Unknown booking action.');
        if ($booking && ($booking['status'] === 'cancelled' || $booking['payment_status'] === 'refunded')) {
            throw new Exception('Cancelled or refunded bookings cannot be edited. Their history is kept.');
        }
        $date = $_POST['date'] ?? '';
        $hour_text = $_POST['hour'] ?? '';
        $duration_text = $_POST['duration'] ?? '';
        $court_id = (int) ($_POST['court_id'] ?? 0);
        if (!valid_date($date) || !ctype_digit((string) $hour_text) || !ctype_digit((string) $duration_text) ||
            (int) $hour_text < 9 || (int) $hour_text > 23 || (int) $duration_text < 1 || (int) $hour_text + (int) $duration_text > 24) {
            throw new Exception('Choose a valid date, start time and duration ending by midnight.');
        }
        $hour = (int) $hour_text;
        $duration = (int) $duration_text;
        $start = sprintf('%02d:00:00', $hour);
        $end = sprintf('%02d:00:00', ($hour + $duration) % 24);
        $repeat = $_POST['repeat'] ?? 'once';
        if ($booking && $repeat !== 'once') throw new Exception('Create a new monthly block using New booking.');
        $dates = repeat_dates($date, $repeat);
        $changed = !$booking || (int) $booking['court_id'] !== $court_id || $booking['booking_date'] !== $date ||
            $booking['start_time'] !== $start || $booking['end_time'] !== $end;
        if ($booking && $changed) {
            if ($user['role'] !== 'admin') throw new Exception('Only Admin can change a booking schedule.');
            if ($booking['status'] !== 'confirmed') throw new Exception('Only confirmed bookings can be rescheduled.');
            if ($booking['booking_date'] . ' ' . $booking['start_time'] <= date('Y-m-d H:i:s')) {
                throw new Exception('A booking that has already started cannot be rescheduled.');
            }
            // Keep a monthly block inside its paid calendar month.
            if (!empty($booking['series_code']) && substr($date,0,7) !== substr($booking['booking_date'],0,7)) {
                throw new Exception('A monthly-block date must stay in its original month.');
            }
        }
        if ($changed) {
            $conflicts = [];
            foreach ($dates as $slot_date) {
                if (!available($court_id, $slot_date, $hour, $duration, $id)) $conflicts[] = $slot_date;
            }
            if ($conflicts) throw new Exception('Cannot book ' . implode(', ', $conflicts) .
                '. The time is occupied, under maintenance, outside opening hours or in the past. Nothing was saved. Choose another date, court or time.');
        }
        $court = one('SELECT * FROM courts WHERE id=?', 'i', [$court_id]);
        if (!$court) throw new Exception('Choose an existing court.');
        $total = $booking && !$changed ? (float) $booking['total_amount'] : round((float) $court['hourly_rate'] * $duration, 2);
        $grand_total = $total * count($dates);
        $payment = $_POST['payment_status'] ?? 'paid';
        if (!in_array($payment, ['paid', 'unpaid'], true)) throw new Exception('Refunds must be recorded through the cancellation form.');
        $amount_text = text_input('amount_paid', 20);
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount_text)) throw new Exception('Enter a valid amount with up to two decimal places.');
        $amount = (float) $amount_text;
        if (!$booking && $payment !== 'paid') throw new Exception('New bookings require full payment.');
        if ($changed && $payment !== 'paid') throw new Exception('Rescheduling requires full payment.');
        if ($payment === 'paid' && (abs($amount - $grand_total) > 0.001 || ($_POST['verified'] ?? '') !== '1')) {
            throw new Exception('Verify full payment of ' . money($grand_total) . ' for ' . count($dates) . ' booking(s).');
        }
        if ($payment === 'unpaid' && $amount != 0) throw new Exception('Unpaid bookings must have an amount received of 0.');
        $method = $reference = $paid_at = null;
        if ($payment === 'paid') {
            $method = text_input('payment_method', 50);
            $reference = text_input('payment_reference', 100);
            if (!in_array($method, ['GCash QRPH', 'Bank QRPH'], true)) throw new Exception('Choose a valid payment method.');
            $duplicates = rows('SELECT b.id,d.series_code FROM bookings b LEFT JOIN booking_details d ON d.booking_id=b.id WHERE b.payment_reference=? AND b.id<>?', 'si', [$reference,$id]);
            foreach ($duplicates as $duplicate) {
                if (empty($booking['series_code']) || $duplicate['series_code'] !== $booking['series_code']) {
                    throw new Exception('This payment reference is already used by another booking.');
                }
            }
            $paid_value = $_POST['paid_at'] ?? '';
            $paid_date = DateTime::createFromFormat('!Y-m-d\TH:i', $paid_value);
            if (!$paid_date || $paid_date->format('Y-m-d\TH:i') !== $paid_value || $paid_date->getTimestamp() > time()) {
                throw new Exception('Choose a valid payment date and time that is not in the future.');
            }
            $paid_at = $paid_date->format('Y-m-d H:i:s');
        }
        if ($booking && !empty($booking['series_code'])) {
            if ($total != (float) $booking['total_amount'] || $payment !== $booking['payment_status'] ||
                $amount != (float) $booking['amount_paid'] || $reference !== $booking['payment_reference'] ||
                $method !== $booking['payment_method'] || substr((string) $paid_at,0,16) !== substr((string) $booking['paid_at'],0,16)) {
                throw new Exception('Monthly-block payment is shared. Keep its original price and payment details; use cancellation to record refunds.');
            }
        }
        $payment_notes = text_input('payment_notes', 1000, false);
        $notes = text_input('notes', 1000, false);
        if ($booking) {
            query('UPDATE bookings SET court_id=?,booking_date=?,start_time=?,end_time=?,total_amount=?,payment_status=?,amount_paid=?,payment_method=?,payment_reference=?,paid_at=?,payment_notes=?,payment_updated_by=?,notes=? WHERE id=?',
                'isssdsdssssisi', [$court_id,$date,$start,$end,$total,$payment,$amount,$method,$reference,$paid_at,$payment_notes,$user['id'],$notes,$id]);
        } else {
            $mode = $_POST['client_mode'] ?? '';
            if ($mode === 'new') {
                $name = text_input('full_name', 100);
                $phone = text_input('phone', 30);
                $email = email_input(true);
                query('INSERT INTO clients(full_name,phone,email) VALUES(?,?,?)', 'sss', [$name,$phone,$email]);
                $client_id = mysqli_insert_id($db);
            } elseif ($mode === 'existing') {
                $client_id = (int) ($_POST['client_id'] ?? 0);
                if (!one('SELECT id FROM clients WHERE id=? AND is_active=1','i',[$client_id])) throw new Exception('Select an active existing client.');
            } else {
                throw new Exception('Choose an existing or new client.');
            }
            $series = $repeat === 'monthly' ? bin2hex(random_bytes(6)) : null;
            foreach ($dates as $slot_date) {
                query("INSERT INTO bookings(court_id,client_id,created_by,booking_date,start_time,end_time,total_amount,payment_status,amount_paid,payment_method,payment_reference,paid_at,payment_notes,payment_updated_by,notes)
                    VALUES(?,?,?,?,?,?,?,'paid',?,?,?,?,?,?,?)", 'iiisssddssssis',
                    [$court_id,$client_id,$user['id'],$slot_date,$start,$end,$total,$total,$method,$reference,$paid_at,$payment_notes,$user['id'],$notes]);
                if ($series) query('INSERT INTO booking_details(booking_id,series_code) VALUES(?,?)', 'is', [mysqli_insert_id($db),$series]);
            }
        }
        end_write(true);
        unset($_SESSION['form_draft'], $_SESSION['form_error']);
        $_SESSION['feedback'] = $booking ? 'Booking updated.' : count($dates) . ' booking(s) saved. Total payment: ' . money($grand_total) . '.';
        go($return_page);
    } catch (Exception $error) {
        if ($action === 'save') fail_form($error, $return_page, $id);
        end_write(false);
        $_SESSION['action_error'] = error_message($error);
        $_SESSION['cancel_draft'] = $action === 'cancel' ? ($_POST['cancel_reason'] ?? '') : '';
        $_SESSION['cancel_draft_id'] = $id;
        $suffix = $action === 'cancel' ? '?cancel=' . $id . '&scope=' . (($_POST['scope'] ?? '') === 'series' ? 'series' : 'one') :
            ($action === 'refund' ? '?refund=' . $id : '');
        go('bookings.php' . $suffix);
    }
}

$title = 'Bookings & schedule';
$page = 'bookings.php';
$date = $_GET['date'] ?? '';
$court_id = (int) ($_GET['court_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$bookings = rows(
    'SELECT b.*,c.name AS court_name,cl.full_name AS client_name,d.series_code,d.cancelled_at,d.cancel_reason,d.refund_due,d.refunded_amount,d.refunded_at,d.refund_reference FROM bookings b JOIN courts c ON c.id=b.court_id JOIN clients cl ON cl.id=b.client_id LEFT JOIN booking_details d ON d.booking_id=b.id ORDER BY b.booking_date DESC,b.start_time',
);
$courts = rows('SELECT * FROM courts ORDER BY id');
$view = ($_GET['view'] ?? '') === 'calendar' ? 'calendar' : 'list';
require_once 'layout.php';
page_header($title, $page, $user);
?>
<div class="heading"><div><h1>Bookings & schedule</h1><p class="muted">Reservations and payment details.</p></div><button data-open="booking-dialog">+ New booking</button></div>
<div class="view-switch"><a class="button <?= $view === 'list' ? '' : 'light' ?>" href="bookings.php">List view</a><a class="button <?= $view === 'calendar' ? '' : 'light' ?>" href="bookings.php?view=calendar">Calendar view</a></div>
<?php if ($view === 'calendar') { booking_calendar($courts, $bookings); } else { ?>
<form method="get" class="toolbar" id="booking-filters">
<div class="field"><label for="search">Client name</label><input id="search" name="search" type="search" value="<?= e(
    $search,
) ?>"></div>
<div class="field"><label for="filter-date">Date</label><input id="filter-date" name="date" type="date" value="<?= e(
    $date,
) ?>"></div>
<button type="button" class="light filter-clear" data-filter-clear="filter-date"><?= action_icon(
    'clear',
) ?>Clear date</button>
<div class="field"><label for="filter-court">Court</label><select name="court_id" id="filter-court"><option value="0">All courts</option><?php foreach (
    $courts
    as $court
): ?><option value="<?= (int) $court['id'] ?>" <?= $court_id === (int) $court['id']
    ? 'selected'
    : '' ?>><?= e($court['name']) ?></option><?php endforeach; ?></select></div>
    <button type="button" class="light filter-clear" data-filter-clear="filter-court"><?= action_icon(
    'clear',
) ?>Clear court</button>
    <div class="field"><label for="filter-status">Booking status</label><select name="status" id="filter-status"><option value="">All statuses</option><?php foreach (
    ['confirmed', 'completed', 'cancelled']
    as $s
): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(
    ucfirst($s),
) ?></option><?php endforeach; ?></select></div>
<button type="button" class="light filter-clear" data-filter-clear="filter-status"><?= action_icon(
    'clear',
) ?>Clear status</button>
<button type="submit"><?= action_icon('filter') ?>Filter</button><a class="button light" href="bookings.php"><?= action_icon(
    'clear',
) ?>Clear all</a>
</form>
<section class="card"><div class="table-wrap"><table id="booking-records"><colgroup><col class="booking-id-column"><col class="booking-time-column"><col class="booking-court-column"><col class="booking-client-column"><col class="booking-status-column"><col class="booking-payment-column"><col class="booking-actions-column"></colgroup><thead><tr><th>ID</th><th>Date / Time</th><th>Court</th><th>Client</th><th>Booking</th><th>Payment details</th><th>Actions</th></tr></thead><tbody>
<?php
foreach ($bookings as $b):
    ?><tr data-date="<?= e($b['booking_date']) ?>" data-court="<?= (int) $b['court_id'] ?>" data-status="<?= e(
    $b['status'],
) ?>"><td><?= (int) $b['id'] ?><?php if ($b['series_code']): ?><br><span class="monthly-tag" title="Monthly block <?= e($b['series_code']) ?>">Monthly</span><?php endif; ?></td><td><?= e($b['booking_date']) ?><br><?= e(
    slot_label($b['start_time'], $b['end_time']),
) ?></td><td><?= e($b['court_name']) ?></td><td><?= e($b['client_name']) ?></td><td><?= e(
    ucfirst($b['status']),
) ?></td><td><?= e(payment_label($b)) ?><br>Received: <?= e(money($b['amount_paid'])) ?><br><?= e(
    $b['payment_method'],
) ?><br><?= e($b['payment_reference']) ?><br><?= e($b['paid_at']) ?><br><?= e(
    $b['payment_notes'],
) ?><?php if ($b['status'] === 'cancelled'): ?><br><strong>Refund pending: <?= e(money(max(0, (float) $b['refund_due'] - (float) $b['refunded_amount']))) ?></strong><br>Refund paid: <?= e(money($b['refunded_amount'] ?? 0)) ?><br>Net kept: <?= e(money(net_received($b))) ?><br><?= e($b['cancel_reason'] ?? 'Earlier cancellation') ?><?php if ($b['refunded_at']): ?><br><?= e($b['refund_reference']) ?> · <?= e($b['refunded_at']) ?><?php endif; ?><?php endif; ?></td><td><div class="row-actions">
<?php if ($b['status'] !== 'cancelled' && $b['payment_status'] !== 'refunded'): ?>
<a class="button light" href="bookings.php?edit=<?= (int) $b['id'] ?>"><?= $user['role'] === 'admin'
    ? action_icon('edit') . ' Edit'
    : 'Payment details' ?></a>
<?php endif; ?>
<?php if (
    $b['status'] === 'confirmed'
): ?>
<?php if (booking_end($b) <= date('Y-m-d H:i:s')): ?><form method="post" action="bookings.php" data-confirm="Mark this booking as completed?"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $b[
    'id'
] ?>"><input type="hidden" name="action" value="complete"><button class="light"><?= action_icon(
    'complete',
) ?>Complete</button></form><?php endif; ?>
<a class="button light cancel-button" href="bookings.php?cancel=<?= (int) $b['id'] ?>"><?= action_icon('cancel') ?>Cancel</a><?php endif; ?>
<?php if ($b['status'] === 'cancelled' && (float) $b['refund_due'] > (float) $b['refunded_amount']): ?>
<a class="button light" href="bookings.php?refund=<?= (int) $b['id'] ?>">Record refund</a><?php endif; ?>
</div></td></tr><?php
endforeach;
?>
<tr class="filter-empty error" hidden><td colspan="7">No bookings match the selected filters.</td></tr>
</tbody></table></div></section>
<?php
}
cancellation_modal();
booking_modal($page, $user);
page_footer();
 ?>
