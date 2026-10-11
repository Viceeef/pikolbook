<?php
require 'functions.php';
$user = signed_in();
// POST: validate and save this page's form. GET: show the page below.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_only($user);
    check_post();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        begin_write();
        if ($id && !one("SELECT id FROM users WHERE id=? AND role='staff'", 'i', [$id])) {
            throw new Exception('Staff account not found.');
        }
        if (($_POST['action'] ?? '') === 'reset_password') {
            $current = one('SELECT password_hash FROM users WHERE id=?', 'i', [$user['id']]);
            if (!password_verify($_POST['current_password'] ?? '', $current['password_hash'])) {
                throw new Exception('Your Admin password is incorrect.');
            }
            $new_password = $_POST['new_password'] ?? '';
            if (!$id || strlen($new_password) < 8 || strlen($new_password) > 72 || $new_password !== ($_POST['confirm_password'] ?? '')) {
                throw new Exception('Enter matching passwords of 8–72 characters.');
            }
            query('UPDATE users SET password_hash=? WHERE id=?', 'si', [password_hash($new_password, PASSWORD_DEFAULT), $id]);
        } elseif (($_POST['action'] ?? '') === 'delete') {
            if (!$id) {
                throw new Exception('Choose a staff account.');
            }
            if (
                one('SELECT id FROM bookings WHERE created_by=? OR payment_updated_by=? LIMIT 1', 'ii', [
                    $id,
                    $id,
                ]) || one('SELECT booking_id FROM booking_details WHERE handled_by=? LIMIT 1', 'i', [$id])
            ) {
                query('UPDATE users SET is_active=0 WHERE id=?', 'i', [$id]);
            } else {
                query('DELETE FROM users WHERE id=?', 'i', [$id]);
            }
        } else {
            if (!isset($_POST['is_active']) || !in_array($_POST['is_active'], ['0', '1'], true)) {
                throw new Exception('Choose a staff account status.');
            }
            $name = text_input('full_name', 100);
            $email = email_input(true);
            $phone = phone_input();
            $password = $_POST['password'] ?? '';
            if (!$id && (strlen($password) < 8 || strlen($password) > 72)) {
                throw new Exception('Password must contain 8–72 characters.');
            }
            $active = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;
            if ($id) {
                query('UPDATE users SET full_name=?,email=?,phone=?,is_active=? WHERE id=?', 'sssii', [
                    $name,
                    $email,
                    $phone,
                    $active,
                    $id,
                ]);

            } else {
                query(
                    "INSERT INTO users(full_name,email,phone,password_hash,role,is_active) VALUES(?,?,?,?,'staff',?)",
                    'ssssi',
                    [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $active],
                );
            }
        }
        end_write(true);
        notice('Staff account updated.');
        go('staff.php');
    } catch (Exception $error) {
        if (($_POST['action'] ?? '') === 'reset_password') {
            end_write(false);
            $_SESSION['action_error'] = error_message($error);
            go('staff.php?reset=' . $id);
        }
        fail_form($error, 'staff.php', $id);
    }
}

admin_only($user);
$title = 'Staff management';
$page = 'staff.php';
$staff = rows("SELECT * FROM users WHERE role='staff' ORDER BY id");
$edit = !empty($_GET['edit'])
    ? one("SELECT * FROM users WHERE id=? AND role='staff'", 'i', [(int) $_GET['edit']])
    : null;
require_once 'layout.php';
page_header($title, $page, $user);
?>
<div class="heading"><div><h1>Staff management</h1><p class="muted">Admin-created accounts.</p></div><button data-open="staff-dialog">+ New staff</button></div>
<div class="field"><label for="table-search">Search staff</label><input id="table-search" type="search" data-search="records"></div>
<section class="card"><div class="table-wrap"><table id="records"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($staff as $account): ?><tr><td><?= e($account['full_name']) ?></td><td><?= e(
    $account['email'],
) ?></td><td><?= e(phone_display($account['phone'])) ?></td><td><?= $account['is_active']
    ? 'Active'
    : 'Deactivated' ?></td><td><div class="row-actions"><a class="button light" href="staff.php?edit=<?= (int) $account[
    'id'
] ?>"><?= action_icon('edit') ?>Edit</a><a class="button light" href="staff.php?reset=<?= (int) $account['id'] ?>">Reset password</a><?php delete_button(
    'staff.php',
    $account['id'],
    'Delete this staff account? Referenced accounts will be deactivated to preserve history.',
); ?></div></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<dialog id="staff-dialog" <?= $edit || isset($_GET['new']) ? 'data-auto-open' : '' ?>>
<div class="heading"><h2><?= $edit
    ? 'Edit staff'
    : 'New staff' ?></h2><button type="button" class="light" data-close="staff-dialog">Close</button></div>
<form method="post" action="staff.php"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) ($edit[
    'id'
] ?? 0) ?>">
<?php if (!empty($_SESSION['form_error'])): ?><p class="error" role="alert"><?= e($_SESSION['form_error']) ?></p><?php unset($_SESSION['form_error']); endif; ?>
<?php
field('Full name', 'full_name', fv('full_name', $edit['full_name'] ?? ''));
field('Email', 'email', fv('email', $edit['email'] ?? ''), 'email', true, 150);
phone_field(fv('phone', $edit['phone'] ?? ''));
if (!$edit) {
    field('Initial password (8–72 characters)', 'password', '', 'password', true, 72);
} else {
    echo '<p class="note">Staff can change their own password in Edit profile. Saving these details keeps their password.</p>';
}

?>
<div class="field"><label for="is_active">Status<?= required_mark() ?></label><select id="is_active" name="is_active" required><option value="1">Active</option><option value="0" <?= fv(
    'is_active',
    $edit['is_active'] ?? 1,
) == 0
    ? 'selected'
    : '' ?>>Deactivated</option></select></div>
<div class="actions"><button type="button" class="light" data-close="staff-dialog"><?= action_icon(
    'cancel',
) ?>Cancel</button><button>Save staff</button></div></form></dialog>
<?php
$reset = !empty($_GET['reset']) ? one("SELECT * FROM users WHERE id=? AND role='staff'", 'i', [(int) $_GET['reset']]) : null;
if ($reset): ?>
<dialog id="reset-password-dialog" data-auto-open><h2>Reset staff password</h2><p><?= e($reset['full_name']) ?> · <?= e($reset['email']) ?></p>
<form action="staff.php" method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int) $reset['id'] ?>">
<?php field('Your Admin password', 'current_password', '', 'password', true, 72); field('New staff password', 'new_password', '', 'password', true, 72); field('Confirm new password', 'confirm_password', '', 'password', true, 72); ?>
<div class="actions"><a class="button light" href="staff.php">Cancel</a><button>Reset password</button></div></form></dialog>
<?php endif; page_footer(); ?>
