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
            <?php
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
            foreach ($links as $path => $label): ?>
                <a href="<?= e($path) ?>" <?= $page === $path
    ? 'class="active" aria-current="page"'
    : '' ?>><?= e($label) ?></a>
            <?php endforeach;
            ?>
        </nav>
        <div class="aside-end">
            <form action="logout.php" method="post"><?php csrf_field(); ?><button class="light">Log out</button></form>
            <p>9 AM – midnight<br>₱300 per one-hour slot</p>
        </div>
    </aside>
    <div class="main-area">
        <header class="topbar"><strong>PIKOLBOOK <small>/ COURT BOOKING SYSTEM</small></strong><span class="tag"><?= e(
            ucfirst($user['role']),
        ) ?></span><span>Welcome back, <?= e($user['full_name']) ?></span></header>
        <main class="content">
            <?php if (!empty($_SESSION['message'])): ?>
                <p class="notice" role="status"><?= e($_SESSION['message']) ?></p>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
