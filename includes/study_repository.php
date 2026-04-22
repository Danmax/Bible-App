<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function curated_studies_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'studies'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function study_table_exists(string $tableName): bool
{
    static $cache = [];
    $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName) ?? '';

    if ($safeName === '') {
        return false;
    }

    if (array_key_exists($safeName, $cache)) {
        return $cache[$safeName];
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE " . db()->quote($safeName));
        $cache[$safeName] = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $cache[$safeName] = false;
    }

    return $cache[$safeName];
}

function study_items_available(): bool
{
    return study_table_exists('study_step_items');
}

function study_editor_requests_available(): bool
{
    return study_table_exists('study_editor_access_requests');
}

function study_template_options(): array
{
    return [
        'seven-day-devotional' => [
            'label' => '7-Day Devotional',
            'duration_days' => 7,
            'summary' => 'A one-week devotional rhythm with Scripture, reflection, action, and a locked teaching video.',
            'description' => 'Walk through a focused week of Scripture, prayer, reflection questions, and daily obedience steps.',
            'badge_name' => '7-Day Devotional Finisher',
        ],
        'thirty-day-plan' => [
            'label' => '30-Day Bible Plan',
            'duration_days' => 30,
            'summary' => 'A month-long reading plan with steady daily passages and weekly reflection checkpoints.',
            'description' => 'Build a 30-day Scripture rhythm with repeatable readings, reflection questions, challenges, and group encouragement.',
            'badge_name' => '30-Day Bible Plan Finisher',
        ],
        'group-study' => [
            'label' => 'Group Study',
            'duration_days' => 6,
            'summary' => 'A discussion-forward study designed for friends, small groups, and shared challenges.',
            'description' => 'Gather friends around Scripture, shared questions, practical challenges, and discussion after each study step.',
            'badge_name' => 'Group Study Finisher',
        ],
    ];
}

function default_study_template_steps(string $templateKey, int $durationDays): array
{
    $references = [
        'Proverbs 3:5-6',
        'John 15:1-8',
        'Psalm 119:105',
        'Romans 12:1-2',
        'Philippians 4:4-9',
        'James 1:22-25',
        'Matthew 28:18-20',
    ];

    $steps = [];

    for ($day = 1; $day <= $durationDays; $day++) {
        $reference = $references[($day - 1) % count($references)];
        $isGroupStudy = $templateKey === 'group-study';
        $sectionTitle = $isGroupStudy ? 'Group Conversation' : 'Daily Study';
        $devotionalBody = $isGroupStudy
            ? 'Read the passage together. Name what the text says, where it presses on real life, and how the group can practice it before the next meeting.'
            : 'Read the passage slowly. Notice what God reveals, what He asks you to trust, and one concrete step of obedience for today.';

        $steps[] = [
            'day_number' => $day,
            'title' => 'Day ' . $day,
            'section_title' => $sectionTitle,
            'content' => '',
            'verses' => [],
            'questions' => [],
            'challenges' => [],
            'video_title' => '',
            'youtube_video_id' => '',
            'video_unlock_rule' => 'after_step',
            'items' => [
                [
                    'item_type' => 'bible_verse',
                    'title' => 'Scripture reading',
                    'body' => '',
                    'resource_url' => '',
                    'bible_reference' => $reference,
                    'unlock_rule' => 'none',
                    'is_required' => 1,
                ],
                [
                    'item_type' => 'devotional',
                    'title' => $sectionTitle,
                    'body' => $devotionalBody,
                    'resource_url' => '',
                    'bible_reference' => '',
                    'unlock_rule' => 'after_previous',
                    'is_required' => 1,
                ],
                [
                    'item_type' => 'reflection',
                    'title' => 'Reflection questions',
                    'body' => "What word, phrase, or promise stands out from this passage?\nWhat is one honest response you can bring to God today?",
                    'resource_url' => '',
                    'bible_reference' => '',
                    'unlock_rule' => 'after_previous',
                    'is_required' => 1,
                ],
                [
                    'item_type' => 'challenge',
                    'title' => $isGroupStudy ? 'Shared challenge' : 'Daily challenge',
                    'body' => 'Practice one action from this passage before the day ends.',
                    'resource_url' => '',
                    'bible_reference' => '',
                    'unlock_rule' => 'after_previous',
                    'is_required' => 1,
                ],
                [
                    'item_type' => 'video',
                    'title' => 'Teaching video',
                    'body' => 'Add a YouTube URL or video ID when this day needs a teaching segment.',
                    'resource_url' => '',
                    'bible_reference' => '',
                    'unlock_rule' => 'after_previous',
                    'is_required' => 0,
                ],
            ],
        ];
    }

    return $steps;
}

