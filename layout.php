<?php
// Three shared HTML functions keep every page's design consistent.
// PHP changes the menu for Admin/Staff. The booking form is shared by two pages.
function page_header($title, $page, $user)
{
    $links = [
        'dashboard.php' => 'Dashboard',
        'resources.php' => 'Resources',
        'bookings.php' => 'Bookings & schedule',
        'clients.php' => 'Clients',
    ];
    if ($user['role'] === 'admin') {
        $links['staff.php'] = 'Staff';
    }
    $links['reports.php'] = 'Reports & analytics';
    $links['profile.php'] = 'Edit profile';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Pikolbook</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js" defer></script>
</head>
<body>
<div class="shell">
    <aside>
        <a class="brand" href="dashboard.php"><img src="img/logo.png" alt="Pikolbook"></a>
        <nav aria-label="Main navigation">
            <?php foreach ($links as $path => $label): ?>
                <a href="<?= e($path) ?>" <?= $page === $path
    ? 'class="active" aria-current="page"'
    : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="aside-end">
            <div class="aside-user">
                <span class="tag"><?= e(ucfirst($user['role'])) ?></span>
                <p>Welcome back,<strong><?= e($user['full_name']) ?></strong></p>
            </div>
            <form action="index.php" method="post">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="logout">
                <button class="light">Log out</button>
            </form>
        </div>
    </aside>
    <div class="main-area">
        <header class="topbar">
            <strong>PIKOLBOOK <small>/ COURT BOOKING SYSTEM</small></strong>
        </header>
        <main class="content">
            <?php if (!empty($_SESSION['message'])): ?>
                <p class="notice" role="status"><?= e($_SESSION['message']) ?></p>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <p class="error error-notice" role="alert"><?= e($_SESSION['error_message']) ?></p>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
<?php
}
function page_footer()
{
    ?>
        </main>
    </div>
</div>
<dialog id="confirm-dialog">
    <h2>Confirm action</h2>
    <p id="confirm-text"></p>
    <div class="actions">
        <button type="button" class="light" data-close="confirm-dialog">Keep record</button>
        <button type="button" id="confirm-submit">Confirm</button>
    </div>
</dialog>
<dialog id="feedback-dialog" aria-labelledby="feedback-title" <?= !empty($_SESSION['feedback']) || !empty($_SESSION['action_error']) ? 'data-auto-open' : '' ?>>
    <h2 id="feedback-title"><?= !empty($_SESSION['action_error']) ? 'Please check this action' : 'Saved successfully' ?></h2>
    <p id="feedback-text"><?= e($_SESSION['action_error'] ?? $_SESSION['feedback'] ?? '') ?></p>
    <div class="actions"><button type="button" data-close="feedback-dialog">OK</button></div>
</dialog>
<?php unset($_SESSION['feedback'], $_SESSION['action_error']); ?>
</body>
</html>
<?php unset($_SESSION['form_draft']);
}
function booking_modal($page, $user)
{
    global $payment_qr, $merchant_name;
    $booking = null;
    if ($page === 'bookings.php' && !empty($_GET['edit'])) {
        $booking = booking_record((int) $_GET['edit']);
        if ($booking) {
            $client = one('SELECT full_name FROM clients WHERE id=?', 'i', [$booking['client_id']]);
            $booking['client_name'] = $client['full_name'];
        }
        if (!$booking || $booking['status'] === 'cancelled' || $booking['payment_status'] === 'refunded') {
            $_SESSION['action_error'] = $_SESSION['action_error'] ?? 'This booking is missing, cancelled or refunded and cannot be edited.';
            return;
        }
    }
    $payment_only = $booking && ($user['role'] === 'staff' || $booking['status'] !== 'confirmed' || $booking['booking_date'] . ' ' . $booking['start_time'] <= date('Y-m-d H:i:s'));
    $form_error = $_SESSION['form_error'] ?? '';
    unset($_SESSION['form_error']);
    $client_list = rows('SELECT * FROM clients WHERE is_active=1 ORDER BY full_name');
    $court_list = rows('SELECT * FROM courts WHERE is_active=1 OR id=? ORDER BY id', 'i', [
        $booking['court_id'] ?? 0,
    ]);
    $date = fv('date', $booking['booking_date'] ?? date('Y-m-d'));
    $hour = fv('hour', $booking ? (int) substr($booking['start_time'], 0, 2) : 9);
    $duration = fv('duration', $booking ? booking_hours($booking) : 1);
    $paid_at = fv(
        'paid_at',
        !empty($booking['paid_at']) ? date('Y-m-d\TH:i', strtotime($booking['paid_at'])) : date('Y-m-d\TH:i'),
    );
    $title = $booking ? ($payment_only ? 'Payment details' : 'Booking master override') : 'New booking';
    ?>
<dialog id="booking-dialog" <?= $booking || isset($_GET['new']) ? 'data-auto-open' : '' ?>>
    <div class="heading">
        <h2><?= e($title) ?></h2>
        <button class="light" type="button" data-close="booking-dialog">Close</button>
    </div>
    <form method="post" action="bookings.php" id="booking-form" data-edit="<?= $booking ? '1' : '0' ?>" data-total="<?= e($booking['total_amount'] ?? '') ?>" data-shared-payment="<?= !empty($booking['series_code']) ? '1' : '0' ?>" data-original-court="<?= (int) ($booking['court_id'] ?? 0) ?>" data-original-date="<?= e($booking['booking_date'] ?? '') ?>" data-original-hour="<?= $booking ? (int) substr($booking['start_time'],0,2) : '' ?>" data-original-duration="<?= $booking ? booking_hours($booking) : '' ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= (int) ($booking['id'] ?? 0) ?>">
        <input type="hidden" name="return_page" value="<?= e($page) ?>">
        <p id="booking-validation-error" class="error validation-error" role="alert" hidden></p>
        <?php if ($booking): ?>
            <p>Client: <strong><?= e($booking['client_name']) ?></strong> · <?= e(
    ucfirst($booking['status']),
) ?></p>
        <?php else: ?>
            <div class="field">
                <label for="client_mode">Client type<?= required_mark() ?></label>
                <select id="client_mode" name="client_mode" required>
                    <option value="existing">Existing client</option>
                    <option value="new" <?= fv('client_mode') === 'new'
                        ? 'selected'
                        : '' ?>>New client</option>
                </select>
            </div>
            <div id="existing-client-fields">
                <div class="field"><label for="client-search">Search client name</label><input id="client-search" type="search" placeholder="Type a name" autocomplete="off"><ul id="client-search-results" class="client-search-results" aria-live="polite" hidden></ul></div>
                <div class="field">
                    <label for="client_id">Select client<?= required_mark() ?></label>
                    <select id="client_id" name="client_id" required>
                        <option value="">Choose a client</option>
                        <?php foreach ($client_list as $client): ?>
                            <option value="<?= (int) $client['id'] ?>" <?= fv('client_id') == $client['id']
    ? 'selected'
    : '' ?>><?= e($client['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="new-client-fields" hidden>
                <?php
                field('Full name', 'full_name', fv('full_name'));
                field('Phone', 'phone', fv('phone'), 'tel', true, 30);
                field('Email', 'email', fv('email'), 'email', true, 150);
                ?>
            </div>
        <?php endif; ?>
        <?php if (!$booking): ?>
        <div class="field"><label for="repeat">Booking type<?= required_mark() ?></label>
        <select id="repeat" name="repeat"><option value="once">One date</option><option value="monthly" <?= fv('repeat') === 'monthly' ? 'selected' : '' ?>>Monthly block (same weekday until month end)</option></select></div>
        <p class="note">For a full month, select the first occurrence of the weekday. A later first date books only the remaining occurrences in that month.</p>
        <?php elseif (!empty($booking['series_code'])): ?><p class="notice">Part of monthly block <?= e($booking['series_code']) ?>. This form changes one occurrence. Its payment share stays fixed.</p><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label for="court_id">Court<?= required_mark() ?></label>
                <select id="court_id" name="court_id" required <?= $payment_only ? 'disabled' : '' ?>>
                    <?php foreach ($court_list as $court): ?>
                        <option value="<?= (int) $court['id'] ?>" data-rate="<?= e(
    $court['hourly_rate'],
) ?>" <?= fv('court_id', $booking['court_id'] ?? 0) == $court['id'] ? 'selected' : '' ?>><?= e(
    $court['name'],
) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="date">Booking date<?= required_mark() ?></label>
                <input type="date" id="date" name="date" value="<?= e(
                    $date,
                ) ?>" required <?= $payment_only ? 'readonly' : '' ?>>
            </div>
            <div class="field">
                <label for="hour">Start time<?= required_mark() ?></label>
                <select id="hour" name="hour" required <?= $payment_only ? 'disabled' : '' ?>>
                    <?php for ($start = 9; $start <= 23; $start++): ?>
                        <option value="<?= $start ?>" <?= (int) $hour === $start ? 'selected' : '' ?>><?= e(
    hour_label($start),
) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label for="duration">Duration<?= required_mark() ?></label>
                <select id="duration" name="duration" required <?= ($payment_only || !empty($booking['series_code'])) ? 'disabled' : '' ?>>
                    <?php for ($hours = 1; $hours <= 15; $hours++): ?>
                        <option value="<?= $hours ?>" <?= (int) $duration === $hours
    ? 'selected'
    : '' ?>><?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <?php if ($payment_only): ?>
            <input type="hidden" name="court_id" value="<?= (int) $booking['court_id'] ?>">
            <input type="hidden" name="hour" value="<?= (int) substr($booking['start_time'], 0, 2) ?>">
            <input type="hidden" name="duration" value="<?= booking_hours($booking) ?>">
        <?php endif; ?>
        <?php if (!$payment_only && !empty($booking['series_code'])): ?><input type="hidden" name="duration" value="<?= booking_hours($booking) ?>"><?php endif; ?>
        <p class="note" id="repeat-summary" aria-live="polite"></p>
        <p class="summary" id="booking-summary" aria-live="polite">₱300 per hour. Bookings must end by midnight.</p>
        <div class="form-grid">
            <div class="field">
                <label for="payment_status">Payment status<?= required_mark() ?></label>
                <select id="payment_status" name="payment_status" required>
                    <?php foreach (
                        ($booking && empty($booking['series_code']) ? ['paid' => 'Paid', 'unpaid' => 'Unpaid'] : ['paid' => 'Paid'])
                        as $key => $label
                    ): ?>
                        <option value="<?= e($key) ?>" <?= fv(
    'payment_status',
    $booking['payment_status'] ?? 'paid',
) === $key
    ? 'selected'
    : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="payment_method">Payment method<?= required_mark() ?></label>
                <select id="payment_method" name="payment_method" required>
                    <?php foreach (!empty($booking['series_code']) ? [$booking['payment_method']] : ['GCash QRPH', 'Bank QRPH'] as $method): ?>
                        <option <?= fv('payment_method', $booking['payment_method'] ?? '') === $method
                            ? 'selected'
                            : '' ?>><?= e($method) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                fv(
                    'amount_paid',
                    $booking['amount_paid'] ?? number_format(300 * (int) $duration, 2, '.', ''),
                ),
                'number',
            );
            field('Payment date and time', 'paid_at', $paid_at, 'datetime-local');
            ?>
        </div>
        <div class="field"><label for="payment_notes">Payment notes (optional)</label><textarea id="payment_notes" name="payment_notes" maxlength="1000"><?= e(
            fv('payment_notes', $booking['payment_notes'] ?? ''),
        ) ?></textarea></div>
        <div class="field"><label for="notes">Booking notes (optional)</label><textarea id="notes" name="notes" maxlength="1000"><?= e(
            fv('notes', $booking['notes'] ?? ''),
        ) ?></textarea></div>
        <label><input type="checkbox" name="verified" value="1" <?= !$booking ? 'required' : '' ?> <?= $booking &&
        $booking['payment_status'] === 'paid' && !$form_error
            ? 'checked'
            : '' ?>> I manually verified that payment was received.<?= !$booking ? required_mark() : '' ?></label>
        <p class="note">Monthly blocks require one verified payment for all shown dates. The payment is divided across those dates for reports and refunds. Payments and refunds are made outside this website.</p>
        <?php if (is_file($payment_qr)): ?>
            <img class="payment-qr" src="<?= e($payment_qr) ?>" alt="<?= e(
    $merchant_name,
) ?> merchant QRPH code">
        <?php endif; ?>
        <div class="actions">
            <button class="light" type="button" data-close="booking-dialog"><?= action_icon(
    'cancel',
) ?>Cancel</button>
            <button id="save-booking">Save booking</button>
        </div>
    </form>
