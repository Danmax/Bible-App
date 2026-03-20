<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? null;
$activePage = $activePage ?? '';
$flash = pull_flash();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(page_title($pageTitle)); ?></title>
    <meta name="description" content="A warm, mobile-first Bible study app with bookmarks, planner tools, and community events.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bree+Serif&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')); ?>">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="container header-row">
                <a class="brand" href="<?= e(app_url('index.php')); ?>">
                    <span class="brand-mark">WT</span>
                    <span>
                        <strong><?= e(APP_NAME); ?></strong>
                        <small>Study, celebrate, and gather</small>
                    </span>
                </a>

                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
                    Menu
                </button>

                <nav class="primary-nav" id="primary-nav">
                    <a class="<?= $activePage === 'home' ? 'is-active' : ''; ?>" href="<?= e(app_url('index.php')); ?>">Home</a>
                    <a class="<?= $activePage === 'bible' ? 'is-active' : ''; ?>" href="<?= e(app_url('bible.php')); ?>">Bible</a>
                    <a class="<?= $activePage === 'community' ? 'is-active' : ''; ?>" href="<?= e(app_url('community.php')); ?>">Community</a>
                    <a class="<?= $activePage === 'planner' ? 'is-active' : ''; ?>" href="<?= e(app_url('planner.php')); ?>">Planner</a>
                    <?php if (is_logged_in()): ?>
                        <a class="<?= $activePage === 'dashboard' ? 'is-active' : ''; ?>" href="<?= e(app_url('dashboard.php')); ?>">Dashboard</a>
                        <a class="<?= $activePage === 'bookmarks' ? 'is-active' : ''; ?>" href="<?= e(app_url('bookmarks.php')); ?>">Saved</a>
                        <a class="<?= $activePage === 'notes' ? 'is-active' : ''; ?>" href="<?= e(app_url('notes.php')); ?>">Notes</a>
                        <a class="<?= $activePage === 'profile' ? 'is-active' : ''; ?>" href="<?= e(app_url('profile.php')); ?>">Profile</a>
                        <a class="nav-action" href="<?= e(app_url('logout.php')); ?>">Logout</a>
                    <?php else: ?>
                        <a href="<?= e(app_url('login.php')); ?>">Login</a>
                        <a class="nav-action" href="<?= e(app_url('register.php')); ?>">Create Account</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main>
            <?php if ($flash): ?>
                <div class="container">
                    <div class="flash flash-<?= e($flash['type']); ?>">
                        <?= e($flash['message']); ?>
                    </div>
                </div>
            <?php endif; ?>
