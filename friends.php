<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Friends';
$activePage = 'friends';
$user = refresh_current_user();
$pageError = null;
$inviteError = null;
$inviteLink = null;
$friends = [];
$incomingInvites = [];
$sentInvites = [];

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'send-invite') {
            enforce_rate_limit(rate_limit_key('friend-invite', (string) ($user['id'] ?? 0)), 5, 900);
            $email = trim((string) ($_POST['recipient_email'] ?? ''));
            $invite = create_friend_invite_record((int) $user['id'], $email);
            $inviteLink = app_url('accept-friend.php', true) . '?token=' . urlencode((string) ($invite['share_token'] ?? ''));
            set_flash('Friend invite created.', 'success');
        } elseif ($action === 'accept-invite') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            accept_friend_invite_record($inviteId, (int) $user['id'], (string) $user['email']);
            set_flash('Friend request accepted.', 'success');
            redirect('friends.php');
        } elseif ($action === 'decline-invite') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            decline_friend_invite_record($inviteId, (int) $user['id'], (string) $user['email']);
            set_flash('Friend request declined.', 'success');
            redirect('friends.php');
        } elseif ($action === 'cancel-invite') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            cancel_friend_invite_record($inviteId, (int) $user['id']);
            set_flash('Friend invite cancelled.', 'success');
            redirect('friends.php');
        }
    } catch (Throwable $exception) {
        if ($action === 'send-invite') {
            $inviteError = $exception->getMessage();
        } else {
            $pageError = $exception->getMessage();
        }
    }
}

try {
    $friends = fetch_friendships_for_user((int) $user['id']);
    $incomingInvites = fetch_pending_friend_invites_for_user((int) $user['id'], (string) $user['email']);
    $sentInvites = fetch_sent_friend_invites((int) $user['id']);
} catch (Throwable $exception) {
    $pageError = 'Friend connections could not be loaded because the database is unavailable.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Friends</p>
            <h1>Invite friends and grow your circle</h1>
            <p>Send invite links, accept requests, and keep your study community close.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="card-grid card-grid-3">
            <article class="stat-card">
                <p class="eyebrow">Friends</p>
                <h3><?= e((string) count($friends)); ?></h3>
                <p class="muted-copy">Confirmed connections</p>
            </article>
            <article class="stat-card">
                <p class="eyebrow">Incoming</p>
                <h3><?= e((string) count($incomingInvites)); ?></h3>
                <p class="muted-copy">Requests waiting on you</p>
            </article>
            <article class="stat-card">
                <p class="eyebrow">Sent</p>
                <h3><?= e((string) count($sentInvites)); ?></h3>
                <p class="muted-copy">Invites you have sent</p>
            </article>
        </div>

        <div class="community-layout top-gap">
            <div class="stack-list">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Your friends</h2>
                            <p class="muted-copy">People you are already connected with in the app.</p>
                        </div>
                        <span class="mini-card"><?= e((string) count($friends)); ?> total</span>
                    </div>

                    <?php if ($friends === []): ?>
                        <p class="empty-state">No friends yet. Send the first invite from the panel.</p>
                    <?php else: ?>
                        <div class="card-grid card-grid-2 top-gap-sm">
                            <?php foreach ($friends as $friend): ?>
                                <article class="bookmark-card friend-card">
                                    <div class="profile-hero">
                                        <?php if (!empty($friend['friend_avatar_url'])): ?>
                                            <img class="profile-avatar" src="<?= e((string) $friend['friend_avatar_url']); ?>" alt="<?= e((string) $friend['friend_name']); ?> avatar">
                                        <?php else: ?>
                                            <div class="profile-avatar profile-avatar-fallback"><?= e(profile_initials((string) $friend['friend_name'])); ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= e((string) $friend['friend_name']); ?></strong>
                                            <p class="muted-copy"><?= e((string) $friend['friend_city'] ?: (string) $friend['friend_email']); ?></p>
                                        </div>
                                    </div>
                                    <p class="muted-copy">Connected <?= e(date('M j, Y', strtotime((string) $friend['created_at']))); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Incoming requests</h2>
                            <p class="muted-copy">Accept or decline requests sent to your email.</p>
                        </div>
                    </div>

                    <?php if ($incomingInvites === []): ?>
                        <p class="empty-state">No incoming friend requests right now.</p>
                    <?php else: ?>
                        <?php foreach ($incomingInvites as $invite): ?>
                            <article class="list-card list-card-block">
                                <div class="planner-item-body">
                                    <div class="planner-item-header">
                                        <div>
                                            <strong><?= e((string) $invite['sender_name']); ?></strong>
                                            <span class="muted-copy"><?= e((string) $invite['sender_email']); ?></span>
                                        </div>
                                        <span class="pill"><?= e(date('M j', strtotime((string) $invite['created_at']))); ?></span>
                                    </div>
                                    <div class="inline-actions top-gap-sm">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="accept-invite">
                                            <input type="hidden" name="invite_id" value="<?= e((string) $invite['id']); ?>">
                                            <button class="button button-primary" type="submit">Accept</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="decline-invite">
                                            <input type="hidden" name="invite_id" value="<?= e((string) $invite['id']); ?>">
                                            <button class="button button-secondary" type="submit">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Sent invites</h2>
                            <p class="muted-copy">Track requests you already sent and copy the link if needed.</p>
                        </div>
                    </div>

                    <?php if ($sentInvites === []): ?>
                        <p class="empty-state">No sent invites yet.</p>
                    <?php else: ?>
                        <?php foreach ($sentInvites as $invite): ?>
                            <article class="list-card list-card-block">
                                <div class="planner-item-body">
                                    <div class="planner-item-header">
                                        <div>
                                            <strong><?= e((string) $invite['recipient_name'] ?: (string) $invite['recipient_email']); ?></strong>
                                            <span class="muted-copy"><?= e((string) $invite['recipient_email']); ?></span>
                                        </div>
                                        <span class="pill pill-dark"><?= e(ucfirst((string) $invite['status'])); ?></span>
                                    </div>
                                    <?php if ((string) $invite['status'] === 'pending'): ?>
                                        <div class="inline-message">
                                            <strong>Invite status</strong>
                                            <p>Pending invite recorded. For security, the share link is only shown when the invite is first created.</p>
                                        </div>
                                        <div class="inline-actions top-gap-sm">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="cancel-invite">
                                                <input type="hidden" name="invite_id" value="<?= e((string) $invite['id']); ?>">
                                                <button class="button button-secondary" type="submit">Cancel Invite</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>

            <div class="stack-list community-manager-panel">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Invite a friend</h2>
                            <p class="muted-copy">Invite someone by email and share the generated friend link.</p>
                        </div>
                    </div>

                    <?php if ($inviteError): ?>
                        <div class="flash flash-warning"><?= e($inviteError); ?></div>
                    <?php endif; ?>

                    <form class="form-stack compact-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="send-invite">

                        <label>
                            Friend email
                            <input type="email" name="recipient_email" placeholder="friend@example.com" required>
                        </label>

                        <button class="button button-primary" type="submit">Create Invite</button>
                    </form>

                    <?php if ($inviteLink): ?>
                        <div class="inline-message top-gap-sm">
                            <strong>Share this link</strong>
                            <p><a href="<?= e($inviteLink); ?>"><?= e($inviteLink); ?></a></p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