</dialog>
<dialog id="booking-error-dialog" aria-labelledby="booking-error-title" <?= $form_error ? 'data-auto-open' : '' ?>>
    <h2 id="booking-error-title">Booking was not saved</h2>
    <p id="booking-error-text"><?= e($form_error) ?></p>
    <p>Your entries are kept. Edit them and try again, or discard this form.</p>
    <div class="actions"><button type="button" class="light" id="discard-booking">Discard form</button><button type="button" id="retry-booking">Edit and try again</button></div>
</dialog>
<?php
}

// Cancellation and refund forms share the same page as the booking list.
function cancellation_modal()
{
    $id = (int) ($_GET['cancel'] ?? $_GET['refund'] ?? 0);
    if (!$id) return;
    $booking = booking_record($id);
    $refund = isset($_GET['refund']);
    if (!$booking || (!$refund && $booking['status'] !== 'confirmed') ||
        ($refund && ($booking['status'] !== 'cancelled' || (float) $booking['refund_due'] <= (float) $booking['refunded_amount']))) {
        $_SESSION['action_error'] = $_SESSION['action_error'] ?? 'This booking is not eligible for the selected action.';
        return;
    }
    $scope = ($_GET['scope'] ?? '') === 'series' && !empty($booking['series_code']) ? 'series' : 'one';
    $targets = cancellation_targets($booking, $scope);
    $due = 0;
    foreach ($targets as $target) $due += refund_quote($target)['amount'];
    ?>
<dialog id="cancellation-dialog" data-auto-open aria-labelledby="cancellation-title">
    <div class="heading"><h2 id="cancellation-title"><?= $refund ? 'Record refund' : 'Cancel booking' ?></h2><a class="button light" href="bookings.php">Close</a></div>
    <?php if ($refund): ?>
    <p>Booking #<?= $id ?> · <?= e($booking['booking_date']) ?></p>
    <p class="summary">Refund due: <?= e(money($booking['refund_due'])) ?></p>
    <p>Send the refund outside Pikolbook first, then record its transaction reference below.</p>
    <form method="post" action="bookings.php"><?php csrf_field(); ?>
        <input type="hidden" name="action" value="refund"><input type="hidden" name="id" value="<?= $id ?>">
        <?php field('Refund transaction reference', 'refund_reference', '', 'text', true, 100); ?>
        <label><input type="checkbox" name="refund_verified" value="1" required> I verified that <?= e(money($booking['refund_due'])) ?> was sent to the client.</label>
        <div class="actions"><a class="button light" href="bookings.php">Back</a><button>Record paid refund</button></div>
    </form>
    <?php else: ?>
    <p>At least 24 hours before: 100% refund. From 3 to under 24 hours: 50%. Under 3 hours, already started or no-show: no refund.</p>
    <?php if (!empty($booking['series_code'])): ?>
    <div class="view-switch"><a class="button <?= $scope === 'one' ? '' : 'light' ?>" href="bookings.php?cancel=<?= $id ?>&amp;scope=one">This date only</a><a class="button <?= $scope === 'series' ? '' : 'light' ?>" href="bookings.php?cancel=<?= $id ?>&amp;scope=series">All future dates in block</a></div>
    <?php endif; ?>
    <div class="table-wrap"><table><thead><tr><th>Date / time</th><th>Refund rate</th><th>Refund due</th></tr></thead><tbody>
    <?php foreach ($targets as $target): $quote = refund_quote($target); ?>
    <tr><td><?= e($target['booking_date']) ?><br><?= e(slot_label($target['start_time'],$target['end_time'])) ?></td><td><?= $quote['percent'] ?>%</td><td><?= e(money($quote['amount'])) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p class="summary"><?= count($targets) ?> booking(s) · Total refund due: <?= e(money($due)) ?></p>
    <p>Cancellation releases the selected slots immediately. Refunds remain pending until recorded as paid.</p>
    <form method="post" action="bookings.php"><?php csrf_field(); ?>
        <input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="scope" value="<?= e($scope) ?>"><input type="hidden" name="quoted_refund" value="<?= e($due) ?>"><input type="hidden" name="quoted_count" value="<?= count($targets) ?>">
        <div class="field"><label for="cancel_reason">Cancellation reason<?= required_mark() ?></label><textarea id="cancel_reason" name="cancel_reason" maxlength="500" required><?= e((int) ($_SESSION['cancel_draft_id'] ?? 0) === $id ? ($_SESSION['cancel_draft'] ?? '') : '') ?></textarea></div>
        <div class="actions"><a class="button light" href="bookings.php">Keep booking</a><button <?= !$targets ? 'disabled' : '' ?>>Confirm cancellation</button></div>
    </form>
    <?php endif; ?>
</dialog>
<?php unset($_SESSION['cancel_draft'], $_SESSION['cancel_draft_id']);
}

