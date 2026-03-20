<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$pageTitle = 'Profile';
$activePage = 'profile';
$user = refresh_current_user();
$pageError = null;

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? 'profile';

    try {
        if ($action === 'profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($name === '' || $email === '') {
                $pageError = 'Name and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $pageError = 'Enter a valid email address.';
            } else {
                $user = update_user_profile_record((int) $user['id'], $name, $email);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'name' => (string) $user['name'],
                    'email' => (string) $user['email'],
                    'role' => (string) ($user['role'] ?? 'member'),
                ];
                set_flash('Profile updated.', 'success');
                redirect('profile.php');
            }
        }

        if ($action === 'password') {
            $password = trim($_POST['password'] ?? '');
            $passwordConfirm = trim($_POST['password_confirm'] ?? '');

            if ($password === '' || $passwordConfirm === '') {
                $pageError = 'Enter and confirm the new password.';
            } elseif (mb_strlen($password) < 8) {
                $pageError = 'Passwords must be at least 8 characters.';
            } elseif ($password !== $passwordConfirm) {
                $pageError = 'Password confirmation does not match.';
            } else {
                update_user_password_record((int) $user['id'], $password);
                set_flash('Password updated.', 'success');
                redirect('profile.php');
            }
        }
    } catch (PDOException $exception) {
        $pageError = $exception->getCode() === '23000'
            ? 'That email address is already in use.'
            : 'Profile changes could not be saved because the database is unavailable.';
    } catch (Throwable $exception) {
        $pageError = 'Profile changes could not be saved because the database is unavailable.';
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="section-heading">
            <p class="eyebrow">Profile</p>
            <h1><?= e($user['name'] ?? 'Member'); ?></h1>
            <p>Manage your basic account details and password.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="panel">
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

            <form class="form-stack top-gap" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="profile">

                <label>
                    <span>Name</span>
                    <input type="text" name="name" value="<?= e($user['name'] ?? ''); ?>" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e($user['email'] ?? ''); ?>" required>
                </label>

                <button class="button button-primary" type="submit">Save Profile</button>
            </form>

            <form class="form-stack top-gap" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="password">

                <label>
                    <span>New Password</span>
                    <input type="password" name="password" minlength="8" required>
                </label>

                <label>
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirm" minlength="8" required>
                </label>

                <button class="button button-secondary" type="submit">Update Password</button>
            </form>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