function normalize_study_slug(string $value): string
{
    $slug = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower(trim($value))) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? mb_substr($slug, 0, 170) : 'study';
}

function unique_study_slug(string $title, ?int $ignoreStudyId = null): string
{
    $baseSlug = normalize_study_slug($title);
    $slug = $baseSlug;
    $suffix = 2;

    while (study_slug_exists($slug, $ignoreStudyId)) {
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function study_slug_exists(string $slug, ?int $ignoreStudyId = null): bool
{
    $sql = 'SELECT id FROM studies WHERE slug = :slug';
    $params = ['slug' => $slug];

    if ($ignoreStudyId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreStudyId;
    }

    $sql .= ' LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetch() !== false;
}

function fetch_public_studies(?int $userId = null): array
{
    $enrollmentSelect = $userId !== null ? ', e.id AS current_user_enrollment_id, e.completed_at AS current_user_completed_at' : '';
    $enrollmentJoin = $userId !== null ? 'LEFT JOIN user_study_enrollments e ON e.study_id = s.id AND e.user_id = :user_id' : '';
    $params = [];

    if ($userId !== null) {
        $params['user_id'] = $userId;
    }

    $statement = db()->prepare(
        "SELECT s.*, COUNT(st.id) AS step_count{$enrollmentSelect}
        FROM studies s
        LEFT JOIN study_steps st ON st.study_id = s.id
        {$enrollmentJoin}
        WHERE s.status = 'published'
            AND s.visibility = 'public'
        GROUP BY s.id
        ORDER BY s.is_featured DESC, s.updated_at DESC, s.id DESC"
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetch_manageable_studies(?int $userId = null, bool $includeAll = true): array
{
    $where = '';
    $params = [];

    if (!$includeAll && $userId !== null) {
        $where = 'WHERE s.created_by_user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $statement = db()->prepare(
        'SELECT s.*, COUNT(st.id) AS step_count
        FROM studies s
        LEFT JOIN study_steps st ON st.study_id = s.id
        ' . $where . '
        GROUP BY s.id
        ORDER BY s.updated_at DESC, s.id DESC'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function delete_study_record(int $studyId): void
{
    $statement = db()->prepare('DELETE FROM studies WHERE id = :id');
    $statement->execute(['id' => $studyId]);
}

function fetch_study_badge(int $studyId): ?array
{
    $statement = db()->prepare('SELECT * FROM study_badges WHERE study_id = :study_id LIMIT 1');
    $statement->execute(['study_id' => $studyId]);
    $badge = $statement->fetch();

    return $badge ?: null;
}

function fetch_study_by_slug(string $slug, ?int $userId = null, bool $includeDraft = false): ?array
{
    $params = ['slug' => $slug];
    $conditions = ['s.slug = :slug'];

    if (!$includeDraft) {
        $conditions[] = "s.status = 'published'";
        $conditions[] = "s.visibility = 'public'";
    }

    $enrollmentSelect = $userId !== null ? ', e.id AS current_user_enrollment_id, e.completed_at AS current_user_completed_at' : '';
    $enrollmentJoin = $userId !== null ? 'LEFT JOIN user_study_enrollments e ON e.study_id = s.id AND e.user_id = :user_id' : '';

    if ($userId !== null) {
        $params['user_id'] = $userId;
    }

    $statement = db()->prepare(
        "SELECT s.*{$enrollmentSelect}
        FROM studies s
        {$enrollmentJoin}
        WHERE " . implode(' AND ', $conditions) . '
        LIMIT 1'
    );
    $statement->execute($params);
    $study = $statement->fetch();

    return $study ?: null;
}

function fetch_study_by_id(int $studyId): ?array
{
    $statement = db()->prepare('SELECT * FROM studies WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $studyId]);
    $study = $statement->fetch();

    return $study ?: null;
}

function fetch_study_steps(int $studyId): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM study_steps
        WHERE study_id = :study_id
        ORDER BY sort_order ASC, day_number ASC, id ASC'
    );
    $statement->execute(['study_id' => $studyId]);
    $steps = $statement->fetchAll();

    foreach ($steps as &$step) {
        $step['verses'] = fetch_study_step_verses((int) $step['id']);
        $step['questions'] = fetch_study_step_questions((int) $step['id']);
        $step['challenges'] = fetch_study_step_challenges((int) $step['id']);
        $step['items'] = fetch_study_step_items((int) $step['id']);
    }

    return $steps;
}

function fetch_study_step_by_day(int $studyId, int $dayNumber): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM study_steps
        WHERE study_id = :study_id
            AND day_number = :day_number
        LIMIT 1'
    );
    $statement->execute([
        'study_id' => $studyId,
        'day_number' => $dayNumber,
    ]);
    $step = $statement->fetch();

    if ($step === false) {
        return null;
    }

    $step['verses'] = fetch_study_step_verses((int) $step['id']);
    $step['questions'] = fetch_study_step_questions((int) $step['id']);
    $step['challenges'] = fetch_study_step_challenges((int) $step['id']);
    $step['items'] = fetch_study_step_items((int) $step['id']);

    return $step;
}

function fetch_study_step_verses(int $stepId): array
{
    $statement = db()->prepare('SELECT * FROM study_step_verses WHERE study_step_id = :step_id ORDER BY sort_order ASC, id ASC');
    $statement->execute(['step_id' => $stepId]);

    return $statement->fetchAll();
}

function fetch_study_step_questions(int $stepId): array
{
    $statement = db()->prepare('SELECT * FROM study_step_questions WHERE study_step_id = :step_id ORDER BY sort_order ASC, id ASC');
    $statement->execute(['step_id' => $stepId]);

    return $statement->fetchAll();
}

function fetch_study_step_challenges(int $stepId): array
{
    $statement = db()->prepare('SELECT * FROM study_step_challenges WHERE study_step_id = :step_id ORDER BY sort_order ASC, id ASC');
    $statement->execute(['step_id' => $stepId]);

    return $statement->fetchAll();
}

function fetch_study_step_items(int $stepId): array
{
    if (!study_items_available()) {
        return [];
    }

    $statement = db()->prepare('SELECT * FROM study_step_items WHERE study_step_id = :step_id ORDER BY sort_order ASC, id ASC');
    $statement->execute(['step_id' => $stepId]);

    return $statement->fetchAll();
}

function create_study_from_template(int $userId, string $templateKey, string $title): int
{
    $templates = study_template_options();

    if (!isset($templates[$templateKey])) {
        throw new RuntimeException('Choose a valid study template.');
    }

    $template = $templates[$templateKey];
    $durationDays = (int) $template['duration_days'];
    $slug = unique_study_slug($title);

    $statement = db()->prepare(
        'INSERT INTO studies (
            created_by_user_id, template_key, title, slug, summary, description, duration_days, status, visibility
        ) VALUES (
            :created_by_user_id, :template_key, :title, :slug, :summary, :description, :duration_days, :status, :visibility
        )'
    );
    $statement->execute([
        'created_by_user_id' => $userId,
        'template_key' => $templateKey,
        'title' => $title,
        'slug' => $slug,
        'summary' => $template['summary'],
        'description' => $template['description'],
        'duration_days' => $durationDays,
        'status' => 'draft',
        'visibility' => 'public',
    ]);

    $studyId = (int) db()->lastInsertId();
    replace_study_steps($studyId, default_study_template_steps($templateKey, $durationDays));
    upsert_study_badge($studyId, (string) $template['badge_name'], 'Awarded after completing every required day in this study.');

    return $studyId;
}

function update_study_record(int $studyId, array $payload): void
{
    $slug = trim((string) ($payload['slug'] ?? '')) !== ''
        ? normalize_study_slug((string) $payload['slug'])
        : unique_study_slug((string) $payload['title'], $studyId);

    if (study_slug_exists($slug, $studyId)) {
        throw new RuntimeException('That study URL slug is already in use.');
    }

    $statement = db()->prepare(
        'UPDATE studies
        SET title = :title,
            slug = :slug,
            summary = :summary,
            description = :description,
            duration_days = :duration_days,
            cover_image_url = :cover_image_url,
            status = :status,
            visibility = :visibility,
            is_featured = :is_featured
        WHERE id = :id'
    );
    $statement->execute([
        'id' => $studyId,
        'title' => $payload['title'],
        'slug' => $slug,
        'summary' => $payload['summary'],
        'description' => $payload['description'],
        'duration_days' => $payload['duration_days'],
        'cover_image_url' => $payload['cover_image_url'],
        'status' => $payload['status'],
        'visibility' => $payload['visibility'],
        'is_featured' => !empty($payload['is_featured']) ? 1 : 0,
    ]);

    upsert_study_badge($studyId, (string) ($payload['badge_name'] ?? 'Study Finisher'), (string) ($payload['badge_description'] ?? 'Awarded after completing this study.'));
}

function replace_study_steps(int $studyId, array $steps): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM study_steps WHERE study_id = :study_id')->execute(['study_id' => $studyId]);

        foreach ($steps as $index => $step) {
            $stepStatement = $pdo->prepare(
                'INSERT INTO study_steps (
                    study_id, day_number, title, section_title, content, video_title, youtube_video_id, video_unlock_rule, sort_order
                ) VALUES (
                    :study_id, :day_number, :title, :section_title, :content, :video_title, :youtube_video_id, :video_unlock_rule, :sort_order
                )'
            );
            $stepStatement->execute([
                'study_id' => $studyId,
                'day_number' => (int) ($step['day_number'] ?? ($index + 1)),
                'title' => trim((string) ($step['title'] ?? 'Day ' . ($index + 1))),
                'section_title' => trim((string) ($step['section_title'] ?? 'Daily Study')),
                'content' => trim((string) ($step['content'] ?? '')),
                'video_title' => trim((string) ($step['video_title'] ?? '')),
                'youtube_video_id' => normalize_youtube_video_id((string) ($step['youtube_video_id'] ?? '')),
                'video_unlock_rule' => normalize_video_unlock_rule((string) ($step['video_unlock_rule'] ?? 'after_step')),
                'sort_order' => $index,
            ]);

            $stepId = (int) $pdo->lastInsertId();
            insert_study_step_children($stepId, 'study_step_verses', 'reference_text', $step['verses'] ?? []);
            insert_study_step_children($stepId, 'study_step_questions', 'question_text', $step['questions'] ?? []);
            insert_study_step_children($stepId, 'study_step_challenges', 'challenge_text', $step['challenges'] ?? []);

            if (study_items_available()) {
                insert_study_step_items($stepId, $step['items'] ?? []);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function insert_study_step_items(int $stepId, array $items): void
{
    $statement = db()->prepare(
        'INSERT INTO study_step_items (
            study_step_id, item_type, title, body, resource_url, bible_reference, unlock_rule, is_required, sort_order
        ) VALUES (
            :study_step_id, :item_type, :title, :body, :resource_url, :bible_reference, :unlock_rule, :is_required, :sort_order
        )'
    );

    foreach (array_values($items) as $index => $item) {
        $title = trim((string) ($item['title'] ?? ''));
        $body = trim((string) ($item['body'] ?? ''));
        $resourceUrl = trim((string) ($item['resource_url'] ?? ''));
        $bibleReference = trim((string) ($item['bible_reference'] ?? ''));

        if ($title === '' && $body === '' && $resourceUrl === '' && $bibleReference === '') {
            continue;
        }

        $statement->execute([
            'study_step_id' => $stepId,
            'item_type' => normalize_study_item_type((string) ($item['item_type'] ?? 'devotional')),
            'title' => $title !== '' ? $title : 'Study item',
            'body' => $body,
            'resource_url' => normalize_study_resource_url($resourceUrl),
            'bible_reference' => $bibleReference,
            'unlock_rule' => normalize_study_item_unlock_rule((string) ($item['unlock_rule'] ?? 'none')),
            'is_required' => !empty($item['is_required']) ? 1 : 0,
            'sort_order' => $index,
        ]);
    }
}

function normalize_study_item_type(string $value): string
{
    $allowed = ['devotional', 'reflection', 'challenge', 'image', 'video', 'bible_verse'];

    return in_array($value, $allowed, true) ? $value : 'devotional';
}

function normalize_study_item_unlock_rule(string $value): string
{
    $allowed = ['none', 'after_previous'];

    return in_array($value, $allowed, true) ? $value : 'none';
}

function normalize_study_resource_url(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return '';
    }

    if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return $trimmed;
    }

    return preg_match('/^[a-zA-Z0-9_-]{6,80}$/', $trimmed) ? $trimmed : '';
}

