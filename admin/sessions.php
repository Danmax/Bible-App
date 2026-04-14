<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_has_role(['admin'])) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    $activePage = '';
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Restricted</p>
                <h1>Admin access required</h1>
                <p>You do not have permission to manage public sessions.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

function admin_public_session_datetime_value(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
}

function admin_public_session_datetime_sql(string $value): ?string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    $timestamp = strtotime($trimmed);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

$pageTitle = 'Admin Sessions';
$activePage = 'admin';
$pageError = null;
$sessions = [];
$editingSession = null;
$sessionTypeOptions = [
    'study' => 'Bible Study',
    'prayer' => 'Prayer',
    'workshop' => 'Workshop',
    'service' => 'Service',
    'qa' => 'Q&A',
    'fellowship' => 'Fellowship',
];
$statusOptions = [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived',
];
$formValues = [
    'title' => '',
    'summary' => '',
    'session_type' => 'study',
    'host_name' => '',
    'location_name' => '',
    'meeting_url' => '',
    'start_at' => '',
    'end_at' => '',
    'capacity' => '',
    'is_featured' => '0',
    'status' => 'published',
];

if (public_sessions_available() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    $formValues = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'summary' => trim((string) ($_POST['summary'] ?? '')),
        'session_type' => trim((string) ($_POST['session_type'] ?? 'study')),
        'host_name' => trim((string) ($_POST['host_name'] ?? '')),
        'location_name' => trim((string) ($_POST['location_name'] ?? '')),
        'meeting_url' => trim((string) ($_POST['meeting_url'] ?? '')),
        'start_at' => trim((string) ($_POST['start_at'] ?? '')),
        'end_at' => trim((string) ($_POST['end_at'] ?? '')),
        'capacity' => trim((string) ($_POST['capacity'] ?? '')),
        'is_featured' => trim((string) ($_POST['is_featured'] ?? '0')) === '1' ? '1' : '0',
        'status' => trim((string) ($_POST['status'] ?? 'published')),
    ];

    try {
        if ($action === 'delete') {
            if ($sessionId <= 0) {
                throw new RuntimeException('Select a valid public session.');
            }

            delete_public_session($sessionId);
            record_audit_event((int) current_user()['id'], 'public_session.deleted', null, [
                'public_session_id' => $sessionId,
            ]);
            set_flash('Public session removed.', 'success');
            redirect('admin/sessions.php');
        }

        if ($formValues['title'] === '' || $formValues['summary'] === '') {
            throw new RuntimeException('Title and summary are required.');
        }

        if (!isset($sessionTypeOptions[$formValues['session_type']])) {
            throw new RuntimeException('Select a valid session type.');
        }

        if (!isset($statusOptions[$formValues['status']])) {
            throw new RuntimeException('Select a valid status.');
        }

        $startAtSql = admin_public_session_datetime_sql($formValues['start_at']);
        $endAtSql = admin_public_session_datetime_sql($formValues['end_at']);

        if ($startAtSql === null) {
            throw new RuntimeException('Choose a valid start date and time.');
        }

        if ($endAtSql !== null && strtotime($endAtSql) < strtotime($startAtSql)) {
            throw new RuntimeException('End time must be after the start time.');
        }

        $capacity = null;
        if ($formValues['capacity'] !== '') {
            $capacity = (int) $formValues['capacity'];

            if ($capacity <= 0) {
                throw new RuntimeException('Capacity must be greater than zero.');
            }
        }

        if ($action === 'update') {
            if ($sessionId <= 0) {
                throw new RuntimeException('Select a valid public session.');
            }

            update_public_session(
                $sessionId,
                $formValues['title'],
                $formValues['summary'],
                $formValues['session_type'],
                $formValues['host_name'],
                $formValues['location_name'],
                $formValues['meeting_url'],
                $startAtSql,
                $endAtSql,
                $capacity,
                $formValues['is_featured'] === '1',
                $formValues['status']
            );
            record_audit_event((int) current_user()['id'], 'public_session.updated', null, [
                'public_session_id' => $sessionId,
                'status' => $formValues['status'],
            ]);
            set_flash('Public session updated.', 'success');
            redirect('admin/sessions.php');
        }

        $createdSessionId = create_public_session(
            (int) current_user()['id'],
            $formValues['title'],
            $formValues['summary'],
            $formValues['session_type'],
            $formValues['host_name'],
            $formValues['location_name'],
            $formValues['meeting_url'],
            $startAtSql,
            $endAtSql,
            $capacity,
            $formValues['is_featured'] === '1',
            $formValues['status']
        );
        record_audit_event((int) current_user()['id'], 'public_session.created', null, [
            'public_session_id' => $createdSessionId,
            'status' => $formValues['status'],
        ]);
        set_flash('Public session created.', 'success');
        redirect('admin/sessions.php');
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Public session changes could not be saved because the database is unavailable.';
    }
}

