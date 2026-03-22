<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

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
            enforce_rate_limit(rate_limit_key('forgot-password', mb_strtolower($email)), 5, 900);
            $user = fetch_user_by_email($email);

            if ($user) {
                $token = create_password_reset_token((int) $user['id']);
                if (debug_links_enabled()) {
                    $resetLink = app_url('reset-password.php', true) . '?token=' . urlencode($token);
                }
            }

            set_flash('If that email exists, reset instructions have been prepared.', 'success');
        } catch (Throwable $exception) {
            $errorMessage = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The reset request could not be completed because the database is unavailable.';
        }
    }
}

$pageTitle = 'Forgot Password';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="auth-shell">
            <aside class="auth-rail">
                <div>
                    <p class="eyebrow">Password Reset</p>
                    <h2>Recover your account without losing your study progress.</h2>
                    <p>Request a secure reset link and return to your saved verses, notes, and planner history.</p>
                </div>

                <ul class="auth-feature-list">
                    <li><span class="auth-feature-mark">1</span><span>Reset links expire automatically for security.</span></li>
                    <li><span class="auth-feature-mark">2</span><span>Existing sessions can be revoked after a password reset.</span></li>
                </ul>
            </aside>

            <div class="auth-panel">
                <p class="eyebrow">Password Reset</p>
                <h1>Request a reset link</h1>
                <p>Request a password reset. Direct reset-link previews are only available in local debug mode.</p>

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

                <?php if ($resetLink && debug_links_enabled()): ?>
                    <div class="inline-message top-gap-sm">
                        <strong>Debug reset link</strong>
                        <p><a href="<?= e($resetLink); ?>"><?= e($resetLink); ?></a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
