<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_can_manage_studies()) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    $activePage = '';
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Restricted</p>
                <h1>Editor access required</h1>
                <p>You do not have permission to manage Bible studies.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$pageTitle = 'Admin Bible Studies';
$activePage = 'admin';
$pageError = null;
$activeUser = current_user();
$isAdmin = current_user_has_role(['admin']);
$templates = study_template_options();
$studies = [];
$editingStudy = null;
$editingSteps = [];
$editingBadge = null;
$statusOptions = [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived',
];
$visibilityOptions = [
    'public' => 'Public',
    'private' => 'Private',
];
$unlockOptions = [
    'none' => 'Unlocked immediately',
    'after_reflection' => 'After reflection',
    'after_challenge' => 'After challenge',
    'after_step' => 'After completing day',
];
$itemTypeOptions = [
    'devotional' => 'Devotional',
    'reflection' => 'Reflection',
    'image' => 'Image reflection',
    'video' => 'Video',
    'bible_verse' => 'Bible verses',
];
$itemUnlockOptions = [
    'none' => 'Unlocked',
    'after_previous' => 'After previous item',
];

if (curated_studies_available() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));
    $studyId = (int) ($_POST['study_id'] ?? 0);

    try {
        if ($action === 'create') {
            $templateKey = trim((string) ($_POST['template_key'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));

            if ($title === '') {
                throw new RuntimeException('Study title is required.');
            }

            $createdStudyId = create_study_from_template((int) current_user()['id'], $templateKey, $title);
            record_audit_event((int) current_user()['id'], 'study.created', null, [
                'study_id' => $createdStudyId,
                'template_key' => $templateKey,
            ]);
            set_flash('Bible study draft created from template.', 'success');
            redirect('admin/studies.php?edit=' . $createdStudyId);
        }

        if ($studyId <= 0) {
            throw new RuntimeException('Select a valid Bible study.');
        }

        $targetStudy = fetch_study_by_id($studyId);

        if ($targetStudy === null || (!$isAdmin && (int) ($targetStudy['created_by_user_id'] ?? 0) !== (int) ($activeUser['id'] ?? 0))) {
            throw new RuntimeException('You can only edit studies you created.');
        }

        if ($action === 'delete') {
            delete_study_record($studyId);
            record_audit_event((int) current_user()['id'], 'study.deleted', null, ['study_id' => $studyId]);
            set_flash('Bible study deleted.', 'success');
            redirect('admin/studies.php');
        }

        if ($action === 'update') {
            $payload = [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'slug' => trim((string) ($_POST['slug'] ?? '')),
                'summary' => trim((string) ($_POST['summary'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'duration_days' => max(1, (int) ($_POST['duration_days'] ?? 1)),
                'cover_image_url' => trim((string) ($_POST['cover_image_url'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? 'draft')),
                'visibility' => trim((string) ($_POST['visibility'] ?? 'public')),
                'is_featured' => trim((string) ($_POST['is_featured'] ?? '0')) === '1',
                'badge_name' => trim((string) ($_POST['badge_name'] ?? 'Study Finisher')),
                'badge_description' => trim((string) ($_POST['badge_description'] ?? 'Awarded after completing this study.')),
            ];

            if ($payload['title'] === '' || $payload['summary'] === '') {
                throw new RuntimeException('Title and summary are required.');
            }

            if (!isset($statusOptions[$payload['status']])) {
                throw new RuntimeException('Choose a valid status.');
            }

            if (!isset($visibilityOptions[$payload['visibility']])) {
                throw new RuntimeException('Choose a valid visibility.');
            }

            update_study_record($studyId, $payload);

            $dayNumbers = $_POST['step_day_number'] ?? [];
            $titles = $_POST['step_title'] ?? [];
            $sectionTitles = $_POST['step_section_title'] ?? [];
            $contents = $_POST['step_content'] ?? [];
            $verses = $_POST['step_verses'] ?? [];
            $questions = $_POST['step_questions'] ?? [];
            $challenges = $_POST['step_challenges'] ?? [];
            $videoTitles = $_POST['step_video_title'] ?? [];
            $videoIds = $_POST['step_youtube_video_id'] ?? [];
            $unlockRules = $_POST['step_video_unlock_rule'] ?? [];
            $itemTypes = $_POST['step_item_type'] ?? [];
            $itemTitles = $_POST['step_item_title'] ?? [];
            $itemBodies = $_POST['step_item_body'] ?? [];
            $itemResourceUrls = $_POST['step_item_resource_url'] ?? [];
            $itemBibleReferences = $_POST['step_item_bible_reference'] ?? [];
            $itemUnlockRules = $_POST['step_item_unlock_rule'] ?? [];
            $itemRequired = $_POST['step_item_required'] ?? [];
            $steps = [];
            $dayNumbers = (array) $dayNumbers;
            $titles = (array) $titles;
            $sectionTitles = (array) $sectionTitles;
            $contents = (array) $contents;
            $verses = (array) $verses;
            $questions = (array) $questions;
            $challenges = (array) $challenges;
            $videoTitles = (array) $videoTitles;
            $videoIds = (array) $videoIds;
            $unlockRules = (array) $unlockRules;
            $itemTypes = (array) $itemTypes;
            $itemTitles = (array) $itemTitles;
            $itemBodies = (array) $itemBodies;
            $itemResourceUrls = (array) $itemResourceUrls;
            $itemBibleReferences = (array) $itemBibleReferences;
            $itemUnlockRules = (array) $itemUnlockRules;
            $itemRequired = (array) $itemRequired;

            foreach ($titles as $index => $title) {
                $stepTitle = trim((string) $title);

                if ($stepTitle === '') {
                    continue;
                }

                $items = [];
                $stepItemTitles = (array) ($itemTitles[$index] ?? []);
                $stepItemTypes = (array) ($itemTypes[$index] ?? []);
                $stepItemBodies = (array) ($itemBodies[$index] ?? []);
                $stepItemResourceUrls = (array) ($itemResourceUrls[$index] ?? []);
                $stepItemBibleReferences = (array) ($itemBibleReferences[$index] ?? []);
                $stepItemUnlockRules = (array) ($itemUnlockRules[$index] ?? []);
                $stepItemRequired = (array) ($itemRequired[$index] ?? []);

                foreach ($stepItemTitles as $itemIndex => $itemTitle) {
                    $items[] = [
                        'item_type' => normalize_study_item_type((string) ($stepItemTypes[$itemIndex] ?? 'devotional')),
                        'title' => trim((string) $itemTitle),
                        'body' => trim((string) ($stepItemBodies[$itemIndex] ?? '')),
                        'resource_url' => trim((string) ($stepItemResourceUrls[$itemIndex] ?? '')),
                        'bible_reference' => trim((string) ($stepItemBibleReferences[$itemIndex] ?? '')),
                        'unlock_rule' => normalize_study_item_unlock_rule((string) ($stepItemUnlockRules[$itemIndex] ?? 'none')),
                        'is_required' => isset($stepItemRequired[$itemIndex]),
                    ];
                }

                $steps[] = [
                    'day_number' => max(1, (int) ($dayNumbers[$index] ?? ($index + 1))),
                    'title' => $stepTitle,
                    'section_title' => trim((string) ($sectionTitles[$index] ?? 'Daily Study')),
                    'content' => trim((string) ($contents[$index] ?? '')),
                    'verses' => admin_parse_study_lines((string) ($verses[$index] ?? '')),
                    'questions' => admin_parse_study_lines((string) ($questions[$index] ?? '')),
                    'challenges' => admin_parse_study_lines((string) ($challenges[$index] ?? '')),
                    'video_title' => trim((string) ($videoTitles[$index] ?? '')),
                    'youtube_video_id' => trim((string) ($videoIds[$index] ?? '')),
                    'video_unlock_rule' => normalize_video_unlock_rule((string) ($unlockRules[$index] ?? 'after_step')),
                    'items' => $items,
                ];
            }

            if ($steps === []) {
                throw new RuntimeException('Keep at least one study day.');
            }

            replace_study_steps($studyId, $steps);
            record_audit_event((int) current_user()['id'], 'study.updated', null, [
                'study_id' => $studyId,
                'status' => $payload['status'],
            ]);
            set_flash('Bible study updated.', 'success');
            redirect('admin/studies.php?edit=' . $studyId);
        }
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Bible study changes could not be saved.';
    }
}

if (curated_studies_available()) {
    try {
        $studies = fetch_manageable_studies((int) ($activeUser['id'] ?? 0), $isAdmin);
        $editId = (int) ($_GET['edit'] ?? 0);

        if ($editId > 0) {
            $editingStudy = fetch_study_by_id($editId);

            if ($editingStudy === null) {
                set_flash('That Bible study could not be found.', 'warning');
                redirect('admin/studies.php');
            }

            if (!$isAdmin && (int) ($editingStudy['created_by_user_id'] ?? 0) !== (int) ($activeUser['id'] ?? 0)) {
                set_flash('You can only edit studies you created.', 'warning');
                redirect('admin/studies.php');
            }

            $editingSteps = fetch_study_steps($editId);
            $editingBadge = fetch_study_badge($editId);
        }
    } catch (Throwable $exception) {
        $pageError = 'Bible studies could not be loaded.';
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Admin</p>
            <h1>Curated Bible studies</h1>
            <p>Create public devotionals and plans with Scripture, images, videos, reflection items, sequential unlocks, discussion, invites, and badges.</p>
        </div>

        <?php if (!curated_studies_available()): ?>
            <div class="flash flash-warning">Curated Bible studies are not installed yet. Run <strong>sql/add_curated_bible_studies.sql</strong> to enable this feature.</div>
        <?php endif; ?>

        <?php if ($pageError !== null): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if (curated_studies_available()): ?>
            <div class="two-column">
                <section class="panel">
                    <h2>Create from template</h2>
                    <form class="form-stack" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="create">
                        <label>
                            Study title
                            <input type="text" name="title" maxlength="180" required placeholder="Faith That Walks">
                        </label>
                        <label>
                            Template
                            <select name="template_key" required>
                                <?php foreach ($templates as $key => $template): ?>
                                    <option value="<?= e($key); ?>"><?= e((string) $template['label']); ?> - <?= e((string) $template['duration_days']); ?> days</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button button-primary" type="submit">Create Draft</button>
                    </form>
                </section>

                <section class="panel">
                    <h2>Published library</h2>
                    <?php if ($studies === []): ?>
                        <p class="empty-state">No studies yet.</p>
                    <?php else: ?>
                        <div class="stack-list">
                            <?php foreach ($studies as $study): ?>
                                <article class="list-card list-card-block">
                                    <div class="planner-item-header">
                                        <div>
                                            <strong><?= e((string) $study['title']); ?></strong>
                                            <p class="muted-copy"><?= e((string) $study['status']); ?> / <?= e((string) $study['step_count']); ?> days</p>
                                        </div>
                                        <a class="button button-secondary" href="<?= e(app_url('admin/studies.php?edit=' . (int) $study['id'])); ?>">Edit</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($editingStudy !== null): ?>
                <section class="panel top-gap">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Edit Study</p>
                            <h2><?= e((string) $editingStudy['title']); ?></h2>
                        </div>
                        <a class="button button-secondary" href="<?= e(app_url('study.php?slug=' . urlencode((string) $editingStudy['slug']))); ?>">View Study</a>
                    </div>

                    <form class="form-stack" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="study_id" value="<?= e((string) $editingStudy['id']); ?>">

                        <div class="card-grid card-grid-2">
                            <label>
                                Title
                                <input type="text" name="title" maxlength="180" required value="<?= e((string) $editingStudy['title']); ?>">
                            </label>
                            <label>
                                URL slug
                                <input type="text" name="slug" maxlength="190" value="<?= e((string) $editingStudy['slug']); ?>">
                            </label>
                            <label>
                                Duration days
                                <input type="number" name="duration_days" min="1" max="365" value="<?= e((string) $editingStudy['duration_days']); ?>">
                            </label>
                            <label>
                                Cover image URL
                                <input type="url" name="cover_image_url" maxlength="500" value="<?= e((string) ($editingStudy['cover_image_url'] ?? '')); ?>">
                            </label>
                            <label>
                                Status
                                <select name="status">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= e($value); ?>" <?= (string) $editingStudy['status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Visibility
                                <select name="visibility">
                                    <?php foreach ($visibilityOptions as $value => $label): ?>
                                        <option value="<?= e($value); ?>" <?= (string) $editingStudy['visibility'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <label>
                            Summary
                            <textarea name="summary" rows="3" required><?= e((string) ($editingStudy['summary'] ?? '')); ?></textarea>
                        </label>
                        <label>
                            Description
                            <textarea name="description" rows="5"><?= e((string) ($editingStudy['description'] ?? '')); ?></textarea>
                        </label>
                        <label class="sermon-checkbox-field">
                            <input type="checkbox" name="is_featured" value="1" <?= (int) ($editingStudy['is_featured'] ?? 0) === 1 ? 'checked' : ''; ?>>
                            Feature this study
                        </label>

                        <div class="card-grid card-grid-2">
                            <label>
                                Completion badge name
                                <input type="text" name="badge_name" maxlength="160" value="<?= e((string) ($editingBadge['badge_name'] ?? 'Study Finisher')); ?>">
                            </label>
                            <label>
                                Completion badge description
                                <input type="text" name="badge_description" value="<?= e((string) ($editingBadge['badge_description'] ?? 'Awarded after completing this study.')); ?>">
                            </label>
                        </div>

                        <div class="top-gap">
                            <h2>Study days</h2>
                            <p class="muted-copy">Use day cards like a small board. Add items, move them up or down, and choose whether each item unlocks after the previous one.</p>
                        </div>

                        <?php foreach ($editingSteps as $index => $step): ?>
                            <article class="panel study-builder-day" data-study-day>
                                <div class="panel-heading">
                                    <h3>Day <?= e((string) $step['day_number']); ?></h3>
                                    <div class="study-builder-controls">
                                        <button class="button button-icon" type="button" data-study-day-move="up" aria-label="Move day up"><span aria-hidden="true">^</span></button>
                                        <button class="button button-icon" type="button" data-study-day-move="down" aria-label="Move day down"><span aria-hidden="true">v</span></button>
                                        <button class="button button-icon" type="button" data-study-day-remove aria-label="Remove day"><span aria-hidden="true">x</span></button>
                                    </div>
                                </div>
                                <div class="card-grid card-grid-2">
                                    <label>
                                        Day number
                                        <input type="number" name="step_day_number[]" min="1" value="<?= e((string) $step['day_number']); ?>">
                                    </label>
                                    <label>
                                        Day title
                                        <input type="text" name="step_title[]" required value="<?= e((string) $step['title']); ?>">
                                    </label>
                                    <label>
                                        Section title
                                        <input type="text" name="step_section_title[]" value="<?= e((string) ($step['section_title'] ?? '')); ?>">
                                    </label>
                                    <label>
                                        Video title
                                        <input type="text" name="step_video_title[]" value="<?= e((string) ($step['video_title'] ?? '')); ?>">
                                    </label>
                                    <label>
                                        YouTube video ID or URL
                                        <input type="text" name="step_youtube_video_id[]" value="<?= e((string) ($step['youtube_video_id'] ?? '')); ?>">
                                    </label>
                                    <label>
                                        Video unlock
                                        <select name="step_video_unlock_rule[]">
                                            <?php foreach ($unlockOptions as $value => $label): ?>
                                                <option value="<?= e($value); ?>" <?= (string) ($step['video_unlock_rule'] ?? 'after_step') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <label>
                                    Section content
                                    <textarea name="step_content[]" rows="5"><?= e((string) ($step['content'] ?? '')); ?></textarea>
                                </label>
                                <div class="card-grid card-grid-3">
                                    <label>
                                        Verses
                                        <textarea name="step_verses[]" rows="5"><?= e(implode("\n", array_map(static fn(array $v): string => (string) $v['reference_text'], $step['verses'] ?? []))); ?></textarea>
                                    </label>
                                    <label>
                                        Reflection questions
                                        <textarea name="step_questions[]" rows="5"><?= e(implode("\n", array_map(static fn(array $q): string => (string) $q['question_text'], $step['questions'] ?? []))); ?></textarea>
                                    </label>
                                    <label>
                                        Daily challenges
                                        <textarea name="step_challenges[]" rows="5"><?= e(implode("\n", array_map(static fn(array $c): string => (string) $c['challenge_text'], $step['challenges'] ?? []))); ?></textarea>
                                    </label>
                                </div>
                                <div class="study-kanban" data-study-items data-step-index="<?= e((string) $index); ?>">
                                    <div class="panel-heading">
                                        <h3>Day items</h3>
                                        <button class="button button-secondary" type="button" data-study-item-add>Add Item</button>
                                    </div>
                                    <?php $items = (array) ($step['items'] ?? []); ?>
                                    <?php if ($items === []): ?>
                                        <?php $items = [[
                                            'item_type' => 'devotional',
                                            'title' => (string) ($step['section_title'] ?? 'Devotional'),
                                            'body' => (string) ($step['content'] ?? ''),
                                            'resource_url' => '',
                                            'bible_reference' => implode(', ', array_map(static fn(array $v): string => (string) $v['reference_text'], $step['verses'] ?? [])),
                                            'unlock_rule' => 'none',
                                            'is_required' => 1,
                                        ]]; ?>
                                    <?php endif; ?>
                                    <?php foreach ($items as $itemIndex => $item): ?>
                                        <article class="study-kanban-item" data-study-item>
                                            <div class="study-builder-controls">
                                                <span class="pill">Item <?= e((string) ($itemIndex + 1)); ?></span>
                                                <button class="button button-icon" type="button" data-study-item-move="up" aria-label="Move item up"><span aria-hidden="true">^</span></button>
                                                <button class="button button-icon" type="button" data-study-item-move="down" aria-label="Move item down"><span aria-hidden="true">v</span></button>
                                                <button class="button button-icon" type="button" data-study-item-remove aria-label="Remove item"><span aria-hidden="true">x</span></button>
                                            </div>
                                            <div class="card-grid card-grid-2">
                                                <label>
                                                    Type
                                                    <select name="step_item_type[<?= e((string) $index); ?>][]">
                                                        <?php foreach ($itemTypeOptions as $value => $label): ?>
                                                            <option value="<?= e($value); ?>" <?= (string) ($item['item_type'] ?? 'devotional') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label>
                                                    Title
                                                    <input type="text" name="step_item_title[<?= e((string) $index); ?>][]" value="<?= e((string) ($item['title'] ?? '')); ?>">
                                                </label>
                                                <label>
                                                    Image or video URL
                                                    <input type="url" name="step_item_resource_url[<?= e((string) $index); ?>][]" value="<?= e((string) ($item['resource_url'] ?? '')); ?>">
                                                </label>
                                                <label>
                                                    Bible reference
                                                    <input type="text" name="step_item_bible_reference[<?= e((string) $index); ?>][]" value="<?= e((string) ($item['bible_reference'] ?? '')); ?>">
                                                </label>
                                                <label>
                                                    Unlock
                                                    <select name="step_item_unlock_rule[<?= e((string) $index); ?>][]">
                                                        <?php foreach ($itemUnlockOptions as $value => $label): ?>
                                                            <option value="<?= e($value); ?>" <?= (string) ($item['unlock_rule'] ?? 'none') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label class="sermon-checkbox-field">
                                                    <input type="checkbox" name="step_item_required[<?= e((string) $index); ?>][<?= e((string) $itemIndex); ?>]" value="1" <?= !empty($item['is_required']) ? 'checked' : ''; ?>>
                                                    Required
                                                </label>
                                            </div>
                                            <label>
                                                Item content
                                                <textarea name="step_item_body[<?= e((string) $index); ?>][]" rows="4"><?= e((string) ($item['body'] ?? '')); ?></textarea>
                                            </label>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <div class="inline-actions">
                            <button class="button button-secondary" type="button" data-study-day-add>Add Day</button>
                            <button class="button button-primary" type="submit">Save Study</button>
                        </div>
                    </form>

                    <form class="top-gap-sm" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="study_id" value="<?= e((string) $editingStudy['id']); ?>">
                        <button class="button button-secondary" type="submit">Delete Study</button>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
