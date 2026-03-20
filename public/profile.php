<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$pageTitle = 'Profile';
$activePage = 'profile';
$user = current_user();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Profile</p>
            <h1><?= e($user['name'] ?? 'Member'); ?></h1>
            <div class="profile-row">
                <div>
                    <strong>Email</strong>
                    <p><?= e($user['email'] ?? ''); ?></p>
                </div>
                <div>
                    <strong>Role</strong>
                    <p><?= e($user['role'] ?? 'member'); ?></p>
                </div>
            </div>
            <p>Next implementation step: back this page with the `users` table and profile editing form handlers.</p>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
