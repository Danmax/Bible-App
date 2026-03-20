<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pageTitle = 'Admin';
$activePage = '';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Admin Preview</p>
            <h1>Content and event management</h1>
            <p>Use this area later for verses, reading plans, featured content, users, and community events.</p>
        </div>

        <div class="card-grid card-grid-3">
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
