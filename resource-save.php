<?php
require 'functions.php';
$user = signed_in();
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
    if ($id && ($deleting || $maintenance || !$active)) {
        $future = rows(
            "SELECT * FROM bookings WHERE court_id=? AND status='confirmed' AND booking_date>=DATE_SUB(?, INTERVAL 1 DAY)",
            'is',
            [$id, date('Y-m-d')],
        );
        foreach ($future as $booking) {
            if (booking_end($booking) > date('Y-m-d H:i:s')) {
                throw new Exception(
                    'Cancel or reschedule upcoming bookings before archiving this court or setting maintenance.',
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
        $name = text_input('name', 100);
        $description = text_input('description', 1000, false);
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
                "INSERT INTO court_blocks(court_id,start_at,end_at,reason) VALUES(?,?,'2099-12-31 23:59:59','Court maintenance (master override)')",
                'is',
                [$id, date('Y-m-d H:i:s')],
            );
        }
    }
    end_write(true);
    notice($deleting ? 'Court removed or archived.' : 'Court saved.');
    go('resources.php');
} catch (Exception $error) {
    fail_form($error, 'resources.php', (int) ($_POST['id'] ?? 0));
}
