<?php
require 'functions.php';
$user = signed_in();
// POST: validate and save this page's form. GET: show the page below.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        begin_write();
        if (!$id || !one('SELECT id FROM clients WHERE id=?', 'i', [$id])) {
            throw new Exception('Choose an existing client. New clients can only be added with a booking.');
        }
        if (($_POST['action'] ?? '') === 'delete') {
            if (one('SELECT id FROM bookings WHERE client_id=? LIMIT 1', 'i', [$id])) {
                query('UPDATE clients SET is_active=0 WHERE id=?', 'i', [$id]);
            } else {
                query('DELETE FROM clients WHERE id=?', 'i', [$id]);
            }
        } else {
            if (!isset($_POST['is_active']) || !in_array($_POST['is_active'], ['0', '1'], true)) {
                throw new Exception('Choose a client status.');
            }
            $name = text_input('full_name', 100);
            $phone = text_input('phone', 30);
            $email = email_input(true);
            query('UPDATE clients SET full_name=?,phone=?,email=?,is_active=? WHERE id=?', 'sssii', [
                $name,
                $phone,
                $email,
                ($_POST['is_active'] ?? '1') === '1' ? 1 : 0,
                $id,
            ]);
        }
        end_write(true);
        notice('Client updated or removed.');
        go('clients.php');
    } catch (Exception $error) {
        fail_form($error, 'clients.php', $id);
    }
}

$title = 'Client management';
$page = 'clients.php';
$clients = rows('SELECT * FROM clients ORDER BY full_name');
$edit = !empty($_GET['edit']) ? one('SELECT * FROM clients WHERE id=?', 'i', [(int) $_GET['edit']]) : null;
require_once 'layout.php';
page_header($title, $page, $user);
?>
<h1>Client management</h1><p class="muted">Edit or delete clients here. New clients are added only with a new booking.</p>
<div class="field"><label for="table-search">Search clients</label><input id="table-search" type="search" data-search="records"></div>
<section class="card"><div class="table-wrap"><table id="records"><thead><tr><th>ID</th><th>Full name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($clients as $client): ?><tr><td><?= (int) $client['id'] ?></td><td><?= e(
    $client['full_name'],
) ?></td><td><?= e($client['email']) ?></td><td><?= e($client['phone']) ?></td><td><?= $client['is_active']
    ? 'Active'
    : 'Archived' ?></td><td><div class="row-actions"><a class="button light" href="clients.php?edit=<?= (int) $client[
    'id'
] ?>"><?= action_icon('edit') ?>Edit</a><?php delete_button(
    'clients.php',
    $client['id'],
    'Delete this client? Clients linked to bookings will be archived to keep history.',
); ?></div></td></tr><?php endforeach; ?>
<?php if (
    !$clients
): ?><tr data-empty-state><td colspan="6">No clients yet. Create a booking to add one.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php
if ($edit): ?>
<dialog id="client-dialog" data-auto-open><div class="heading"><h2>Edit client</h2><button type="button" class="light" data-close="client-dialog">Close</button></div>
<form method="post" action="clients.php"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $edit[
    'id'
] ?>">
<?php if (!empty($_SESSION['form_error'])): ?><p class="error" role="alert"><?= e($_SESSION['form_error']) ?></p><?php unset($_SESSION['form_error']); endif; ?>
<?php
field('Full name', 'full_name', fv('full_name', $edit['full_name']));
field('Phone', 'phone', fv('phone', $edit['phone']), 'tel', true, 30);
field('Email', 'email', fv('email', $edit['email']), 'email', true, 150);
?>
<div class="field"><label for="is_active">Status<?= required_mark() ?></label><select id="is_active" name="is_active" required><option value="1">Active</option><option value="0" <?= fv(
    'is_active',
    $edit['is_active'],
) == 0
    ? 'selected'
    : '' ?>>Archived</option></select></div>
<div class="actions"><button type="button" class="light" data-close="client-dialog"><?= action_icon(
    'cancel',
) ?>Cancel</button><button>Save client</button></div></form></dialog>
<?php endif;
page_footer();
 ?>
