<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function fetch_user_by_email(string $email): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => mb_strtolower(trim($email))]);
    $user = $statement->fetch();

    return $user ?: null;
}

function fetch_user_by_id(int $userId): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function canonical_friend_pair(int $userId, int $friendUserId): array
{
    return $userId < $friendUserId
        ? [$userId, $friendUserId]
        : [$friendUserId, $userId];
}

function fetch_friendship_between_users(int $userId, int $friendUserId): ?array
{
    [$userOneId, $userTwoId] = canonical_friend_pair($userId, $friendUserId);
    $statement = db()->prepare(
        'SELECT *
        FROM friendships
        WHERE user_one_id = :user_one_id
            AND user_two_id = :user_two_id
        LIMIT 1'
    );
    $statement->execute([
        'user_one_id' => $userOneId,
        'user_two_id' => $userTwoId,
    ]);

    $friendship = $statement->fetch();

    return $friendship ?: null;
}

function fetch_friendships_for_user(int $userId): array
{
    $statement = db()->prepare(
        'SELECT friendships.id, friendships.created_at,
            CASE
                WHEN friendships.user_one_id = :user_id_case THEN friendships.user_two_id
                ELSE friendships.user_one_id
            END AS friend_user_id,
            users.name AS friend_name,
            users.email AS friend_email,
            users.city AS friend_city,
            users.avatar_url AS friend_avatar_url
        FROM friendships
        INNER JOIN users ON users.id = CASE
            WHEN friendships.user_one_id = :user_id_join THEN friendships.user_two_id
            ELSE friendships.user_one_id
        END
        WHERE friendships.user_one_id = :user_id_one
            OR friendships.user_two_id = :user_id_two
        ORDER BY users.name ASC'
    );
    $statement->execute([
        'user_id_case' => $userId,
        'user_id_join' => $userId,
        'user_id_one' => $userId,
        'user_id_two' => $userId,
    ]);

    return $statement->fetchAll();
}

function fetch_sent_friend_invites(int $userId): array
{
    $statement = db()->prepare(
        'SELECT friend_invites.*, users.name AS recipient_name
        FROM friend_invites
        LEFT JOIN users ON users.id = friend_invites.recipient_user_id
        WHERE friend_invites.sender_user_id = :user_id
        ORDER BY friend_invites.created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_pending_friend_invites_for_user(int $userId, string $email): array
{
    $statement = db()->prepare(
        'SELECT friend_invites.*, sender.name AS sender_name, sender.email AS sender_email
        FROM friend_invites
        INNER JOIN users AS sender ON sender.id = friend_invites.sender_user_id
        WHERE friend_invites.status = :status
            AND friend_invites.expires_at >= NOW()
            AND (
                friend_invites.recipient_user_id = :recipient_user_id
                OR (
                    friend_invites.recipient_user_id IS NULL
                    AND friend_invites.recipient_email = :recipient_email
                )
            )
        ORDER BY friend_invites.created_at DESC'
    );
    $statement->execute([
        'status' => 'pending',
        'recipient_user_id' => $userId,
        'recipient_email' => mb_strtolower(trim($email)),
    ]);

    return $statement->fetchAll();
}

function fetch_friend_invite_by_id(int $inviteId): ?array
{
    $statement = db()->prepare(
        'SELECT friend_invites.*, sender.name AS sender_name, sender.email AS sender_email,
            recipient.name AS recipient_name
        FROM friend_invites
        INNER JOIN users AS sender ON sender.id = friend_invites.sender_user_id
        LEFT JOIN users AS recipient ON recipient.id = friend_invites.recipient_user_id
        WHERE friend_invites.id = :id
        LIMIT 1'
    );
    $statement->execute(['id' => $inviteId]);

    $invite = $statement->fetch();

    return $invite ?: null;
}

function fetch_friend_invite_by_token(string $token): ?array
{
    $trimmedToken = trim($token);

    if ($trimmedToken === '') {
        return null;
    }

    $usesHashedTokens = friend_invites_use_hashed_tokens();
    $statement = db()->prepare(
        'SELECT friend_invites.*, sender.name AS sender_name, sender.email AS sender_email,
            recipient.name AS recipient_name
        FROM friend_invites
        INNER JOIN users AS sender ON sender.id = friend_invites.sender_user_id
        LEFT JOIN users AS recipient ON recipient.id = friend_invites.recipient_user_id
        WHERE ' . ($usesHashedTokens
            ? 'friend_invites.invite_token_hash = :token_hash'
            : 'friend_invites.invite_token = :token') . '
            AND friend_invites.status = :status
            AND friend_invites.expires_at >= NOW()
        LIMIT 1'
    );
    $params = ['status' => 'pending'];

    if ($usesHashedTokens) {
        $params['token_hash'] = hash('sha256', $trimmedToken);
    } else {
        $params['token'] = $trimmedToken;
    }

    $statement->execute($params);

    $invite = $statement->fetch();

    return $invite ?: null;
}

function create_friend_invite_record(int $senderUserId, string $recipientEmail): array
{
    $sender = fetch_user_by_id($senderUserId);

    if ($sender === null) {
        throw new RuntimeException('The sender account could not be found.');
    }

    $normalizedEmail = mb_strtolower(trim($recipientEmail));

    if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address for the invite.');
    }

    if ($normalizedEmail === mb_strtolower((string) $sender['email'])) {
        throw new RuntimeException('You cannot invite your own account.');
    }

    $recipient = fetch_user_by_email($normalizedEmail);

    if ($recipient !== null && fetch_friendship_between_users($senderUserId, (int) $recipient['id']) !== null) {
        throw new RuntimeException('You are already friends with that user.');
    }

    $statement = db()->prepare(
        'SELECT id
        FROM friend_invites
        WHERE sender_user_id = :sender_user_id
            AND recipient_email = :recipient_email
            AND status = :status
            AND expires_at >= NOW()
        LIMIT 1'
    );
    $statement->execute([
        'sender_user_id' => $senderUserId,
        'recipient_email' => $normalizedEmail,
        'status' => 'pending',
    ]);

    if ($statement->fetchColumn() !== false) {
        throw new RuntimeException('A pending invite already exists for that email.');
    }

    $token = bin2hex(random_bytes(24));
    $usesHashedTokens = friend_invites_use_hashed_tokens();
    $sql = 'INSERT INTO friend_invites (
            sender_user_id,
            recipient_user_id,
            recipient_email, ' .
            ($usesHashedTokens ? 'invite_token_hash' : 'invite_token') . ',
            status,
            expires_at
        ) VALUES (
            :sender_user_id,
            :recipient_user_id,
            :recipient_email, ' .
            ($usesHashedTokens ? ':invite_token_hash' : ':invite_token') . ',
            :status,
            :expires_at
        )';
    $statement = db()->prepare($sql);
    $params = [
        'sender_user_id' => $senderUserId,
        'recipient_user_id' => $recipient['id'] ?? null,
        'recipient_email' => $normalizedEmail,
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
    ];

    if ($usesHashedTokens) {
        $params['invite_token_hash'] = hash('sha256', $token);
    } else {
        $params['invite_token'] = $token;
    }

    $statement->execute($params);

    $invite = fetch_friend_invite_by_id((int) db()->lastInsertId());

    if ($invite === null) {
        throw new RuntimeException('The friend invite could not be created.');
    }

    $invite['share_token'] = $token;

    return $invite;
}

function create_friendship_record(int $userId, int $friendUserId): void
{
    if ($userId === $friendUserId) {
        throw new RuntimeException('You cannot friend your own account.');
    }

    if (fetch_friendship_between_users($userId, $friendUserId) !== null) {
        return;
    }

    [$userOneId, $userTwoId] = canonical_friend_pair($userId, $friendUserId);
    $statement = db()->prepare(
        'INSERT INTO friendships (user_one_id, user_two_id) VALUES (:user_one_id, :user_two_id)'
    );
    $statement->execute([
        'user_one_id' => $userOneId,
        'user_two_id' => $userTwoId,
    ]);
}

