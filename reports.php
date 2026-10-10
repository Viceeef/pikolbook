<?php
require 'functions.php';
$user = signed_in();
$title = 'Reports & analytics';
$page = 'reports.php';
$start_month = filter_input(INPUT_GET, 'start_month', FILTER_VALIDATE_INT);
$end_month = filter_input(INPUT_GET, 'end_month', FILTER_VALIDATE_INT);
$start_year = filter_input(INPUT_GET, 'start_year', FILTER_VALIDATE_INT);
$end_year = filter_input(INPUT_GET, 'end_year', FILTER_VALIDATE_INT);
$start_month = $start_month !== false && $start_month >= 1 && $start_month <= 12 ? $start_month : 0;
$end_month = $end_month !== false && $end_month >= 1 && $end_month <= 12 ? $end_month : 0;
$available_years = rows('SELECT DISTINCT YEAR(booking_date) AS year FROM bookings ORDER BY year DESC');
$year_options = array_map('intval', array_column($available_years, 'year'));
if (!in_array((int) date('Y'), $year_options, true)) {
    $year_options[] = (int) date('Y');
}
rsort($year_options);
$start_year = $start_year !== false && in_array($start_year, $year_options, true) ? $start_year : 0;
$end_year = $end_year !== false && in_array($end_year, $year_options, true) ? $end_year : 0;
$start_bound = '';
$end_bound = '';
if ($start_month || $end_month || $start_year || $end_year) {
    $first_year = $year_options ? min($year_options) : (int) date('Y');
    $last_year = $year_options ? max($year_options) : (int) date('Y');
    $from_year = $start_year ?: $first_year;
    $to_year = $end_year ?: $last_year;
    $from_month = $start_month ?: 1;
    $to_month = $end_month ?: 12;
    $start_bound = sprintf('%04d-%02d-01', $from_year, $from_month);
    $end_bound = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $to_year, $to_month)));
    if ($start_bound > $end_bound) {
        [$start_bound, $end_bound] = [$end_bound, $start_bound];
    }
}
$records = rows('SELECT * FROM bookings ORDER BY booking_date');
$periodic_records = rows(
    'SELECT * FROM bookings WHERE (?=\'\' OR booking_date>=?) AND (?=\'\' OR booking_date<=?) ORDER BY booking_date',
    'ssss',
    [$start_bound, $start_bound, $end_bound, $end_bound],
);
$years = [];
foreach ($periodic_records as $b) {
    $years[substr($b['booking_date'], 0, 4)] = true;
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
<div class="report-choices" role="group" aria-label="Choose a report">
    <button type="button" class="light" data-report-view="periodic-report" aria-pressed="true">Periodic reporting</button>
    <button type="button" class="light" data-report-view="resource-report" aria-pressed="false">Bookings per resource</button>
    <button type="button" class="light" data-report-view="client-report" aria-pressed="false">Bookings per client</button>
</div>
<div id="report-filter-wrap">
<form method="get" class="toolbar" id="report-filters">
<div class="field"><label for="start-month-filter">From month</label><select id="start-month-filter" name="start_month"><option value="0">Any month</option><?php for (
    $month = 1;
    $month <= 12;
    $month++
): ?><option value="<?= $month ?>" <?= $start_month === $month
    ? 'selected'
    : '' ?>><?= e(date('F', mktime(0, 0, 0, $month, 1))) ?></option><?php endfor; ?></select></div>
<div class="field"><label for="end-month-filter">To month</label><select id="end-month-filter" name="end_month"><option value="0">Any month</option><?php for (
    $month = 1;
    $month <= 12;
    $month++
): ?><option value="<?= $month ?>" <?= $end_month === $month
    ? 'selected'
    : '' ?>><?= e(date('F', mktime(0, 0, 0, $month, 1))) ?></option><?php endfor; ?></select></div>
<div class="field"><label for="start-year-filter">From year</label><select id="start-year-filter" name="start_year"><option value="0">Any year</option><?php foreach (
    $year_options
    as $year_option
): ?><option value="<?= $year_option ?>" <?= $start_year === $year_option
    ? 'selected'
    : '' ?>><?= $year_option ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="end-year-filter">To year</label><select id="end-year-filter" name="end_year"><option value="0">Any year</option><?php foreach (
    $year_options
    as $year_option
): ?><option value="<?= $year_option ?>" <?= $end_year === $year_option
    ? 'selected'
    : '' ?>><?= $year_option ?></option><?php endforeach; ?></select></div>
<button type="submit"><?= action_icon('filter') ?>Filter</button><a class="button light" href="reports.php"><?= action_icon(
    'clear',
) ?>Clear all</a><button type="button" class="light" id="print-report">Print report</button>
</form>
<p class="note">Counts include cancelled bookings. Received payments are grouped by booking date and exclude unpaid/refunded records. Cancelling does not automatically refund payment.</p>
</div>
<section class="card report-view" id="periodic-report"><h2>Periodic reporting</h2>
<?php if (!$periodic_records): ?><p class="error error-notice" role="alert">No bookings match the selected month and year.</p><?php endif; ?>
<?php foreach ($years as $year => $unused): ?>
<details class="card" <?= (int) $year === (int) date('Y') ? 'open' : '' ?>><summary><?= e(
    $year,
) ?></summary><div class="table-wrap"><table><thead><tr><th>Month</th><th>Total bookings</th><th>Cancelled</th><th>Received payments</th></tr></thead><tbody>
<?php for ($month = 1; $month <= 12; $month++):

    if (
        ($start_bound && (string) $year === substr($start_bound, 0, 4) && $month < (int) substr($start_bound, 5, 2)) ||
        ($end_bound && (string) $year === substr($end_bound, 0, 4) && $month > (int) substr($end_bound, 5, 2))
    ) {
        continue;
    }

    $prefix = sprintf('%04d-%02d', $year, $month);
    $monthly = [];
    foreach ($periodic_records as $b) {
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
        ['Bookings per resource', $courts, 'court_id', 'name', 'resource-report'],
        ['Bookings per client', $clients, 'client_id', 'full_name', 'client-report'],
    ]
    as $group
): ?>
<section class="card report-view" id="<?= e($group[4]) ?>" hidden><h2><?= e(
    $group[0],
) ?></h2><?php if (!$records): ?><p class="error error-notice" role="alert">No booking records are available.</p><?php elseif (!$group[1]): ?><p class="error" role="alert">No results available for this report.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Name</th><th>Total bookings</th><th>Confirmed</th><th>Cancelled</th><th>Received payments</th></tr></thead><tbody>
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
</tbody></table></div><?php endif; ?></section><?php endforeach; ?>
<?php page_footer(); ?>
