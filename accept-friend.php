<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$invite = null;
$pageError = null;
$user = is_logged_in() ? refresh_current_user() : null;

if ($token !== '') {
    try {
        $invite = fetch_friend_invite_by_token($token);
    } catch (Throwable $exception) {
        $pageError = 'The friend invite could not be loaded because the database is unavailable.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($token === '' || $invite === null) {
        $pageError = 'This friend invite is invalid or has expired.';
    } elseif ($user === null) {
        set_flash('Sign in to accept this friend invite.', 'warning');
        redirect('login.php');
    } else {
        try {
            accept_friend_invite_record((int) $invite['id'], (int) $user['id'], (string) $user['email']);
            set_flash('Friend invite accepted.', 'success');
            redirect('friends.php');
        } catch (Throwable $exception) {
            $pageError = $exception->getMessage();
        }
    }
}

$pageTitle = 'Accept Friend Invite';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Friend Invite</p>
            <h1>Accept connection</h1>

            <?php if ($pageError): ?>
                <div class="flash flash-warning"><?= e($pageError); ?></div>
            <?php endif; ?>

            <?php if ($invite === null): ?>
                <p>This friend invite is missing, invalid, or expired.</p>
                <a class="button button-secondary" href="<?= e(app_url('friends.php')); ?>">Open Friends</a>
            <?php else: ?>
                <p>
                    <strong><?= e((string) $invite['sender_name']); ?></strong>
                    invited
                    <strong><?= e((string) $invite['recipient_email']); ?></strong>
                    to connect.
                </p>

                <?php if ($user === null): ?>
                    <p>Sign in with the invited email address to accept this connection.</p>
                    <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Sign In</a>
                <?php else: ?>
                    <form class="form-stack" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="token" value="<?= e($token); ?>">
                        <button class="button button-primary" type="submit">Accept Invite</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
