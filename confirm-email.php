<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errorMessage = null;
$tokenRecord = null;

if ($token !== '') {
    try {
        $tokenRecord = fetch_email_change_token($token);
    } catch (Throwable $exception) {
        $errorMessage = 'The email approval link could not be checked because the database is unavailable.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($token === '' || $tokenRecord === null) {
        $errorMessage = 'This email approval link is invalid or has expired.';
    } else {
        try {
            $updatedUser = confirm_email_change_with_token($token);

            if ($updatedUser === null) {
                $errorMessage = 'This email approval link is invalid or has expired.';
            } else {
                if (is_logged_in() && (int) (current_user()['id'] ?? 0) === (int) $updatedUser['id']) {
                    log_in_user($updatedUser);
                    set_flash('Email address confirmed and updated.', 'success');
                    redirect('profile.php');
                }

                set_flash('Email address confirmed. Sign in with the updated email.', 'success');
                redirect('login.php');
            }
        } catch (RuntimeException $exception) {
            $errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            $errorMessage = 'The email address could not be updated because the database is unavailable.';
        }
    }
}

$pageTitle = 'Confirm Email';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Email Approval</p>
            <h1>Confirm your new email address</h1>

            <?php if ($errorMessage): ?>
                <div class="flash flash-warning"><?= e($errorMessage); ?></div>
            <?php endif; ?>

            <?php if ($tokenRecord === null): ?>
                <p>This email approval link is missing, invalid, or expired.</p>
                <a class="button button-secondary" href="<?= e(app_url('profile.php')); ?>">Back to profile</a>
            <?php else: ?>
                <p>
                    Approve changing
                    <strong><?= e((string) $tokenRecord['current_email']); ?></strong>
                    to
                    <strong><?= e((string) $tokenRecord['new_email']); ?></strong>.
                </p>

                <form class="form-stack" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="token" value="<?= e($token); ?>">

                    <button class="button button-primary" type="submit">Approve Email Change</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
