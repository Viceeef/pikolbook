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
    if ($user['role'] === 'staff') {
        $links['profile.php'] = 'Edit profile';
    }
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
</body>
</html>
<?php unset($_SESSION['form_draft']);
}
function booking_modal($page, $user)
{
    global $payment_qr, $merchant_name;
    $booking = null;
    if ($page === 'bookings.php' && !empty($_GET['edit'])) {
        $booking = one(
            'SELECT b.*, cl.full_name AS client_name FROM bookings b JOIN clients cl ON cl.id=b.client_id WHERE b.id=?',
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
    <form method="post" action="bookings.php" id="booking-form" data-edit="<?= $booking ? '1' : '0' ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= (int) ($booking['id'] ?? 0) ?>">
        <input type="hidden" name="return_page" value="<?= e($page) ?>">
        <p id="booking-validation-error" class="error validation-error" role="alert" hidden></p>
        <?php if (!empty($_SESSION['form_error'])): ?>
            <p class="error" role="alert"><?= e($_SESSION['form_error']) ?></p>
            <?php unset($_SESSION['form_error']); ?>
        <?php endif; ?>
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
                <select id="duration" name="duration" required <?= $payment_only ? 'disabled' : '' ?>>
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
        <p class="summary" id="booking-summary">₱300 per hour. Bookings must end by midnight.</p>
        <div class="form-grid">
            <div class="field">
                <label for="payment_status">Payment status<?= required_mark() ?></label>
                <select id="payment_status" name="payment_status" required>
                    <?php foreach (
                        ['paid' => 'Paid', 'unpaid' => 'Unpaid', 'refunded' => 'Refunded']
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
                    <?php foreach (['GCash QRPH', 'Bank QRPH'] as $method): ?>
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
        $booking['payment_status'] === 'paid'
            ? 'checked'
            : '' ?>> I manually verified that payment was received.<?= !$booking ? required_mark() : '' ?></label>
        <p class="note">New reservations require verified full payment. Changing the duration changes the required payment. Payment and refunds happen outside this website.</p>
        <?php if (is_file($payment_qr)): ?>
            <img class="payment-qr" src="<?= e($payment_qr) ?>" alt="<?= e(
    $merchant_name,
) ?> merchant QRPH code">
        <?php else: ?>
            <p class="note">The merchant QRPH image is pending. Add the venue's real QR to img/qrph.png.</p>
        <?php endif; ?>
        <div class="actions">
            <button class="light" type="button" data-close="booking-dialog"><?= action_icon(
    'cancel',
) ?>Cancel</button>
            <button>Save booking</button>
        </div>
    </form>
</dialog>
<?php
}
