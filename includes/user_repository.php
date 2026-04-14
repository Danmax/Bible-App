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

function search_users_by_name(string $query, int $limit = 20): array
{
    $like = '%' . $query . '%';
    $statement = db()->prepare(
        'SELECT id, name, city, avatar_url, primary_flag
        FROM users
        WHERE name LIKE :q
        ORDER BY name ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['q' => $like]);

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
    ?string $primaryFlag = null
): array
{
    $statement = db()->prepare(
        'UPDATE users
        SET name = :name,
            email = :email,
            city = :city,
            avatar_url = :avatar_url,
            primary_flag = :primary_flag
        WHERE id = :id'
    );
    $statement->execute([
        'id' => $userId,
        'name' => trim($name),
        'email' => mb_strtolower(trim($email)),
        'city' => normalize_optional_text($city ?? ''),
        'avatar_url' => normalize_optional_text($avatarUrl ?? ''),
        'primary_flag' => normalize_optional_text($primaryFlag ?? ''),
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