function accept_friend_invite_record(int $inviteId, int $recipientUserId, string $recipientEmail): void
{
    $invite = fetch_friend_invite_by_id($inviteId);

    if ($invite === null || (string) $invite['status'] !== 'pending' || strtotime((string) $invite['expires_at']) < time()) {
        throw new RuntimeException('That friend invite is no longer available.');
    }

    $normalizedEmail = mb_strtolower(trim($recipientEmail));

    if (
        (int) ($invite['recipient_user_id'] ?? 0) !== 0
        && (int) $invite['recipient_user_id'] !== $recipientUserId
    ) {
        throw new RuntimeException('That invite is assigned to another account.');
    }

    if (
        (int) ($invite['recipient_user_id'] ?? 0) === 0
        && mb_strtolower((string) $invite['recipient_email']) !== $normalizedEmail
    ) {
        throw new RuntimeException('Sign in with the invited email address to accept this invite.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        create_friendship_record((int) $invite['sender_user_id'], $recipientUserId);

        $statement = $pdo->prepare(
            'UPDATE friend_invites
            SET recipient_user_id = :recipient_user_id,
                status = :status,
                responded_at = NOW()
            WHERE id = :id'
        );
        $statement->execute([
            'recipient_user_id' => $recipientUserId,
            'status' => 'accepted',
            'id' => $inviteId,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function decline_friend_invite_record(int $inviteId, int $recipientUserId, string $recipientEmail): void
{
    $invite = fetch_friend_invite_by_id($inviteId);

    if ($invite === null || (string) $invite['status'] !== 'pending') {
        throw new RuntimeException('That friend invite is no longer available.');
    }

    $normalizedEmail = mb_strtolower(trim($recipientEmail));

    if (
        (int) ($invite['recipient_user_id'] ?? 0) !== 0
        && (int) $invite['recipient_user_id'] !== $recipientUserId
    ) {
        throw new RuntimeException('That invite is assigned to another account.');
    }

    if (
        (int) ($invite['recipient_user_id'] ?? 0) === 0
        && mb_strtolower((string) $invite['recipient_email']) !== $normalizedEmail
    ) {
        throw new RuntimeException('Sign in with the invited email address to respond to this invite.');
    }

    $statement = db()->prepare(
        'UPDATE friend_invites
        SET recipient_user_id = :recipient_user_id,
            status = :status,
            responded_at = NOW()
        WHERE id = :id'
    );
    $statement->execute([
        'recipient_user_id' => $recipientUserId,
        'status' => 'declined',
        'id' => $inviteId,
    ]);
}

function cancel_friend_invite_record(int $inviteId, int $senderUserId): void
{
    $statement = db()->prepare(
        'UPDATE friend_invites
        SET status = :status,
            responded_at = NOW()
        WHERE id = :id
            AND sender_user_id = :sender_user_id
            AND status = :pending_status'
    );
    $statement->execute([
        'status' => 'cancelled',
        'id' => $inviteId,
        'sender_user_id' => $senderUserId,
        'pending_status' => 'pending',
    ]);
}

function create_user(string $name, string $email, string $password): array
{
    $normalizedEmail = mb_strtolower(trim($email));

    $statement = db()->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
    );
    $statement->execute([
        'name' => trim($name),
        'email' => $normalizedEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'member',
    ]);

    $user = fetch_user_by_id((int) db()->lastInsertId());

    if ($user === null) {
        throw new RuntimeException('User record was not created.');
    }

    return $user;
}

function update_user_profile_record(
    int $userId,
    string $name,
    string $email,
    ?string $city = null,
    ?string $avatarUrl = null,
    ?string $primaryFlag = null,
    ?string $secondaryFlag = null
): array
{
    $statement = db()->prepare(
        'UPDATE users
        SET name = :name,
            email = :email,
            city = :city,
            avatar_url = :avatar_url,
            primary_flag = :primary_flag,
            secondary_flag = :secondary_flag
        WHERE id = :id'
    );
    $statement->execute([
        'id' => $userId,
        'name' => trim($name),
        'email' => mb_strtolower(trim($email)),
        'city' => normalize_optional_text($city ?? ''),
        'avatar_url' => normalize_optional_text($avatarUrl ?? ''),
        'primary_flag' => normalize_optional_text($primaryFlag ?? ''),
        'secondary_flag' => normalize_optional_text($secondaryFlag ?? ''),
    ]);

    $user = fetch_user_by_id($userId);

    if ($user === null) {
        throw new RuntimeException('User record was not found after update.');
    }

    return $user;
}

function update_user_password_record(int $userId, string $password): void
{
    $statement = db()->prepare(
        'UPDATE users SET password_hash = :password_hash WHERE id = :id'
    );
    $statement->execute([
        'id' => $userId,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function find_user_for_login(string $email, string $password): ?array
{
    $user = fetch_user_by_email($email);

    if ($user === null) {
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

function create_password_reset_token(int $userId): string
{
    db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')
        ->execute(['user_id' => $userId]);

    $token = bin2hex(random_bytes(24));
    $statement = db()->prepare(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
    );
    $statement->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    ]);

    record_audit_event(null, 'password_reset.requested', $userId, [
        'reset_window_minutes' => 60,
    ]);

    return $token;
}

function fetch_password_reset_token(string $token): ?array
{
    $statement = db()->prepare(
        'SELECT password_reset_tokens.*, users.email
        FROM password_reset_tokens
        INNER JOIN users ON users.id = password_reset_tokens.user_id
        WHERE token_hash = :token_hash
            AND used_at IS NULL
            AND expires_at >= NOW()
        LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash('sha256', $token),
    ]);

    $record = $statement->fetch();

    return $record ?: null;
}

function reset_user_password_with_token(string $token, string $password): ?int
{
    $record = fetch_password_reset_token($token);

    if ($record === null) {
        return null;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        update_user_password_record((int) $record['user_id'], $password);

        $statement = $pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $record['id']]);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    record_audit_event(null, 'password_reset.completed', (int) $record['user_id'], [
        'password_reset_token_id' => (int) $record['id'],
    ]);

    return (int) $record['user_id'];
}

function create_email_change_token(int $userId, string $newEmail): string
{
    $normalizedEmail = mb_strtolower(trim($newEmail));

    db()->prepare('DELETE FROM email_change_tokens WHERE user_id = :user_id')
        ->execute(['user_id' => $userId]);

    $token = bin2hex(random_bytes(24));
    $statement = db()->prepare(
        'INSERT INTO email_change_tokens (user_id, new_email, token_hash, expires_at)
        VALUES (:user_id, :new_email, :token_hash, :expires_at)'
    );
    $statement->execute([
        'user_id' => $userId,
        'new_email' => $normalizedEmail,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    ]);

    record_audit_event($userId, 'email_change.requested', $userId, [
        'new_email' => $normalizedEmail,
        'approval_window_minutes' => 60,
    ]);

    return $token;
}

function fetch_email_change_token(string $token): ?array
{
    $statement = db()->prepare(
        'SELECT email_change_tokens.*, users.email AS current_email, users.name
        FROM email_change_tokens
        INNER JOIN users ON users.id = email_change_tokens.user_id
        WHERE token_hash = :token_hash
            AND used_at IS NULL
            AND expires_at >= NOW()
        LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash('sha256', $token),
    ]);

    $record = $statement->fetch();

    return $record ?: null;
}

function fetch_pending_email_change_request(int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM email_change_tokens
        WHERE user_id = :user_id
            AND used_at IS NULL
            AND expires_at >= NOW()
        ORDER BY created_at DESC
        LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);

    $record = $statement->fetch();

    return $record ?: null;
}

function confirm_email_change_with_token(string $token): ?array
{
    $record = fetch_email_change_token($token);

    if ($record === null) {
        return null;
    }

    $existingUser = fetch_user_by_email((string) $record['new_email']);

    if ($existingUser !== null && (int) $existingUser['id'] !== (int) $record['user_id']) {
        throw new RuntimeException('That email address is already in use.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare('UPDATE users SET email = :email WHERE id = :id');
        $statement->execute([
            'email' => (string) $record['new_email'],
            'id' => (int) $record['user_id'],
        ]);

        $statement = $pdo->prepare('UPDATE email_change_tokens SET used_at = NOW() WHERE id = :id');
        $statement->execute(['id' => (int) $record['id']]);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    record_audit_event((int) $record['user_id'], 'email_change.confirmed', (int) $record['user_id'], [
        'previous_email' => (string) $record['current_email'],
        'new_email' => (string) $record['new_email'],
    ]);

    return fetch_user_by_id((int) $record['user_id']);
}

function fetch_dashboard_stats(int $userId): array
{
    return [
        'bookmarks' => count_records(
            'SELECT COUNT(*) FROM bookmarks WHERE user_id = :user_id',
            ['user_id' => $userId]
        ),
        'notes' => count_records(
            'SELECT COUNT(*) FROM study_notes WHERE user_id = :user_id',
            ['user_id' => $userId]
        ),
        'goals' => count_records(
            'SELECT COUNT(*) FROM yearly_goals WHERE user_id = :user_id AND status = :status',
            ['user_id' => $userId, 'status' => 'active']
        ),
        'events' => count_records(
            'SELECT COUNT(*) FROM community_events WHERE status = :status AND start_at >= NOW()',
            ['status' => 'published']
        ),
    ];
}

function fetch_recent_notes(int $userId, int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT study_notes.*, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM study_notes
        LEFT JOIN verses ON verses.id = study_notes.verse_id
        LEFT JOIN books ON books.id = verses.book_id
        WHERE study_notes.user_id = :user_id
        ORDER BY study_notes.updated_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_recent_bookmarks(int $userId, int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_upcoming_events(int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT community_events.*, community_event_categories.label AS category_label
        FROM community_events
        LEFT JOIN community_event_categories ON community_event_categories.id = community_events.category_id
        WHERE community_events.status = :status
            AND community_events.start_at >= NOW()
        ORDER BY community_events.start_at ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['status' => 'published']);

    return $statement->fetchAll();
}

function fetch_yearly_goals_for_user(int $userId, int $year): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM yearly_goals
        WHERE user_id = :user_id
            AND year = :year
        ORDER BY
            CASE status
                WHEN :active_status THEN 0
                WHEN :paused_status THEN 1
                WHEN :completed_status THEN 2
                ELSE 3
            END,
            updated_at DESC,
            id DESC'
    );
    $statement->execute([
        'user_id' => $userId,
        'year' => $year,
        'active_status' => 'active',
        'paused_status' => 'paused',
        'completed_status' => 'completed',
    ]);

    return $statement->fetchAll();
}

function fetch_yearly_goal_by_id(int $goalId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM yearly_goals
        WHERE id = :id
            AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $goalId,
        'user_id' => $userId,
    ]);

    $goal = $statement->fetch();

    return $goal ?: null;
}

function create_yearly_goal_record(int $userId, array $payload): int
{
    $statement = db()->prepare(
        'INSERT INTO yearly_goals (user_id, year, goal_title, goal_type, target_value, current_value, status)
        VALUES (:user_id, :year, :goal_title, :goal_type, :target_value, :current_value, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'year' => $payload['year'],
        'goal_title' => $payload['goal_title'],
        'goal_type' => $payload['goal_type'],
        'target_value' => $payload['target_value'],
        'current_value' => $payload['current_value'],
        'status' => $payload['status'],
    ]);

    return (int) db()->lastInsertId();
}

function update_yearly_goal_record(int $goalId, int $userId, array $payload): void
{
    $statement = db()->prepare(
        'UPDATE yearly_goals
        SET year = :year,
            goal_title = :goal_title,
            goal_type = :goal_type,
            target_value = :target_value,
            current_value = :current_value,
            status = :status
        WHERE id = :id
            AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $goalId,
        'user_id' => $userId,
        'year' => $payload['year'],
        'goal_title' => $payload['goal_title'],
        'goal_type' => $payload['goal_type'],
        'target_value' => $payload['target_value'],
        'current_value' => $payload['current_value'],
        'status' => $payload['status'],
    ]);
}

function delete_yearly_goal_record(int $goalId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM yearly_goals WHERE id = :id AND user_id = :user_id');
    $statement->execute([
        'id' => $goalId,
        'user_id' => $userId,
    ]);
}

function fetch_planner_events_for_user(int $userId, int $limit = 50): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM planner_events
        WHERE user_id = :user_id
        ORDER BY event_date ASC, id ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_planner_events_between(int $userId, string $startDateTime, string $endDateTime): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM planner_events
        WHERE user_id = :user_id
            AND event_date >= :start_date
            AND event_date < :end_date
        ORDER BY event_date ASC, id ASC'
    );
    $statement->execute([
        'user_id' => $userId,
        'start_date' => $startDateTime,
        'end_date' => $endDateTime,
    ]);

    return $statement->fetchAll();
}

function fetch_planner_event_by_id(int $eventId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM planner_events
        WHERE id = :id
            AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $eventId,
        'user_id' => $userId,
    ]);

    $event = $statement->fetch();

    return $event ?: null;
}

function create_planner_event_record(int $userId, array $payload): int
{
    $statement = db()->prepare(
        'INSERT INTO planner_events (user_id, title, description, event_date, event_type, related_community_event_id)
        VALUES (:user_id, :title, :description, :event_date, :event_type, :related_community_event_id)'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => $payload['title'],
        'description' => $payload['description'],
        'event_date' => $payload['event_date'],
        'event_type' => $payload['event_type'],
        'related_community_event_id' => $payload['related_community_event_id'],
    ]);

    return (int) db()->lastInsertId();
}

function update_planner_event_record(int $eventId, int $userId, array $payload): void
{
    $statement = db()->prepare(
        'UPDATE planner_events
        SET title = :title,
            description = :description,
            event_date = :event_date,
            event_type = :event_type,
            related_community_event_id = :related_community_event_id
        WHERE id = :id
            AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $eventId,
        'user_id' => $userId,
        'title' => $payload['title'],
        'description' => $payload['description'],
        'event_date' => $payload['event_date'],
        'event_type' => $payload['event_type'],
        'related_community_event_id' => $payload['related_community_event_id'],
    ]);
}

function delete_planner_event_record(int $eventId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM planner_events WHERE id = :id AND user_id = :user_id');
    $statement->execute([
        'id' => $eventId,
        'user_id' => $userId,
    ]);
}

