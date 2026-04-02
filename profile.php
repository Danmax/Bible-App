<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Profile';
$activePage = 'profile';
$user = refresh_current_user();
$pageError = null;
$emailChangeLink = null;
$pendingEmailChange = null;
$activeSessions = [];
$profileEditMode = false;
$passwordEditMode = false;

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
                        $confirmationLink = app_url('confirm-email.php', true) . '?token=' . urlencode($token);

                        if (debug_links_enabled()) {
                            $emailChangeLink = $confirmationLink;
                        }

                        $deliverySent = false;

                        if (mailer_enabled()) {
                            try {
                                send_email_change_confirmation_email(
                                    (string) ($user['name'] ?? ''),
                                    $email,
                                    (string) ($user['email'] ?? ''),
                                    $confirmationLink
                                );
                                $deliverySent = true;
                            } catch (Throwable $mailException) {
                                $deliverySent = false;
                            }
                        }

                        $pendingEmailChange = fetch_pending_email_change_request((int) $user['id']);
                        set_flash(
                            $deliverySent
                                ? 'Profile updated. Confirm the new email from your inbox before it becomes active.'
                                : (
                                    debug_links_enabled()
                                        ? 'Profile updated. Confirm the new email from the approval link below before it becomes active.'
                                        : 'Profile updated. The email change request was created, but delivery is not configured yet.'
                                ),
                            $deliverySent || debug_links_enabled() ? 'success' : 'warning'
                        );
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
                if (user_sessions_available()) {
                    revoke_other_user_sessions((int) $user['id'], session_id());
                }
                record_audit_event((int) $user['id'], 'password_change.completed', (int) $user['id'], [
                    'source' => 'profile',
                    'other_sessions_revoked' => user_sessions_available(),
                ]);
                set_flash('Password updated.', 'success');
                redirect('profile.php');
            }
        }

        if ($action === 'revoke-other-sessions') {
            if (!user_sessions_available()) {
                throw new RuntimeException('Session controls are not available yet.');
            }

            revoke_other_user_sessions((int) $user['id'], session_id());
            record_audit_event((int) $user['id'], 'session.revoke_others', (int) $user['id'], [
                'source' => 'profile',
            ]);
            set_flash('Other devices have been signed out.', 'success');
            redirect('profile.php');
        }

        if ($action === 'revoke-session') {
            if (!user_sessions_available()) {
                throw new RuntimeException('Session controls are not available yet.');
            }

            $sessionRecordId = (int) ($_POST['session_record_id'] ?? 0);

            if ($sessionRecordId <= 0) {
                throw new RuntimeException('Select a valid session.');
            }

            $revoked = revoke_user_session_record_by_id((int) $user['id'], $sessionRecordId);

            if (!$revoked) {
                throw new RuntimeException('That session could not be revoked.');
            }

            record_audit_event((int) $user['id'], 'session.revoked_by_user', (int) $user['id'], [
                'source' => 'profile',
                'session_record_id' => $sessionRecordId,
            ]);
            set_flash('Selected device signed out.', 'success');
            redirect('profile.php');
        }
    } catch (PDOException $exception) {
        $pageError = $exception->getCode() === '23000'
            ? 'That email address is already in use.'
            : 'Profile changes could not be saved because the database is unavailable.';
    } catch (Throwable $exception) {
        $pageError = 'Profile changes could not be saved because the database is unavailable.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'profile' && $pageError !== null) {
    $profileEditMode = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'password' && $pageError !== null) {
    $passwordEditMode = true;
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

if (user_sessions_available()) {
    try {
        $activeSessions = fetch_user_session_records((int) $user['id'], session_id());
    } catch (Throwable $exception) {
        if ($pageError === null) {
            $pageError = 'Active sessions could not be loaded because the database is unavailable.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow">
        <div class="section-heading">
            <p class="eyebrow">Profile</p>
            <h1>Your profile</h1>
            <p>Manage your account details, email approval, avatar, and password.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="panel profile-panel">
            <div class="profile-hero-card">
                <div class="profile-hero-media">
                    <div class="profile-avatar-frame">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img class="profile-avatar profile-avatar-large" src="<?= e((string) $user['avatar_url']); ?>" alt="<?= e((string) ($user['name'] ?? 'Member')); ?> avatar">
                        <?php else: ?>
                            <div class="profile-avatar profile-avatar-large profile-avatar-fallback"><?= e(profile_initials((string) ($user['name'] ?? 'Member'))); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-hero-copy">
                    <span class="profile-badge">Member Profile</span>
                    <div class="profile-hero-heading">
                        <h2><?= e($user['name'] ?? 'Member'); ?></h2>
                        <p class="muted-copy"><?= e($user['city'] ?: 'City not set yet'); ?></p>
                    </div>

                    <div class="profile-meta-grid">
                        <div class="profile-meta-card">
                            <span>Role</span>
                            <strong><?= e(ucfirst((string) ($user['role'] ?? 'member'))); ?></strong>
                        </div>
                        <div class="profile-meta-card">
                            <span>Email</span>
                            <strong><?= e((string) ($user['email'] ?? '')); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($pendingEmailChange): ?>
                <div class="inline-message top-gap-sm">
                    <strong>Pending email approval</strong>
                    <p>New email: <?= e((string) $pendingEmailChange['new_email']); ?></p>
                    <p>Expires: <?= e(format_event_datetime((string) $pendingEmailChange['expires_at'])); ?></p>
                </div>
            <?php endif; ?>

            <section class="account-action-card top-gap">
                <div class="panel-heading">
                    <div>
                        <h3>Edit profile</h3>
                        <p class="muted-copy">Update your name, city, avatar, and request an email change.</p>
                    </div>
                </div>

                <form method="post" data-profile-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="profile">

                    <div class="inline-actions profile-actions">
                        <button class="button button-secondary" type="button" data-profile-edit-toggle <?= $profileEditMode ? '' : 'aria-hidden="false"'; ?> <?= $profileEditMode ? 'hidden style="display: none;" aria-hidden="true"' : ''; ?>>Edit Profile</button>
                    </div>

                    <div
                        class="form-stack top-gap-sm"
                        data-profile-fields
                        <?= $profileEditMode ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>
                    >
                        <label>
                            <span>Name</span>
                            <input type="text" name="name" value="<?= e($user['name'] ?? ''); ?>" required <?= $profileEditMode ? '' : 'disabled'; ?>>
                        </label>

                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e($user['email'] ?? ''); ?>" required <?= $profileEditMode ? '' : 'disabled'; ?>>
                        </label>

                        <label>
                            <span>City</span>
                            <input type="text" name="city" value="<?= e($user['city'] ?? ''); ?>" placeholder="Charlotte" <?= $profileEditMode ? '' : 'disabled'; ?>>
                        </label>

                        <label>
                            <span>Avatar Image URL</span>
                            <input type="url" name="avatar_url" value="<?= e($user['avatar_url'] ?? ''); ?>" placeholder="https://example.com/avatar.jpg" <?= $profileEditMode ? '' : 'disabled'; ?>>
                        </label>

                        <div class="inline-actions profile-actions profile-action-footer">
                            <button class="button button-secondary" type="button" data-profile-edit-cancel <?= $profileEditMode ? '' : 'hidden style="display: none;" aria-hidden="true"'; ?>>Cancel</button>
                            <button class="button button-primary" type="submit" data-profile-save <?= $profileEditMode ? '' : 'hidden style="display: none;" aria-hidden="true"'; ?>>Save Profile</button>
                        </div>
                    </div>
                </form>
            </section>

            <?php if ($emailChangeLink && debug_links_enabled()): ?>
                <div class="inline-message top-gap-sm">
                    <strong>Debug email approval link</strong>
                    <p>This preview is only shown when debug links are enabled.</p>
                    <p><a href="<?= e($emailChangeLink); ?>"><?= e($emailChangeLink); ?></a></p>
                </div>
            <?php endif; ?>

            <div class="top-gap" data-community-panels>
                <div class="community-action-bar">
                    <button
                        class="button button-secondary"
                        type="button"
                        data-community-panel-toggle="appearance"
                        aria-expanded="false"
                    >
                        Appearance Settings
                    </button>
                </div>

                <section
                    class="panel-modal"
                    data-community-panel="appearance"
                    data-panel-modal
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="profile-appearance-modal-title"
                    hidden
                    aria-hidden="true"
                    style="display: none;"
                >
                    <div class="panel community-manager-panel panel-modal-card" data-panel-modal-content>
                        <div class="panel-heading">
                            <div>
                                <h3 id="profile-appearance-modal-title">Appearance</h3>
                                <p class="muted-copy">Choose a seasonal site theme for this browser.</p>
                            </div>
                            <button class="button button-secondary" type="button" data-community-panel-close="appearance">Close</button>
                        </div>

                        <div class="form-stack top-gap-sm">
                            <label>
                                <span>Site theme</span>
                                <select data-app-theme-select aria-label="Select site theme">
                                    <?php foreach (app_theme_options() as $themeOption): ?>
                                        <option value="<?= e((string) ($themeOption['value'] ?? 'good-news')); ?>">
                                            <?= e((string) ($themeOption['label'] ?? 'Theme')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div class="theme-swatch-grid" aria-hidden="true">
                                <?php foreach (app_theme_options() as $themeOption): ?>
                                    <button
                                        class="theme-swatch"
                                        type="button"
                                        data-app-theme-option="<?= e((string) ($themeOption['value'] ?? 'good-news')); ?>"
                                        title="<?= e((string) ($themeOption['label'] ?? 'Theme')); ?>"
                                    >
                                        <span><?= e((string) ($themeOption['label'] ?? 'Theme')); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <p class="muted-copy" data-app-theme-status>Theme changes are saved on this device.</p>
                        </div>
                    </div>
                </section>
            </div>

            <section class="account-action-card top-gap">
                <div class="panel-heading">
                    <div>
                        <h3>Change password</h3>
                        <p class="muted-copy">Open the password form only when you need to update your sign-in credentials.</p>
                    </div>
                </div>

                <form method="post" data-password-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="password">

                    <div class="inline-actions profile-actions">
                        <button class="button button-secondary" type="button" data-password-edit-toggle <?= $passwordEditMode ? '' : 'aria-hidden="false"'; ?> <?= $passwordEditMode ? 'hidden style="display: none;" aria-hidden="true"' : ''; ?>>Change Password</button>
                    </div>

                    <div
                        class="form-stack top-gap-sm"
                        data-password-fields
                        <?= $passwordEditMode ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>
                    >
                        <label>
                            <span>New Password</span>
                            <input type="password" name="password" minlength="8" required <?= $passwordEditMode ? '' : 'disabled'; ?>>
                        </label>

                        <label>
                            <span>Confirm Password</span>
                            <input type="password" name="password_confirm" minlength="8" required <?= $passwordEditMode ? '' : 'disabled'; ?>>
                        </label>

                        <div class="inline-actions profile-actions profile-action-footer">
                            <button class="button button-secondary" type="button" data-password-edit-cancel <?= $passwordEditMode ? '' : 'hidden style="display: none;" aria-hidden="true"'; ?>>Cancel</button>
                            <button class="button button-primary" type="submit" data-password-save <?= $passwordEditMode ? '' : 'hidden style="display: none;" aria-hidden="true"'; ?>>Update Password</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="account-action-card top-gap">
                <div class="panel-heading">
                    <div>
                        <h3>Active sessions</h3>
                        <p class="muted-copy">Review the devices signed in to your account and revoke any session you do not recognize.</p>
                    </div>

                    <?php if (user_sessions_available() && count($activeSessions) > 1): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="revoke-other-sessions">
                            <button class="button button-secondary" type="submit">Sign Out Other Devices</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!user_sessions_available()): ?>
                    <div class="inline-message top-gap-sm">
                        <strong>Session controls pending</strong>
                        <p>Run the user session migration to enable device-level session management.</p>
                    </div>
                <?php elseif ($activeSessions === []): ?>
                    <p class="muted-copy top-gap-sm">No active session records were found.</p>
                <?php else: ?>
                    <div class="session-list top-gap-sm">
                        <?php foreach ($activeSessions as $session): ?>
                            <?php $isCurrentSession = (string) ($session['session_id'] ?? '') === session_id(); ?>
                            <article class="session-card">
                                <div class="session-card-body">
                                    <div class="session-card-top">
                                        <div>
                                            <strong><?= $isCurrentSession ? 'Current device' : 'Signed-in device'; ?></strong>
                                            <p class="muted-copy"><?= e((string) ($session['user_agent'] ?: 'Browser details unavailable')); ?></p>
                                        </div>
                                        <?php if ($isCurrentSession): ?>
                                            <span class="profile-badge session-badge">Current</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="session-meta-grid">
                                        <div class="profile-meta-card">
                                            <span>Last active</span>
                                            <strong><?= e(format_event_datetime((string) $session['last_seen_at'])); ?></strong>
                                        </div>
                                        <div class="profile-meta-card">
                                            <span>Expires</span>
                                            <strong><?= e(format_event_datetime((string) $session['expires_at'])); ?></strong>
                                        </div>
                                        <div class="profile-meta-card">
                                            <span>IP address</span>
                                            <strong><?= e((string) ($session['ip_address'] ?: 'Unknown')); ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!$isCurrentSession): ?>
                                    <form method="post" class="session-card-action">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="revoke-session">
                                        <input type="hidden" name="session_record_id" value="<?= e((string) $session['id']); ?>">
                                        <button class="button button-secondary" type="submit">Sign Out</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