function insert_study_step_children(int $stepId, string $table, string $column, array $values): void
{
    $allowed = [
        'study_step_verses' => 'reference_text',
        'study_step_questions' => 'question_text',
        'study_step_challenges' => 'challenge_text',
    ];

    if (($allowed[$table] ?? '') !== $column) {
        throw new RuntimeException('Invalid study step child table.');
    }

    $statement = db()->prepare(
        "INSERT INTO {$table} (study_step_id, {$column}, sort_order)
        VALUES (:study_step_id, :value, :sort_order)"
    );

    foreach (array_values($values) as $index => $value) {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            continue;
        }

        $statement->execute([
            'study_step_id' => $stepId,
            'value' => $trimmed,
            'sort_order' => $index,
        ]);
    }
}

function normalize_youtube_video_id(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return '';
    }

    if (preg_match('/^[a-zA-Z0-9_-]{6,80}$/', $trimmed)) {
        return $trimmed;
    }

    $parts = parse_url($trimmed);
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (isset($query['v']) && is_string($query['v'])) {
        return preg_match('/^[a-zA-Z0-9_-]{6,80}$/', $query['v']) ? $query['v'] : '';
    }

    $path = trim((string) ($parts['path'] ?? ''), '/');
    $segments = explode('/', $path);
    $candidate = end($segments);

    return is_string($candidate) && preg_match('/^[a-zA-Z0-9_-]{6,80}$/', $candidate) ? $candidate : '';
}