function fetch_planner_schedule(int $userId, int $limit = 8): array
{
    $statement = db()->prepare(
        'SELECT
            id,
            title,
            description,
            event_date,
            event_type,
            related_community_event_id,
            created_at,
            updated_at,
            :source AS source
        FROM planner_events
        WHERE user_id = :user_id
            AND event_date >= NOW() - INTERVAL 1 DAY
        ORDER BY event_date ASC, id ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'source' => 'personal',
        'user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function fetch_prayer_entries_for_user(int $userId, int $limit = 8): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM prayer_entries
        WHERE user_id = :user_id
        ORDER BY
            CASE WHEN status = "active" THEN 0 ELSE 1 END,
            updated_at DESC,
            id DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function create_prayer_entry_record(int $userId, string $title, ?string $details = null, string $status = 'active'): int
{
    $statement = db()->prepare(
        'INSERT INTO prayer_entries (user_id, title, details, status)
        VALUES (:user_id, :title, :details, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => trim($title),
        'details' => normalize_optional_text($details),
        'status' => $status,
    ]);

    return (int) db()->lastInsertId();
}

function update_prayer_entry_status(int $entryId, int $userId, string $status): void
{
    $statement = db()->prepare(
        'UPDATE prayer_entries
        SET status = :status
        WHERE id = :id
            AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $entryId,
        'user_id' => $userId,
        'status' => $status,
    ]);
}

function delete_prayer_entry_record(int $entryId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM prayer_entries WHERE id = :id AND user_id = :user_id');
    $statement->execute([
        'id' => $entryId,
        'user_id' => $userId,
    ]);
}

function summarize_yearly_goals(array $goals): array
{
    $summary = [
        'total' => count($goals),
        'active' => 0,
        'completed' => 0,
        'progress_percent' => 0,
    ];

    $percentages = [];

    foreach ($goals as $goal) {
        $status = (string) ($goal['status'] ?? 'active');

        if ($status === 'active') {
            $summary['active']++;
        }

        if ($status === 'completed') {
            $summary['completed']++;
        }

        $percent = calculate_goal_progress_percent($goal);

        if ($percent !== null) {
            $percentages[] = $percent;
        }
    }

    if ($percentages !== []) {
        $summary['progress_percent'] = (int) round(array_sum($percentages) / count($percentages));
    }

    return $summary;
}

function fetch_event_categories(): array
{
    return db()->query(
        'SELECT id, slug, label, icon, color
        FROM community_event_categories
        ORDER BY label ASC'
    )->fetchAll();
}

function community_event_settings_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM community_events LIKE 'settings_json'");
        $available = $statement !== false && $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function community_event_images_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM community_events LIKE 'image_url'");
        $available = $statement !== false && $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function community_event_items_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'community_event_items'");
        $available = $statement !== false && $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function community_event_messages_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'community_event_messages'");
        $available = $statement !== false && $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function community_event_rsvp_preferences_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM community_event_rsvps LIKE 'bring_item_id'");
        $available = $statement !== false && $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function community_event_default_settings(): array
{
    return [
        'format' => 'standard',
        'custom_options' => [],
        'reminders' => [
            'three_days' => true,
            'same_day' => true,
        ],
        'potluck' => [
            'enabled' => false,
            'allow_self_pick' => true,
            'allow_custom_items' => true,
            'allow_host_assign' => true,
        ],
    ];
}

function normalize_community_event_custom_options(array $options): array
{
    $normalized = [];

    foreach ($options as $option) {
        $value = trim((string) $option);

        if ($value === '' || in_array($value, $normalized, true)) {
            continue;
        }

        $normalized[] = $value;
    }

    return array_slice($normalized, 0, 12);
}

function normalize_community_event_settings(array $settings): array
{
    $defaults = community_event_default_settings();
    $format = strtolower(trim((string) ($settings['format'] ?? $defaults['format'])));
    $allowedFormats = ['standard', 'potluck', 'study', 'prayer', 'worship', 'discipleship', 'outreach', 'service', 'fellowship', 'scripture-memory'];

    if (!in_array($format, $allowedFormats, true)) {
        $format = 'standard';
    }

    $customOptions = $settings['custom_options'] ?? [];

    if (!is_array($customOptions)) {
        $customOptions = [];
    }

    $reminders = $settings['reminders'] ?? [];
    $potluck = $settings['potluck'] ?? [];

    return [
        'format' => $format,
        'custom_options' => normalize_community_event_custom_options($customOptions),
        'reminders' => [
            'three_days' => !array_key_exists('three_days', $reminders) || !empty($reminders['three_days']),
            'same_day' => !array_key_exists('same_day', $reminders) || !empty($reminders['same_day']),
        ],
        'potluck' => [
            'enabled' => $format === 'potluck' || !empty($potluck['enabled']),
            'allow_self_pick' => !array_key_exists('allow_self_pick', $potluck) || !empty($potluck['allow_self_pick']),
            'allow_custom_items' => !array_key_exists('allow_custom_items', $potluck) || !empty($potluck['allow_custom_items']),
            'allow_host_assign' => !array_key_exists('allow_host_assign', $potluck) || !empty($potluck['allow_host_assign']),
        ],
    ];
}

function decode_community_event_settings(?string $settingsJson): array
{
    if (!community_event_settings_available() || trim((string) $settingsJson) === '') {
        return community_event_default_settings();
    }

    $decoded = json_decode((string) $settingsJson, true);

    if (!is_array($decoded)) {
        return community_event_default_settings();
    }

    return normalize_community_event_settings($decoded);
}

function encode_community_event_settings(array $settings): ?string
{
    if (!community_event_settings_available()) {
        return null;
    }

    $encoded = json_encode(
        normalize_community_event_settings($settings),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return is_string($encoded) ? $encoded : null;
}

function fetch_community_event_items(int $eventId): array
{
    if (!community_event_items_available()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT community_event_items.*,
            claimed_user.name AS claimed_by_name
        FROM community_event_items
        LEFT JOIN users AS claimed_user
            ON claimed_user.id = community_event_items.claimed_by_user_id
        WHERE community_event_items.community_event_id = :community_event_id
        ORDER BY community_event_items.sort_order ASC, community_event_items.id ASC'
    );
    $statement->execute(['community_event_id' => $eventId]);

    return $statement->fetchAll();
}

function fetch_community_event_items_map(array $eventIds): array
{
    $map = [];

    foreach ($eventIds as $eventId) {
        $map[(int) $eventId] = [];
    }

    if (!community_event_items_available() || $eventIds === []) {
        return $map;
    }

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $statement = db()->prepare(
        'SELECT community_event_items.*,
            claimed_user.name AS claimed_by_name
        FROM community_event_items
        LEFT JOIN users AS claimed_user
            ON claimed_user.id = community_event_items.claimed_by_user_id
        WHERE community_event_items.community_event_id IN (' . $placeholders . ')
        ORDER BY community_event_items.community_event_id ASC, community_event_items.sort_order ASC, community_event_items.id ASC'
    );
    $statement->execute(array_map('intval', $eventIds));

    foreach ($statement->fetchAll() as $item) {
        $map[(int) $item['community_event_id']][] = $item;
    }

    return $map;
}

function fetch_community_event_attendees(int $eventId): array
{
    $sql = 'SELECT community_event_rsvps.*,
            users.name AS attendee_name,
            users.email AS attendee_email,
            users.avatar_url AS attendee_avatar_url';

    if (community_event_items_available()) {
        $sql .= ',
            community_event_items.label AS bring_item_name';
    } else {
        $sql .= ',
            NULL AS bring_item_name';
    }

    $sql .= ' FROM community_event_rsvps
        INNER JOIN users ON users.id = community_event_rsvps.user_id';

    if (community_event_items_available()) {
        $sql .= ' LEFT JOIN community_event_items
            ON community_event_items.id = community_event_rsvps.bring_item_id';
    }

    $sql .= ' WHERE community_event_rsvps.community_event_id = :community_event_id
        ORDER BY
            CASE community_event_rsvps.response
                WHEN \'going\' THEN 0
                WHEN \'interested\' THEN 1
                WHEN \'maybe\' THEN 2
                ELSE 3
            END ASC,
            community_event_rsvps.updated_at DESC';

    $statement = db()->prepare($sql);
    $statement->execute(['community_event_id' => $eventId]);

    return $statement->fetchAll();
}

function fetch_recent_community_event_messages(int $eventId, int $limit = 3): array
{
    if (!community_event_messages_available()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT community_event_messages.*,
            users.name AS sender_name
        FROM community_event_messages
        LEFT JOIN users ON users.id = community_event_messages.sender_user_id
        WHERE community_event_messages.community_event_id = :community_event_id
        ORDER BY community_event_messages.created_at DESC, community_event_messages.id DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['community_event_id' => $eventId]);

    return $statement->fetchAll();
}

function hydrate_community_events(array $events, bool $includeAttendees = false, int $messageLimit = 3): array
{
    if ($events === []) {
        return [];
    }

    $eventIds = array_map(static fn(array $event): int => (int) $event['id'], $events);
    $itemsMap = fetch_community_event_items_map($eventIds);

    foreach ($events as &$event) {
        $event['settings'] = decode_community_event_settings((string) ($event['settings_json'] ?? ''));
        $event['items'] = $itemsMap[(int) $event['id']] ?? [];
        $event['potluck_item_count'] = count($event['items']);
        $event['claimed_item_count'] = count(array_filter(
            $event['items'],
            static fn(array $item): bool => in_array((string) ($item['status'] ?? 'open'), ['claimed', 'assigned'], true)
        ));
        $event['recent_messages'] = fetch_recent_community_event_messages((int) $event['id'], $messageLimit);

        if ($includeAttendees) {
            $event['attendees'] = fetch_community_event_attendees((int) $event['id']);
        }
    }
    unset($event);

    return $events;
}

function fetch_community_event_item_by_id(int $itemId, int $eventId): ?array
{
    if (!community_event_items_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT *
        FROM community_event_items
        WHERE id = :id
            AND community_event_id = :community_event_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $itemId,
        'community_event_id' => $eventId,
    ]);
    $item = $statement->fetch();

    return $item ?: null;
}

function fetch_community_event_by_id(int $eventId, ?int $viewerUserId = null, bool $canManageAll = false): ?array
{
    $viewerId = $viewerUserId ?? 0;
    $viewerIsLoggedIn = $viewerId > 0 ? 1 : 0;
    $userRsvpFields = 'user_rsvp.response AS current_user_rsvp';

    if (community_event_rsvp_preferences_available()) {
        $userRsvpFields .= ',
            user_rsvp.bring_item_id AS current_user_bring_item_id,
            user_rsvp.bring_item_label AS current_user_bring_item_label,
            user_rsvp.bring_item_note AS current_user_bring_item_note,
            user_rsvp.remind_three_days AS current_user_remind_three_days,
            user_rsvp.remind_same_day AS current_user_remind_same_day';
    }

    $statement = db()->prepare(
        'SELECT community_events.*,
            community_event_categories.slug AS category_slug,
            community_event_categories.label AS category_label,
            community_event_categories.color AS category_color,
            users.name AS created_by_name,
            COALESCE(rsvp_counts.going_count, 0) AS going_count,
            COALESCE(rsvp_counts.interested_count, 0) AS interested_count,
            COALESCE(rsvp_counts.maybe_count, 0) AS maybe_count,
            COALESCE(rsvp_counts.not_going_count, 0) AS not_going_count,
            COALESCE(rsvp_counts.total_count, 0) AS total_rsvp_count,
            ' . $userRsvpFields . '
        FROM community_events
        LEFT JOIN community_event_categories
            ON community_event_categories.id = community_events.category_id
        LEFT JOIN users
            ON users.id = community_events.created_by_user_id
        LEFT JOIN (
            SELECT community_event_id,
                SUM(CASE WHEN response = \'going\' THEN 1 ELSE 0 END) AS going_count,
                SUM(CASE WHEN response = \'interested\' THEN 1 ELSE 0 END) AS interested_count,
                SUM(CASE WHEN response = \'maybe\' THEN 1 ELSE 0 END) AS maybe_count,
                SUM(CASE WHEN response = \'not-going\' THEN 1 ELSE 0 END) AS not_going_count,
                COUNT(*) AS total_count
            FROM community_event_rsvps
            GROUP BY community_event_id
        ) AS rsvp_counts
            ON rsvp_counts.community_event_id = community_events.id
        LEFT JOIN community_event_rsvps AS user_rsvp
            ON user_rsvp.community_event_id = community_events.id
            AND user_rsvp.user_id = :viewer_user_id_join
        WHERE community_events.id = :id
            AND (
                :can_manage_all = 1
                OR (
                    :viewer_user_id_owner > 0
                    AND community_events.created_by_user_id = :viewer_user_id_owner_match
                )
                OR (
                    community_events.status <> :draft_status
                    AND (
                        community_events.visibility = :public_visibility
                        OR (
                            community_events.visibility = :members_visibility
                            AND :viewer_is_logged_in = 1
                        )
                        OR (
                            community_events.visibility = :private_visibility
                            AND :viewer_user_id_private > 0
                            AND community_events.created_by_user_id = :viewer_user_id_private_match
                        )
                    )
                )
            )
        LIMIT 1'
    );
    $statement->execute([
        'id' => $eventId,
        'can_manage_all' => $canManageAll ? 1 : 0,
        'draft_status' => 'draft',
        'public_visibility' => 'public',
        'members_visibility' => 'members',
        'private_visibility' => 'private',
        'viewer_is_logged_in' => $viewerIsLoggedIn,
        'viewer_user_id_join' => $viewerId,
        'viewer_user_id_owner' => $viewerId,
        'viewer_user_id_owner_match' => $viewerId,
        'viewer_user_id_private' => $viewerId,
        'viewer_user_id_private_match' => $viewerId,
    ]);
    $event = $statement->fetch();

    if (!$event) {
        return null;
    }

    $events = hydrate_community_events([$event], true);

    return $events[0] ?? null;
}

function fetch_community_events(?int $categoryId = null, ?int $viewerUserId = null, bool $canManageAll = false): array
{
    $viewerId = $viewerUserId ?? 0;
    $viewerIsLoggedIn = $viewerId > 0 ? 1 : 0;
    $userRsvpFields = 'user_rsvp.response AS current_user_rsvp';

    if (community_event_rsvp_preferences_available()) {
        $userRsvpFields .= ',
            user_rsvp.bring_item_id AS current_user_bring_item_id,
            user_rsvp.bring_item_label AS current_user_bring_item_label,
            user_rsvp.bring_item_note AS current_user_bring_item_note,
            user_rsvp.remind_three_days AS current_user_remind_three_days,
            user_rsvp.remind_same_day AS current_user_remind_same_day';
    }

    $sql = 'SELECT community_events.*,
            community_event_categories.slug AS category_slug,
            community_event_categories.label AS category_label,
            community_event_categories.color AS category_color,
            users.name AS created_by_name,
            COALESCE(rsvp_counts.going_count, 0) AS going_count,
            COALESCE(rsvp_counts.interested_count, 0) AS interested_count,
            COALESCE(rsvp_counts.maybe_count, 0) AS maybe_count,
            COALESCE(rsvp_counts.not_going_count, 0) AS not_going_count,
            COALESCE(rsvp_counts.total_count, 0) AS total_rsvp_count,
            ' . $userRsvpFields . '
        FROM community_events
        LEFT JOIN community_event_categories
            ON community_event_categories.id = community_events.category_id
        LEFT JOIN users
            ON users.id = community_events.created_by_user_id
        LEFT JOIN (
            SELECT community_event_id,
                SUM(CASE WHEN response = \'going\' THEN 1 ELSE 0 END) AS going_count,
                SUM(CASE WHEN response = \'interested\' THEN 1 ELSE 0 END) AS interested_count,
                SUM(CASE WHEN response = \'maybe\' THEN 1 ELSE 0 END) AS maybe_count,
                SUM(CASE WHEN response = \'not-going\' THEN 1 ELSE 0 END) AS not_going_count,
                COUNT(*) AS total_count
            FROM community_event_rsvps
            GROUP BY community_event_id
        ) AS rsvp_counts
            ON rsvp_counts.community_event_id = community_events.id
        LEFT JOIN community_event_rsvps AS user_rsvp
            ON user_rsvp.community_event_id = community_events.id
            AND user_rsvp.user_id = :viewer_user_id_join
        WHERE (
            :can_manage_all = 1
            OR (
                :viewer_user_id_owner > 0
                AND community_events.created_by_user_id = :viewer_user_id_owner_match
            )
            OR (
                community_events.status <> :draft_status
                AND (
                    community_events.visibility = :public_visibility
                    OR (
                        community_events.visibility = :members_visibility
                        AND :viewer_is_logged_in = 1
                    )
                    OR (
                        community_events.visibility = :private_visibility
                        AND :viewer_user_id_private > 0
                        AND community_events.created_by_user_id = :viewer_user_id_private_match
                    )
                )
            )
        )';
    $params = [
        'can_manage_all' => $canManageAll ? 1 : 0,
        'members_visibility' => 'members',
        'private_visibility' => 'private',
        'public_visibility' => 'public',
        'viewer_user_id_join' => $viewerId,
        'viewer_user_id_owner' => $viewerId,
        'viewer_user_id_owner_match' => $viewerId,
        'viewer_user_id_private' => $viewerId,
        'viewer_user_id_private_match' => $viewerId,
        'viewer_is_logged_in' => $viewerIsLoggedIn,
        'draft_status' => 'draft',
    ];

    if ($categoryId !== null) {
        $sql .= ' AND community_events.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $sql .= ' ORDER BY community_events.is_featured DESC, community_events.start_at ASC, community_events.id DESC';

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return hydrate_community_events($statement->fetchAll());
}

function fetch_manageable_community_events(int $userId, bool $canManageAll = false, int $limit = 12): array
{
    $sql = 'SELECT community_events.*,
            community_event_categories.label AS category_label,
            community_event_categories.slug AS category_slug,
            users.name AS created_by_name
        FROM community_events
        LEFT JOIN community_event_categories
            ON community_event_categories.id = community_events.category_id
        LEFT JOIN users
            ON users.id = community_events.created_by_user_id';

    $params = [];

    if (!$canManageAll) {
        $sql .= ' WHERE community_events.created_by_user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $sql .= ' ORDER BY community_events.updated_at DESC, community_events.start_at DESC
        LIMIT ' . (int) $limit;

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return hydrate_community_events($statement->fetchAll(), true, 4);
}

function fetch_manageable_community_event_by_id(int $eventId, int $actorUserId, bool $canManageAll = false): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM community_events
        WHERE id = :id
            AND (
                :can_manage_all = 1
                OR created_by_user_id = :actor_user_id
            )
        LIMIT 1'
    );
    $statement->execute([
        'actor_user_id' => $actorUserId,
        'can_manage_all' => $canManageAll ? 1 : 0,
        'id' => $eventId,
    ]);
    $event = $statement->fetch();

    return $event ?: null;
}

function fetch_public_sessions(bool $includeUnpublished = false, int $limit = 24): array
{
    $sql = 'SELECT public_sessions.*, users.name AS created_by_name
        FROM public_sessions
        LEFT JOIN users ON users.id = public_sessions.created_by_user_id';
    $params = [];

    if (!$includeUnpublished) {
        $sql .= ' WHERE public_sessions.status = :status';
        $params['status'] = 'published';
    }

    $sql .= ' ORDER BY public_sessions.is_featured DESC, public_sessions.start_at ASC
        LIMIT ' . (int) $limit;

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetch_public_session_by_id(int $sessionId, bool $includeUnpublished = false): ?array
{
    $sql = 'SELECT public_sessions.*, users.name AS created_by_name
        FROM public_sessions
        LEFT JOIN users ON users.id = public_sessions.created_by_user_id
        WHERE public_sessions.id = :id';
    $params = ['id' => $sessionId];

    if (!$includeUnpublished) {
        $sql .= ' AND public_sessions.status = :status';
        $params['status'] = 'published';
    }

    $sql .= ' LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $session = $statement->fetch();

    return $session ?: null;
}

function fetch_manageable_public_sessions(int $limit = 50): array
{
    $statement = db()->prepare(
        'SELECT public_sessions.*, users.name AS created_by_name
        FROM public_sessions
        LEFT JOIN users ON users.id = public_sessions.created_by_user_id
        ORDER BY public_sessions.updated_at DESC, public_sessions.start_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute();

    return $statement->fetchAll();
}

function fetch_manageable_public_session_by_id(int $sessionId): ?array
{
    $statement = db()->prepare(
        'SELECT public_sessions.*, users.name AS created_by_name
        FROM public_sessions
        LEFT JOIN users ON users.id = public_sessions.created_by_user_id
        WHERE public_sessions.id = :id
        LIMIT 1'
    );
    $statement->execute(['id' => $sessionId]);
    $session = $statement->fetch();

    return $session ?: null;
}

function create_public_session(
    int $actorUserId,
    string $title,
    string $summary,
    string $sessionType,
    string $hostName,
    string $locationName,
    string $meetingUrl,
    string $startAt,
    ?string $endAt,
    ?int $capacity,
    bool $isFeatured,
    string $status
): int {
    $statement = db()->prepare(
        'INSERT INTO public_sessions (
            created_by_user_id,
            title,
            summary,
            session_type,
            host_name,
            location_name,
            meeting_url,
            start_at,
            end_at,
            capacity,
            is_featured,
            status
        ) VALUES (
            :created_by_user_id,
            :title,
            :summary,
            :session_type,
            :host_name,
            :location_name,
            :meeting_url,
            :start_at,
            :end_at,
            :capacity,
            :is_featured,
            :status
        )'
    );
    $statement->execute([
        'created_by_user_id' => $actorUserId,
        'title' => trim($title),
        'summary' => trim($summary),
        'session_type' => trim($sessionType),
        'host_name' => normalize_optional_text($hostName),
        'location_name' => normalize_optional_text($locationName),
        'meeting_url' => normalize_optional_text($meetingUrl),
        'start_at' => trim($startAt),
        'end_at' => normalize_optional_text((string) $endAt),
        'capacity' => $capacity,
        'is_featured' => $isFeatured ? 1 : 0,
        'status' => trim($status),
    ]);

    return (int) db()->lastInsertId();
}

function update_public_session(
    int $sessionId,
    string $title,
    string $summary,
    string $sessionType,
    string $hostName,
    string $locationName,
    string $meetingUrl,
    string $startAt,
    ?string $endAt,
    ?int $capacity,
    bool $isFeatured,
    string $status
): void {
    $statement = db()->prepare(
        'UPDATE public_sessions
        SET title = :title,
            summary = :summary,
            session_type = :session_type,
            host_name = :host_name,
            location_name = :location_name,
            meeting_url = :meeting_url,
            start_at = :start_at,
            end_at = :end_at,
            capacity = :capacity,
            is_featured = :is_featured,
            status = :status
        WHERE id = :id'
    );
    $statement->execute([
        'id' => $sessionId,
        'title' => trim($title),
        'summary' => trim($summary),
        'session_type' => trim($sessionType),
        'host_name' => normalize_optional_text($hostName),
        'location_name' => normalize_optional_text($locationName),
        'meeting_url' => normalize_optional_text($meetingUrl),
        'start_at' => trim($startAt),
        'end_at' => normalize_optional_text((string) $endAt),
        'capacity' => $capacity,
        'is_featured' => $isFeatured ? 1 : 0,
        'status' => trim($status),
    ]);
}

function delete_public_session(int $sessionId): void
{
    $statement = db()->prepare('DELETE FROM public_sessions WHERE id = :id');
    $statement->execute(['id' => $sessionId]);
}

function fetch_public_radio_stations(bool $includeUnpublished = false, int $limit = 24): array
{
    if (!public_radio_stations_available()) {
        return [];
    }

    $sql = 'SELECT public_radio_stations.*, users.name AS created_by_name
        FROM public_radio_stations
        LEFT JOIN users ON users.id = public_radio_stations.created_by_user_id';
    $params = [];

    if (!$includeUnpublished) {
        $sql .= ' WHERE public_radio_stations.status = :status';
        $params['status'] = 'published';
    }

    $sql .= ' ORDER BY public_radio_stations.is_featured DESC, public_radio_stations.sort_order ASC, public_radio_stations.name ASC
        LIMIT ' . (int) $limit;

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetch_manageable_public_radio_stations(int $limit = 50): array
{
    if (!public_radio_stations_available()) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT public_radio_stations.*, users.name AS created_by_name
        FROM public_radio_stations
        LEFT JOIN users ON users.id = public_radio_stations.created_by_user_id
        ORDER BY public_radio_stations.updated_at DESC, public_radio_stations.sort_order ASC, public_radio_stations.name ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute();

    return $statement->fetchAll();
}

function fetch_manageable_public_radio_station_by_id(int $stationId): ?array
{
    if (!public_radio_stations_available()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT public_radio_stations.*, users.name AS created_by_name
        FROM public_radio_stations
        LEFT JOIN users ON users.id = public_radio_stations.created_by_user_id
        WHERE public_radio_stations.id = :id
        LIMIT 1'
    );
    $statement->execute(['id' => $stationId]);
    $station = $statement->fetch();

    return $station ?: null;
}

function fetch_public_radio_station_by_name(string $name, bool $includeUnpublished = false): ?array
{
    if (!public_radio_stations_available()) {
        return null;
    }

    $sql = 'SELECT public_radio_stations.*, users.name AS created_by_name
        FROM public_radio_stations
        LEFT JOIN users ON users.id = public_radio_stations.created_by_user_id
        WHERE LOWER(public_radio_stations.name) = LOWER(:name)';
    $params = ['name' => trim($name)];

    if (!$includeUnpublished) {
        $sql .= ' AND public_radio_stations.status = :status';
        $params['status'] = 'published';
    }

    $sql .= ' LIMIT 1';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $station = $statement->fetch();

    return $station ?: null;
}

function create_public_radio_station(
    int $actorUserId,
    string $name,
    string $kind,
    string $tagline,
    string $streamUrl,
    string $listenUrl,
    ?string $youtubePlaylistId,
    int $sortOrder,
    bool $isFeatured,
    string $status
): int {
    $columns = [
        'created_by_user_id',
        'name',
        'kind',
        'tagline',
        'stream_url',
        'listen_url',
    ];
    $placeholders = [
        ':created_by_user_id',
        ':name',
        ':kind',
        ':tagline',
        ':stream_url',
        ':listen_url',
    ];
    $params = [
        'created_by_user_id' => $actorUserId,
        'name' => trim($name),
        'kind' => trim($kind),
        'tagline' => trim($tagline),
        'stream_url' => normalize_optional_text($streamUrl),
        'listen_url' => trim($listenUrl),
    ];

    if (public_radio_playlist_support_available()) {
        $columns[] = 'youtube_playlist_id';
        $placeholders[] = ':youtube_playlist_id';
        $params['youtube_playlist_id'] = normalize_optional_text((string) $youtubePlaylistId);
    }

    $columns[] = 'sort_order';
    $columns[] = 'is_featured';
    $columns[] = 'status';
    $placeholders[] = ':sort_order';
    $placeholders[] = ':is_featured';
    $placeholders[] = ':status';

    $params += [
        'sort_order' => $sortOrder,
        'is_featured' => $isFeatured ? 1 : 0,
        'status' => trim($status),
    ];

    $statement = db()->prepare(
        'INSERT INTO public_radio_stations (' . implode(', ', $columns) . ')
        VALUES (' . implode(', ', $placeholders) . ')'
    );
    $statement->execute($params);

    return (int) db()->lastInsertId();
}

function update_public_radio_station(
    int $stationId,
    string $name,
    string $kind,
    string $tagline,
    string $streamUrl,
    string $listenUrl,
    ?string $youtubePlaylistId,
    int $sortOrder,
    bool $isFeatured,
    string $status
): void {
    $assignments = [
        'name = :name',
        'kind = :kind',
        'tagline = :tagline',
        'stream_url = :stream_url',
        'listen_url = :listen_url',
    ];
    $params = [
        'id' => $stationId,
        'name' => trim($name),
        'kind' => trim($kind),
        'tagline' => trim($tagline),
        'stream_url' => normalize_optional_text($streamUrl),
        'listen_url' => trim($listenUrl),
    ];

    if (public_radio_playlist_support_available()) {
        $assignments[] = 'youtube_playlist_id = :youtube_playlist_id';
        $params['youtube_playlist_id'] = normalize_optional_text((string) $youtubePlaylistId);
    }

    $assignments[] = 'sort_order = :sort_order';
    $assignments[] = 'is_featured = :is_featured';
    $assignments[] = 'status = :status';

    $params += [
        'sort_order' => $sortOrder,
        'is_featured' => $isFeatured ? 1 : 0,
        'status' => trim($status),
    ];

    $statement = db()->prepare(
        'UPDATE public_radio_stations
        SET ' . implode(', ', $assignments) . '
        WHERE id = :id'
    );
    $statement->execute($params);
}

function delete_public_radio_station(int $stationId): void
{
    $statement = db()->prepare('DELETE FROM public_radio_stations WHERE id = :id');
    $statement->execute(['id' => $stationId]);
}

function upsert_user_session_record(
    int $userId,
    string $sessionId,
    string $sessionToken,
    string $lastSeenAt,
    string $expiresAt
): void {
    $statement = db()->prepare(
        'INSERT INTO user_sessions (
            user_id,
            session_id,
            session_token_hash,
            ip_address,
            user_agent,
            last_seen_at,
            expires_at,
            revoked_at
        ) VALUES (
            :user_id,
            :session_id,
            :session_token_hash,
            :ip_address,
            :user_agent,
            :last_seen_at,
            :expires_at,
            NULL
        )
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            session_token_hash = VALUES(session_token_hash),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            last_seen_at = VALUES(last_seen_at),
            expires_at = VALUES(expires_at),
            revoked_at = NULL'
    );
    $statement->execute([
        'expires_at' => $expiresAt,
        'ip_address' => current_request_ip_address(),
        'last_seen_at' => $lastSeenAt,
        'session_id' => trim($sessionId),
        'session_token_hash' => hash('sha256', $sessionToken),
        'user_agent' => current_request_user_agent(),
        'user_id' => $userId,
    ]);
}

function fetch_active_user_session_record(
    int $userId,
    string $sessionId,
    string $sessionToken,
    string $minimumCreatedAt
): ?array {
    $statement = db()->prepare(
        'SELECT *
        FROM user_sessions
        WHERE user_id = :user_id
            AND session_id = :session_id
            AND session_token_hash = :session_token_hash
            AND revoked_at IS NULL
            AND expires_at >= NOW()
            AND created_at >= :minimum_created_at
        LIMIT 1'
    );
    $statement->execute([
        'minimum_created_at' => $minimumCreatedAt,
        'session_id' => trim($sessionId),
        'session_token_hash' => hash('sha256', $sessionToken),
        'user_id' => $userId,
    ]);
    $session = $statement->fetch();

    return $session ?: null;
}

function touch_user_session_record(int $sessionRecordId, string $lastSeenAt, string $expiresAt): void
{
    $statement = db()->prepare(
        'UPDATE user_sessions
        SET last_seen_at = :last_seen_at,
            expires_at = :expires_at
        WHERE id = :id'
    );
    $statement->execute([
        'expires_at' => $expiresAt,
        'id' => $sessionRecordId,
        'last_seen_at' => $lastSeenAt,
    ]);
}

function revoke_user_session_record(string $sessionId): void
{
    $statement = db()->prepare(
        'UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE session_id = :session_id
            AND revoked_at IS NULL'
    );
    $statement->execute([
        'session_id' => trim($sessionId),
    ]);
}

function revoke_other_user_sessions(int $userId, string $currentSessionId): void
{
    $statement = db()->prepare(
        'UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE user_id = :user_id
            AND session_id <> :current_session_id
            AND revoked_at IS NULL'
    );
    $statement->execute([
        'current_session_id' => trim($currentSessionId),
        'user_id' => $userId,
    ]);
}

function revoke_all_user_sessions(int $userId): void
{
    $statement = db()->prepare(
        'UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE user_id = :user_id
            AND revoked_at IS NULL'
    );
    $statement->execute([
        'user_id' => $userId,
    ]);
}

function revoke_user_session_record_by_id(int $userId, int $sessionRecordId): bool
{
    $statement = db()->prepare(
        'UPDATE user_sessions
        SET revoked_at = NOW()
        WHERE id = :id
            AND user_id = :user_id
            AND revoked_at IS NULL'
    );
    $statement->execute([
        'id' => $sessionRecordId,
        'user_id' => $userId,
    ]);

    return $statement->rowCount() > 0;
}

function fetch_user_session_records(int $userId, ?string $currentSessionId = null, int $limit = 12): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM user_sessions
        WHERE user_id = :user_id
            AND revoked_at IS NULL
            AND expires_at >= NOW()
        ORDER BY
            CASE WHEN session_id = :current_session_id THEN 0 ELSE 1 END ASC,
            last_seen_at DESC,
            created_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'current_session_id' => trim((string) $currentSessionId),
        'user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function create_community_event_record(int $userId, array $payload): int
{
    $settingsJson = encode_community_event_settings((array) ($payload['settings'] ?? []));
    $sql = 'INSERT INTO community_events (
            created_by_user_id,
            category_id,
            title,
            description,
            event_type, ';

    if (community_event_settings_available()) {
        $sql .= 'settings_json, ';
    }

    $sql .= 'visibility, ';

    if (community_event_images_available()) {
        $sql .= 'image_url, ';
    }

    $sql .= 'location_name,
            location_address,
            meeting_url,
            start_at,
            end_at,
            is_featured,
            status
        ) VALUES (
            :created_by_user_id,
            :category_id,
            :title,
            :description,
            :event_type, ';

    if (community_event_settings_available()) {
        $sql .= ':settings_json, ';
    }

    $sql .= ':visibility, ';

    if (community_event_images_available()) {
        $sql .= ':image_url, ';
    }

    $sql .= ':location_name,
            :location_address,
            :meeting_url,
            :start_at,
            :end_at,
            :is_featured,
            :status
        )';

    $params = [
        'created_by_user_id' => $userId,
        'category_id' => $payload['category_id'],
        'title' => trim((string) $payload['title']),
        'description' => trim((string) $payload['description']),
        'event_type' => trim((string) $payload['event_type']),
        'visibility' => trim((string) $payload['visibility']),
        'location_name' => normalize_optional_text((string) ($payload['location_name'] ?? '')),
        'location_address' => normalize_optional_text((string) ($payload['location_address'] ?? '')),
        'meeting_url' => normalize_optional_text((string) ($payload['meeting_url'] ?? '')),
        'start_at' => $payload['start_at'],
        'end_at' => $payload['end_at'],
        'is_featured' => !empty($payload['is_featured']) ? 1 : 0,
        'status' => trim((string) $payload['status']),
    ];

    if (community_event_settings_available()) {
        $params['settings_json'] = $settingsJson;
    }

    if (community_event_images_available()) {
        $params['image_url'] = normalize_optional_text((string) ($payload['image_url'] ?? ''));
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    $eventId = (int) db()->lastInsertId();

    sync_community_event_items(
        $eventId,
        $userId,
        is_array($payload['potluck_items'] ?? null) ? $payload['potluck_items'] : []
    );

    record_audit_event($userId, 'community_event.created', $userId, [
        'community_event_id' => $eventId,
        'status' => trim((string) $payload['status']),
        'visibility' => trim((string) $payload['visibility']),
    ]);

    return $eventId;
}

function update_community_event_record(int $eventId, array $payload, int $actorUserId, bool $canManageAll = false): void
{
    $existingEvent = fetch_manageable_community_event_by_id($eventId, $actorUserId, $canManageAll);

    if ($existingEvent === null) {
        throw new RuntimeException('You are not allowed to update that event.');
    }

    $settingsJson = encode_community_event_settings((array) ($payload['settings'] ?? []));
    $sql = 'UPDATE community_events
        SET category_id = :category_id,
            title = :title,
            description = :description,
            event_type = :event_type, ';

    if (community_event_settings_available()) {
        $sql .= 'settings_json = :settings_json, ';
    }

    $sql .= 'visibility = :visibility, ';

    if (community_event_images_available()) {
        $sql .= 'image_url = :image_url, ';
    }

    $sql .= 'location_name = :location_name,
            location_address = :location_address,
            meeting_url = :meeting_url,
            start_at = :start_at,
            end_at = :end_at,
            is_featured = :is_featured,
            status = :status
        WHERE id = :id';

    $params = [
        'id' => $eventId,
        'category_id' => $payload['category_id'],
        'title' => trim((string) $payload['title']),
        'description' => trim((string) $payload['description']),
        'event_type' => trim((string) $payload['event_type']),
        'visibility' => trim((string) $payload['visibility']),
        'location_name' => normalize_optional_text((string) ($payload['location_name'] ?? '')),
        'location_address' => normalize_optional_text((string) ($payload['location_address'] ?? '')),
        'meeting_url' => normalize_optional_text((string) ($payload['meeting_url'] ?? '')),
        'start_at' => $payload['start_at'],
        'end_at' => $payload['end_at'],
        'is_featured' => !empty($payload['is_featured']) ? 1 : 0,
        'status' => trim((string) $payload['status']),
    ];

    if (community_event_settings_available()) {
        $params['settings_json'] = $settingsJson;
    }

    if (community_event_images_available()) {
        $params['image_url'] = normalize_optional_text((string) ($payload['image_url'] ?? ''));
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);

    sync_community_event_items(
        $eventId,
        $actorUserId,
        is_array($payload['potluck_items'] ?? null) ? $payload['potluck_items'] : []
    );

    record_audit_event($actorUserId, 'community_event.updated', (int) ($existingEvent['created_by_user_id'] ?? 0) ?: null, [
        'community_event_id' => $eventId,
        'status' => trim((string) $payload['status']),
        'visibility' => trim((string) $payload['visibility']),
    ]);
}

function delete_community_event_record(int $eventId, int $actorUserId, bool $canManageAll = false): void
{
    $existingEvent = fetch_manageable_community_event_by_id($eventId, $actorUserId, $canManageAll);

    if ($existingEvent === null) {
        throw new RuntimeException('You are not allowed to delete that event.');
    }

    $statement = db()->prepare('DELETE FROM community_events WHERE id = :id');
    $statement->execute(['id' => $eventId]);

    record_audit_event($actorUserId, 'community_event.deleted', (int) ($existingEvent['created_by_user_id'] ?? 0) ?: null, [
        'community_event_id' => $eventId,
        'status' => (string) ($existingEvent['status'] ?? ''),
        'visibility' => (string) ($existingEvent['visibility'] ?? ''),
    ]);
}

function upsert_community_event_rsvp(int $eventId, int $userId, string $response): void
{
    if (community_event_rsvp_preferences_available()) {
        upsert_community_event_rsvp_details($eventId, $userId, $response);
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO community_event_rsvps (community_event_id, user_id, response)
        VALUES (:community_event_id, :user_id, :response)
        ON DUPLICATE KEY UPDATE response = VALUES(response), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'community_event_id' => $eventId,
        'user_id' => $userId,
        'response' => $response,
    ]);
}

function delete_community_event_rsvp(int $eventId, int $userId): void
{
    if (community_event_rsvp_preferences_available() && community_event_items_available()) {
        $statement = db()->prepare(
            'SELECT bring_item_id
            FROM community_event_rsvps
            WHERE community_event_id = :community_event_id
                AND user_id = :user_id
            LIMIT 1'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'user_id' => $userId,
        ]);
        $existingRsvp = $statement->fetch();

        if (!empty($existingRsvp['bring_item_id'])) {
            release_community_event_item_claim($eventId, (int) $existingRsvp['bring_item_id'], $userId, false);
        }
    }

    $statement = db()->prepare(
        'DELETE FROM community_event_rsvps
        WHERE community_event_id = :community_event_id
            AND user_id = :user_id'
    );
    $statement->execute([
        'community_event_id' => $eventId,
        'user_id' => $userId,
    ]);
}

function upsert_community_event_rsvp_details(
    int $eventId,
    int $userId,
    string $response,
    ?int $bringItemId = null,
    ?string $bringItemLabel = null,
    ?string $bringItemNote = null,
    ?bool $remindThreeDays = null,
    ?bool $remindSameDay = null
): void {
    if (!community_event_rsvp_preferences_available()) {
        $statement = db()->prepare(
            'INSERT INTO community_event_rsvps (community_event_id, user_id, response)
            VALUES (:community_event_id, :user_id, :response)
            ON DUPLICATE KEY UPDATE response = VALUES(response), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'user_id' => $userId,
            'response' => $response,
        ]);
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO community_event_rsvps (
            community_event_id,
            user_id,
            response,
            bring_item_id,
            bring_item_label,
            bring_item_note,
            remind_three_days,
            remind_same_day
        ) VALUES (
            :community_event_id,
            :user_id,
            :response,
            :bring_item_id,
            :bring_item_label,
            :bring_item_note,
            :remind_three_days,
            :remind_same_day
        )
        ON DUPLICATE KEY UPDATE
            response = VALUES(response),
            bring_item_id = VALUES(bring_item_id),
            bring_item_label = VALUES(bring_item_label),
            bring_item_note = VALUES(bring_item_note),
            remind_three_days = VALUES(remind_three_days),
            remind_same_day = VALUES(remind_same_day),
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'community_event_id' => $eventId,
        'user_id' => $userId,
        'response' => $response,
        'bring_item_id' => $bringItemId,
        'bring_item_label' => normalize_optional_text((string) $bringItemLabel),
        'bring_item_note' => normalize_optional_text((string) $bringItemNote),
        'remind_three_days' => $remindThreeDays === null || $remindThreeDays ? 1 : 0,
        'remind_same_day' => $remindSameDay === null || $remindSameDay ? 1 : 0,
    ]);
}

function sync_community_event_items(int $eventId, int $actorUserId, array $itemDefinitions): void
{
    if (!community_event_items_available()) {
        return;
    }

    $existingItems = fetch_community_event_items($eventId);
    $matchedItemIds = [];
    $matchedKeys = [];

    foreach (array_values($itemDefinitions) as $index => $itemDefinition) {
        $label = trim((string) ($itemDefinition['label'] ?? ''));
        $details = trim((string) ($itemDefinition['details'] ?? ''));

        if ($label === '') {
            continue;
        }

        $normalizedKey = mb_strtolower($label);
        $matchingItem = null;

        foreach ($existingItems as $existingItem) {
            $existingKey = mb_strtolower(trim((string) ($existingItem['label'] ?? '')));

            if ($existingKey !== $normalizedKey || in_array((int) $existingItem['id'], $matchedItemIds, true)) {
                continue;
            }

            $matchingItem = $existingItem;
            break;
        }

        if ($matchingItem !== null) {
            $statement = db()->prepare(
                'UPDATE community_event_items
                SET label = :label,
                    details = :details,
                    sort_order = :sort_order
                WHERE id = :id'
            );
            $statement->execute([
                'id' => (int) $matchingItem['id'],
                'label' => $label,
                'details' => normalize_optional_text($details),
                'sort_order' => $index + 1,
            ]);
            $matchedItemIds[] = (int) $matchingItem['id'];
            $matchedKeys[] = $normalizedKey;
            continue;
        }

        $statement = db()->prepare(
            'INSERT INTO community_event_items (
                community_event_id,
                created_by_user_id,
                label,
                details,
                status,
                assigned_by_host,
                sort_order
            ) VALUES (
                :community_event_id,
                :created_by_user_id,
                :label,
                :details,
                :status,
                0,
                :sort_order
            )'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'created_by_user_id' => $actorUserId,
            'label' => $label,
            'details' => normalize_optional_text($details),
            'status' => 'open',
            'sort_order' => $index + 1,
        ]);
    }

    foreach ($existingItems as $existingItem) {
        if (in_array((int) $existingItem['id'], $matchedItemIds, true)) {
            continue;
        }

        $statement = db()->prepare('DELETE FROM community_event_items WHERE id = :id');
        $statement->execute(['id' => (int) $existingItem['id']]);
    }
}

function create_community_event_item(int $eventId, int $userId, string $label, string $details = ''): int
{
    if (!community_event_items_available()) {
        throw new RuntimeException('Event item lists are not available yet.');
    }

    $maxStatement = db()->prepare(
        'SELECT COALESCE(MAX(sort_order), 0)
        FROM community_event_items
        WHERE community_event_id = :community_event_id'
    );
    $maxStatement->execute(['community_event_id' => $eventId]);
    $sortOrder = (int) $maxStatement->fetchColumn() + 1;

    $statement = db()->prepare(
        'INSERT INTO community_event_items (
            community_event_id,
            created_by_user_id,
            label,
            details,
            status,
            assigned_by_host,
            sort_order
        ) VALUES (
            :community_event_id,
            :created_by_user_id,
            :label,
            :details,
            :status,
            0,
            :sort_order
        )'
    );
    $statement->execute([
        'community_event_id' => $eventId,
        'created_by_user_id' => $userId,
        'label' => trim($label),
        'details' => normalize_optional_text($details),
        'status' => 'open',
        'sort_order' => $sortOrder,
    ]);

    return (int) db()->lastInsertId();
}

function update_community_event_item(int $eventId, int $itemId, string $label, string $details = ''): void
{
    if (!community_event_items_available()) {
        throw new RuntimeException('Event item lists are not available yet.');
    }

    $item = fetch_community_event_item_by_id($itemId, $eventId);

    if ($item === null) {
        throw new RuntimeException('That event item could not be found.');
    }

    $statement = db()->prepare(
        'UPDATE community_event_items
        SET label = :label,
            details = :details,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
            AND community_event_id = :community_event_id'
    );
    $statement->execute([
        'id' => $itemId,
        'community_event_id' => $eventId,
        'label' => trim($label),
        'details' => normalize_optional_text($details),
    ]);

    if (community_event_rsvp_preferences_available()) {
        $statement = db()->prepare(
            'UPDATE community_event_rsvps
            SET bring_item_label = :bring_item_label,
                updated_at = CURRENT_TIMESTAMP
            WHERE community_event_id = :community_event_id
                AND bring_item_id = :bring_item_id'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'bring_item_id' => $itemId,
            'bring_item_label' => trim($label),
        ]);
    }
}

function delete_community_event_item(int $eventId, int $itemId): void
{
    if (!community_event_items_available()) {
        throw new RuntimeException('Event item lists are not available yet.');
    }

    $item = fetch_community_event_item_by_id($itemId, $eventId);

    if ($item === null) {
        return;
    }

    if (!empty($item['claimed_by_user_id'])) {
        release_community_event_item_claim($eventId, $itemId, (int) $item['claimed_by_user_id'], true);
    }

    $statement = db()->prepare(
        'DELETE FROM community_event_items
        WHERE id = :id
            AND community_event_id = :community_event_id'
    );
    $statement->execute([
        'id' => $itemId,
        'community_event_id' => $eventId,
    ]);
}

function release_community_event_item_claim(int $eventId, int $itemId, int $userId, bool $canManageAll = false): void
{
    $item = fetch_community_event_item_by_id($itemId, $eventId);

    if ($item === null) {
        return;
    }

    if (!$canManageAll && (int) ($item['claimed_by_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('You are not allowed to release that item.');
    }

    $rsvpUserId = !empty($item['claimed_by_user_id']) ? (int) $item['claimed_by_user_id'] : $userId;

    $statement = db()->prepare(
        'UPDATE community_event_items
        SET claimed_by_user_id = NULL,
            status = :status,
            assigned_by_host = 0
        WHERE id = :id'
    );
    $statement->execute([
        'id' => $itemId,
        'status' => 'open',
    ]);

    if (community_event_rsvp_preferences_available()) {
        $statement = db()->prepare(
            'UPDATE community_event_rsvps
            SET bring_item_id = NULL,
                bring_item_label = NULL
            WHERE community_event_id = :community_event_id
                AND user_id = :user_id
                AND bring_item_id = :bring_item_id'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'user_id' => $rsvpUserId,
            'bring_item_id' => $itemId,
        ]);
    }
}

function claim_community_event_item(
    int $eventId,
    int $itemId,
    int $userId,
    string $response,
    ?string $bringItemNote = null,
    ?bool $remindThreeDays = null,
    ?bool $remindSameDay = null
): void {
    $item = fetch_community_event_item_by_id($itemId, $eventId);

    if ($item === null) {
        throw new RuntimeException('That event item could not be found.');
    }

    if (
        !empty($item['claimed_by_user_id'])
        && (int) $item['claimed_by_user_id'] !== $userId
    ) {
        throw new RuntimeException('That item has already been claimed.');
    }

    if (community_event_rsvp_preferences_available()) {
        $statement = db()->prepare(
            'SELECT bring_item_id
            FROM community_event_rsvps
            WHERE community_event_id = :community_event_id
                AND user_id = :user_id
            LIMIT 1'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'user_id' => $userId,
        ]);
        $existing = $statement->fetch();

        if (!empty($existing['bring_item_id']) && (int) $existing['bring_item_id'] !== $itemId) {
            release_community_event_item_claim($eventId, (int) $existing['bring_item_id'], $userId, false);
        }
    }

    $statement = db()->prepare(
        'UPDATE community_event_items
        SET claimed_by_user_id = :claimed_by_user_id,
            status = :status,
            assigned_by_host = 0
        WHERE id = :id'
    );
    $statement->execute([
        'claimed_by_user_id' => $userId,
        'status' => 'claimed',
        'id' => $itemId,
    ]);

    upsert_community_event_rsvp_details(
        $eventId,
        $userId,
        $response,
        $itemId,
        (string) $item['label'],
        $bringItemNote,
        $remindThreeDays,
        $remindSameDay
    );
}

function assign_community_event_item(int $eventId, int $itemId, int $attendeeUserId): void
{
    $item = fetch_community_event_item_by_id($itemId, $eventId);

    if ($item === null) {
        throw new RuntimeException('That event item could not be found.');
    }

    if (community_event_rsvp_preferences_available()) {
        $statement = db()->prepare(
            'SELECT bring_item_id
            FROM community_event_rsvps
            WHERE community_event_id = :community_event_id
                AND user_id = :user_id
            LIMIT 1'
        );
        $statement->execute([
            'community_event_id' => $eventId,
            'user_id' => $attendeeUserId,
        ]);
        $existing = $statement->fetch();

        if (!empty($existing['bring_item_id']) && (int) $existing['bring_item_id'] !== $itemId) {
            release_community_event_item_claim($eventId, (int) $existing['bring_item_id'], $attendeeUserId, true);
        }
    }

    $statement = db()->prepare(
        'UPDATE community_event_items
        SET claimed_by_user_id = :claimed_by_user_id,
            status = :status,
            assigned_by_host = 1
        WHERE id = :id'
    );
    $statement->execute([
        'claimed_by_user_id' => $attendeeUserId,
        'status' => 'assigned',
        'id' => $itemId,
    ]);

    upsert_community_event_rsvp_details(
        $eventId,
        $attendeeUserId,
        'going',
        $itemId,
        (string) $item['label']
    );
}

function create_community_event_message_record(
    int $eventId,
    int $senderUserId,
    string $messageType,
    string $subject,
    string $body,
    int $deliveredCount
): void {
    if (!community_event_messages_available()) {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO community_event_messages (
            community_event_id,
            sender_user_id,
            message_type,
            subject,
            body,
            delivered_count
        ) VALUES (
            :community_event_id,
            :sender_user_id,
            :message_type,
            :subject,
            :body,
            :delivered_count
        )'
    );
    $statement->execute([
        'community_event_id' => $eventId,
        'sender_user_id' => $senderUserId,
        'message_type' => trim($messageType),
        'subject' => trim($subject),
        'body' => trim($body),
        'delivered_count' => $deliveredCount,
    ]);
}

function fetch_books(): array
{
    return db()->query('SELECT id, name, abbreviation FROM books ORDER BY id ASC')->fetchAll();
}

function fetch_book_catalog(string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    $statement = db()->prepare(
        'SELECT books.id, books.name, books.abbreviation, books.testament, COUNT(DISTINCT verses.chapter_number) AS chapter_count
        FROM books
        LEFT JOIN verses
            ON verses.book_id = books.id
            AND verses.translation = :translation
        GROUP BY books.id, books.name, books.abbreviation, books.testament
        ORDER BY books.id ASC'
    );
    $statement->execute(['translation' => $translation]);

    return $statement->fetchAll();
}

function fetch_book_by_id(int $bookId): ?array
{
    $statement = db()->prepare('SELECT id, name, abbreviation, testament FROM books WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $bookId]);
    $book = $statement->fetch();

    return $book ?: null;
}

function fetch_book_chapters(int $bookId, string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    $statement = db()->prepare(
        'SELECT chapter_number, COUNT(*) AS verse_count
        FROM verses
        WHERE book_id = :book_id AND translation = :translation
        GROUP BY chapter_number
        ORDER BY chapter_number ASC'
    );
    $statement->execute([
        'book_id' => $bookId,
        'translation' => $translation,
    ]);

    return $statement->fetchAll();
}

function fetch_chapter_verses(int $bookId, int $chapterNumber, string $translation): array
{
    if (uses_external_translation($translation)) {
        return fetch_external_translation_chapter_verses($bookId, $chapterNumber, $translation);
    }

    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.book_id = :book_id
            AND verses.chapter_number = :chapter_number
            AND verses.translation = :translation
        ORDER BY verses.verse_number ASC'
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'translation' => $translation,
    ]);

    return $statement->fetchAll();
}

function supported_translations(): array
{
    return ['MSB', 'KJV', 'WEB', 'NIV', 'NKJV', 'NLT', 'RVR'];
}

function fetch_available_translations(): array
{
    $statement = db()->query('SELECT DISTINCT translation FROM verses ORDER BY translation ASC');
    $storedTranslations = array_map(
        static fn(array $row): string => (string) $row['translation'],
        $statement->fetchAll()
    );

    $translations = array_values(array_unique(array_merge(supported_translations(), $storedTranslations)));

    return array_values(array_filter(
        $translations,
        static function (string $translation) use ($storedTranslations): bool {
            if (in_array($translation, $storedTranslations, true)) {
                return true;
            }

            return uses_external_translation($translation) && external_translation_available($translation);
        }
    ));
}

function friend_invites_use_hashed_tokens(): bool
{
    static $usesHashedTokens = null;

    if ($usesHashedTokens !== null) {
        return $usesHashedTokens;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM friend_invites LIKE 'invite_token_hash'");
        $usesHashedTokens = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $usesHashedTokens = false;
    }

    return $usesHashedTokens;
}

function audit_logs_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'audit_logs'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function user_sessions_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'user_sessions'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_sessions_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'public_sessions'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_radio_stations_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'public_radio_stations'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_radio_playlist_support_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    if (!public_radio_stations_available()) {
        $available = false;

        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM public_radio_stations LIKE 'youtube_playlist_id'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function record_audit_event(?int $actorUserId, string $eventType, ?int $targetUserId = null, array $context = []): void
{
    if (!audit_logs_available()) {
        return;
    }

    $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES);

    if (!is_string($contextJson)) {
        $contextJson = '{}';
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO audit_logs (
                actor_user_id,
                target_user_id,
                event_type,
                ip_address,
                user_agent,
                context_json
            ) VALUES (
                :actor_user_id,
                :target_user_id,
                :event_type,
                :ip_address,
                :user_agent,
                :context_json
            )'
        );
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'context_json' => $contextJson,
            'event_type' => trim($eventType),
            'ip_address' => current_request_ip_address(),
            'target_user_id' => $targetUserId,
            'user_agent' => current_request_user_agent(),
        ]);
    } catch (Throwable $exception) {
        return;
    }
}

