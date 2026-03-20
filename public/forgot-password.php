<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$email = '';
$errorMessage = null;
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Enter a valid email address.';
    } else {
        try {
            $user = fetch_user_by_email($email);

            if ($user) {
                $token = create_password_reset_token((int) $user['id']);
                $resetLink = app_url('reset-password.php') . '?token=' . urlencode($token);
            }

            set_flash('If that email exists, a password reset link is ready.', 'success');
        } catch (Throwable $exception) {
            $errorMessage = 'The reset request could not be completed because the database is unavailable.';
        }
    }
}

$pageTitle = 'Forgot Password';
$activePage = '';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Password Reset</p>
            <h1>Request a reset link</h1>
            <p>This build generates a reset link directly so you can test the full flow before mail delivery is added.</p>

            <?php if ($errorMessage): ?>
                <div class="flash flash-warning"><?= e($errorMessage); ?></div>
            <?php endif; ?>

            <form class="form-stack" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e($email); ?>" placeholder="you@example.com" required>
                </label>

                <button class="button button-primary" type="submit">Create Reset Link</button>
            </form>

            <?php if ($resetLink): ?>
                <div class="inline-message top-gap-sm">
                    <strong>Reset link</strong>
                    <p><a href="<?= e($resetLink); ?>"><?= e($resetLink); ?></a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