function normalize_video_unlock_rule(string $value): string
{
    $allowed = ['none', 'after_reflection', 'after_challenge', 'after_step'];

    return in_array($value, $allowed, true) ? $value : 'after_step';
}

function fetch_study_enrollment(int $studyId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM user_study_enrollments
        WHERE study_id = :study_id
            AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'study_id' => $studyId,
        'user_id' => $userId,
    ]);
    $enrollment = $statement->fetch();

    return $enrollment ?: null;
}

function fetch_study_enrollment_by_id(int $enrollmentId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT e.*, s.title AS study_title, s.slug AS study_slug, s.duration_days
        FROM user_study_enrollments e
        INNER JOIN studies s ON s.id = e.study_id
        WHERE e.id = :id
            AND e.user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $enrollmentId,
        'user_id' => $userId,
    ]);
    $enrollment = $statement->fetch();

    return $enrollment ?: null;
}

function enroll_user_in_study(int $studyId, int $userId): int
{
    $existing = fetch_study_enrollment($studyId, $userId);

    if ($existing !== null) {
        return (int) $existing['id'];
    }

    $statement = db()->prepare(
        'INSERT INTO user_study_enrollments (study_id, user_id)
        VALUES (:study_id, :user_id)'
    );
    $statement->execute([
        'study_id' => $studyId,
        'user_id' => $userId,
    ]);

    return (int) db()->lastInsertId();
}

