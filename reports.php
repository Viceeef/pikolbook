<?php
require 'functions.php';
$user = signed_in();
$title = 'Reports & analytics';
$page = 'reports.php';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$error = '';
if (($from && !valid_date($from)) || ($to && !valid_date($to)) || ($from && $to && $from > $to)) {
    $error = 'Choose a valid date range with the end after the start.';
}
$records = $error
    ? []
    : rows(
        'SELECT * FROM bookings WHERE (?=\'\' OR booking_date>=?) AND (?=\'\' OR booking_date<=?) ORDER BY booking_date',
        'ssss',
        [$from, $from, $to, $to],
    );
$years = [];
foreach ($records as $b) {
    $years[substr($b['booking_date'], 0, 4)] = true;
}
if (!$years && !$error) {
    $years[date('Y')] = true;
}
krsort($years);
$courts = rows('SELECT * FROM courts ORDER BY name');
$clients = rows('SELECT * FROM clients ORDER BY full_name');
function report_totals($records)
{
    $result = ['total' => count($records), 'confirmed' => 0, 'cancelled' => 0, 'received' => 0];
    foreach ($records as $b) {
        if ($b['status'] === 'confirmed') {
            $result['confirmed']++;
        }
        if ($b['status'] === 'cancelled') {
            $result['cancelled']++;
        }
        if ($b['payment_status'] === 'paid') {
            $result['received'] += (float) $b['amount_paid'];
        }
    }
    return $result;
}
require_once 'layout.php';
page_header($title, $page, $user);
?>
<h1>Reports & analytics</h1><p class="muted">Booking counts and recorded payments from the database.</p>
<form method="get" class="toolbar">
<?php
field('From date', 'from', $from, 'date', false);
field('To date', 'to', $to, 'date', false);
?>
<button>Filter</button><a class="button light" href="reports.php">All dates</a><button type="button" class="light" id="print-report">Print report</button>
</form>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<p class="note">Counts include cancelled bookings. Received payments are grouped by booking date and exclude unpaid/refunded records. Cancelling does not automatically refund payment.</p>
<section class="card"><h2>Periodic reporting</h2>
<?php foreach ($years as $year => $unused): ?>
<details class="card" open><summary><?= e(
    $year,
) ?></summary><div class="table-wrap"><table><thead><tr><th>Month</th><th>Total bookings</th><th>Cancelled</th><th>Received payments</th></tr></thead><tbody>
<?php for ($month = 1; $month <= 12; $month++):

    $prefix = sprintf('%04d-%02d', $year, $month);
    $monthly = [];
    foreach ($records as $b) {
        if (strpos($b['booking_date'], $prefix) === 0) {
            $monthly[] = $b;
        }
    }
    $t = report_totals($monthly);
    ?><tr><td><?= e(date('F', mktime(0, 0, 0, $month, 1, (int) $year))) ?></td><td><?= $t[
    'total'
] ?></td><td><?= $t['cancelled'] ?></td><td><?= e(money($t['received'])) ?></td></tr><?php
endfor; ?>
</tbody></table></div></details><?php endforeach; ?>
</section>
<?php foreach (
    [
        ['Bookings per resource', $courts, 'court_id', 'name'],
        ['Bookings per client', $clients, 'client_id', 'full_name'],
    ]
    as $group
): ?>
<section class="card"><h2><?= e(
    $group[0],
) ?></h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Total bookings</th><th>Confirmed</th><th>Cancelled</th><th>Received payments</th></tr></thead><tbody>
<?php foreach ($group[1] as $item):

    $matching = [];
    foreach ($records as $b) {
        if ((int) $b[$group[2]] === (int) $item['id']) {
            $matching[] = $b;
        }
    }
    $t = report_totals($matching);
    ?><tr><td><?= e($item[$group[3]] . ($item['is_active'] ? '' : ' (archived)')) ?></td><td><?= $t[
    'total'
] ?></td><td><?= $t['confirmed'] ?></td><td><?= $t['cancelled'] ?></td><td><?= e(
    money($t['received']),
) ?></td></tr><?php
endforeach; ?>
<?php if (!$group[1]): ?><tr><td colspan="5">No records yet.</td></tr><?php endif; ?>
</tbody></table></div></section><?php endforeach; ?>
<?php page_footer(); ?>
