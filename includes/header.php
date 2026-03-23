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
    <meta name="description" content="Good News Bible is a warm, mobile-first Bible study app with bookmarks, planner tools, and community events.">
    <meta name="application-name" content="<?= e(APP_NAME); ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME); ?>">
    <meta name="theme-color" content="#d7a035">
    <link rel="icon" type="image/x-icon" href="<?= e(asset_url('assets/icons/favicon.ico')); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset_url('assets/icons/favicon-32x32.png')); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset_url('assets/icons/favicon-16x16.png')); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset_url('assets/icons/apple-touch-icon.png')); ?>">
    <link rel="manifest" href="<?= e(asset_url('assets/icons/site.webmanifest')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bree+Serif&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')); ?>">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="container header-row">
                <a class="brand" href="<?= e(app_url('index.php')); ?>">
                    <span class="brand-mark">STWB</span>
                    <span>
                        <strong><?= e(APP_NAME); ?></strong>
                        <small>Study The Word Bible</small>
                    </span>
                </a>

                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
                    Menu
                </button>

                <nav class="primary-nav" id="primary-nav">
                    <a class="<?= $activePage === 'home' ? 'is-active' : ''; ?>" href="<?= e(app_url('index.php')); ?>">Home</a>
                    <a class="<?= $activePage === 'good-news' ? 'is-active' : ''; ?>" href="<?= e(app_url('good-news.php')); ?>">Good News</a>
                    <a class="<?= $activePage === 'bible' ? 'is-active' : ''; ?>" href="<?= e(app_url('bible.php')); ?>">Bible</a>
                    <a class="<?= $activePage === 'community' ? 'is-active' : ''; ?>" href="<?= e(app_url('community.php')); ?>">Community</a>
                    <?php
                    $morePages = ['planner', 'dashboard', 'friends', 'bookmarks', 'notes', 'prayer', 'profile'];
                    $moreIsActive = in_array($activePage, $morePages, true);
                    $moreLinks = [
                        [
                            'label' => 'Planner',
                            'href' => app_url('planner.php'),
                            'active' => $activePage === 'planner',
                            'class' => '',
                        ],
                    ];

                    if (is_logged_in()) {
                        $moreLinks[] = [
                            'label' => 'Dashboard',
                            'href' => app_url('dashboard.php'),
                            'active' => $activePage === 'dashboard',
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Friends',
                            'href' => app_url('friends.php'),
                            'active' => $activePage === 'friends',
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Saved',
                            'href' => app_url('bookmarks.php'),
                            'active' => $activePage === 'bookmarks',
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Notes',
                            'href' => app_url('notes.php'),
                            'active' => $activePage === 'notes',
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Prayer',
                            'href' => app_url('prayer.php'),
                            'active' => $activePage === 'prayer',
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Profile',
                            'href' => app_url('profile.php'),
                            'active' => $activePage === 'profile',
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Logout',
                            'href' => app_url('logout.php'),
                            'active' => false,
                            'class' => 'nav-action',
                        ];
                    } else {
                        $moreLinks[] = [
                            'label' => 'Login',
                            'href' => app_url('login.php'),
                            'active' => false,
                            'class' => '',
                        ];
                        $moreLinks[] = [
                            'label' => 'Create Account',
                            'href' => app_url('register.php'),
                            'active' => false,
                            'class' => 'nav-action',
                        ];
                    }
                    ?>
                    <details class="more-nav">
                        <summary class="<?= $moreIsActive ? 'is-active' : ''; ?>">More</summary>
                        <div class="more-nav-menu">
                            <?php foreach ($moreLinks as $link): ?>
                                <a
                                    class="<?= e(trim(($link['active'] ? 'is-active ' : '') . $link['class'])); ?>"
                                    href="<?= e($link['href']); ?>"
                                >
                                    <?= e($link['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <div class="more-nav-mobile" aria-label="More">
                        <?php foreach ($moreLinks as $link): ?>
                            <a
                                class="<?= e(trim(($link['active'] ? 'is-active ' : '') . $link['class'])); ?>"
                                href="<?= e($link['href']); ?>"
                            >
                                <?= e($link['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
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