function fetch_step_progress(int $enrollmentId, int $stepId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM user_study_step_progress
        WHERE enrollment_id = :enrollment_id
            AND study_step_id = :study_step_id
        LIMIT 1'
    );
    $statement->execute([
        'enrollment_id' => $enrollmentId,
        'study_step_id' => $stepId,
    ]);
    $progress = $statement->fetch();

    return $progress ?: null;
}

function fetch_enrollment_progress_map(int $enrollmentId): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM user_study_step_progress
        WHERE enrollment_id = :enrollment_id'
    );
    $statement->execute(['enrollment_id' => $enrollmentId]);
    $rows = $statement->fetchAll();
    $map = [];

    foreach ($rows as $row) {
        $map[(int) $row['study_step_id']] = $row;
    }

    return $map;
}

function fetch_enrollment_item_progress_map(int $enrollmentId): array
{
    if (!study_table_exists('user_study_item_progress')) {
        return [];
    }

    $statement = db()->prepare(
        'SELECT *
        FROM user_study_item_progress
        WHERE enrollment_id = :enrollment_id'
    );
    $statement->execute(['enrollment_id' => $enrollmentId]);
    $rows = $statement->fetchAll();
    $map = [];

    foreach ($rows as $row) {
        $map[(int) $row['study_step_item_id']] = $row;
    }

    return $map;
}

