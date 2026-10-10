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
        if (($_POST['action'] ?? '') === 'delete') {
            if (!$id) {
                throw new Exception('Choose a staff account.');
            }
            if (
                one('SELECT id FROM bookings WHERE created_by=? OR payment_updated_by=? LIMIT 1', 'ii', [
                    $id,
                    $id,
                ])
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
            $phone = text_input('phone', 30);
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 8 || strlen($password) > 72) {
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
                if ($password !== '') {
                    query('UPDATE users SET password_hash=? WHERE id=?', 'si', [
                        password_hash($password, PASSWORD_DEFAULT),
                        $id,
                    ]);
                }
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
) ?></td><td><?= e($account['phone']) ?></td><td><?= $account['is_active']
    ? 'Active'
    : 'Deactivated' ?></td><td><div class="row-actions"><a class="button light" href="staff.php?edit=<?= (int) $account[
    'id'
] ?>"><?= action_icon('edit') ?>Edit</a><?php delete_button(
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
field('Phone', 'phone', fv('phone', $edit['phone'] ?? ''), 'tel', true, 30);
field(
    'Password (8–72 characters)',
    'password',
    '',
    'password',
    true,
    72,
);
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
<?php page_footer(); ?>
