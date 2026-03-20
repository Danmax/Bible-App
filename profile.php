<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'WT';
}

$pageTitle = 'Profile';
$activePage = 'profile';
$user = refresh_current_user();
$pageError = null;
$emailChangeLink = null;
$pendingEmailChange = null;

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
            $city = trim($_POST['city'] ?? '');
            $avatarUrl = trim($_POST['avatar_url'] ?? '');
            $normalizedRequestedEmail = mb_strtolower($email);
            $normalizedCurrentEmail = mb_strtolower((string) ($user['email'] ?? ''));

            if ($name === '' || $email === '') {
                $pageError = 'Name and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $pageError = 'Enter a valid email address.';
            } elseif ($avatarUrl !== '' && filter_var($avatarUrl, FILTER_VALIDATE_URL) === false) {
                $pageError = 'Avatar must be a valid image URL.';
            } else {
                $user = update_user_profile_record((int) $user['id'], $name, (string) $user['email'], $city, $avatarUrl);
                log_in_user($user);

                if ($normalizedRequestedEmail !== $normalizedCurrentEmail) {
                    $existingUser = fetch_user_by_email($email);

                    if ($existingUser !== null && (int) $existingUser['id'] !== (int) $user['id']) {
                        $pageError = 'That email address is already in use.';
                    } else {
                        $token = create_email_change_token((int) $user['id'], $email);
                        $emailChangeLink = app_url('confirm-email.php', true) . '?token=' . urlencode($token);
                        $pendingEmailChange = fetch_pending_email_change_request((int) $user['id']);
                        set_flash('Profile updated. Confirm the new email from the approval link below before it becomes active.', 'success');
                    }
                } else {
                    set_flash('Profile updated.', 'success');
                    redirect('profile.php');
                }
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

if ($pendingEmailChange === null) {
    try {
        $pendingEmailChange = fetch_pending_email_change_request((int) $user['id']);
    } catch (Throwable $exception) {
        if ($pageError === null) {
            $pageError = 'Profile changes could not be loaded because the database is unavailable.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
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
            <div class="profile-hero">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img class="profile-avatar" src="<?= e((string) $user['avatar_url']); ?>" alt="<?= e((string) ($user['name'] ?? 'Member')); ?> avatar">
                <?php else: ?>
                    <div class="profile-avatar profile-avatar-fallback"><?= e(profile_initials((string) ($user['name'] ?? 'Member'))); ?></div>
                <?php endif; ?>

                <div>
                    <h2><?= e($user['name'] ?? 'Member'); ?></h2>
                    <p class="muted-copy"><?= e($user['city'] ?: 'City not set yet'); ?></p>
                </div>
            </div>

            <div class="profile-row">
                <div>
                    <strong>Email</strong>
                    <p><?= e($user['email'] ?? ''); ?></p>
                </div>
                <div>
                    <strong>Role</strong>
                    <p><?= e($user['role'] ?? 'member'); ?></p>
                </div>
                <div>
                    <strong>City</strong>
                    <p><?= e($user['city'] ?: 'Not set'); ?></p>
                </div>
            </div>

            <?php if ($pendingEmailChange): ?>
                <div class="inline-message top-gap-sm">
                    <strong>Pending email approval</strong>
                    <p>New email: <?= e((string) $pendingEmailChange['new_email']); ?></p>
                    <p>Expires: <?= e(format_event_datetime((string) $pendingEmailChange['expires_at'])); ?></p>
                </div>
            <?php endif; ?>

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

                <label>
                    <span>City</span>
                    <input type="text" name="city" value="<?= e($user['city'] ?? ''); ?>" placeholder="Charlotte">
                </label>

                <label>
                    <span>Avatar Image URL</span>
                    <input type="url" name="avatar_url" value="<?= e($user['avatar_url'] ?? ''); ?>" placeholder="https://example.com/avatar.jpg">
                </label>

                <button class="button button-primary" type="submit">Save Profile</button>
            </form>

            <?php if ($emailChangeLink): ?>
                <div class="inline-message top-gap-sm">
                    <strong>Email approval link</strong>
                    <p>This build still exposes the approval link directly until outbound email delivery is added.</p>
                    <p><a href="<?= e($emailChangeLink); ?>"><?= e($emailChangeLink); ?></a></p>
                </div>
            <?php endif; ?>

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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
