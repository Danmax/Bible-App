<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errorMessage = null;
$tokenRecord = null;

if ($token !== '') {
    try {
        $tokenRecord = fetch_password_reset_token($token);
    } catch (Throwable $exception) {
        $errorMessage = 'The reset token could not be checked because the database is unavailable.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = trim($_POST['password'] ?? '');
    $passwordConfirm = trim($_POST['password_confirm'] ?? '');

    if ($token === '' || $tokenRecord === null) {
        $errorMessage = 'This reset link is invalid or has expired.';
    } elseif ($password === '' || $passwordConfirm === '') {
        $errorMessage = 'Enter and confirm your new password.';
    } elseif (mb_strlen($password) < 8) {
        $errorMessage = 'Passwords must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errorMessage = 'Password confirmation does not match.';
    } else {
        try {
            reset_user_password_with_token($token, $password);
            set_flash('Your password has been updated. Sign in with the new password.', 'success');
            redirect('login.php');
        } catch (Throwable $exception) {
            $errorMessage = 'The password could not be updated because the database is unavailable.';
        }
    }
}

$pageTitle = 'Reset Password';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Password Reset</p>
            <h1>Create a new password</h1>

            <?php if ($errorMessage): ?>
                <div class="flash flash-warning"><?= e($errorMessage); ?></div>
            <?php endif; ?>

            <?php if ($tokenRecord === null): ?>
                <p>This reset link is missing, invalid, or expired.</p>
                <a class="button button-secondary" href="<?= e(app_url('forgot-password.php')); ?>">Request another link</a>
            <?php else: ?>
                <p>Reset password for <?= e($tokenRecord['email']); ?>.</p>

                <form class="form-stack" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="token" value="<?= e($token); ?>">

                    <label>
                        <span>New Password</span>
                        <input type="password" name="password" minlength="8" required>
                    </label>

                    <label>
                        <span>Confirm Password</span>
                        <input type="password" name="password_confirm" minlength="8" required>
                    </label>

                    <button class="button button-primary" type="submit">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
