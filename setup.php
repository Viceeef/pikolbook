<?php
require 'functions.php';
// Setup cannot run again once any user exists. It never replaces local accounts.
if ((int) one('SELECT COUNT(*) AS total FROM users')['total'] > 0) {
    exit('Accounts already exist. Use index.php. Setup does not overwrite your accounts.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    try {
        $email = email_input(true);
        $password = text_input('admin_password', 72);
        $mwf = text_input('mwf_password', 72);
        $tths = text_input('tths_password', 72);
        foreach ([$password, $mwf, $tths] as $value) {
            if (strlen($value) < 8) {
                throw new Exception('Use at least 8 characters for every password.');
            }
        }
        begin_write();
        if ((int) one('SELECT COUNT(*) AS total FROM users')['total'] > 0) {
            throw new Exception('Setup has already finished in another window.');
        }
        $accounts = [
            ['Administrator', $email, $password, 'admin'],
            ['MWF Staff', 'mwf@pikolbook.test', $mwf, 'staff'],
            ['TTHS Staff', 'tths@pikolbook.test', $tths, 'staff'],
        ];
        foreach ($accounts as $account) {
            query('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)', 'ssss', [
                $account[0],
                $account[1],
                password_hash($account[2], PASSWORD_DEFAULT),
                $account[3],
            ]);
        }
        for ($number = 1; $number <= 4; $number++) {
            if (!one('SELECT id FROM courts WHERE name = ?', 's', ['Court ' . $number])) {
                query(
                    "INSERT INTO courts (name, description, hourly_rate, opening_time, closing_time) VALUES (?, 'Pickleball court', 300, '09:00:00', '00:00:00')",
                    's',
                    ['Court ' . $number],
                );
            }
        }
        end_write(true);
        notice('Accounts and courts are ready. Log in with the credentials you just chose.');
        go('index.php');
    } catch (Exception $error_object) {
        end_write(false);
        $error = error_message($error_object);
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>First setup | Pikolbook</title><link rel="stylesheet" href="style.css"></head>
<body><main class="content"><section class="card login-box">
<h1>First setup</h1><p>Create your Admin account and the two assigned staff accounts. Choose and keep your passwords.</p>
<?php if ($error): ?><p class="message error"><?= e($error) ?></p><?php endif; ?>
<form method="post"><?php csrf_field(); ?>
<?php field('Admin email', 'email', 'admin@pikolbook.test', 'email', true, 150); ?>
<?php field('Admin password (8–72 characters)', 'admin_password', '', 'password', true, 72); ?>
<?php field('MWF Staff password (mwf@pikolbook.test)', 'mwf_password', '', 'password', true, 72); ?>
<?php field('TTHS Staff password (tths@pikolbook.test)', 'tths_password', '', 'password', true, 72); ?>
<button type="submit">Create accounts & courts</button>
</form><p class="note">Court 1–4 · ₱300/hour · 9 AM–12 midnight</p></section></main></body></html>