function current_request_ip_address(): ?string
{
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ipAddress !== '' ? $ipAddress : null;
}

function current_request_user_agent(): ?string
{
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    return $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null;
}

function uses_external_translation(string $translation): bool
{
    return external_translation_provider_config($translation) !== null;
}

function external_translation_available(string $translation): bool
{
    $provider = external_translation_provider_config($translation);

    if ($provider === null || !($provider['implemented'] ?? false)) {
        return false;
    }

    $envKey = (string) ($provider['env_key'] ?? '');

    if ($envKey === '') {
        return true;
    }

    return trim((string) getenv($envKey)) !== '';
}

function external_translation_provider_config(string $translation): ?array
{
    $translation = strtoupper(trim($translation));

    return match ($translation) {
        'NLT' => [
            'provider' => 'nlt',
            'env_key' => 'NLT_API_KEY',
            'implemented' => true,
        ],
        'NIV' => [
            'provider' => 'youversion',
            'env_key' => 'YOUVERSION_APP_KEY',
            'implemented' => false,
        ],
        default => null,
    };
}

function parse_reference_query(string $query, array $books): ?array
{
    $normalizedQuery = preg_replace('/\s+/', ' ', trim($query));

    if ($normalizedQuery === '') {
        return null;
    }

    if (!preg_match('/^(.+?)\s+(\d+)(?::(\d+)(?:-(\d+))?)?$/i', $normalizedQuery, $matches)) {
        return null;
    }

    $bookQuery = normalize_book_key($matches[1]);
    $chapter = (int) $matches[2];
    $startVerse = isset($matches[3]) ? (int) $matches[3] : null;
    $endVerse = isset($matches[4]) ? (int) $matches[4] : null;

    foreach ($books as $book) {
        $nameKey = normalize_book_key((string) $book['name']);
        $abbreviationKey = normalize_book_key((string) $book['abbreviation']);

        if ($bookQuery !== $nameKey && $bookQuery !== $abbreviationKey) {
            continue;
        }

        return [
            'book_id' => (int) $book['id'],
            'book_name' => (string) $book['name'],
            'chapter' => $chapter,
            'start_verse' => $startVerse,
            'end_verse' => $endVerse,
        ];
    }

    return null;
}

