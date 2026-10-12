<?php
require_once 'config.php';
ini_set('session.use_strict_mode', '1');
session_name('pikolbook_session');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
header('Cache-Control: no-store');

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function go($path)
{
    header('Location: ' . $path);
    exit();
}
function notice($text)
{
    $_SESSION['message'] = $text;
}
function csrf_field()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function check_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Please use the form to make changes.');
    }
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Your form expired. Return to the page, refresh, and try again.');
    }
}
function signed_in()
{
    if (empty($_SESSION['user_id'])) {
        go('index.php');
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > 1800) {
        unset($_SESSION['user_id']);
        notice('Your session expired after 30 minutes without activity. Please log in again.');
        go('index.php');
    }
    $user = one('SELECT * FROM users WHERE id = ? AND is_active = 1', 'i', [$_SESSION['user_id']]);
    if (!$user || (!empty($_SESSION['auth_hash']) && $_SESSION['auth_hash'] !== $user['password_hash'])) {
        unset($_SESSION['user_id'], $_SESSION['auth_hash']);
        notice('Please log in again. Your account may have been updated.');
        go('index.php');
    }
    $_SESSION['last_activity'] = time();
    return $user;
}
function admin_only($user)
{
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Only an administrator can open this page or perform this action.');
    }
}
function text_input($name, $max, $required = true)
{
    $value = trim($_POST[$name] ?? '');
    if (($required && $value === '') || strlen($value) > $max) {
        throw new Exception(
            'Please check ' . str_replace('_', ' ', $name) . ' (maximum ' . $max . ' characters).',
        );
    }
    return $value;
}
// All contact numbers in this project are Philippine mobile numbers.
// Accept common local/international input; keep one +63 format in MySQL.
function normalize_phone($value)
{
    if (!is_string($value)) return null;
    $number = str_replace([' ', '-'], '', trim($value));
    if (preg_match('/^09[0-9]{9}$/', $number)) $number = '+63' . substr($number, 1);
    elseif (preg_match('/^9[0-9]{9}$/', $number)) $number = '+63' . $number;
    elseif (preg_match('/^639[0-9]{9}$/', $number)) $number = '+' . $number;
    return preg_match('/^\+639[0-9]{9}$/', $number) ? $number : null;
}
function phone_input()
{
    $phone = normalize_phone($_POST['phone'] ?? '');
    if ($phone === null) {
        throw new Exception('Enter a valid Philippine mobile number: +639 followed by 9 digits. Letters are not allowed.');
    }
    return $phone;
}
function phone_display($value)
{
    if ($value === null || trim((string) $value) === '') return 'Not provided';
    return normalize_phone($value) ?? 'Needs correction';
}
function phone_field($value = '')
{
    if (!is_string($value)) $value = '';
    $normalized = normalize_phone($value ?? '');
    $local = $normalized ? substr($normalized, 3) : ($value ?? '');
    echo '<div class="field"><label for="phone">Philippine mobile number' . required_mark() . '</label>';
    echo '<div class="phone-input"><span class="phone-prefix" aria-hidden="true">+63</span>';
    echo '<input id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel-national" value="' . e($local) . '" maxlength="30" pattern="9[0-9]{9}" title="Enter 10 digits starting with 9, for example 9171234567" placeholder="9171234567" required aria-describedby="phone-help"></div>';
    echo '<p class="note" id="phone-help">Enter 10 digits starting with 9. Your number is saved with +63.' . ($local === '' ? ' No phone number is stored yet; enter your real number.' : '') . '</p></div>';
}

