<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system_repository.php';
require_once __DIR__ . '/bible_repository.php';

function sermon_note_reference_type_options(): array
{
    return [
        'character' => 'Characters',
        'place' => 'Places',
        'item' => 'Items',
        'scene' => 'Scenes',
        'history' => 'History',
        'promise' => 'Promises',
        'prophecy' => 'Prophecy',
        'book' => 'Books',
        'gospel' => 'Gospel',
        'theme' => 'Thematic',
    ];
}

function sermon_note_status_options(): array
{
    return [
        'draft' => 'Draft',
        'active' => 'Active',
        'archived' => 'Archived',
    ];
}

function sermon_note_layout_options(): array
{
    return [
        'split' => 'Split View',
        'editor' => 'Editor Focus',
        'board' => 'Storm Board',
    ];
}

function sermon_note_board_template(): array
{
    return [
        'insight' => [],
        'application' => [],
        'prayer' => [],
    ];
}

function fetch_sermon_note_folders(int $userId): array
{
    $statement = db()->prepare(
        'SELECT folders.*,
            (
                SELECT COUNT(*)
                FROM sermon_notes
                WHERE sermon_notes.folder_id = folders.id
            ) AS note_count
        FROM sermon_note_folders AS folders
        WHERE folders.user_id = :user_id
        ORDER BY folders.sort_order ASC, folders.name ASC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_sermon_note_folder(int $folderId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM sermon_note_folders WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $statement->execute([
        'id' => $folderId,
        'user_id' => $userId,
    ]);
    $folder = $statement->fetch();

    return $folder ?: null;
}

function create_sermon_note_folder(int $userId, string $name, ?int $parentFolderId = null): int
{
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        throw new RuntimeException('Enter a folder name.');
    }

    $sortOrder = count_records(
        'SELECT COUNT(*) FROM sermon_note_folders WHERE user_id = :user_id',
        ['user_id' => $userId]
    );
    $statement = db()->prepare(
        'INSERT INTO sermon_note_folders (user_id, parent_folder_id, name, sort_order)
        VALUES (:user_id, :parent_folder_id, :name, :sort_order)'
    );
    $statement->execute([
        'user_id' => $userId,
        'parent_folder_id' => $parentFolderId,
        'name' => $normalizedName,
        'sort_order' => $sortOrder,
    ]);

    return (int) db()->lastInsertId();
}

function update_sermon_note_folder(int $folderId, int $userId, string $name): void
{
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        throw new RuntimeException('Enter a folder name.');
    }

    $statement = db()->prepare(
        'UPDATE sermon_note_folders
        SET name = :name
        WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $folderId,
        'user_id' => $userId,
        'name' => $normalizedName,
    ]);
}

