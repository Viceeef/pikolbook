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
        $court = $id ? one('SELECT * FROM courts WHERE id=?', 'i', [$id]) : null;
        if ($id && !$court) {
            throw new Exception('Court not found.');
        }
        $deleting = ($_POST['action'] ?? '') === 'delete';
        $maintenance = ($_POST['maintenance'] ?? '0') === '1';
        $active = ($_POST['is_active'] ?? '1') === '1';
        $maintenance_start = null;
        $maintenance_end = null;
        if (!$deleting && $maintenance) {
            $start_value = $_POST['maintenance_start'] ?? '';
            $end_value = $_POST['maintenance_end'] ?? '';
            $start_date = DateTime::createFromFormat('!Y-m-d\TH:i', $start_value);
            $end_date = DateTime::createFromFormat('!Y-m-d\TH:i', $end_value);
            if (
                !$start_date ||
                !$end_date ||
                $start_date->format('Y-m-d\TH:i') !== $start_value ||
                $end_date->format('Y-m-d\TH:i') !== $end_value
            ) {
                throw new Exception('Choose valid start and end dates for maintenance.');
            }
            if ($end_date <= $start_date) {
                throw new Exception('The maintenance end date and time must be after the start.');
            }
            $maintenance_start = $start_date->format('Y-m-d H:i:s');
            $maintenance_end = $end_date->format('Y-m-d H:i:s');
        }
        if ($id && ($deleting || !$active || $maintenance)) {
            $future = rows(
                "SELECT * FROM bookings WHERE court_id=? AND status='confirmed' AND booking_date>=DATE_SUB(?, INTERVAL 1 DAY)",
                'is',
                [$id, date('Y-m-d')],
            );
            foreach ($future as $booking) {
                $booking_start = $booking['booking_date'] . ' ' . $booking['start_time'];
                $overlaps_maintenance =
                    $maintenance &&
                    $booking_start < $maintenance_end &&
                    booking_end($booking) > $maintenance_start;
                if (
                    booking_end($booking) > date('Y-m-d H:i:s') &&
                    ($deleting || !$active || $overlaps_maintenance)
                ) {
                    throw new Exception(
                        $maintenance && $active
                            ? 'Cancel or reschedule bookings that overlap the maintenance period.'
                            : 'Cancel or reschedule upcoming bookings before archiving this court.',
                    );
                }
            }
        }
        if ($deleting) {
            if (one('SELECT id FROM bookings WHERE court_id=? LIMIT 1', 'i', [$id])) {
                query('UPDATE courts SET is_active=0 WHERE id=?', 'i', [$id]);
            } else {
                query('DELETE FROM court_blocks WHERE court_id=?', 'i', [$id]);
                query('DELETE FROM courts WHERE id=?', 'i', [$id]);
            }
        } else {
            if (
                !isset($_POST['maintenance'], $_POST['is_active']) ||
                !in_array($_POST['maintenance'], ['0', '1'], true) ||
                !in_array($_POST['is_active'], ['0', '1'], true)
            ) {
                throw new Exception('Choose a master override and record status.');
            }
            $name = text_input('name', 100);
            $description = text_input('description', 1000);
            if ($id) {
                query('UPDATE courts SET name=?,description=?,is_active=? WHERE id=?', 'ssii', [
                    $name,
                    $description,
                    (int) $active,
                    $id,
                ]);
            } else {
                query(
                    "INSERT INTO courts(name,description,hourly_rate,opening_time,closing_time,is_active) VALUES(?,?,300,'09:00:00','00:00:00',?)",
                    'ssi',
                    [$name, $description, (int) $active],
                );
                $id = mysqli_insert_id($db);
            }
            query(
                "DELETE FROM court_blocks WHERE court_id=? AND reason='Court maintenance (master override)'",
                'i',
                [$id],
            );
            if ($maintenance) {
                query(
                    "INSERT INTO court_blocks(court_id,start_at,end_at,reason) VALUES(?,?,?,'Court maintenance (master override)')",
                    'iss',
                    [$id, $maintenance_start, $maintenance_end],
                );
            }
        }
        end_write(true);
        notice($deleting ? 'Court removed or archived.' : 'Court saved.');
        go('resources.php');
    } catch (Exception $error) {
        fail_form($error, 'resources.php', (int) ($_POST['id'] ?? 0));
    }
}