function email_input($required = true)
{
    $email = text_input('email', 150, $required);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Enter a valid email address.');
    }
    return $email;
}
function valid_date($value)
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}
function money($value)
{
    return '₱' . number_format((float) $value, 2);
}
function hour_label($hour)
{
    if ($hour === 24) {
        return '12 AM (next day)';
    }
    return date('g A', mktime($hour, 0));
}
function slot_label($start, $end)
{
    $start_hour = (int) substr($start, 0, 2);
    $end_hour = $end === '00:00:00' ? 24 : (int) substr($end, 0, 2);
    return hour_label($start_hour) . ' – ' . hour_label($end_hour);
}
function booking_hours($booking)
{
    $start = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    return (int) ((strtotime(booking_end($booking)) - $start) / 3600);
}
function booking_end($booking)
{
    // A TIME of 00:00 following 23:00 means midnight on the next date.
    $end = $booking['booking_date'] . ' ' . $booking['end_time'];
    if ($booking['end_time'] <= $booking['start_time']) {
        return date('Y-m-d H:i:s', strtotime($end . ' +1 day'));
    }
    return $end;
}
function begin_write()
{
    global $db, $write_started;
    // A short database lock makes simultaneous check-and-save operations wait.
    // Every application write takes this same lock, including maintenance edits.
    $lock = one("SELECT GET_LOCK('pikolbook_write', 5) AS acquired");
    if ((int) $lock['acquired'] !== 1) {
        throw new Exception('Another change is being saved. Please try again.');
    }
    mysqli_begin_transaction($db);
    $write_started = true;
}
function end_write($success)
{
    global $db, $write_started;
    if (empty($write_started)) {
        return;
    }
    if ($success) {
        mysqli_commit($db);
    } else {
        mysqli_rollback($db);
    }
    query("SELECT RELEASE_LOCK('pikolbook_write')");
    $write_started = false;
}
function available($court_id, $date, $hour, $duration = 1, $ignore_id = 0)
{
    $court = one('SELECT * FROM courts WHERE id = ? AND is_active = 1', 'i', [$court_id]);
    if (!$court || !valid_date($date) || $hour < 9 || $hour > 23 || $duration < 1 || $hour + $duration > 24) {
        return false;
    }
    $start = $date . ' ' . sprintf('%02d:00:00', $hour);
    $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $duration . ' hours'));
    if ($start <= date('Y-m-d H:i:s')) {
        return false;
    }
    $open = $date . ' ' . $court['opening_time'];
    $close = $date . ' ' . $court['closing_time'];
    if ($court['closing_time'] <= $court['opening_time']) {
        $close = date('Y-m-d H:i:s', strtotime($close . ' +1 day'));
    }
    if ($start < $open || $end > $close) {
        return false;
    }
    $block = one(
        'SELECT id FROM court_blocks WHERE court_id = ? AND start_at < ? AND end_at > ? LIMIT 1',
        'iss',
        [$court_id, $end, $start],
    );
    if ($block) {
        return false;
    }
    // General overlap test also respects any older, longer bookings in the database.
    $bookings = rows(
        "SELECT * FROM bookings WHERE court_id = ? AND status <> 'cancelled' AND id <> ? AND booking_date BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND ?",
        'iiss',
        [$court_id, $ignore_id, $date, $date],
    );
    foreach ($bookings as $booking) {
        if (
            $booking['booking_date'] . ' ' . $booking['start_time'] < $end &&
            booking_end($booking) > $start
        ) {
            return false;
        }
    }
    return true;
}
function field($label, $name, $value = '', $type = 'text', $required = true, $max = 100)
{
    echo '<div class="field"><label for="' . e($name) . '">' . e($label) . required_mark($required) . '</label>';
    $input = '<input id="' .
        e($name) .
        '" name="' .
        e($name) .
        '" type="' .
        e($type) .
        '" value="' .
        e($value) .
        '" maxlength="' .
        $max .
        '" ' .
        ($required ? 'required' : '') .
        '>';
    if ($type === 'password') {
        echo '<div class="password-input">' .
            $input .
            '<button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false"><svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a16.8 16.8 0 0 1-3.2 4.1M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 4.1-.8"></path></svg></button></div></div>';
    } else {
        echo $input . '</div>';
    }
}
function required_mark($required = true)
{
    return $required ? ' <span class="required-mark" aria-hidden="true">*</span>' : '';
}
function action_icon($name)
{
    $paths = [
        'edit' => '<path d="m14 5 5 5M4 20l4.2-.8L19 8.4 15.6 5 4.8 15.8 4 20Z"></path>',
        'cancel' => '<path d="M18 6 6 18M6 6l12 12"></path>',
        'delete' => '<path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"></path>',
        'complete' => '<path d="m5 12 4 4L19 6"></path>',
        'clear' => '<path d="M18 6 6 18M6 6l12 12"></path>',
        'filter' => '<path d="M4 6h16M7 12h10m-7 6h4"></path>',
    ];
    return isset($paths[$name])
        ? '<svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' .
            $paths[$name] .
            '</svg>'
        : '';
}
function error_message($error)
{
    if ($error instanceof mysqli_sql_exception) {
        error_log($error->getMessage());
        return 'This change could not be saved. Check for a duplicate email or court name and try again.';
    }
    return $error->getMessage();
}

