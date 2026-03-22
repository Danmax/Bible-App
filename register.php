<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$form = [
    'name' => '',
    'email' => '',
];
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $form['name'] = trim($_POST['name'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $passwordConfirm = trim($_POST['password_confirm'] ?? '');

    if ($form['name'] === '' || $form['email'] === '' || $password === '' || $passwordConfirm === '') {
        $errorMessage = 'Complete all registration fields.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Enter a valid email address.';
    } elseif (mb_strlen($password) < 8) {
        $errorMessage = 'Passwords must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errorMessage = 'Password confirmation does not match.';
    } else {
        try {
            $user = create_user($form['name'], $form['email'], $password);
            session_regenerate_id(true);
            log_in_user($user);
            set_flash('Your account is ready.', 'success');
            redirect('dashboard.php');
        } catch (PDOException $exception) {
            $errorMessage = $exception->getCode() === '23000'
                ? 'That email address is already registered.'
                : 'We could not create the account because the database is unavailable.';
        } catch (Throwable $exception) {
            $errorMessage = 'We could not create the account because the database is unavailable.';
        }
    }
}

$pageTitle = 'Create Account';
$activePage = '';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="auth-shell">
            <aside class="auth-rail">
                <div>
                    <p class="eyebrow">Create Your Account</p>
                    <h2>Build your personal study space.</h2>
                    <p>Create an account to save verses, write notes, plan goals, and stay connected with your community.</p>
                </div>

                <ul class="auth-feature-list">
                    <li><span class="auth-feature-mark">A</span><span>Save passages and highlights from the reader.</span></li>
                    <li><span class="auth-feature-mark">B</span><span>Write personal study notes and planner entries.</span></li>
                    <li><span class="auth-feature-mark">C</span><span>Manage your profile, sessions, and community access.</span></li>
                </ul>
            </aside>

            <div class="auth-panel">
                <p class="eyebrow">New Account</p>
                <h1>Start your study journey</h1>
                <p>Register for bookmarks, verse search, personal notes, and profile tools.</p>

                <?php if ($errorMessage): ?>
                    <div class="flash flash-warning"><?= e($errorMessage); ?></div>
                <?php endif; ?>

                <form class="form-stack" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                    <label>
                        <span>Name</span>
                        <input type="text" name="name" value="<?= e($form['name']); ?>" placeholder="Your full name" required>
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="<?= e($form['email']); ?>" placeholder="you@example.com" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input type="password" name="password" placeholder="Create a password" minlength="8" required>
                    </label>

                    <label>
                        <span>Confirm Password</span>
                        <input type="password" name="password_confirm" placeholder="Repeat your password" minlength="8" required>
                    </label>

                    <button class="button button-primary" type="submit">Create Account</button>
                </form>

                <p class="form-note">Passwords are stored with PHP `password_hash()` and checked with `password_verify()`.</p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