function booking_calendar($courts, $bookings)
{
    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || !valid_date($month . '-01') || (int) substr($month,0,4) < 2000 || (int) substr($month,0,4) > 2100) $month = date('Y-m');
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first));
    $selected = $_GET['day'] ?? (substr(date('Y-m-d'),0,7) === $month ? date('Y-m-d') : $first);
    if (!valid_date($selected) || substr($selected,0,7) !== $month) $selected = $first;
    $court_id = (int) ($_GET['court_id'] ?? 0);
    $shown = [];
    foreach ($courts as $court) {
        if (($court_id && (int) $court['id'] === $court_id) || (!$court_id && $court['is_active'])) $shown[] = $court;
    }
    $blocks = rows('SELECT * FROM court_blocks WHERE start_at<? AND end_at>?', 'ss', [date('Y-m-d',strtotime($last.' +1 day')), $first]);
    $month_bookings = [];
    foreach ($bookings as $b) {
        if ($b['booking_date'] >= $first && $b['booking_date'] <= $last && $b['status'] !== 'cancelled') $month_bookings[] = $b;
    }
    $previous = date('Y-m', strtotime($first . ' -1 month'));
    $next = date('Y-m', strtotime($first . ' +1 month'));
    ?>
<section class="card">
    <form method="get" class="toolbar"><input type="hidden" name="view" value="calendar">
        <div class="field"><label for="calendar-month">Month</label><input id="calendar-month" type="month" name="month" min="2000-01" max="2100-12" value="<?= e($month) ?>" required></div>
        <div class="field"><label for="calendar-court">Court</label><select id="calendar-court" name="court_id"><option value="0">All active courts</option><?php foreach ($courts as $court): ?><option value="<?= (int) $court['id'] ?>" <?= $court_id === (int) $court['id'] ? 'selected' : '' ?>><?= e($court['name'] . ($court['is_active'] ? '' : ' (archived)')) ?></option><?php endforeach; ?></select></div>
        <button>View calendar</button><a class="button light" href="bookings.php?view=calendar">Today</a>
    </form>
    <div class="heading"><a class="button light" aria-label="Previous month" href="bookings.php?view=calendar&amp;month=<?= e($previous) ?>&amp;court_id=<?= $court_id ?>">Previous</a><h2><?= e(date('F Y',strtotime($first))) ?></h2><a class="button light" aria-label="Next month" href="bookings.php?view=calendar&amp;month=<?= e($next) ?>&amp;court_id=<?= $court_id ?>">Next</a></div>
    <p class="note">Select a day to see each court's time slots. Available hours exclude past times and maintenance. Cancelled bookings release their slots.</p>
    <div class="calendar-legend" aria-label="Calendar legend">
        <span><i class="legend-dot legend-booked"></i>Has bookings</span>
        <span><i class="legend-dot legend-available"></i>Availability</span>
        <span><i class="legend-dot legend-maintenance"></i>Maintenance</span>
        <span><i class="legend-bar"></i>Booked-hour share</span>
    </div>
    <p class="mobile-note">Swipe sideways to see the full week and all courts.</p>
    <div class="calendar-scroll"><div class="calendar-grid">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday): ?><div class="calendar-weekday"><?= $weekday ?></div><?php endforeach; ?>
    <?php for ($blank=0;$blank<(int)date('w',strtotime($first));$blank++): ?><div class="calendar-blank"></div><?php endfor; ?>
    <?php for ($day=1;$day<=(int)date('t',strtotime($first));$day++):
        $day_date = $month . '-' . sprintf('%02d',$day);
        $free = 0; $occupied = 0; $maintenance = 0;
        foreach ($shown as $court) {
            for ($hour=9;$hour<=23;$hour++) {
                $slot = calendar_slot($court,$day_date,$hour,$month_bookings,$blocks);
                if ($slot['state'] === 'free') $free++;
                if ($slot['state'] === 'booked') $occupied++;
                if ($slot['state'] === 'maintenance') $maintenance++;
            }
        }
        $tracked = $free + $occupied + $maintenance;
        $load = $tracked ? (int) round($occupied / $tracked * 100) : 0;
        $day_class = $occupied ? 'calendar-day--booked' : ($free ? 'calendar-day--open' : 'calendar-day--full');
        ?>
        <a class="calendar-day <?= $day_class ?> <?= $day_date === $selected ? 'calendar-selected' : '' ?>" href="bookings.php?view=calendar&amp;month=<?= e($month) ?>&amp;day=<?= e($day_date) ?>&amp;court_id=<?= $court_id ?>#day-schedule" <?= $day_date === date('Y-m-d') ? 'aria-current="date"' : '' ?>>
            <span class="calendar-date"><strong><?= $day ?></strong><span><?= e(date('D', strtotime($day_date))) ?></span><?php if ($day_date === date('Y-m-d')): ?><b>Today</b><?php endif; ?></span>
            <span class="calendar-metrics"><span class="calendar-booked"><i class="calendar-metric-dot"></i><?= $occupied ?> booked</span><span class="calendar-free"><i class="calendar-metric-dot"></i><?= $free ?> available</span><?php if ($maintenance): ?><span class="calendar-maintenance"><i class="calendar-metric-dot"></i><?= $maintenance ?> maintenance</span><?php endif; ?></span>
            <span class="calendar-load" role="img" aria-label="<?= $occupied ?> booked hours out of <?= $tracked ?> tracked hours" title="<?= $load ?>% of tracked hours booked"><span style="width: <?= $load ?>%"></span></span>
        </a>
    <?php endfor; ?>
    </div></div>
