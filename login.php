<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$email = '';
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rateLimitKey = rate_limit_key('login', mb_strtolower($email));

    if ($email === '' || $password === '') {
        $errorMessage = 'Enter both email and password.';
    } else {
        try {
            enforce_rate_limit($rateLimitKey, 8, 900);
            $user = find_user_for_login($email, $password);

            if ($user === null) {
                $errorMessage = 'We could not match that email and password.';
            } else {
                clear_rate_limit($rateLimitKey);
                session_regenerate_id(true);
                log_in_user($user);
                set_flash('You are signed in.', 'success');
                redirect('dashboard.php');
            }
        } catch (Throwable $exception) {
            $errorMessage = 'Database access is unavailable right now. Check the MySQL connection and try again.';
        }
    }
}

$pageTitle = 'Login';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="auth-shell">
            <aside class="auth-rail">
                <div>
                    <p class="eyebrow">Account Access</p>
                    <h2>Pick up where your study left off.</h2>
                    <p>Sign in to reopen your saved verses, personal notes, planner rhythms, and community tools.</p>
                </div>

                <ul class="auth-feature-list">
                    <li><span class="auth-feature-mark">1</span><span>Open the Bible reader with your saved context.</span></li>
                    <li><span class="auth-feature-mark">2</span><span>Manage bookmarks, notes, planner goals, and profile settings.</span></li>
                    <li><span class="auth-feature-mark">3</span><span>Use stronger session controls across your devices.</span></li>
                </ul>
            </aside>

            <div class="auth-panel">
                <p class="eyebrow">Sign In</p>
                <h1>Welcome back</h1>
                <p>Use your saved account to open bookmarks, notes, planner items, and profile tools.</p>

                <?php if ($errorMessage): ?>
                    <div class="flash flash-warning"><?= e($errorMessage); ?></div>
                <?php endif; ?>

                <form class="form-stack" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="<?= e($email); ?>" placeholder="you@example.com" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </label>

                    <button class="button button-primary" type="submit">Sign In</button>
                </form>

                <div class="auth-helper-links top-gap-sm">
                    <a class="button button-secondary" href="<?= e(app_url('forgot-password.php')); ?>">Forgot password</a>
                    <a class="button button-secondary" href="<?= e(app_url('register.php')); ?>">Create account</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
