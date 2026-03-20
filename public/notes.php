<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$pageTitle = 'Study Notes';
$activePage = '';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Study Notes</p>
            <h1>Capture what you are learning</h1>
            <p>Attach notes to verses, sermons, and topic studies.</p>
        </div>

        <div class="card-grid card-grid-2">
            <article class="panel">
                <h2>Romans Study</h2>
                <p>Grace, righteousness, faith, and the new life in Christ.</p>
            </article>
            <article class="panel">
                <h2>Sunday Sermon</h2>
                <p>Walking in trust rather than leaning on your own understanding.</p>
            </article>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
