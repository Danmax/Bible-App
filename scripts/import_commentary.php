<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once __DIR__ . '/import_translation_helpers.php';
require_once __DIR__ . '/scripture_reference_import_helpers.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/import_commentary.php /path/to/commentary.json [--validate-only]\n");
    exit(1);
}

$sourcePath = (string) $argv[1];

if (!is_file($sourcePath) || !is_readable($sourcePath)) {
    fwrite(STDERR, "Commentary file is not readable: {$sourcePath}\n");
    exit(1);
}

$json = file_get_contents($sourcePath);
$payload = is_string($json) ? json_decode($json, true) : null;

if (!is_array($payload) || !is_array($payload['resource'] ?? null) || !is_array($payload['entries'] ?? null)) {
    fwrite(STDERR, "Commentary JSON must contain resource and entries objects.\n");
    exit(1);
}

$resource = $payload['resource'];
$slug = strtolower(trim((string) ($resource['slug'] ?? '')));
$title = trim((string) ($resource['title'] ?? ''));
$licenseName = trim((string) ($resource['license_name'] ?? ''));
$sourceUrl = trim((string) ($resource['source_url'] ?? ''));
$studyLevel = strtolower(trim((string) ($resource['study_level'] ?? 'devotional')));

if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    fwrite(STDERR, "Resource slug must use lowercase letters, numbers, and single hyphens.\n");
    exit(1);
}

if ($title === '' || $licenseName === '' || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "Resource title, license_name, and a valid source_url are required.\n");
    exit(1);
}

if (!in_array($studyLevel, ['devotional', 'pastoral', 'technical'], true)) {
    fwrite(STDERR, "study_level must be devotional, pastoral, or technical.\n");
    exit(1);
}

$licenseUrl = trim((string) ($resource['license_url'] ?? ''));

if ($licenseUrl !== '' && filter_var($licenseUrl, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "license_url must be a valid URL when provided.\n");
    exit(1);
}

$bookAliases = canonical_book_alias_map(canonical_book_definitions());
$validateOnly = in_array('--validate-only', $argv, true);

if ($validateOnly) {
    $validEntries = 0;
    $invalidEntries = 0;

    foreach ($payload['entries'] as $index => $entry) {
        if (!is_array($entry)) {
            $invalidEntries++;
            continue;
        }

        $reference = parse_osis_scripture_reference((string) ($entry['reference'] ?? ''), $bookAliases);
        $bodyText = normalize_commentary_body((string) ($entry['body'] ?? ''));
        $entrySourceUrl = trim((string) ($entry['source_url'] ?? ''));

        if ($entrySourceUrl !== '' && filter_var($entrySourceUrl, FILTER_VALIDATE_URL) === false) {
            fwrite(STDERR, 'Invalid entry source_url at entry ' . ($index + 1) . ".\n");
            $invalidEntries++;
            continue;
        }

        if ($reference === null || $bodyText === '') {
            $invalidEntries++;
            continue;
        }

        $validEntries++;
    }

    fwrite(STDOUT, "Validated {$title}: {$validEntries} valid entries, {$invalidEntries} invalid entries.\n");
    exit($invalidEntries > 0 ? 1 : 0);
}

$pdo = db();
$referenceIds = [];
$imported = 0;
$skipped = 0;