$title = 'Court management';
$page = 'resources.php';
$now = date('Y-m-d H:i:s');
$courts = rows(
    'SELECT c.*, EXISTS(SELECT 1 FROM court_blocks cb WHERE cb.court_id=c.id AND cb.start_at<=? AND cb.end_at>?) AS maintenance FROM courts c ORDER BY c.id',
    'ss',
    [$now, $now],
);
$edit = null;
if (!empty($_GET['edit'])) {
    admin_only($user);
    $edit = one('SELECT * FROM courts WHERE id=?', 'i', [(int) $_GET['edit']]);
}
require_once 'layout.php';
page_header($title, $page, $user);
?>
<div class="heading"><div><h1>Court management</h1><p class="muted">Courts, opening hours and maintenance.</p></div><?php if (
    $user['role'] === 'admin'
): ?><button data-open="resource-dialog">+ New resource</button><?php endif; ?></div>
<div class="field"><label for="table-search">Search courts</label><input id="table-search" type="search" data-search="records"></div>
<section class="card"><div class="table-wrap"><table id="records"><thead><tr><th>ID</th><th>Court</th><th>Description</th><th>Rate</th><th>Hours</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($courts as $court): ?><tr><td><?= (int) $court['id'] ?></td><td><?= e(
    $court['name'],
) ?></td><td><?= e($court['description']) ?></td><td><?= e(
    money($court['hourly_rate']),
) ?></td><td>9 AM – midnight</td><td><?= !$court['is_active']
    ? 'Archived'
    : ($court['maintenance']
        ? 'Maintenance'
        : 'Available') ?></td><td>
<?php if (
    $user['role'] === 'admin'
): ?><div class="row-actions"><a class="button light" href="resources.php?edit=<?= (int) $court[
    'id'
] ?>"><?= action_icon('edit') ?>Edit</a><?php delete_button(
    'resources.php',
    $court['id'],
    'Delete this court? Referenced courts are archived. Cancel or reschedule upcoming bookings first.',
); ?></div><?php else: ?>View only<?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php
if ($user['role'] === 'admin'):
    $maintenance = $edit
        ? one(
            "SELECT * FROM court_blocks WHERE court_id=? AND reason='Court maintenance (master override)' ORDER BY id DESC LIMIT 1",
            'i',
            [$edit['id']],
        )
        : null; ?>
<dialog id="resource-dialog" <?= $edit || isset($_GET['new']) ? 'data-auto-open' : '' ?>>
<div class="heading"><h2><?= $edit
    ? 'Edit resource'
    : 'New resource' ?></h2><button type="button" class="light" data-close="resource-dialog">Close</button></div>
<form method="post" action="resources.php"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) ($edit[
    'id'
] ?? 0) ?>">
<?php if (!empty($_SESSION['form_error'])): ?><p class="error" role="alert"><?= e($_SESSION['form_error']) ?></p><?php unset($_SESSION['form_error']); endif; ?>
<?php
$maintenance_enabled = fv('maintenance', $maintenance ? 1 : 0) == 1;
field('Court name', 'name', fv('name', $edit['name'] ?? ''));
field('Description', 'description', fv('description', $edit['description'] ?? ''), 'text', true, 1000);
?>
<div class="field"><label for="maintenance">Master override<?= required_mark() ?></label><select name="maintenance" id="maintenance" required><option value="0">Available</option><option value="1" <?= fv(
    'maintenance',
    $maintenance ? 1 : 0,
) == 1
    ? 'selected'
    : '' ?>>Under maintenance</option></select></div>
<div id="maintenance-period" class="form-grid" <?= $maintenance_enabled ? '' : 'hidden' ?>>
<?php
$maintenance_start_value = fv(
    'maintenance_start',
    $maintenance ? date('Y-m-d\TH:i', strtotime($maintenance['start_at'])) : '',
);
$maintenance_end_value = fv(
    'maintenance_end',
    $maintenance ? date('Y-m-d\TH:i', strtotime($maintenance['end_at'])) : '',
);
?>
    <div class="field">
        <label for="maintenance_start">Maintenance starts<?= required_mark() ?></label>
        <input type="datetime-local" id="maintenance_start" name="maintenance_start" value="<?= e(
            $maintenance_start_value,
        ) ?>" <?= $maintenance_enabled ? 'required' : '' ?>>
    </div>
    <div class="field">
        <label for="maintenance_end">Maintenance ends<?= required_mark() ?></label>
        <input type="datetime-local" id="maintenance_end" name="maintenance_end" value="<?= e(
            $maintenance_end_value,
        ) ?>" <?= $maintenance_enabled ? 'required' : '' ?>>
    </div>
</div>
<div class="field"><label for="is_active">Record status<?= required_mark() ?></label><select name="is_active" id="is_active" required><option value="1">Active</option><option value="0" <?= fv(
    'is_active',
    $edit['is_active'] ?? 1,
) == 0
    ? 'selected'
    : '' ?>>Archived</option></select></div>
<p>₱300 per hour · 9 AM to midnight</p><p class="note">Maintenance blocks new bookings. Cancel or reschedule affected bookings before blocking this court.</p>
<div class="actions"><button type="button" class="light" data-close="resource-dialog"><?= action_icon(
    'cancel',
) ?>Cancel</button><button>Save resource</button></div></form></dialog>
<?php
endif;
page_footer();

?>