// Recover safe form entries when validation fails. Passwords are never kept here.
function fv($name, $fallback = '')
{
    return $_SESSION['form_draft'][$name] ?? $fallback;
}
function delete_button($file, $id, $message)
{
    echo '<form method="post" action="' . e($file) . '" data-confirm="' . e($message) . '">';
    csrf_field();
    echo '<input type="hidden" name="id" value="' .
        (int) $id .
        '"><input type="hidden" name="action" value="delete"><button class="light delete-button">' .
        action_icon('delete') .
        ' Delete</button></form>';
}
function fail_form($error, $path, $id = 0)
{
    end_write(false);
    $_SESSION['form_draft'] = $_POST;
    unset(
        $_SESSION['form_draft']['csrf'],
        $_SESSION['form_draft']['password'],
        $_SESSION['form_draft']['verified'],
        $_SESSION['form_draft']['current_password'],
        $_SESSION['form_draft']['new_password'],
        $_SESSION['form_draft']['confirm_password'],
    );
    $_SESSION['form_error'] = error_message($error);
    go($path . ($id ? '?edit=' . $id : '?new=1'));
}

// Read optional monthly-block and cancellation details with the booking.
function booking_record($id)
{
    return one('SELECT b.*, d.series_code, d.cancelled_at, d.cancel_reason,
        d.refund_due, d.refunded_amount, d.refunded_at, d.refund_reference
        FROM bookings b LEFT JOIN booking_details d ON d.booking_id=b.id WHERE b.id=?', 'i', [$id]);
}
function repeat_dates($first, $repeat)
{
    if (!valid_date($first)) {
        throw new Exception('Choose a valid booking date.');
    }
    if (!in_array($repeat, ['once', 'monthly'], true)) {
        throw new Exception('Choose a valid booking type.');
    }
    $dates = [$first];
    if ($repeat === 'monthly') {
        $next = date('Y-m-d', strtotime($first . ' +7 days'));
        while (substr($next, 0, 7) === substr($first, 0, 7)) {
            $dates[] = $next;
            $next = date('Y-m-d', strtotime($next . ' +7 days'));
        }
    }
    return $dates;
}
function refund_quote($booking, $now = null)
{
    $now = $now ?? time();
    $hours = (strtotime($booking['booking_date'] . ' ' . $booking['start_time']) - $now) / 3600;
    $percent = $hours >= 24 ? 100 : ($hours >= 3 ? 50 : 0);
    $paid = $booking['payment_status'] === 'paid' ? (float) $booking['amount_paid'] : 0;
    return ['percent' => $percent, 'amount' => round($paid * $percent / 100, 2)];
}
function net_received($booking)
{
    if ($booking['payment_status'] !== 'paid') return 0;
    return max(0, (float) $booking['amount_paid'] - (float) ($booking['refunded_amount'] ?? 0));
}
// Each recurring date is a real booking with its own share of the payment.
function cancellation_targets($booking, $scope)
{
    if ($scope === 'series' && !empty($booking['series_code'])) {
        return rows("SELECT b.* FROM bookings b JOIN booking_details d ON d.booking_id=b.id
            WHERE d.series_code=? AND b.status='confirmed' AND CONCAT(b.booking_date,' ',b.start_time)>?
            ORDER BY b.booking_date", 'ss', [$booking['series_code'], date('Y-m-d H:i:s')]);
    }
    return [$booking];
}
// Calendar availability uses already-loaded records, so it does not query per cell.
function calendar_slot($court, $date, $hour, $bookings, $blocks)
{
    $start = $date . ' ' . sprintf('%02d:00:00', $hour);
    $end = date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));
    foreach ($bookings as $b) {
        if ((int) $b['court_id'] === (int) $court['id'] && $b['status'] !== 'cancelled' &&
            $b['booking_date'] . ' ' . $b['start_time'] < $end && booking_end($b) > $start) {
            return ['state' => 'booked', 'label' => $b['client_name'], 'id' => $b['id']];
        }
    }
    if (!$court['is_active']) return ['state' => 'closed', 'label' => 'Archived'];
    foreach ($blocks as $block) {
        if ((int) $block['court_id'] === (int) $court['id'] && $block['start_at'] < $end && $block['end_at'] > $start) {
            return ['state' => 'maintenance', 'label' => 'Maintenance'];
        }
    }
    $open = $date . ' ' . $court['opening_time'];
    $close = $date . ' ' . $court['closing_time'];
    if ($court['closing_time'] <= $court['opening_time']) $close = date('Y-m-d H:i:s', strtotime($close . ' +1 day'));
    if ($start < $open || $end > $close) return ['state' => 'closed', 'label' => 'Closed'];
    if ($start <= date('Y-m-d H:i:s')) return ['state' => 'past', 'label' => 'Past'];
    return ['state' => 'free', 'label' => 'Available'];
}

function payment_label($booking)
{
    $refunded = (float) ($booking['refunded_amount'] ?? 0);
    $due = (float) ($booking['refund_due'] ?? 0);
    if ($due > $refunded) return 'Refund pending';
    if ($refunded > 0) return $refunded >= (float) $booking['amount_paid'] ? 'Refunded' : 'Partially refunded';
    return ucfirst($booking['payment_status']);
}