if (public_sessions_available()) {
    try {
        $editId = (int) ($_GET['edit'] ?? 0);

        if ($editId > 0) {
            $editingSession = fetch_manageable_public_session_by_id($editId);

            if ($editingSession === null) {
                set_flash('That public session could not be found.', 'warning');
                redirect('admin/sessions.php');
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $formValues = [
                    'title' => (string) ($editingSession['title'] ?? ''),
                    'summary' => (string) ($editingSession['summary'] ?? ''),
                    'session_type' => (string) ($editingSession['session_type'] ?? 'study'),
                    'host_name' => (string) ($editingSession['host_name'] ?? ''),
                    'location_name' => (string) ($editingSession['location_name'] ?? ''),
                    'meeting_url' => (string) ($editingSession['meeting_url'] ?? ''),
                    'start_at' => admin_public_session_datetime_value((string) ($editingSession['start_at'] ?? '')),
                    'end_at' => admin_public_session_datetime_value((string) ($editingSession['end_at'] ?? '')),
                    'capacity' => isset($editingSession['capacity']) && $editingSession['capacity'] !== null ? (string) $editingSession['capacity'] : '',
                    'is_featured' => (int) ($editingSession['is_featured'] ?? 0) === 1 ? '1' : '0',
                    'status' => (string) ($editingSession['status'] ?? 'published'),
                ];
            }
        }

        $sessions = fetch_manageable_public_sessions();
    } catch (Throwable $exception) {
        $pageError = 'Public sessions could not be loaded because the database is unavailable.';
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Admin</p>
                <h1>Public session management</h1>
                <p>Create and publish the public sessions that appear on the shared sessions page.</p>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('sessions.php')); ?>">Open Public Page</a>
                <a class="button button-secondary" href="<?= e(app_url('admin/index.php')); ?>">Back to Admin</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if (!public_sessions_available()): ?>
            <article class="panel">
                <h2>Public sessions migration required</h2>
                <p>Run <code>sql/add_public_sessions.sql</code> to enable admin-managed public sessions.</p>
            </article>
        <?php else: ?>
            <div class="card-grid card-grid-2">
                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow"><?= $editingSession ? 'Edit Session' : 'New Session'; ?></p>
                            <h2><?= $editingSession ? 'Update public session' : 'Create a public session'; ?></h2>
                        </div>
                    </div>

                    <form class="form-stack top-gap-sm" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="<?= e($editingSession ? 'update' : 'create'); ?>">
                        <input type="hidden" name="session_id" value="<?= e($editingSession ? (string) $editingSession['id'] : ''); ?>">

                        <label>
                            <span>Title</span>
                            <input type="text" name="title" value="<?= e($formValues['title']); ?>" placeholder="Tuesday night Bible study" required>
                        </label>

                        <label>
                            <span>Summary</span>
                            <textarea name="summary" rows="5" placeholder="What this session covers, who it is for, and how people should join." required><?= e($formValues['summary']); ?></textarea>
                        </label>

                        <div class="two-column">
                            <label>
                                <span>Session type</span>
                                <select name="session_type">
                                    <?php foreach ($sessionTypeOptions as $value => $label): ?>
                                        <option value="<?= e($value); ?>" <?= $formValues['session_type'] === $value ? 'selected' : ''; ?>>
                                            <?= e($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Status</span>
                                <select name="status">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= e($value); ?>" <?= $formValues['status'] === $value ? 'selected' : ''; ?>>
                                            <?= e($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="two-column">
                            <label>
                                <span>Host name</span>
                                <input type="text" name="host_name" value="<?= e($formValues['host_name']); ?>" placeholder="Admin team or speaker">
                            </label>

                            <label>
                                <span>Capacity</span>
                                <input type="number" min="1" name="capacity" value="<?= e($formValues['capacity']); ?>" placeholder="25">
                            </label>
                        </div>

                        <div class="two-column">
                            <label>
                                <span>Start</span>
                                <input type="datetime-local" name="start_at" value="<?= e($formValues['start_at']); ?>" required>
                            </label>

                            <label>
                                <span>End</span>
                                <input type="datetime-local" name="end_at" value="<?= e($formValues['end_at']); ?>">
                            </label>
                        </div>

                        <label>
                            <span>Location</span>
                            <input type="text" name="location_name" value="<?= e($formValues['location_name']); ?>" placeholder="Main hall or Zoom">
                        </label>

                        <label>
                            <span>Meeting URL</span>
                            <input type="url" name="meeting_url" value="<?= e($formValues['meeting_url']); ?>" placeholder="https://...">
                        </label>

                        <label>
                            <span>Featured</span>
                            <select name="is_featured">
                                <option value="0" <?= $formValues['is_featured'] === '0' ? 'selected' : ''; ?>>Standard</option>
                                <option value="1" <?= $formValues['is_featured'] === '1' ? 'selected' : ''; ?>>Featured</option>
                            </select>
                        </label>

                        <div class="inline-actions">
                            <button class="button button-primary" type="submit"><?= $editingSession ? 'Save Changes' : 'Create Session'; ?></button>
                            <?php if ($editingSession): ?>
                                <a class="button button-secondary" href="<?= e(app_url('admin/sessions.php')); ?>">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>

                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Current Sessions</p>
                            <h2>Admin-managed list</h2>
                        </div>
                    </div>

                    <?php if ($sessions === []): ?>
                        <p class="empty-state top-gap-sm">No public sessions have been created yet.</p>
                    <?php else: ?>
                        <div class="stack-list top-gap-sm">
                            <?php foreach ($sessions as $session): ?>
                                <?php $sessionStatus = (string) ($session['status'] ?? 'published'); ?>
                                <div class="list-card">
                                    <div>
                                        <strong><?= e((string) $session['title']); ?></strong>
                                        <p class="muted-copy"><?= e(format_event_datetime((string) $session['start_at'])); ?></p>
                                        <div class="inline-actions">
                                            <span class="pill"><?= e($statusOptions[$sessionStatus] ?? 'Published'); ?></span>
                                            <span class="pill pill-dark"><?= e(ucwords(str_replace('-', ' ', (string) $session['session_type']))); ?></span>
                                        </div>
                                    </div>

                                    <div class="inline-actions">
                                        <a class="button button-secondary" href="<?= e(app_url('admin/sessions.php?edit=' . (int) $session['id'])); ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="session_id" value="<?= e((string) $session['id']); ?>">
                                            <button class="button button-secondary" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
