<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        set_flash('Enter both email and password.', 'warning');
    } else {
        login_demo_user('Demo Member', $email);
        set_flash('Demo login active. Replace this flow with MySQL-backed auth next.', 'success');
        redirect('dashboard.php');
    }
}

$pageTitle = 'Login';
$activePage = '';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Easy Auth</p>
            <h1>Sign in</h1>
            <p>This starter uses a temporary session login so you can move through the app before wiring MySQL auth.</p>

            <form class="form-stack" method="post">
                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </label>

                <label>
                    <span>Password</span>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </label>

                <button class="button button-primary" type="submit">Sign In</button>
            </form>

            <p class="form-note">No Node.js required. Final auth should use `password_hash()` and MySQL users.</p>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