</section>
<section class="card" id="day-schedule"><h2><?= e(date('l, F j, Y',strtotime($selected))) ?></h2>
<p class="note">Choose an available hour to open a booking. You can extend its duration in the form.</p>
<?php if (!$shown): ?><p>No courts match this selection.</p><?php else: ?>
<div class="table-wrap"><table class="day-table"><thead><tr><th>Time</th><?php foreach ($shown as $court): ?><th><?= e($court['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php for ($hour=9;$hour<=23;$hour++): ?><tr><th><?= e(hour_label($hour)) ?> – <?= e(hour_label($hour+1)) ?></th>
<?php foreach ($shown as $court): $slot=calendar_slot($court,$selected,$hour,$month_bookings,$blocks); ?>
<td><?php if ($slot['state'] === 'free'): ?><button type="button" class="light slot-free" data-slot-date="<?= e($selected) ?>" data-slot-hour="<?= $hour ?>" data-slot-court="<?= (int) $court['id'] ?>">Available</button>
<?php elseif ($slot['state'] === 'booked'): ?><a class="slot-booked" href="bookings.php?date=<?= e($selected) ?>&amp;court_id=<?= (int) $court['id'] ?>">Booked · <?= e($slot['label']) ?></a>
<?php else: ?><span class="slot-<?= e($slot['state']) ?>"><?= e($slot['label']) ?></span><?php endif; ?></td>
<?php endforeach; ?></tr><?php endfor; ?></tbody></table></div><?php endif; ?>
</section>
<?php
}