function delete_sermon_note_folder(int $folderId, int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $updateNotes = $pdo->prepare(
            'UPDATE sermon_notes
            SET folder_id = NULL
            WHERE folder_id = :folder_id AND user_id = :user_id'
        );
        $updateNotes->execute([
            'folder_id' => $folderId,
            'user_id' => $userId,
        ]);

        $deleteFolder = $pdo->prepare(
            'DELETE FROM sermon_note_folders WHERE id = :id AND user_id = :user_id'
        );
        $deleteFolder->execute([
            'id' => $folderId,
            'user_id' => $userId,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function fetch_sermon_notes(int $userId, ?int $folderId = null): array
{
    $sql = 'SELECT notes.*, folders.name AS folder_name
        FROM sermon_notes AS notes
        LEFT JOIN sermon_note_folders AS folders ON folders.id = notes.folder_id
        WHERE notes.user_id = :user_id';
    $params = ['user_id' => $userId];

    if ($folderId !== null) {
        $sql .= ' AND notes.folder_id = :folder_id';
        $params['folder_id'] = $folderId;
    }

    $sql .= ' ORDER BY notes.updated_at DESC';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $notes = $statement->fetchAll();

    foreach ($notes as &$note) {
        $note['content_excerpt'] = truncate_text((string) ($note['content_text'] ?? ''), 140);
    }

    return $notes;
}

function fetch_sermon_note(int $noteId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT notes.*, folders.name AS folder_name
        FROM sermon_notes AS notes
        LEFT JOIN sermon_note_folders AS folders ON folders.id = notes.folder_id
        WHERE notes.id = :id AND notes.user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $noteId,
        'user_id' => $userId,
    ]);
    $note = $statement->fetch();

    if (!$note) {
        return null;
    }

    $note['verse_refs'] = fetch_sermon_note_verse_refs($noteId, $userId);
    $note['reference_tags'] = fetch_sermon_note_reference_tags($noteId, $userId);
    $note['storm_board'] = decode_sermon_note_board((string) ($note['storm_board_json'] ?? ''));

    return $note;
}

function fetch_public_sermon_note_by_share_code(string $shareCode): ?array
{
    $statement = db()->prepare(
        'SELECT notes.*, users.name AS author_name, folders.name AS folder_name
        FROM sermon_notes AS notes
        INNER JOIN users ON users.id = notes.user_id
        LEFT JOIN sermon_note_folders AS folders ON folders.id = notes.folder_id
        WHERE notes.share_code = :share_code
        LIMIT 1'
    );
    $statement->execute(['share_code' => trim($shareCode)]);
    $note = $statement->fetch();

    if (!$note) {
        return null;
    }

    $noteId = (int) ($note['id'] ?? 0);
    $note['verse_refs'] = fetch_public_sermon_note_verse_refs($noteId);
    $note['reference_tags'] = fetch_public_sermon_note_reference_tags($noteId);
    $note['storm_board'] = decode_sermon_note_board((string) ($note['storm_board_json'] ?? ''));

    return $note;
}

function create_sermon_note_record(int $userId, array $data): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $payload = prepare_sermon_note_payload($userId, $data);
        $statement = $pdo->prepare(
            'INSERT INTO sermon_notes (
                user_id,
                folder_id,
                title,
                speaker_name,
                series_name,
                service_date,
                source_url,
                share_code,
                summary_text,
                speaker_notes_text,
                content_html,
                content_text,
                storm_board_json,
                status,
                layout_mode,
                is_starred
            ) VALUES (
                :user_id,
                :folder_id,
                :title,
                :speaker_name,
                :series_name,
                :service_date,
                :source_url,
                :share_code,
                :summary_text,
                :speaker_notes_text,
                :content_html,
                :content_text,
                :storm_board_json,
                :status,
                :layout_mode,
                :is_starred
            )'
        );
        $statement->execute([
            'user_id' => $userId,
            'folder_id' => $payload['folder_id'],
            'title' => $payload['title'],
            'speaker_name' => $payload['speaker_name'],
            'series_name' => $payload['series_name'],
            'service_date' => $payload['service_date'],
            'source_url' => $payload['source_url'],
            'share_code' => generate_sermon_note_share_code(),
            'summary_text' => $payload['summary_text'],
            'speaker_notes_text' => $payload['speaker_notes_text'],
            'content_html' => $payload['content_html'],
            'content_text' => $payload['content_text'],
            'storm_board_json' => $payload['storm_board_json'],
            'status' => $payload['status'],
            'layout_mode' => $payload['layout_mode'],
            'is_starred' => $payload['is_starred'],
        ]);

        $noteId = (int) $pdo->lastInsertId();
        sync_sermon_note_verse_refs($noteId, $userId, $payload['verse_refs']);
        sync_sermon_note_reference_tags($noteId, $userId, $payload['reference_tags']);
        $pdo->commit();

        return $noteId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function update_sermon_note_record(int $noteId, int $userId, array $data): void
{
    $existing = fetch_sermon_note($noteId, $userId);

    if ($existing === null) {
        throw new RuntimeException('That sermon note could not be found.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $payload = prepare_sermon_note_payload($userId, $data);
        $statement = $pdo->prepare(
            'UPDATE sermon_notes
            SET folder_id = :folder_id,
                title = :title,
                speaker_name = :speaker_name,
                series_name = :series_name,
                service_date = :service_date,
                source_url = :source_url,
                summary_text = :summary_text,
                speaker_notes_text = :speaker_notes_text,
                content_html = :content_html,
                content_text = :content_text,
                storm_board_json = :storm_board_json,
                status = :status,
                layout_mode = :layout_mode,
                is_starred = :is_starred
            WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => $noteId,
            'user_id' => $userId,
            'folder_id' => $payload['folder_id'],
            'title' => $payload['title'],
            'speaker_name' => $payload['speaker_name'],
            'series_name' => $payload['series_name'],
            'service_date' => $payload['service_date'],
            'source_url' => $payload['source_url'],
            'summary_text' => $payload['summary_text'],
            'speaker_notes_text' => $payload['speaker_notes_text'],
            'content_html' => $payload['content_html'],
            'content_text' => $payload['content_text'],
            'storm_board_json' => $payload['storm_board_json'],
            'status' => $payload['status'],
            'layout_mode' => $payload['layout_mode'],
            'is_starred' => $payload['is_starred'],
        ]);

        sync_sermon_note_verse_refs($noteId, $userId, $payload['verse_refs']);
        sync_sermon_note_reference_tags($noteId, $userId, $payload['reference_tags']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function delete_sermon_note_record(int $noteId, int $userId): void
{
    $statement = db()->prepare(
        'DELETE FROM sermon_notes WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $noteId,
        'user_id' => $userId,
    ]);
}

function fetch_sermon_note_verse_refs(int $noteId, int $userId): array
{
    $statement = db()->prepare(
        'SELECT refs.*, verses.book_id, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text, books.name AS book_name
        FROM sermon_note_verse_refs AS refs
        INNER JOIN sermon_notes ON sermon_notes.id = refs.sermon_note_id
        INNER JOIN verses ON verses.id = refs.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE refs.sermon_note_id = :note_id
            AND sermon_notes.user_id = :user_id
        ORDER BY refs.sort_order ASC, refs.id ASC'
    );
    $statement->execute([
        'note_id' => $noteId,
        'user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function fetch_public_sermon_note_verse_refs(int $noteId): array
{
    $statement = db()->prepare(
        'SELECT refs.*, verses.book_id, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text, books.name AS book_name
        FROM sermon_note_verse_refs AS refs
        INNER JOIN verses ON verses.id = refs.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE refs.sermon_note_id = :note_id
        ORDER BY refs.sort_order ASC, refs.id ASC'
    );
    $statement->execute(['note_id' => $noteId]);

    return $statement->fetchAll();
}

function fetch_sermon_note_reference_tags(int $noteId, int $userId): array
{
    $statement = db()->prepare(
        'SELECT tags.*
        FROM sermon_note_reference_tags AS tags
        INNER JOIN sermon_notes ON sermon_notes.id = tags.sermon_note_id
        WHERE tags.sermon_note_id = :note_id
            AND sermon_notes.user_id = :user_id
        ORDER BY tags.tag_type ASC, tags.sort_order ASC, tags.id ASC'
    );
    $statement->execute([
        'note_id' => $noteId,
        'user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function fetch_public_sermon_note_reference_tags(int $noteId): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM sermon_note_reference_tags
        WHERE sermon_note_id = :note_id
        ORDER BY tag_type ASC, sort_order ASC, id ASC'
    );
    $statement->execute(['note_id' => $noteId]);

    return $statement->fetchAll();
}

function group_sermon_note_reference_tags(array $tags): array
{
    $grouped = [];

    foreach (sermon_note_reference_type_options() as $type => $label) {
        $grouped[$type] = [];
    }

    foreach ($tags as $tag) {
        $type = (string) ($tag['tag_type'] ?? '');

        if (!isset($grouped[$type])) {
            $grouped[$type] = [];
        }

        $grouped[$type][] = $tag;
    }

    return $grouped;
}

function decode_sermon_note_board(?string $json): array
{
    $defaultBoard = sermon_note_board_template();
    $decoded = json_decode((string) $json, true);

    if (!is_array($decoded)) {
        return $defaultBoard;
    }

    $normalized = $defaultBoard;

    foreach (array_keys($defaultBoard) as $column) {
        $items = $decoded[$column] ?? [];

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            $text = trim((string) $item);

            if ($text !== '') {
                $normalized[$column][] = $text;
            }
        }
    }

    return $normalized;
}

function prepare_sermon_note_payload(int $userId, array $data): array
{
    $title = trim((string) ($data['title'] ?? ''));
    $contentHtml = sanitize_sermon_note_html((string) ($data['content_html'] ?? ''));
    $contentText = trim((string) ($data['content_text'] ?? ''));

    if ($title === '') {
        throw new RuntimeException('Enter a note title.');
    }

    if ($contentText === '') {
        $contentText = sermon_note_html_to_text($contentHtml);
    }

    if ($contentText === '') {
        throw new RuntimeException('Enter note content.');
    }

    $folderId = isset($data['folder_id']) && (int) $data['folder_id'] > 0
        ? (int) $data['folder_id']
        : null;

    if ($folderId !== null && fetch_sermon_note_folder($folderId, $userId) === null) {
        $folderId = null;
    }

    $serviceDate = normalize_sermon_note_service_date((string) ($data['service_date'] ?? ''));
    $sourceUrl = normalize_sermon_note_url((string) ($data['source_url'] ?? ''));
    $status = trim((string) ($data['status'] ?? 'draft'));
    $layoutMode = trim((string) ($data['layout_mode'] ?? 'split'));

    if (!array_key_exists($status, sermon_note_status_options())) {
        $status = 'draft';
    }

    if (!array_key_exists($layoutMode, sermon_note_layout_options())) {
        $layoutMode = 'split';
    }

    $board = decode_sermon_note_board((string) ($data['storm_board_json'] ?? ''));
    $boardJson = json_encode($board, JSON_UNESCAPED_SLASHES);

    if (!is_string($boardJson)) {
        $boardJson = json_encode(sermon_note_board_template(), JSON_UNESCAPED_SLASHES);
    }

    return [
        'folder_id' => $folderId,
        'title' => $title,
        'speaker_name' => normalize_optional_text((string) ($data['speaker_name'] ?? '')),
        'series_name' => normalize_optional_text((string) ($data['series_name'] ?? '')),
        'service_date' => $serviceDate,
        'source_url' => $sourceUrl,
        'summary_text' => normalize_optional_text((string) ($data['summary_text'] ?? '')),
        'speaker_notes_text' => normalize_optional_text((string) ($data['speaker_notes_text'] ?? '')),
        'content_html' => $contentHtml,
        'content_text' => $contentText,
        'storm_board_json' => $boardJson ?: null,
        'status' => $status,
        'layout_mode' => $layoutMode,
        'is_starred' => !empty($data['is_starred']) ? 1 : 0,
        'reference_tags' => normalize_sermon_note_reference_tags($data['reference_tags'] ?? []),
        'verse_refs' => normalize_sermon_note_verse_refs($data['verse_refs'] ?? []),
    ];
}

function normalize_sermon_note_service_date(string $value): ?string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    $timestamp = strtotime($trimmed);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

function normalize_sermon_note_url(string $value): ?string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    if (!preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $trimmed)) {
        $trimmed = 'https://' . ltrim($trimmed, '/');
    }

    return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : null;
}

function sanitize_sermon_note_html(string $html): string
{
    $trimmed = trim($html);

    if ($trimmed === '') {
        return '<p></p>';
    }

    if (!class_exists(DOMDocument::class)) {
        $fallback = strip_tags($trimmed, '<p><br><div><strong><b><em><i><u><span><ul><ol><li><blockquote><h2><h3><a>');

        return $fallback !== '' ? $fallback : '<p></p>';
    }

    $internalErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrappedHtml = '<!DOCTYPE html><html><body>' . $trimmed . '</body></html>';
    $encodedHtml = mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8');
    $dom->loadHTML($encodedHtml, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    $body = $dom->getElementsByTagName('body')->item(0);

    if ($body instanceof DOMElement) {
        sanitize_sermon_note_dom_node($body);
    }

    $output = '';

    if ($body instanceof DOMElement) {
        foreach ($body->childNodes as $childNode) {
            $output .= $dom->saveHTML($childNode) ?: '';
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    $output = trim($output);

    return $output !== '' ? $output : '<p></p>';
}

function sanitize_sermon_note_dom_node(DOMNode $node): void
{
    if (!($node instanceof DOMElement)) {
        return;
    }

    $allowedTags = [
        'body' => [],
        'p' => ['class'],
        'br' => [],
        'div' => ['class'],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'span' => ['class', 'data-verse-id', 'data-verse-reference', 'data-verse-text', 'contenteditable'],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => ['class'],
        'h2' => [],
        'h3' => [],
        'a' => ['href', 'target', 'rel', 'class'],
    ];
    $allowedClasses = [
        'note-highlight-green',
        'note-highlight-theme',
        'note-verse-chip',
        'note-inline-link',
    ];
    $children = [];

    foreach ($node->childNodes as $childNode) {
        $children[] = $childNode;
    }

    foreach ($children as $childNode) {
        if (!($childNode instanceof DOMElement)) {
            continue;
        }

        $tagName = strtolower($childNode->tagName);

        if (!isset($allowedTags[$tagName])) {
            unwrap_sermon_note_dom_node($childNode);
            continue;
        }

        if ($childNode->hasAttributes()) {
            $attributes = [];

            foreach ($childNode->attributes as $attribute) {
                $attributes[] = $attribute;
            }

            foreach ($attributes as $attribute) {
                $attributeName = strtolower($attribute->name);

                if (!in_array($attributeName, $allowedTags[$tagName], true)) {
                    $childNode->removeAttribute($attribute->name);
                    continue;
                }

                if ($attributeName === 'class') {
                    $tokens = preg_split('/\s+/', trim($attribute->value)) ?: [];
                    $tokens = array_values(array_intersect($tokens, $allowedClasses));

                    if ($tokens === []) {
                        $childNode->removeAttribute('class');
                    } else {
                        $childNode->setAttribute('class', implode(' ', $tokens));
                    }

                    continue;
                }

                if ($tagName === 'a' && $attributeName === 'href') {
                    $href = trim($attribute->value);

                    if (
                        $href === ''
                        || preg_match('/^\s*javascript:/i', $href)
                        || preg_match('/^\s*data:/i', $href)
                    ) {
                        $childNode->removeAttribute('href');
                    }
                }

                if ($tagName === 'span' && $attributeName === 'contenteditable') {
                    $childNode->removeAttribute('contenteditable');
                }
            }
        }

        if ($tagName === 'a') {
            if (!$childNode->hasAttribute('href')) {
                unwrap_sermon_note_dom_node($childNode);
                continue;
            }

            $childNode->setAttribute('target', '_blank');
            $childNode->setAttribute('rel', 'noopener noreferrer');
        }

        sanitize_sermon_note_dom_node($childNode);
    }
}

function unwrap_sermon_note_dom_node(DOMElement $node): void
{
    $parent = $node->parentNode;

    if ($parent === null) {
        return;
    }

    while ($node->firstChild !== null) {
        $parent->insertBefore($node->firstChild, $node);
    }

    $parent->removeChild($node);
}

function sermon_note_html_to_text(string $html): string
{
    $normalizedHtml = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
    $normalizedHtml = preg_replace('/<\/(p|div|li|blockquote|h2|h3)>/i', "$0\n", $normalizedHtml) ?? $normalizedHtml;
    $text = trim(strip_tags($normalizedHtml));
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

    return trim($text);
}

function normalize_sermon_note_reference_tags(mixed $tags): array
{
    $allowedTypes = array_keys(sermon_note_reference_type_options());

    if (!is_array($tags)) {
        return [];
    }

    $normalized = [];

    foreach ($tags as $tag) {
        if (!is_array($tag)) {
            continue;
        }

        $type = trim((string) ($tag['tag_type'] ?? $tag['type'] ?? ''));
        $label = trim((string) ($tag['label'] ?? ''));

        if ($label === '' || !in_array($type, $allowedTypes, true)) {
            continue;
        }

        $normalized[] = [
            'tag_type' => $type,
            'label' => mb_substr($label, 0, 160),
            'detail_text' => normalize_optional_text(mb_substr((string) ($tag['detail_text'] ?? $tag['detail'] ?? ''), 0, 255)),
        ];
    }

    return $normalized;
}

function normalize_sermon_note_verse_refs(mixed $verseRefs): array
{
    if (!is_array($verseRefs)) {
        return [];
    }

    $normalized = [];

    foreach ($verseRefs as $verseRef) {
        if (!is_array($verseRef)) {
            continue;
        }

        $verseId = (int) ($verseRef['verse_id'] ?? 0);
        $kind = trim((string) ($verseRef['reference_kind'] ?? $verseRef['kind'] ?? 'citation'));

        if ($verseId <= 0) {
            continue;
        }

        if (!in_array($kind, ['citation', 'paraphrase', 'preview', 'mentioned'], true)) {
            $kind = 'citation';
        }

        $verse = fetch_verse_by_id($verseId);

        if ($verse === null) {
            continue;
        }

        $normalized[] = [
            'verse_id' => $verseId,
            'reference_kind' => $kind,
            'reference_label' => mb_substr(
                trim((string) ($verseRef['reference_label'] ?? $verseRef['reference'] ?? format_verse_reference($verse))),
                0,
                160
            ),
            'quote_text' => normalize_optional_text((string) ($verseRef['quote_text'] ?? $verseRef['quote'] ?? '')),
        ];
    }

    return $normalized;
}

function sync_sermon_note_verse_refs(int $noteId, int $userId, array $verseRefs): void
{
    $note = fetch_sermon_note($noteId, $userId);

    if ($note === null) {
        throw new RuntimeException('That sermon note could not be found.');
    }

    $deleteStatement = db()->prepare(
        'DELETE FROM sermon_note_verse_refs WHERE sermon_note_id = :note_id'
    );
    $deleteStatement->execute(['note_id' => $noteId]);

    if ($verseRefs === []) {
        return;
    }

    $insertStatement = db()->prepare(
        'INSERT INTO sermon_note_verse_refs (
            sermon_note_id,
            verse_id,
            reference_kind,
            reference_label,
            quote_text,
            sort_order
        ) VALUES (
            :sermon_note_id,
            :verse_id,
            :reference_kind,
            :reference_label,
            :quote_text,
            :sort_order
        )'
    );

    foreach ($verseRefs as $index => $verseRef) {
        $insertStatement->execute([
            'sermon_note_id' => $noteId,
            'verse_id' => (int) $verseRef['verse_id'],
            'reference_kind' => (string) $verseRef['reference_kind'],
            'reference_label' => normalize_optional_text((string) $verseRef['reference_label']),
            'quote_text' => $verseRef['quote_text'],
            'sort_order' => $index,
        ]);
    }
}

function sync_sermon_note_reference_tags(int $noteId, int $userId, array $tags): void
{
    $note = fetch_sermon_note($noteId, $userId);

    if ($note === null) {
        throw new RuntimeException('That sermon note could not be found.');
    }

    $deleteStatement = db()->prepare(
        'DELETE FROM sermon_note_reference_tags WHERE sermon_note_id = :note_id'
    );
    $deleteStatement->execute(['note_id' => $noteId]);

    if ($tags === []) {
        return;
    }

    $insertStatement = db()->prepare(
        'INSERT INTO sermon_note_reference_tags (
            sermon_note_id,
            tag_type,
            label,
            detail_text,
            sort_order
        ) VALUES (
            :sermon_note_id,
            :tag_type,
            :label,
            :detail_text,
            :sort_order
        )'
    );

    foreach ($tags as $index => $tag) {
        $insertStatement->execute([
            'sermon_note_id' => $noteId,
            'tag_type' => (string) $tag['tag_type'],
            'label' => (string) $tag['label'],
            'detail_text' => $tag['detail_text'],
            'sort_order' => $index,
        ]);
    }
}

function generate_sermon_note_share_code(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxIndex = strlen($alphabet) - 1;

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $code = '';

        for ($index = 0; $index < $length; $index++) {
            $code .= $alphabet[random_int(0, $maxIndex)];
        }

        $existing = count_records(
            'SELECT COUNT(*) FROM sermon_notes WHERE share_code = :share_code',
            ['share_code' => $code]
        );

        if ($existing === 0) {
            return $code;
        }
    }

    throw new RuntimeException('A short link could not be generated right now.');
}
