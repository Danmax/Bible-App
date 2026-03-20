<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        set_flash('Complete all registration fields.', 'warning');
    } else {
        login_demo_user($name, $email);
        set_flash('Demo account created. Connect this form to MySQL next.', 'success');
        redirect('dashboard.php');
    }
}

$pageTitle = 'Create Account';
$activePage = '';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="panel">
            <p class="eyebrow">Create Your Account</p>
            <h1>Start your study journey</h1>
            <p>Register for bookmarks, notes, yearly planning, and community events.</p>

            <form class="form-stack" method="post">
                <label>
                    <span>Name</span>
                    <input type="text" name="name" placeholder="Your full name" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </label>

                <label>
                    <span>Password</span>
                    <input type="password" name="password" placeholder="Create a password" required>
                </label>

                <button class="button button-primary" type="submit">Create Account</button>
            </form>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
