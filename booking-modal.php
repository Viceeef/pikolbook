<?php
$booking = null;
if ($page === 'bookings.php' && !empty($_GET['edit'])) {
    $booking = one(
        'SELECT b.*,cl.full_name AS client_name FROM bookings b JOIN clients cl ON cl.id=b.client_id WHERE b.id=?',
        'i',
        [(int) $_GET['edit']],
    );
}
$payment_only = $booking && $user['role'] === 'staff';
$client_list = rows('SELECT * FROM clients WHERE is_active=1 ORDER BY full_name');
$court_list = rows('SELECT * FROM courts WHERE is_active=1 OR id=? ORDER BY id', 'i', [
    $booking['court_id'] ?? 0,
]);
$date = fv('date', $booking['booking_date'] ?? date('Y-m-d'));
$hour = fv('hour', $booking ? (int) substr($booking['start_time'], 0, 2) : 9);
$paid_at = fv(
    'paid_at',
    !empty($booking['paid_at'])
        ? date('Y-m-d\TH:i', strtotime($booking['paid_at']))
        : date('Y-m-d\TH:i'),
);
?>
<dialog id="booking-dialog" <?= $booking || isset($_GET['new']) ? 'data-auto-open' : '' ?>>
<div class="heading"><h2><?= $booking
    ? ($payment_only
        ? 'Payment details'
        : 'Booking master override')
    : 'New booking' ?></h2><button class="light" type="button" data-close="booking-dialog">Close</button></div>
<form method="post" action="booking-save.php" id="booking-form">
<?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) ($booking['id'] ??
    0) ?>"><input type="hidden" name="return_page" value="<?= e($page) ?>">
<?php if ($booking): ?><p>Client: <strong><?= e($booking['client_name']) ?></strong> · <?= e(
    ucfirst($booking['status']),
) ?></p>
<?php else: ?>
<div class="field"><label for="client_mode">Client type</label><select id="client_mode" name="client_mode"><option value="existing">Existing client</option><option value="new" <?= fv(
    'client_mode',
) === 'new'
    ? 'selected'
    : '' ?>>New client</option></select></div>
<div id="existing-client-fields">
    <div class="field"><label for="client-search">Search client name</label><input id="client-search" type="search" placeholder="Type a name"></div>
    <div class="field"><label for="client_id">Select client</label><select id="client_id" name="client_id" required><option value="">Choose a client</option><?php foreach (
        $client_list
        as $client
    ): ?><option value="<?= (int) $client['id'] ?>" <?= fv('client_id') == $client['id']
    ? 'selected'
    : '' ?>><?= e($client['full_name']) ?></option><?php endforeach; ?></select></div>
</div>
<div id="new-client-fields" hidden>
    <?php
    field('Full name', 'full_name', fv('full_name'), 'text', false);
    field('Phone', 'phone', fv('phone'), 'tel', false, 30);
    field('Email (optional)', 'email', fv('email'), 'email', false, 150);
    ?>
</div>
<?php endif; ?>
<div class="form-grid">
    <div class="field"><label for="court_id">Court</label><select id="court_id" name="court_id" required <?= $payment_only
        ? 'disabled'
        : '' ?>><?php foreach ($court_list as $court): ?><option value="<?= (int) $court[
    'id'
] ?>" <?= fv('court_id', $booking['court_id'] ?? 0) == $court['id'] ? 'selected' : '' ?>><?= e(
    $court['name'],
) ?></option><?php endforeach; ?></select></div>
    <?php field('Booking date', 'date', $date, 'date'); ?>
    <div class="field"><label for="hour">One-hour slot</label><select id="hour" name="hour" <?= $payment_only
        ? 'disabled'
        : '' ?>><?php for (
    $slot = 9;
    $slot <= 23;
    $slot++
): ?><option value="<?= $slot ?>" <?= (int) $hour === $slot ? 'selected' : '' ?>><?= e(
    hour_label($slot) . ' – ' . hour_label($slot + 1),
) ?></option><?php endfor; ?></select></div>
</div>
<?php if ($payment_only): ?><input type="hidden" name="court_id" value="<?= (int) $booking[
    'court_id'
] ?>"><input type="hidden" name="hour" value="<?= (int) substr(
    $booking['start_time'],
    0,
    2,
) ?>"><script>document.getElementById('date').readOnly=true;</script><?php endif; ?>
<p class="summary">₱300 for one hour. The 11 PM slot ends at midnight on the next day.</p>
<div class="form-grid">
    <div class="field"><label for="payment_status">Payment status</label><select id="payment_status" name="payment_status"><?php foreach (
        ['paid' => 'Paid', 'unpaid' => 'Unpaid', 'refunded' => 'Refunded']
        as $key => $label
    ): ?><option value="<?= e($key) ?>" <?= fv(
    'payment_status',
    $booking['payment_status'] ?? 'paid',
) === $key
    ? 'selected'
    : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><?php foreach (
        ['GCash QRPH', 'Bank QRPH']
        as $method
    ): ?><option <?= fv('payment_method', $booking['payment_method'] ?? '') === $method
    ? 'selected'
    : '' ?>><?= e($method) ?></option><?php endforeach; ?></select></div>
    <?php
    field(
        'Payment reference',
        'payment_reference',
        fv('payment_reference', $booking['payment_reference'] ?? ''),
        'text',
        true,
        100,
    );
    field(
        'Amount received (₱)',
        'amount_paid',
        fv('amount_paid', $booking['amount_paid'] ?? '300.00'),
        'number',
    );
    field('Payment date and time', 'paid_at', $paid_at, 'datetime-local');
    ?>
</div>
<div class="field"><label for="payment_notes">Payment notes</label><textarea id="payment_notes" name="payment_notes" maxlength="1000"><?= e(
    fv('payment_notes', $booking['payment_notes'] ?? ''),
) ?></textarea></div>
<div class="field"><label for="notes">Booking notes</label><textarea id="notes" name="notes" maxlength="1000"><?= e(
    fv('notes', $booking['notes'] ?? ''),
) ?></textarea></div>
<label><input type="checkbox" name="verified" value="1" <?= $booking &&
$booking['payment_status'] === 'paid'
    ? 'checked'
    : '' ?>> I manually verified that payment was received.</label>
<p class="note">New reservations require verified full payment. Selecting a slot does not hold it. Payment and refunds happen outside this website.</p>
<?php if (is_file($payment_qr)): ?><img class="payment-qr" src="<?= e($payment_qr) ?>" alt="<?= e(
    $merchant_name,
) ?> merchant QRPH code"><?php else: ?><p class="note">The merchant QRPH image is pending. Add the venue's real QR to img/qrph.png.</p><?php endif; ?>
<div class="actions"><button class="light" type="button" data-close="booking-dialog">Cancel</button><button>Save booking</button></div>
</form>
</dialog>
