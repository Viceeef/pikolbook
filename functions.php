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
    if (!$user) {
        unset($_SESSION['user_id']);
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
function email_input($required = false)
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
    echo '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label>';
    echo '<input id="' .
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
        '></div>';
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
        '"><input type="hidden" name="action" value="delete"><button class="light delete-button">Delete</button></form>';
}
function fail_form($error, $path, $id = 0)
{
    end_write(false);
    $_SESSION['form_draft'] = $_POST;
    unset(
        $_SESSION['form_draft']['csrf'],
        $_SESSION['form_draft']['password'],
        $_SESSION['form_draft']['verified'],
    );
    notice(error_message($error));
    go($path . ($id ? '?edit=' . $id : '?new=1'));
}
