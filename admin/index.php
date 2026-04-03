<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_has_role(['admin'])) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    $activePage = '';
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Restricted</p>
                <h1>Admin access required</h1>
                <p>You do not have permission to view this area.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$pageTitle = 'Admin';
$activePage = 'admin';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Admin Preview</p>
            <h1>Content and event management</h1>
            <p>Manage public sessions now, and use this area later for verses, reading plans, featured content, users, and community events.</p>
        </div>

        <div class="card-grid card-grid-3">
            <article class="panel">
                <h2>Public Sessions</h2>
                <p>Create, publish, archive, and feature public sessions for everyone to see.</p>
                <div class="top-gap-sm">
                    <a class="button button-primary" href="<?= e(app_url('admin/sessions.php')); ?>">Manage Sessions</a>
                </div>
            </article>
            <article class="panel">
                <h2>Christian Radio</h2>
                <p>Manage station settings and live connections for the Good News player and the dedicated Divine Radio page.</p>
                <div class="top-gap-sm">
                    <a class="button button-primary" href="<?= e(app_url('admin/radio.php')); ?>">Manage Radio</a>
                </div>
            </article>
            <article class="panel">
                <h2>Verses</h2>
                <p>Import and manage public-domain Bible text.</p>
            </article>
            <article class="panel">
                <h2>Events</h2>
                <p>Approve community submissions and highlight featured gatherings.</p>
            </article>
            <article class="panel">
                <h2>Users</h2>
                <p>Manage roles for members, leaders, and admins.</p>
            </article>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
