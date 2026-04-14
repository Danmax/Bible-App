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
$showInvitePanel = false;
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$searchResults = [];
$showFindPanel = $searchQuery !== '';

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

            if (mailer_enabled()) {
                try {
                    send_friend_invite_email((string) ($user['name'] ?? ''), $email, $inviteLink);
                    set_flash('Friend invite created and emailed.', 'success');
                } catch (Throwable $mailException) {
                    $inviteError = 'Friend invite created, but the email could not be delivered. Share the link below instead.';
                }
            } else {
                set_flash('Friend invite created. Share the link below.', 'success');
            }
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
        } elseif ($action === 'send-direct-invite') {
            enforce_rate_limit(rate_limit_key('friend-invite', (string) ($user['id'] ?? 0)), 5, 900);
            $targetUserId = (int) ($_POST['target_user_id'] ?? 0);

            if ($targetUserId <= 0 || $targetUserId === (int) $user['id']) {
                throw new RuntimeException('Invalid user selected.');
            }

            $targetUser = fetch_user_by_id($targetUserId);

            if ($targetUser === null) {
                throw new RuntimeException('That user could not be found.');
            }

            create_friend_invite_record((int) $user['id'], (string) $targetUser['email']);
            set_flash('Friend request sent to ' . $targetUser['name'] . '.', 'success');
            redirect('friends.php' . ($searchQuery !== '' ? '?q=' . urlencode($searchQuery) : ''));
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

// Build exclusion sets so search results show meaningful states
$friendUserIds = array_map(static fn(array $f): int => (int) $f['friend_user_id'], $friends);
$sentInviteEmails = array_map(static fn(array $i): string => mb_strtolower((string) $i['recipient_email']), $sentInvites);
$incomingInviteEmails = array_map(static fn(array $i): string => mb_strtolower((string) $i['sender_email']), $incomingInvites);

if ($searchQuery !== '') {
    try {
        $searchResults = search_users_by_name($searchQuery);
    } catch (Throwable $exception) {
        $pageError = 'Search could not be completed.';
    }
}

$showInvitePanel = $inviteError !== null || $inviteLink !== null;

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

        <div class="two-column top-gap">
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

            <div class="stack-list" data-community-panels>
                <div class="community-action-bar">
                    <button class="button button-primary" type="button" data-community-panel-toggle="find" aria-expanded="<?= $showFindPanel ? 'true' : 'false'; ?>">
                        Find People
                    </button>
                    <button class="button button-secondary" type="button" data-community-panel-toggle="invite" aria-expanded="<?= $showInvitePanel ? 'true' : 'false'; ?>">
                        Invite by Email
                    </button>
                </div>

                <section class="panel community-manager-panel" data-community-panel="find" <?= $showFindPanel ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>>
                    <div class="panel-heading">
                        <div>
                            <h2>Find people</h2>
                            <p class="muted-copy">Search for members by name and send a friend request.</p>
                        </div>
                        <button class="button button-secondary" type="button" data-community-panel-close="find">Close</button>
                    </div>

                    <form class="inline-actions top-gap-sm" method="get" action="<?= e(app_url('friends.php')); ?>">
                        <input
                            type="search"
                            name="q"
                            value="<?= e($searchQuery); ?>"
                            placeholder="Search by name…"
                            required
                            autofocus
                            style="flex:1"
                        >
                        <button class="button button-primary" type="submit">Search</button>
                    </form>

                    <?php if ($searchQuery !== ''): ?>
                        <div class="stack-list top-gap-sm">
                            <?php
                            $currentUserId = (int) $user['id'];
                            $currentUserEmail = mb_strtolower((string) $user['email']);
                            $visibleResults = array_filter(
                                $searchResults,
                                static fn(array $u): bool => (int) $u['id'] !== $currentUserId
                            );
                            ?>
                            <?php if ($visibleResults === []): ?>
                                <p class="empty-state">No members found for "<?= e($searchQuery); ?>".</p>
                            <?php else: ?>
                                <?php foreach ($visibleResults as $found): ?>
                                    <?php
                                    $foundId = (int) $found['id'];
                                    $foundEmail = mb_strtolower((string) ($found['email'] ?? ''));
                                    $isAlreadyFriend = in_array($foundId, $friendUserIds, true);
                                    $hasSentInvite = in_array($foundEmail, $sentInviteEmails, true);
                                    $hasIncomingInvite = in_array($foundEmail, $incomingInviteEmails, true);
                                    ?>
                                    <div class="list-card list-card-block">
                                        <div class="profile-hero">
                                            <?php if (!empty($found['avatar_url'])): ?>
                                                <img class="profile-avatar" src="<?= e((string) $found['avatar_url']); ?>" alt="<?= e((string) $found['name']); ?>">
                                            <?php else: ?>
                                                <div class="profile-avatar profile-avatar-fallback"><?= e(profile_initials((string) $found['name'])); ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <strong>
                                                    <?= e((string) $found['name']); ?>
                                                    <?php if (!empty($found['primary_flag'])): ?>
                                                        <?= e((string) $found['primary_flag']); ?>
                                                    <?php endif; ?>
                                                </strong>
                                                <?php if (!empty($found['city'])): ?>
                                                    <p class="muted-copy"><?= e((string) $found['city']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($isAlreadyFriend): ?>
                                                <span class="pill">Friends</span>
                                            <?php elseif ($hasSentInvite): ?>
                                                <span class="pill pill-dark">Request sent</span>
                                            <?php elseif ($hasIncomingInvite): ?>
                                                <span class="pill pill-dark">Wants to connect</span>
                                            <?php else: ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="send-direct-invite">
                                                    <input type="hidden" name="target_user_id" value="<?= e((string) $foundId); ?>">
                                                    <button class="button button-primary button-sm" type="submit">Add Friend</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="panel community-manager-panel" data-community-panel="invite" <?= $showInvitePanel ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>>
                    <div class="panel-heading">
                        <div>
                            <h2>Invite a friend</h2>
                            <p class="muted-copy">Invite someone by email and share the generated friend link.</p>
                        </div>
                        <button class="button button-secondary" type="button" data-community-panel-close="invite">Close</button>
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

                        <div class="inline-actions">
                            <button class="button button-primary" type="submit">Create Invite</button>
                            <button class="button button-secondary" type="button" data-community-panel-close="invite">Cancel</button>
                        </div>
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