function sync_study_item_progress(int $enrollmentId, array $itemIds): void
{
    if (!study_table_exists('user_study_item_progress')) {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO user_study_item_progress (enrollment_id, study_step_item_id, completed_at)
        SELECT :enrollment_id, i.id, NOW()
        FROM study_step_items i
        INNER JOIN study_steps s ON s.id = i.study_step_id
        INNER JOIN user_study_enrollments e ON e.study_id = s.study_id AND e.id = :enrollment_id_check
        WHERE i.id = :study_step_item_id
        ON DUPLICATE KEY UPDATE completed_at = COALESCE(completed_at, VALUES(completed_at))'
    );

    foreach (array_unique(array_map('intval', $itemIds)) as $itemId) {
        if ($itemId <= 0) {
            continue;
        }

        $statement->execute([
            'enrollment_id' => $enrollmentId,
            'enrollment_id_check' => $enrollmentId,
            'study_step_item_id' => $itemId,
        ]);
    }
}

function upsert_step_progress(int $enrollmentId, int $stepId, string $reflectionResponse, bool $challengeCompleted, bool $completeStep, string $videoUnlockRule): void
{
    $existing = fetch_step_progress($enrollmentId, $stepId);
    $nowSql = date('Y-m-d H:i:s');
    $reflection = trim($reflectionResponse);
    $challengeAt = $challengeCompleted ? $nowSql : null;
    $completedAt = $completeStep ? $nowSql : null;
    $videoUnlockedAt = study_step_video_should_unlock($videoUnlockRule, $reflection, $challengeCompleted, $completeStep) ? $nowSql : null;

    if ($existing === null) {
        $statement = db()->prepare(
            'INSERT INTO user_study_step_progress (
                enrollment_id, study_step_id, reflection_response, challenge_completed_at, video_unlocked_at, completed_at
            ) VALUES (
                :enrollment_id, :study_step_id, :reflection_response, :challenge_completed_at, :video_unlocked_at, :completed_at
            )'
        );
        $statement->execute([
            'enrollment_id' => $enrollmentId,
            'study_step_id' => $stepId,
            'reflection_response' => $reflection,
            'challenge_completed_at' => $challengeAt,
            'video_unlocked_at' => $videoUnlockedAt,
            'completed_at' => $completedAt,
        ]);

        return;
    }

    $statement = db()->prepare(
        'UPDATE user_study_step_progress
        SET reflection_response = :reflection_response,
            challenge_completed_at = COALESCE(challenge_completed_at, :challenge_completed_at),
            video_unlocked_at = COALESCE(video_unlocked_at, :video_unlocked_at),
            completed_at = COALESCE(completed_at, :completed_at)
        WHERE id = :id'
    );
    $statement->execute([
        'id' => (int) $existing['id'],
        'reflection_response' => $reflection,
        'challenge_completed_at' => $challengeAt,
        'video_unlocked_at' => $videoUnlockedAt,
        'completed_at' => $completedAt,
    ]);
}

function study_step_video_should_unlock(string $rule, string $reflection, bool $challengeCompleted, bool $completeStep): bool
{
    return match ($rule) {
        'none' => true,
        'after_reflection' => $reflection !== '',
        'after_challenge' => $challengeCompleted,
        default => $completeStep,
    };
}

