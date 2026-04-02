<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? null;
$pageDescription = $pageDescription ?? 'Good News Bible is a warm, mobile-first Bible study app with bookmarks, planner tools, and community events.';
$activePage = $activePage ?? '';
$flash = pull_flash();
$user = current_user();
$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentPageUrl = app_url($currentRequestUri === '' ? '/' : ltrim($currentRequestUri, '/'), true);
$metaTitle = page_title($pageTitle);
$shareImagePath = 'assets/images/good-news-app.png';
$shareImageFile = dirname(__DIR__) . '/' . $shareImagePath;
$shareImageUrl = app_url($shareImagePath, true);
$shareImageVersion = is_file($shareImageFile) ? filemtime($shareImageFile) : false;

if ($shareImageVersion !== false) {
    $shareImageUrl .= '?v=' . $shareImageVersion;
}

$shareImageSize = is_file($shareImageFile) ? getimagesize($shareImageFile) : false;
$shareImageWidth = is_array($shareImageSize) ? (int) ($shareImageSize[0] ?? 0) : 0;
$shareImageHeight = is_array($shareImageSize) ? (int) ($shareImageSize[1] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle); ?></title>
    <meta name="description" content="<?= e($pageDescription); ?>">
    <meta name="application-name" content="<?= e(APP_NAME); ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME); ?>">
    <meta name="theme-color" content="#22333b">
    <link rel="canonical" href="<?= e($currentPageUrl); ?>">
    <link rel="icon" type="image/x-icon" href="<?= e(asset_url('assets/icons/favicon.ico')); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset_url('assets/icons/favicon-32x32.png')); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset_url('assets/icons/favicon-16x16.png')); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset_url('assets/icons/apple-touch-icon.png')); ?>">
    <link rel="manifest" href="<?= e(asset_url('assets/icons/site.webmanifest')); ?>">
    <meta property="og:site_name" content="<?= e(APP_NAME); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($metaTitle); ?>">
    <meta property="og:description" content="<?= e($pageDescription); ?>">
    <meta property="og:url" content="<?= e($currentPageUrl); ?>">
    <meta property="og:image" content="<?= e($shareImageUrl); ?>">
    <meta property="og:image:alt" content="Good News Bible app icon showing a glowing cross above an open Bible with the Good News Bible name.">
    <?php if ($shareImageWidth > 0 && $shareImageHeight > 0): ?>
        <meta property="og:image:width" content="<?= e((string) $shareImageWidth); ?>">
        <meta property="og:image:height" content="<?= e((string) $shareImageHeight); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($metaTitle); ?>">
    <meta name="twitter:description" content="<?= e($pageDescription); ?>">
    <meta name="twitter:image" content="<?= e($shareImageUrl); ?>">
    <meta name="twitter:image:alt" content="Good News Bible app icon showing a glowing cross above an open Bible with the Good News Bible name.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
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
                    <a class="<?= $activePage === 'sessions' ? 'is-active' : ''; ?>" href="<?= e(app_url('sessions.php')); ?>">Sessions</a>
                    <?php
                    $morePages = ['planner', 'dashboard', 'friends', 'bookmarks', 'notes', 'prayer', 'profile', 'admin'];
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
                        if (current_user_has_role(['admin'])) {
                            $moreLinks[] = [
                                'label' => 'Admin',
                                'href' => app_url('admin/index.php'),
                                'active' => $activePage === 'admin',
                                'class' => '',
                            ];
                        }
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
