<?php
require 'functions.php';
$user = signed_in();
$title = 'System overview';
$page = 'dashboard.php';
$today = date('Y-m-d');
$schedule = rows(
    'SELECT b.*, c.name AS court_name, cl.full_name AS client_name FROM bookings b JOIN courts c ON c.id=b.court_id JOIN clients cl ON cl.id=b.client_id WHERE b.booking_date=? ORDER BY b.start_time',
    's',
    [$today],
);
$counts = one("SELECT COUNT(*) AS total FROM bookings WHERE booking_date=? AND status='confirmed'", 's', [
    $today,
]);
$courts = one(
    'SELECT COUNT(*) AS total FROM courts c WHERE c.is_active=1 AND NOT EXISTS (SELECT 1 FROM court_blocks cb WHERE cb.court_id=c.id AND cb.start_at<=? AND cb.end_at>?)',
    'ss',
    [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')],
);
$clients = one('SELECT COUNT(*) AS total FROM clients WHERE is_active=1');
$payments = one(
    "SELECT COALESCE(SUM(amount_paid),0) AS total FROM bookings WHERE DATE(paid_at)=? AND payment_status='paid'",
    's',
    [$today],
);
require_once 'layout.php';
page_header($title, $page, $user);
?>
<div class="heading"><div><h1>System overview</h1><p class="muted">A quick look at your courts and today's play.</p></div><button data-open="booking-dialog">+ New booking</button></div>
<section class="stats">
    <?php foreach (
        [
            ['Bookings today', $counts['total']],
            ['Courts in service', $courts['total']],
            ['Active clients', $clients['total']],
            ['Payments received today', money($payments['total'])],
        ]
        as $stat
    ): ?>
        <article class="card stat"><p><?= e($stat[0]) ?></p><strong><?= e($stat[1]) ?></strong></article>
    <?php endforeach; ?>
</section>
<section class="card"><h2>Today's quick schedule</h2><p><?= e(
    $today,
) ?></p><div class="table-wrap"><table><thead><tr><th>Time</th><th>Court</th><th>Client</th><th>Booking</th><th>Payment</th></tr></thead><tbody>
<?php foreach ($schedule as $booking): ?><tr><td><?= e(
    slot_label($booking['start_time'], $booking['end_time']),
) ?></td><td><?= e($booking['court_name']) ?></td><td><?= e($booking['client_name']) ?></td><td><?= e(
    ucfirst($booking['status']),
) ?></td><td><?= e(ucfirst($booking['payment_status'])) ?></td></tr><?php endforeach; ?>
<?php if (!$schedule): ?><tr><td colspan="5">No bookings today.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php
booking_modal($page, $user);
page_footer();
 ?>