function search_scripture(string $query, string $translation): array
{
    if (uses_external_translation($translation)) {
        return search_external_translation($query, $translation);
    }

    $books = fetch_books();
    $reference = parse_reference_query($query, $books);

    if ($reference !== null) {
        return fetch_reference_verses($reference, $translation);
    }

    return fetch_keyword_verses($query, $translation);
}

function search_external_translation(string $query, string $translation): array
{
    $books = fetch_books();
    $reference = parse_reference_query($query, $books);

    if ($reference !== null) {
        return fetch_external_translation_reference_verses($reference, $translation);
    }

    return fetch_external_translation_keyword_verses($query, $translation, $books);
}

function fetch_external_translation_reference_verses(array $reference, string $translation): array
{
    $book = fetch_book_by_id((int) $reference['book_id']);

    if ($book === null) {
        return [
            'mode' => 'reference',
            'results' => [],
            'heading' => build_reference_heading($reference, $translation),
        ];
    }

    $referenceString = build_external_translation_reference_string(
        (string) $book['name'],
        (int) $reference['chapter'],
        isset($reference['start_verse']) ? (int) $reference['start_verse'] : null,
        isset($reference['end_verse']) ? (int) $reference['end_verse'] : null
    );

    $html = external_translation_api_get($translation, '/api/passages', [
        'ref' => $referenceString,
        'version' => $translation,
    ]);

    $verseIdMap = fetch_canonical_verse_id_map((int) $reference['book_id'], (int) $reference['chapter']);
    $verses = parse_external_translation_passage_html(
        $html,
        (int) $book['id'],
        (string) $book['name'],
        (string) $book['abbreviation'],
        (int) $reference['chapter'],
        $translation,
        $verseIdMap
    );

    return [
        'mode' => 'reference',
        'results' => $verses,
        'heading' => build_reference_heading($reference, $translation),
    ];
}