function refresh_study_completion(int $enrollmentId, int $studyId, int $userId): void
{
    $totalStatement = db()->prepare('SELECT COUNT(*) FROM study_steps WHERE study_id = :study_id');
    $totalStatement->execute(['study_id' => $studyId]);
    $total = (int) $totalStatement->fetchColumn();

    if ($total <= 0) {
        return;
    }

    $doneStatement = db()->prepare(
        'SELECT COUNT(*)
        FROM user_study_step_progress p
        INNER JOIN study_steps s ON s.id = p.study_step_id
        WHERE p.enrollment_id = :enrollment_id
            AND s.study_id = :study_id
            AND p.completed_at IS NOT NULL'
    );
    $doneStatement->execute([
        'enrollment_id' => $enrollmentId,
        'study_id' => $studyId,
    ]);

    if ((int) $doneStatement->fetchColumn() < $total) {
        return;
    }

    db()->prepare(
        'UPDATE user_study_enrollments
        SET completed_at = COALESCE(completed_at, NOW()),
            badge_awarded_at = COALESCE(badge_awarded_at, NOW())
        WHERE id = :id'
    )->execute(['id' => $enrollmentId]);

    award_study_badge($studyId, $userId, $enrollmentId);
}

function upsert_study_badge(int $studyId, string $badgeName, string $badgeDescription): void
{
    $statement = db()->prepare(
        'INSERT INTO study_badges (study_id, badge_name, badge_description)
        VALUES (:study_id, :badge_name, :badge_description)
        ON DUPLICATE KEY UPDATE badge_name = VALUES(badge_name), badge_description = VALUES(badge_description)'
    );
    $statement->execute([
        'study_id' => $studyId,
        'badge_name' => trim($badgeName) !== '' ? trim($badgeName) : 'Study Finisher',
        'badge_description' => trim($badgeDescription),
    ]);
}

function award_study_badge(int $studyId, int $userId, int $enrollmentId): void
{
    $statement = db()->prepare('SELECT id FROM study_badges WHERE study_id = :study_id LIMIT 1');
    $statement->execute(['study_id' => $studyId]);
    $badgeId = (int) $statement->fetchColumn();

    if ($badgeId <= 0) {
        upsert_study_badge($studyId, 'Study Finisher', 'Awarded after completing this study.');
        $statement->execute(['study_id' => $studyId]);
        $badgeId = (int) $statement->fetchColumn();
    }

    if ($badgeId <= 0) {
        return;
    }

    db()->prepare(
        'INSERT IGNORE INTO user_study_badges (user_id, study_badge_id, enrollment_id)
        VALUES (:user_id, :study_badge_id, :enrollment_id)'
    )->execute([
        'user_id' => $userId,
        'study_badge_id' => $badgeId,
        'enrollment_id' => $enrollmentId,
    ]);
}

function create_study_invite(int $studyId, ?int $enrollmentId, int $senderUserId, string $recipientEmail = ''): array
{
    $token = bin2hex(random_bytes(24));
    $statement = db()->prepare(
        'INSERT INTO study_invites (study_id, enrollment_id, sender_user_id, recipient_email, invite_token)
        VALUES (:study_id, :enrollment_id, :sender_user_id, :recipient_email, :invite_token)'
    );
    $statement->execute([
        'study_id' => $studyId,
        'enrollment_id' => $enrollmentId,
        'sender_user_id' => $senderUserId,
        'recipient_email' => trim($recipientEmail) !== '' ? mb_strtolower(trim($recipientEmail)) : null,
        'invite_token' => $token,
    ]);

    return fetch_study_invite_by_token($token) ?? ['invite_token' => $token];
}

function fetch_study_invite_by_token(string $token): ?array
{
    $statement = db()->prepare(
        'SELECT i.*, s.slug AS study_slug, s.title AS study_title
        FROM study_invites i
        INNER JOIN studies s ON s.id = i.study_id
        WHERE i.invite_token = :token
        LIMIT 1'
    );
    $statement->execute(['token' => trim($token)]);
    $invite = $statement->fetch();

    return $invite ?: null;
}

