<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? null;
$pageDescription = $pageDescription ?? 'Good News Bible is a warm, mobile-first Bible study app with bookmarks, planner tools, and community events.';
$activePage = $activePage ?? '';
$flash = pull_flash();
$user = current_user();
$isSignedIn = $user !== null;
$homeHref = app_url($isSignedIn ? 'dashboard.php' : 'index.php');
$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentPageUrl = app_url($currentRequestUri === '' ? '/' : ltrim($currentRequestUri, '/'), true);
$metaTitle = page_title($pageTitle);
$appThemeOptions = app_theme_options();
$defaultAppTheme = normalize_app_theme(null);
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
<html lang="en" data-theme="<?= e($defaultAppTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle); ?></title>
    <meta name="description" content="<?= e($pageDescription); ?>">
    <meta name="application-name" content="<?= e(APP_NAME); ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME); ?>">
    <meta name="theme-color" content="<?= e(app_theme_meta_color($defaultAppTheme)); ?>">
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
    <script src="<?= e(asset_url('assets/js/theme-bootstrap.js')); ?>"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')); ?>">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="container header-row">
                <a class="brand" href="<?= e($homeHref); ?>">
                    <span class="brand-mark">STWB</span>
                    <span>
                        <strong><?= e(APP_NAME); ?></strong>
                        <small>Study The Word Bible</small>
                    </span>
                </a>

                <button class="menu-toggle" type="button" data-mobile-menu-trigger aria-expanded="false" aria-controls="primary-nav">
                    More
                </button>

                <nav class="primary-nav" id="primary-nav">
                    <a class="desktop-nav-link <?= in_array($activePage, ['home', 'dashboard'], true) ? 'is-active' : ''; ?>" href="<?= e($homeHref); ?>">Home</a>
                    <?php
                    $bibleActivePages = ['bible'];
                    $libraryActivePages = ['library', 'bookmarks', 'notes', 'sermon-notes', 'sermon-note-view', 'prayer'];
                    $planActivePages = ['studies'];
                    $communityActivePages = ['community', 'sessions', 'friends'];
                    ?>
                    <a class="desktop-nav-link <?= in_array($activePage, $bibleActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('bible.php')); ?>">Bible</a>
                    <a class="desktop-nav-link <?= in_array($activePage, $libraryActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('library.php')); ?>">Library</a>
                    <a class="desktop-nav-link <?= in_array($activePage, $planActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('studies.php')); ?>">Plans</a>
                    <a class="desktop-nav-link <?= in_array($activePage, $communityActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('community.php')); ?>">Community</a>
                    <?php
                    $morePages = ['planner', 'profile', 'admin', 'good-news', 'dictionary'];
                    $moreIsActive = in_array($activePage, $morePages, true);
                    $mobileMoreIsActive = $moreIsActive || in_array($activePage, $planActivePages, true);
                    $moreSections = [];

                    if ($isSignedIn) {
                        $moreSections[] = [
                            'label' => 'Plan and tools',
                            'links' => [
                                [
                                    'label' => 'Bible Plans',
                                    'href' => app_url('studies.php'),
                                    'active' => $activePage === 'studies',
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Planner',
                                    'href' => app_url('planner.php'),
                                    'active' => $activePage === 'planner',
                                    'class' => '',
                                ],
                            ],
                        ];
                        $moreSections[] = [
                            'label' => 'Study tools',
                            'links' => [
                                [
                                    'label' => 'Gospel Track',
                                    'href' => app_url('good-news.php'),
                                    'active' => $activePage === 'good-news',
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Dictionary',
                                    'href' => app_url('dictionary.php'),
                                    'active' => $activePage === 'dictionary',
                                    'class' => '',
                                ],
                            ],
                        ];
                        $moreSections[] = [
                            'label' => 'Connections',
                            'links' => [
                                [
                                    'label' => 'Sessions',
                                    'href' => app_url('sessions.php'),
                                    'active' => $activePage === 'sessions',
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Friends',
                                    'href' => app_url('friends.php'),
                                    'active' => $activePage === 'friends',
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Prayer',
                                    'href' => app_url('library.php?view=prayer'),
                                    'active' => $activePage === 'prayer',
                                    'class' => '',
                                ],
                            ],
                        ];

                        $moreSections[] = [
                            'label' => 'Account',
                            'links' => [
                                [
                                    'label' => 'Profile',
                                    'href' => app_url('profile.php'),
                                    'active' => $activePage === 'profile',
                                    'class' => '',
                                ],
                            ],
                        ];

                        if (current_user_has_role(['admin'])) {
                            $moreSections[3]['links'][] = [
                                'label' => 'Admin',
                                'href' => app_url('admin/index.php'),
                                'active' => $activePage === 'admin',
                                'class' => '',
                            ];
                        }

                        $moreSections[3]['links'][] = [
                            'label' => 'Logout',
                            'href' => app_url('logout.php'),
                            'active' => false,
                            'class' => 'nav-action',
                        ];
                    } else {
                        $moreSections[] = [
                            'label' => 'Study',
                            'links' => [
                                [
                                    'label' => 'Bible Plans',
                                    'href' => app_url('studies.php'),
                                    'active' => $activePage === 'studies',
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Gospel Track',
                                    'href' => app_url('good-news.php'),
                                    'active' => $activePage === 'good-news',
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Dictionary',
                                    'href' => app_url('dictionary.php'),
                                    'active' => $activePage === 'dictionary',
                                    'class' => '',
                                ],
                            ],
                        ];
                        $moreSections[] = [
                            'label' => 'Community',
                            'links' => [
                                [
                                    'label' => 'Sessions',
                                    'href' => app_url('sessions.php'),
                                    'active' => $activePage === 'sessions',
                                    'class' => '',
                                ],
                            ],
                        ];
                        $moreSections[] = [
                            'label' => 'Account',
                            'links' => [
                                [
                                    'label' => 'Login',
                                    'href' => app_url('login.php'),
                                    'active' => false,
                                    'class' => '',
                                ],
                                [
                                    'label' => 'Create Account',
                                    'href' => app_url('register.php'),
                                    'active' => false,
                                    'class' => 'nav-action',
                                ],
                            ],
                        ];
                    }
                    ?>
                    <details class="more-nav">
                        <summary class="<?= $moreIsActive ? 'is-active' : ''; ?>">More</summary>
                        <div class="more-nav-menu">
                            <?php foreach ($moreSections as $section): ?>
                                <div class="more-nav-group">
                                    <p class="more-nav-group-label"><?= e((string) ($section['label'] ?? 'More')); ?></p>
                                    <?php foreach (($section['links'] ?? []) as $link): ?>
                                        <a
                                            class="<?= e(trim(($link['active'] ? 'is-active ' : '') . $link['class'])); ?>"
                                            href="<?= e($link['href']); ?>"
                                        >
                                            <?= e($link['label']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <div class="more-nav-mobile" aria-label="More">
                        <?php foreach ($moreSections as $section): ?>
                            <div class="more-nav-group">
                                <p class="more-nav-group-label"><?= e((string) ($section['label'] ?? 'More')); ?></p>
                                <?php foreach (($section['links'] ?? []) as $link): ?>
                                    <a
                                        class="<?= e(trim(($link['active'] ? 'is-active ' : '') . $link['class'])); ?>"
                                        href="<?= e($link['href']); ?>"
                                    >
                                        <?= e($link['label']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </nav>
            </div>
        </header>

        <nav class="mobile-primary-nav" aria-label="Primary navigation">
            <a class="<?= in_array($activePage, ['home', 'dashboard'], true) ? 'is-active' : ''; ?>" href="<?= e($homeHref); ?>">Home</a>
            <a class="<?= in_array($activePage, $bibleActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('bible.php')); ?>">Bible</a>
            <a class="<?= in_array($activePage, $libraryActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('library.php')); ?>">Library</a>
            <a class="<?= in_array($activePage, $communityActivePages, true) ? 'is-active' : ''; ?>" href="<?= e(app_url('community.php')); ?>">Community</a>
            <button class="mobile-primary-nav-more <?= $mobileMoreIsActive ? 'is-active' : ''; ?>" type="button" data-mobile-menu-trigger aria-expanded="false" aria-controls="primary-nav">More</button>
        </nav>

        <main>
            <?php if ($flash): ?>
                <div class="container">
                    <div class="flash flash-<?= e($flash['type']); ?>">
                        <?= e($flash['message']); ?>
                    </div>
                </div>
            <?php endif; ?>