function fetch_external_translation_keyword_verses(string $query, string $translation, array $books, int $limit = 25): array
{
    $html = external_translation_api_get($translation, '/api/search', [
        'text' => trim($query),
        'version' => $translation,
    ]);

    return [
        'mode' => 'keyword',
        'results' => parse_external_translation_search_html($html, $books, $translation, $limit),
        'heading' => 'Search Results',
    ];
}

function fetch_reference_verses(array $reference, string $translation): array
{
    $sql = 'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.book_id = :book_id
            AND verses.chapter_number = :chapter_number
            AND verses.translation = :translation';

    $params = [
        'book_id' => $reference['book_id'],
        'chapter_number' => $reference['chapter'],
        'translation' => $translation,
    ];

    if ($reference['start_verse'] !== null) {
        $sql .= ' AND verses.verse_number >= :start_verse';
        $params['start_verse'] = $reference['start_verse'];
    }

    if ($reference['end_verse'] !== null) {
        $sql .= ' AND verses.verse_number <= :end_verse';
        $params['end_verse'] = $reference['end_verse'];
    } elseif ($reference['start_verse'] !== null) {
        $sql .= ' AND verses.verse_number = :exact_verse';
        $params['exact_verse'] = $reference['start_verse'];
    }

    $sql .= ' ORDER BY verses.verse_number ASC';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $verses = $statement->fetchAll();

    return [
        'mode' => 'reference',
        'results' => $verses,
        'heading' => build_reference_heading($reference, $translation),
    ];
}