function accept_study_invite(string $token, int $userId): ?int
{
    $invite = fetch_study_invite_by_token($token);

    if ($invite === null || (string) $invite['status'] !== 'open') {
        return null;
    }

    $enrollmentId = enroll_user_in_study((int) $invite['study_id'], $userId);
    db()->prepare(
        "UPDATE study_invites
        SET status = 'accepted',
            accepted_by_user_id = :user_id,
            accepted_at = NOW()
        WHERE id = :id"
    )->execute([
        'id' => (int) $invite['id'],
        'user_id' => $userId,
    ]);

    return $enrollmentId;
}

function create_study_discussion_message(int $studyId, ?int $enrollmentId, int $senderUserId, string $message): void
{
    $trimmed = trim($message);

    if ($trimmed === '') {
        throw new RuntimeException('Write a discussion message first.');
    }

    if (mb_strlen($trimmed) > 2000) {
        throw new RuntimeException('Discussion messages must stay under 2,000 characters.');
    }

    $statement = db()->prepare(
        'INSERT INTO study_discussion_messages (study_id, enrollment_id, sender_user_id, message_text)
        VALUES (:study_id, :enrollment_id, :sender_user_id, :message_text)'
    );
    $statement->execute([
        'study_id' => $studyId,
        'enrollment_id' => $enrollmentId,
        'sender_user_id' => $senderUserId,
        'message_text' => $trimmed,
    ]);
}

function fetch_study_discussion_messages(int $studyId, int $limit = 40): array
{
    $statement = db()->prepare(
        'SELECT m.*, u.name AS sender_name, u.avatar_url AS sender_avatar_url
        FROM study_discussion_messages m
        LEFT JOIN users u ON u.id = m.sender_user_id
        WHERE m.study_id = :study_id
        ORDER BY m.created_at DESC, m.id DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['study_id' => $studyId]);

    return $statement->fetchAll();
}

function admin_parse_study_lines(string $value): array
{
    $lines = preg_split('/\R/', $value) ?: [];

    return array_values(array_filter(array_map(static fn(string $line): string => trim($line), $lines), static fn(string $line): bool => $line !== ''));
}

function create_study_editor_access_request(int $userId, string $message = ''): void
{
    if (!study_editor_requests_available()) {
        throw new RuntimeException('Editor requests are not installed yet.');
    }

    $statement = db()->prepare(
        "INSERT INTO study_editor_access_requests (user_id, request_message, status)
        VALUES (:user_id, :request_message, 'pending')
        ON DUPLICATE KEY UPDATE request_message = VALUES(request_message), status = 'pending', reviewed_by_user_id = NULL, reviewed_at = NULL"
    );
    $statement->execute([
        'user_id' => $userId,
        'request_message' => trim($message),
    ]);
}

function fetch_study_editor_access_request_for_user(int $userId): ?array
{
    if (!study_editor_requests_available()) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM study_editor_access_requests WHERE user_id = :user_id LIMIT 1');
    $statement->execute(['user_id' => $userId]);
    $request = $statement->fetch();

    return $request ?: null;
}

function fetch_pending_study_editor_access_requests(): array
{
    if (!study_editor_requests_available()) {
        return [];
    }

    $statement = db()->query(
        "SELECT r.*, u.name, u.email
        FROM study_editor_access_requests r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC, r.id ASC"
    );

    return $statement->fetchAll();
}

function review_study_editor_access_request(int $requestId, int $reviewerUserId, string $status): void
{
    if (!study_editor_requests_available()) {
        throw new RuntimeException('Editor requests are not installed yet.');
    }

    if (!in_array($status, ['approved', 'denied'], true)) {
        throw new RuntimeException('Choose a valid request status.');
    }

    $statement = db()->prepare('SELECT * FROM study_editor_access_requests WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $requestId]);
    $request = $statement->fetch();

    if ($request === false) {
        throw new RuntimeException('Editor request was not found.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($status === 'approved') {
            $pdo->prepare("UPDATE users SET role = 'editor' WHERE id = :id AND role = 'member'")->execute(['id' => (int) $request['user_id']]);
        }

        $pdo->prepare(
            'UPDATE study_editor_access_requests
            SET status = :status,
                reviewed_by_user_id = :reviewed_by_user_id,
                reviewed_at = NOW()
            WHERE id = :id'
        )->execute([
            'id' => $requestId,
            'status' => $status,
            'reviewed_by_user_id' => $reviewerUserId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}