$resourceStatement = $pdo->prepare(
    'INSERT INTO commentary_resources (
        slug, title, author, description, tradition_label, study_level,
        license_name, license_url, source_url, priority, is_active
    ) VALUES (
        :slug, :title, :author, :description, :tradition_label, :study_level,
        :license_name, :license_url, :source_url, :priority, 1
    )
    ON DUPLICATE KEY UPDATE
        id = LAST_INSERT_ID(id),
        title = VALUES(title),
        author = VALUES(author),
        description = VALUES(description),
        tradition_label = VALUES(tradition_label),
        study_level = VALUES(study_level),
        license_name = VALUES(license_name),
        license_url = VALUES(license_url),
        source_url = VALUES(source_url),
        priority = VALUES(priority),
        is_active = 1,
        updated_at = CURRENT_TIMESTAMP'
);
$referenceStatement = $pdo->prepare(
    'INSERT INTO scripture_references (
        start_book_id, start_chapter, start_verse,
        end_book_id, end_chapter, end_verse
    ) VALUES (
        :start_book_id, :start_chapter, :start_verse,
        :end_book_id, :end_chapter, :end_verse
    )
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
);
$entryStatement = $pdo->prepare(
    'INSERT INTO commentary_entries (
        resource_id, scripture_reference_id, section_title, body_text,
        source_url, source_key, sort_order
    ) VALUES (
        :resource_id, :scripture_reference_id, :section_title, :body_text,
        :source_url, :source_key, :sort_order
    )
    ON DUPLICATE KEY UPDATE
        scripture_reference_id = VALUES(scripture_reference_id),
        section_title = VALUES(section_title),
        body_text = VALUES(body_text),
        source_url = VALUES(source_url),
        sort_order = VALUES(sort_order),
        updated_at = CURRENT_TIMESTAMP'
);

$pdo->beginTransaction();

try {
    $resourceStatement->execute([
        'slug' => $slug,
        'title' => $title,
        'author' => normalize_commentary_optional_text((string) ($resource['author'] ?? '')),
        'description' => normalize_commentary_optional_text((string) ($resource['description'] ?? '')),
        'tradition_label' => normalize_commentary_optional_text((string) ($resource['tradition_label'] ?? '')),
        'study_level' => $studyLevel,
        'license_name' => $licenseName,
        'license_url' => $licenseUrl !== '' ? $licenseUrl : null,
        'source_url' => $sourceUrl,
        'priority' => (int) ($resource['priority'] ?? 0),
    ]);
    $resourceId = (int) $pdo->lastInsertId();

    if ($resourceId < 1) {
        throw new RuntimeException('Could not create or resolve the commentary resource.');
    }

    foreach ($payload['entries'] as $index => $entry) {
        if (!is_array($entry)) {
            $skipped++;
            continue;
        }

        $referenceText = trim((string) ($entry['reference'] ?? ''));
        $reference = parse_osis_scripture_reference($referenceText, $bookAliases);
        $bodyText = normalize_commentary_body((string) ($entry['body'] ?? ''));

        if ($reference === null || $bodyText === '') {
            $skipped++;
            continue;
        }

        $referenceId = find_or_create_canonical_scripture_reference(
            $pdo,
            $referenceStatement,
            $referenceIds,
            $reference
        );
        $entrySourceUrl = trim((string) ($entry['source_url'] ?? ''));

        if ($entrySourceUrl !== '' && filter_var($entrySourceUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Invalid entry source_url at entry ' . ($index + 1) . '.');
        }

        $sourceKey = trim((string) ($entry['source_key'] ?? ''));

        if ($sourceKey === '') {
            $sourceKey = $referenceText . ':' . substr(sha1($bodyText), 0, 20);
        }

        $entryStatement->execute([
            'resource_id' => $resourceId,
            'scripture_reference_id' => $referenceId,
            'section_title' => normalize_commentary_optional_text((string) ($entry['title'] ?? '')),
            'body_text' => $bodyText,
            'source_url' => $entrySourceUrl !== '' ? $entrySourceUrl : null,
            'source_key' => substr($sourceKey, 0, 190),
            'sort_order' => (int) ($entry['sort_order'] ?? $index),
        ]);
        $imported++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Commentary import failed: {$exception->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "Imported {$imported} entries for {$title}.\n");
fwrite(STDOUT, "Skipped {$skipped} invalid or empty entries.\n");

function normalize_commentary_optional_text(string $value): ?string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

    return $value === '' ? null : $value;
}

function normalize_commentary_body(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", strip_tags($value));
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;

    return trim($value);
}