function fetch_keyword_verses(string $query, string $translation, int $limit = 25): array
{
    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.translation = :translation
            AND verses.verse_text LIKE :query
        ORDER BY books.id ASC, verses.chapter_number ASC, verses.verse_number ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'translation' => $translation,
        'query' => '%' . trim($query) . '%',
    ]);

    return [
        'mode' => 'keyword',
        'results' => $statement->fetchAll(),
        'heading' => 'Search Results',
    ];
}

function fetch_featured_verses(string $translation, int $limit = 3): array
{
    if (uses_external_translation($translation)) {
        $reference = [
            'book_id' => 43,
            'book_name' => 'John',
            'chapter' => 3,
            'start_verse' => 16,
            'end_verse' => null,
        ];

        return fetch_external_translation_reference_verses($reference, $translation)['results'];
    }

    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.translation = :translation
            AND (
                (books.name = :psalms AND verses.chapter_number = 23 AND verses.verse_number BETWEEN 1 AND 3)
                OR (books.name = :john AND verses.chapter_number = 3 AND verses.verse_number = 16)
                OR (books.name = :proverbs AND verses.chapter_number = 3 AND verses.verse_number BETWEEN 5 AND 6)
            )
        ORDER BY books.id ASC, verses.chapter_number ASC, verses.verse_number ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'translation' => $translation,
        'psalms' => 'Psalms',
        'john' => 'John',
        'proverbs' => 'Proverbs',
    ]);

    return $statement->fetchAll();
}

function fetch_dynamic_scripture_series(string $translation, int $limit = 4): array
{
    $limit = max(1, $limit);

    if (uses_external_translation($translation)) {
        return array_slice(fetch_featured_verses($translation, $limit), 0, $limit);
    }

    $total = count_records(
        'SELECT COUNT(*) FROM verses WHERE translation = :translation',
        ['translation' => $translation]
    );

    if ($total <= 0) {
        return [];
    }

    $targetCount = min($limit, $total);
    $seed = (int) date('z') + 1;
    $offsets = [];
    $attempt = 0;

    while (count($offsets) < $targetCount && $attempt < ($targetCount * 10)) {
        $offset = (($seed * 97) + ($attempt * 389)) % $total;

        if (!in_array($offset, $offsets, true)) {
            $offsets[] = $offset;
        }

        $attempt++;
    }

    sort($offsets);
    $series = [];

    foreach ($offsets as $offset) {
        $statement = db()->prepare(
            'SELECT verses.*, books.name AS book_name, books.abbreviation
            FROM verses
            INNER JOIN books ON books.id = verses.book_id
            WHERE verses.translation = :translation
            ORDER BY books.id ASC, verses.chapter_number ASC, verses.verse_number ASC
            LIMIT 1 OFFSET ' . (int) $offset
        );
        $statement->execute(['translation' => $translation]);
        $verse = $statement->fetch();

        if ($verse !== false) {
            $series[] = $verse;
        }
    }

    return $series;
}

function fetch_thematic_scripture_series(string $translation): array
{
    $themes = [
        ['theme' => 'Hope', 'query' => 'Romans 15:13'],
        ['theme' => 'Wisdom', 'query' => 'James 1:5'],
        ['theme' => 'Peace', 'query' => 'Isaiah 26:3'],
        ['theme' => 'Faith', 'query' => 'Hebrews 11:1'],
    ];
    $books = fetch_books();
    $series = [];

    foreach ($themes as $theme) {
        $reference = parse_reference_query((string) $theme['query'], $books);

        if ($reference === null) {
            continue;
        }

        $results = uses_external_translation($translation)
            ? fetch_external_translation_reference_verses($reference, $translation)
            : fetch_reference_verses($reference, $translation);

        $verse = $results['results'][0] ?? null;

        if ($verse === null) {
            continue;
        }

        $series[] = [
            'theme' => (string) $theme['theme'],
            'query' => (string) $theme['query'],
            'verse' => $verse,
        ];
    }

    return $series;
}

function fetch_verse_by_id(int $verseId): ?array
{
    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.id = :id
        LIMIT 1'
    );
    $statement->execute(['id' => $verseId]);
    $verse = $statement->fetch();

    return $verse ?: null;
}

function is_bookmarked(int $userId, int $verseId): bool
{
    return count_records(
        'SELECT COUNT(*) FROM bookmarks WHERE user_id = :user_id AND verse_id = :verse_id',
        ['user_id' => $userId, 'verse_id' => $verseId]
    ) > 0;
}

function save_bookmark_record(
    int $userId,
    int $verseId,
    string $tag = '',
    string $note = '',
    ?string $selectedText = null,
    ?string $highlightColor = null,
    ?int $selectionStart = null,
    ?int $selectionEnd = null
): void
{
    $normalizedTag = normalize_optional_text($tag);
    $normalizedNote = normalize_optional_text($note);
    $normalizedSelectedText = normalize_optional_text($selectedText ?? '');
    $normalizedColor = normalize_optional_text($highlightColor ?? '');
    $isSectionBookmark = $normalizedSelectedText !== null || $selectionStart !== null || $selectionEnd !== null;

    if (!$isSectionBookmark) {
        $existing = fetch_full_verse_bookmark($userId, $verseId);

        if ($existing !== null) {
            $statement = db()->prepare(
                'UPDATE bookmarks
                SET tag = :tag, note = :note
                WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'id' => $existing['id'],
                'user_id' => $userId,
                'tag' => $normalizedTag,
                'note' => $normalizedNote,
            ]);

            return;
        }
    }

    $statement = db()->prepare(
        'INSERT INTO bookmarks (
            user_id,
            verse_id,
            tag,
            note,
            selected_text,
            highlight_color,
            selection_start,
            selection_end
        ) VALUES (
            :user_id,
            :verse_id,
            :tag,
            :note,
            :selected_text,
            :highlight_color,
            :selection_start,
            :selection_end
        )'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
        'tag' => $normalizedTag,
        'note' => $normalizedNote,
        'selected_text' => $normalizedSelectedText,
        'highlight_color' => $normalizedColor ?: null,
        'selection_start' => $selectionStart,
        'selection_end' => $selectionEnd,
    ]);
}

function fetch_full_verse_bookmark(int $userId, int $verseId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM bookmarks
        WHERE user_id = :user_id
            AND verse_id = :verse_id
            AND selected_text IS NULL
            AND selection_start IS NULL
            AND selection_end IS NULL
        LIMIT 1'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
    ]);

    $bookmark = $statement->fetch();

    return $bookmark ?: null;
}

function fetch_favorite_bookmarks(int $userId, int $limit = 8): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_bookmarks_for_verses(int $userId, array $verseIds): array
{
    if ($verseIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($verseIds), '?'));
    $params = array_merge([$userId], array_map('intval', $verseIds));

    $statement = db()->prepare(
        'SELECT *
        FROM bookmarks
        WHERE user_id = ?
            AND verse_id IN (' . $placeholders . ')
        ORDER BY created_at ASC'
    );
    $statement->execute($params);

    $grouped = [];

    foreach ($statement->fetchAll() as $bookmark) {
        $grouped[(int) $bookmark['verse_id']][] = $bookmark;
    }

    return $grouped;
}

function update_bookmark_record(
    int $bookmarkId,
    int $userId,
    string $tag,
    string $note,
    ?string $highlightColor = null
): void
{
    $statement = db()->prepare(
        'UPDATE bookmarks
        SET tag = :tag, note = :note, highlight_color = :highlight_color
        WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $bookmarkId,
        'user_id' => $userId,
        'tag' => normalize_optional_text($tag),
        'note' => normalize_optional_text($note),
        'highlight_color' => normalize_optional_text($highlightColor ?? ''),
    ]);
}

function fetch_bookmark(int $bookmarkId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM bookmarks
        WHERE id = :id AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute(['id' => $bookmarkId, 'user_id' => $userId]);
    $bookmark = $statement->fetch();

    return $bookmark ?: null;
}

function fetch_bookmarks(int $userId): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function delete_bookmark_record(int $bookmarkId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM bookmarks WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $bookmarkId, 'user_id' => $userId]);
}

function fetch_notes(int $userId): array
{
    $statement = db()->prepare(
        'SELECT study_notes.*, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM study_notes
        LEFT JOIN verses ON verses.id = study_notes.verse_id
        LEFT JOIN books ON books.id = verses.book_id
        WHERE study_notes.user_id = :user_id
        ORDER BY study_notes.updated_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_note(int $noteId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM study_notes WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $noteId, 'user_id' => $userId]);
    $note = $statement->fetch();

    return $note ?: null;
}

function create_note_record(int $userId, string $title, string $content, ?int $verseId = null): void
{
    $statement = db()->prepare(
        'INSERT INTO study_notes (user_id, verse_id, title, content) VALUES (:user_id, :verse_id, :title, :content)'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
        'title' => trim($title),
        'content' => trim($content),
    ]);
}

function update_note_record(int $noteId, int $userId, string $title, string $content, ?int $verseId = null): void
{
    $statement = db()->prepare(
        'UPDATE study_notes
        SET verse_id = :verse_id, title = :title, content = :content
        WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $noteId,
        'user_id' => $userId,
        'verse_id' => $verseId,
        'title' => trim($title),
        'content' => trim($content),
    ]);
}

function delete_note_record(int $noteId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM study_notes WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $noteId, 'user_id' => $userId]);
}

function fetch_noteable_verses(int $userId): array
{
    $statement = db()->prepare(
        'SELECT DISTINCT verses.id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function format_verse_reference(array $verse): string
{
    return sprintf(
        '%s %d:%d (%s)',
        $verse['book_name'],
        $verse['chapter_number'],
        $verse['verse_number'],
        $verse['translation']
    );
}

function calculate_goal_progress_percent(array $goal): ?int
{
    $targetValue = isset($goal['target_value']) ? (int) $goal['target_value'] : 0;
    $currentValue = isset($goal['current_value']) ? (int) $goal['current_value'] : 0;

    if ($targetValue <= 0) {
        return null;
    }

    return max(0, min(100, (int) round(($currentValue / $targetValue) * 100)));
}

function format_event_date(?string $date): string
{
    if ($date === null || $date === '') {
        return 'TBD';
    }

    return date('M d', strtotime($date));
}

function format_event_datetime(?string $date): string
{
    if ($date === null || $date === '') {
        return 'TBD';
    }

    return date('M j, Y g:i A', strtotime($date));
}

function truncate_text(string $text, int $length = 140): string
{
    $trimmed = trim($text);

    if (mb_strlen($trimmed) <= $length) {
        return $trimmed;
    }

    return rtrim(mb_substr($trimmed, 0, $length - 3)) . '...';
}

function count_records(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function normalize_optional_text(string $value): ?string
{
    $trimmed = trim($value);

    return $trimmed === '' ? null : $trimmed;
}

function normalize_book_key(string $value): string
{
    return preg_replace('/[^a-z0-9]/i', '', mb_strtolower(trim($value))) ?? '';
}

function build_reference_heading(array $reference, string $translation): string
{
    $heading = sprintf('%s %d', $reference['book_name'], $reference['chapter']);

    if ($reference['start_verse'] !== null && $reference['end_verse'] !== null) {
        $heading .= sprintf(':%d-%d', $reference['start_verse'], $reference['end_verse']);
    } elseif ($reference['start_verse'] !== null) {
        $heading .= sprintf(':%d', $reference['start_verse']);
    }

    return $heading . ' (' . $translation . ')';
}

function fetch_external_translation_chapter_verses(int $bookId, int $chapterNumber, string $translation): array
{
    $book = fetch_book_by_id($bookId);

    if ($book === null) {
        return [];
    }

    $html = external_translation_api_get($translation, '/api/passages', [
        'ref' => build_external_translation_reference_string((string) $book['name'], $chapterNumber),
        'version' => $translation,
    ]);

    return parse_external_translation_passage_html(
        $html,
        (int) $book['id'],
        (string) $book['name'],
        (string) $book['abbreviation'],
        $chapterNumber,
        $translation,
        fetch_canonical_verse_id_map($bookId, $chapterNumber)
    );
}

function fetch_canonical_verse_id_map(int $bookId, int $chapterNumber): array
{
    $statement = db()->prepare(
        'SELECT verse_number, id
        FROM verses
        WHERE book_id = :book_id
            AND chapter_number = :chapter_number
            AND translation = :translation
        ORDER BY verse_number ASC'
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'translation' => 'KJV',
    ]);

    $map = [];

    foreach ($statement->fetchAll() as $row) {
        $map[(int) $row['verse_number']] = (int) $row['id'];
    }

    return $map;
}

function fetch_canonical_verse_id(int $bookId, int $chapterNumber, int $verseNumber): ?int
{
    $statement = db()->prepare(
        'SELECT id
        FROM verses
        WHERE book_id = :book_id
            AND chapter_number = :chapter_number
            AND verse_number = :verse_number
        ORDER BY CASE WHEN translation = :preferred_translation THEN 0 ELSE 1 END, id ASC
        LIMIT 1'
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'verse_number' => $verseNumber,
        'preferred_translation' => 'KJV',
    ]);

    $value = $statement->fetchColumn();

    return $value === false ? null : (int) $value;
}

function build_external_translation_reference_string(
    string $bookName,
    int $chapterNumber,
    ?int $startVerse = null,
    ?int $endVerse = null
): string {
    $reference = $bookName . ' ' . $chapterNumber;

    if ($startVerse !== null && $endVerse !== null) {
        $reference .= ':' . $startVerse . '-' . $endVerse;
    } elseif ($startVerse !== null) {
        $reference .= ':' . $startVerse;
    }

    return $reference;
}

function external_translation_api_get(string $translation, string $path, array $params): string
{
    $provider = external_translation_provider_config($translation);

    if ($provider === null) {
        throw new RuntimeException('No external provider is configured for ' . strtoupper(trim($translation)) . '.');
    }

    $providerName = (string) ($provider['provider'] ?? '');
    $request = build_external_translation_request($providerName, $path, $params);
    $url = $request['url'];
    $headers = $request['headers'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false || $status >= 400) {
            throw new RuntimeException(build_external_translation_error_message($translation, $error));
        }

        return (string) $response;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new RuntimeException(build_external_translation_error_message($translation));
    }

    return $response;
}

function build_external_translation_request(string $provider, string $path, array $params): array
{
    return match ($provider) {
        'nlt' => build_nlt_translation_request($path, $params),
        'youversion' => throw new RuntimeException(
            'NIV support via YouVersion is configured but not implemented yet. Add the YouVersion request details or app key so it can be completed.'
        ),
        default => throw new RuntimeException('Unsupported external translation provider: ' . $provider . '.'),
    };
}

function build_nlt_translation_request(string $path, array $params): array
{
    $apiKey = trim((string) (getenv('NLT_API_KEY') ?: 'TEST'));
    $params['key'] = $apiKey;

    return [
        'url' => 'https://api.nlt.to' . $path . '?' . http_build_query($params),
        'headers' => ['Accept: text/html,application/json'],
    ];
}

function build_external_translation_error_message(string $translation, string $transportError = ''): string
{
    $message = 'The ' . strtoupper(trim($translation)) . ' API request failed';

    if ($transportError !== '') {
        $message .= ': ' . $transportError;
    } else {
        $message .= '.';
    }

    return $message;
}

function parse_external_translation_passage_html(
    string $html,
    int $bookId,
    string $bookName,
    string $abbreviation,
    int $chapterNumber,
    string $translation,
    array $verseIdMap
): array {
    if (!preg_match_all('/<verse_export\b([^>]*)>(.*?)<\/verse_export>/is', $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $verses = [];

    foreach ($matches as $match) {
        $attributes = parse_external_translation_attributes($match[1]);
        $verseNumber = isset($attributes['vn']) ? (int) $attributes['vn'] : 0;

        if ($verseNumber <= 0) {
            continue;
        }

        $verseText = sanitize_external_translation_html($match[2]);

        if ($verseText === '') {
            continue;
        }

        $verses[] = [
            'id' => $verseIdMap[$verseNumber] ?? fetch_canonical_verse_id($bookId, $chapterNumber, $verseNumber) ?? 0,
            'book_id' => $bookId,
            'book_name' => $bookName,
            'abbreviation' => $abbreviation,
            'chapter_number' => $chapterNumber,
            'verse_number' => $verseNumber,
            'verse_text' => $verseText,
            'translation' => $translation,
        ];
    }

    return $verses;
}

function parse_external_translation_search_html(string $html, array $books, string $translation, int $limit = 25): array
{
    if (!preg_match_all('/<tr>\s*<td><a[^>]*>([^<]+)<\/a><\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $results = [];

    foreach ($matches as $match) {
        $reference = parse_external_translation_dot_reference(trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $books);

        if ($reference === null) {
            continue;
        }

        $results[] = [
            'id' => fetch_canonical_verse_id($reference['book_id'], $reference['chapter_number'], $reference['verse_number']) ?? 0,
            'book_id' => $reference['book_id'],
            'book_name' => $reference['book_name'],
            'abbreviation' => $reference['abbreviation'],
            'chapter_number' => $reference['chapter_number'],
            'verse_number' => $reference['verse_number'],
            'verse_text' => sanitize_external_translation_html($match[2]),
            'translation' => $translation,
        ];

        if (count($results) >= $limit) {
            break;
        }
    }

    return $results;
}

function parse_external_translation_attributes(string $attributeString): array
{
    $attributes = [];

    if (preg_match_all('/([a-z_]+)="([^"]*)"/i', $attributeString, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return $attributes;
}

function sanitize_external_translation_html(string $html): string
{
    $clean = preg_replace('/<a\b[^>]*class="a-tn"[^>]*>.*?<\/a>/is', '', $html) ?? $html;
    $clean = preg_replace('/<span\b[^>]*class="tn"[^>]*>.*?<\/span>/is', '', $clean) ?? $clean;
    $clean = preg_replace('/<span\b[^>]*class="vn"[^>]*>.*?<\/span>/is', '', $clean) ?? $clean;
    $clean = preg_replace('/<h[23]\b[^>]*>.*?<\/h[23]>/is', '', $clean) ?? $clean;
    $clean = preg_replace('/<\/p>\s*<p[^>]*>/i', ' ', $clean) ?? $clean;
    $clean = preg_replace('/<br\s*\/?>/i', ' ', $clean) ?? $clean;
    $clean = strip_tags($clean);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

    return trim($clean);
}

function parse_external_translation_dot_reference(string $reference, array $books): ?array
{
    if (!preg_match('/^([1-3]?\s*[A-Za-z]+)\.(\d+)\.(\d+)$/', str_replace(' ', '', $reference), $matches)) {
        return null;
    }

    $bookToken = normalize_book_key($matches[1]);
    $chapterNumber = (int) $matches[2];
    $verseNumber = (int) $matches[3];
    $lookup = external_translation_book_alias_lookup($books);
    $book = $lookup[$bookToken] ?? null;

    if ($book === null) {
        return null;
    }

    return [
        'book_id' => (int) $book['id'],
        'book_name' => (string) $book['name'],
        'abbreviation' => (string) $book['abbreviation'],
        'chapter_number' => $chapterNumber,
        'verse_number' => $verseNumber,
    ];
}

function external_translation_book_alias_lookup(array $books): array
{
    static $lookup = null;

    if ($lookup !== null) {
        return $lookup;
    }

    $lookup = [];

    foreach ($books as $book) {
        $aliases = [
            (string) $book['name'],
            (string) $book['abbreviation'],
        ];

        foreach (external_translation_manual_aliases() as $alias => $canonicalName) {
            if (strcasecmp((string) $book['name'], $canonicalName) === 0) {
                $aliases[] = $alias;
            }
        }

        foreach ($aliases as $alias) {
            $lookup[normalize_book_key($alias)] = $book;
        }
    }

    return $lookup;
}

function external_translation_manual_aliases(): array
{
    return [
        'Gen' => 'Genesis',
        'Exod' => 'Exodus',
        'Judg' => 'Judges',
        '1Sam' => '1 Samuel',
        '2Sam' => '2 Samuel',
        '1Kgs' => '1 Kings',
        '2Kgs' => '2 Kings',
        '1Chr' => '1 Chronicles',
        '2Chr' => '2 Chronicles',
        'Esth' => 'Esther',
        'Ps' => 'Psalms',
        'Pr' => 'Proverbs',
        'Prov' => 'Proverbs',
        'Eccl' => 'Ecclesiastes',
        'Ezek' => 'Ezekiel',
        'Obad' => 'Obadiah',
        'Zech' => 'Zechariah',
        'Matt' => 'Matthew',
        'Mk' => 'Mark',
        'Lk' => 'Luke',
        'Jn' => 'John',
        'Ac' => 'Acts',
        'Rom' => 'Romans',
        '1Cor' => '1 Corinthians',
        '2Cor' => '2 Corinthians',
        '1Thess' => '1 Thessalonians',
        '2Thess' => '2 Thessalonians',
        '1Tim' => '1 Timothy',
        '2Tim' => '2 Timothy',
        'Phlm' => 'Philemon',
        'Jas' => 'James',
        '1Pet' => '1 Peter',
        '2Pet' => '2 Peter',
        '1John' => '1 John',
        '2John' => '2 John',
        '3John' => '3 John',
        'Rev' => 'Revelation',
    ];
}
