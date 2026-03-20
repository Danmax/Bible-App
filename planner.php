<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

function planner_redirect_url(int $year, ?int $editGoalId = null, ?int $editEventId = null): string
{
    $params = ['year' => $year];

    if ($editGoalId !== null) {
        $params['edit_goal'] = $editGoalId;
    }

    if ($editEventId !== null) {
        $params['edit_event'] = $editEventId;
    }

    return app_url('planner.php?' . http_build_query($params));
}

function planner_format_datetime_input(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }

    return date('Y-m-d\TH:i', strtotime($date));
}

function planner_parse_datetime_input(?string $value): ?string
{
    $trimmed = trim((string) $value);

    if ($trimmed === '') {
        return null;
    }

    $timestamp = strtotime($trimmed);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function planner_goal_form_defaults(int $year, ?array $goal = null): array
{
    return [
        'year' => (string) ($goal['year'] ?? $year),
        'goal_title' => (string) ($goal['goal_title'] ?? ''),
        'goal_type' => (string) ($goal['goal_type'] ?? 'reading'),
        'target_value' => isset($goal['target_value']) && $goal['target_value'] !== null ? (string) $goal['target_value'] : '',
        'current_value' => (string) ($goal['current_value'] ?? '0'),
        'status' => (string) ($goal['status'] ?? 'active'),
    ];
}

function planner_event_form_defaults(?array $event = null): array
{
    return [
        'title' => (string) ($event['title'] ?? ''),
        'event_type' => (string) ($event['event_type'] ?? 'study'),
        'event_date' => planner_format_datetime_input($event['event_date'] ?? null),
        'description' => (string) ($event['description'] ?? ''),
    ];
}

function planner_validate_goal_payload(array $source): array
{
    $formData = [
        'year' => trim((string) ($source['year'] ?? '')),
        'goal_title' => trim((string) ($source['goal_title'] ?? '')),
        'goal_type' => trim((string) ($source['goal_type'] ?? 'reading')),
        'target_value' => trim((string) ($source['target_value'] ?? '')),
        'current_value' => trim((string) ($source['current_value'] ?? '0')),
        'status' => trim((string) ($source['status'] ?? 'active')),
    ];

    $year = filter_var($formData['year'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 2000, 'max_range' => 2100],
    ]);

    if ($year === false || $formData['goal_title'] === '') {
        return [null, $formData, 'Goal title and year are required.'];
    }

    $allowedTypes = ['reading', 'attendance', 'devotion', 'prayer', 'service', 'custom'];
    $allowedStatuses = ['active', 'paused', 'completed'];
    $goalType = in_array($formData['goal_type'], $allowedTypes, true) ? $formData['goal_type'] : 'custom';
    $status = in_array($formData['status'], $allowedStatuses, true) ? $formData['status'] : 'active';

    $targetValue = null;

    if ($formData['target_value'] !== '') {
        $targetValue = filter_var($formData['target_value'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        if ($targetValue === false) {
            return [null, $formData, 'Target value must be zero or greater.'];
        }
    }

    $currentValue = filter_var($formData['current_value'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0],
    ]);

    if ($currentValue === false) {
        return [null, $formData, 'Current progress must be zero or greater.'];
    }

    return [[
        'year' => $year,
        'goal_title' => $formData['goal_title'],
        'goal_type' => $goalType,
        'target_value' => $targetValue,
        'current_value' => $currentValue,
        'status' => $status,
    ], $formData, null];
}

function planner_validate_event_payload(array $source): array
{
    $formData = [
        'title' => trim((string) ($source['title'] ?? '')),
        'event_type' => trim((string) ($source['event_type'] ?? 'study')),
        'event_date' => trim((string) ($source['event_date'] ?? '')),
        'description' => trim((string) ($source['description'] ?? '')),
    ];

    if ($formData['title'] === '' || $formData['event_type'] === '') {
        return [null, $formData, 'Event title and type are required.'];
    }

    $eventDate = planner_parse_datetime_input($formData['event_date']);

    if ($eventDate === null) {
        return [null, $formData, 'Choose a valid date and time for the planner event.'];
    }

    $allowedTypes = ['study', 'prayer', 'service', 'family', 'community', 'goal', 'reminder'];
    $eventType = in_array($formData['event_type'], $allowedTypes, true) ? $formData['event_type'] : 'reminder';

    return [[
        'title' => $formData['title'],
        'event_type' => $eventType,
        'event_date' => $eventDate,
        'description' => normalize_optional_text($formData['description']),
        'related_community_event_id' => null,
    ], $formData, null];
}

$pageTitle = 'Planner';
$activePage = 'planner';
$user = refresh_current_user();
$activeYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$activeYear = $activeYear !== false && $activeYear !== null ? $activeYear : (int) date('Y');
$editingGoalId = filter_input(INPUT_GET, 'edit_goal', FILTER_VALIDATE_INT);
$editingEventId = filter_input(INPUT_GET, 'edit_event', FILTER_VALIDATE_INT);
$pageError = null;
$goalFormError = null;
$eventFormError = null;
$goals = [];
$schedule = [];
$editingGoal = null;
$editingEvent = null;
$goalFormData = planner_goal_form_defaults($activeYear);
$eventFormData = planner_event_form_defaults();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $postedYear = filter_var($_POST['year'] ?? $activeYear, FILTER_VALIDATE_INT);
    $activeYear = $postedYear !== false && $postedYear !== null ? $postedYear : $activeYear;

    try {
        if ($action === 'delete-goal') {
            $goalId = (int) ($_POST['goal_id'] ?? 0);
            delete_yearly_goal_record($goalId, (int) $user['id']);
            set_flash('Goal removed from your planner.', 'success');
            redirect(planner_redirect_url($activeYear));
        }

        if ($action === 'delete-event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            delete_planner_event_record($eventId, (int) $user['id']);
            set_flash('Planner event removed.', 'success');
            redirect(planner_redirect_url($activeYear));
        }

        if ($action === 'create-goal' || $action === 'update-goal') {
            [$payload, $goalFormData, $goalFormError] = planner_validate_goal_payload($_POST);

            if ($goalFormError === null && $payload !== null) {
                if ($action === 'create-goal') {
                    $goalId = create_yearly_goal_record((int) $user['id'], $payload);
                    set_flash('Planner goal created.', 'success');
                    redirect(planner_redirect_url((int) $payload['year'], $goalId, $editingEventId ?: null));
                }

                $goalId = (int) ($_POST['goal_id'] ?? 0);

                if (fetch_yearly_goal_by_id($goalId, (int) $user['id']) === null) {
                    throw new RuntimeException('That goal is no longer available.');
                }

                update_yearly_goal_record($goalId, (int) $user['id'], $payload);
                set_flash('Planner goal updated.', 'success');
                redirect(planner_redirect_url((int) $payload['year'], $goalId, $editingEventId ?: null));
            }

            $editingGoalId = (int) ($_POST['goal_id'] ?? $editingGoalId ?? 0);
        } elseif ($action === 'create-event' || $action === 'update-event') {
            [$payload, $eventFormData, $eventFormError] = planner_validate_event_payload($_POST);

            if ($eventFormError === null && $payload !== null) {
                if ($action === 'create-event') {
                    $eventId = create_planner_event_record((int) $user['id'], $payload);
                    set_flash('Planner event created.', 'success');
                    redirect(planner_redirect_url($activeYear, $editingGoalId ?: null, $eventId));
                }

                $eventId = (int) ($_POST['event_id'] ?? 0);

                if (fetch_planner_event_by_id($eventId, (int) $user['id']) === null) {
                    throw new RuntimeException('That planner event is no longer available.');
                }

                update_planner_event_record($eventId, (int) $user['id'], $payload);
                set_flash('Planner event updated.', 'success');
                redirect(planner_redirect_url($activeYear, $editingGoalId ?: null, $eventId));
            }

            $editingEventId = (int) ($_POST['event_id'] ?? $editingEventId ?? 0);
        }
    } catch (Throwable $exception) {
        if ($action === 'create-goal' || $action === 'update-goal' || $action === 'delete-goal') {
            $goalFormError = $goalFormError ?? $exception->getMessage();
        } else {
            $eventFormError = $eventFormError ?? $exception->getMessage();
        }
    }
}

try {
    $goals = fetch_yearly_goals_for_user((int) $user['id'], $activeYear);
    $schedule = fetch_planner_schedule((int) $user['id'], 10);

    if ($editingGoalId) {
        $editingGoal = fetch_yearly_goal_by_id((int) $editingGoalId, (int) $user['id']);
    }

    if ($editingEventId) {
        $editingEvent = fetch_planner_event_by_id((int) $editingEventId, (int) $user['id']);
    }
} catch (Throwable $exception) {
    $pageError = 'Planner data could not be loaded because the database is unavailable.';
}

if ($editingGoal !== null && $goalFormError === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $goalFormData = planner_goal_form_defaults($activeYear, $editingGoal);
}

if ($editingEvent !== null && $eventFormError === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $eventFormData = planner_event_form_defaults($editingEvent);
}

$summary = summarize_yearly_goals($goals);
$yearOptions = [$activeYear - 1, $activeYear, $activeYear + 1];

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Yearly Planner</p>
            <h1>Manage your goals and schedule</h1>
            <p>Keep your reading rhythms, attendance goals, reminders, and personal events in one place.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="filter-row">
            <?php foreach ($yearOptions as $yearOption): ?>
                <a class="filter-chip <?= $yearOption === $activeYear ? 'is-active' : ''; ?>" href="<?= e(planner_redirect_url($yearOption)); ?>">
                    <?= e((string) $yearOption); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="card-grid card-grid-3 top-gap">
            <article class="stat-card">
                <p class="eyebrow">Goals</p>
                <h3><?= e((string) $summary['total']); ?></h3>
                <p class="muted-copy">Total goals planned for <?= e((string) $activeYear); ?></p>
            </article>
            <article class="stat-card">
                <p class="eyebrow">Active</p>
                <h3><?= e((string) $summary['active']); ?></h3>
                <p class="muted-copy">Goals you are actively working right now</p>
            </article>
            <article class="stat-card">
                <p class="eyebrow">Progress</p>
                <h3><?= e((string) $summary['progress_percent']); ?>%</h3>
                <p class="muted-copy"><?= e((string) $summary['completed']); ?> goal<?= $summary['completed'] === 1 ? '' : 's'; ?> completed</p>
            </article>
        </div>

        <div class="community-layout top-gap">
            <div class="stack-list">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Yearly goals</h2>
                            <p class="muted-copy">Track reading, prayer, attendance, and custom milestones.</p>
                        </div>
                        <span class="mini-card"><?= e((string) $activeYear); ?></span>
                    </div>

                    <?php if ($goals === []): ?>
                        <p class="empty-state">No goals are in your planner yet. Create the first one from the manager panel.</p>
                    <?php else: ?>
                        <?php foreach ($goals as $goal): ?>
                            <?php $progressPercent = calculate_goal_progress_percent($goal); ?>
                            <article class="list-card list-card-block">
                                <div class="planner-item-body">
                                    <div class="planner-item-header">
                                        <div>
                                            <strong><?= e((string) $goal['goal_title']); ?></strong>
                                            <span class="muted-copy"><?= e(ucfirst((string) $goal['goal_type'])); ?> goal</span>
                                        </div>
                                        <div class="planner-pill-row">
                                            <span class="pill"><?= e(ucfirst((string) $goal['status'])); ?></span>
                                            <?php if ($progressPercent !== null): ?>
                                                <span class="pill pill-dark"><?= e((string) $progressPercent); ?>%</span>
                                            <?php elseif ((int) $goal['current_value'] > 0): ?>
                                                <span class="pill pill-dark"><?= e((string) $goal['current_value']); ?> done</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="planner-progress-meta">
                                        <?php if ($goal['target_value'] !== null): ?>
                                            <span><?= e((string) $goal['current_value']); ?> / <?= e((string) $goal['target_value']); ?> complete</span>
                                        <?php else: ?>
                                            <span><?= e((string) $goal['current_value']); ?> progress logged</span>
                                        <?php endif; ?>
                                        <span>Updated <?= e(date('M j', strtotime((string) $goal['updated_at']))); ?></span>
                                    </div>

                                    <?php if ($progressPercent !== null): ?>
                                        <div class="planner-progress-bar" aria-hidden="true">
                                            <span style="width: <?= e((string) $progressPercent); ?>%"></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="inline-actions top-gap-sm">
                                        <a class="button button-secondary" href="<?= e(planner_redirect_url($activeYear, (int) $goal['id'], $editingEventId ?: null)); ?>">Edit Goal</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete-goal">
                                            <input type="hidden" name="year" value="<?= e((string) $activeYear); ?>">
                                            <input type="hidden" name="goal_id" value="<?= e((string) $goal['id']); ?>">
                                            <button class="button button-secondary" type="submit">Delete</button>
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
                            <h2>Upcoming schedule</h2>
                            <p class="muted-copy">Your personal planner events in chronological order.</p>
                        </div>
                        <span class="mini-card"><?= e((string) count($schedule)); ?> items</span>
                    </div>

                    <?php if ($schedule === []): ?>
                        <p class="empty-state">No planner events are scheduled yet. Add reminders, studies, or family rhythms from the form.</p>
                    <?php else: ?>
                        <?php foreach ($schedule as $event): ?>
                            <article class="list-card list-card-block">
                                <div class="planner-item-body">
                                    <div class="planner-item-header">
                                        <div>
                                            <strong><?= e((string) $event['title']); ?></strong>
                                            <span class="muted-copy"><?= e(ucfirst((string) $event['event_type'])); ?></span>
                                        </div>
                                        <span class="pill pill-dark"><?= e(date('M j', strtotime((string) $event['event_date']))); ?></span>
                                    </div>

                                    <div class="planner-progress-meta">
                                        <span><?= e(format_event_datetime((string) $event['event_date'])); ?></span>
                                        <span><?= e(ucfirst((string) $event['source'])); ?> planner item</span>
                                    </div>

                                    <?php if (!empty($event['description'])): ?>
                                        <p class="muted-copy"><?= e((string) $event['description']); ?></p>
                                    <?php endif; ?>

                                    <div class="inline-actions top-gap-sm">
                                        <a class="button button-secondary" href="<?= e(planner_redirect_url($activeYear, $editingGoalId ?: null, (int) $event['id'])); ?>">Edit Event</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete-event">
                                            <input type="hidden" name="year" value="<?= e((string) $activeYear); ?>">
                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                            <button class="button button-secondary" type="submit">Delete</button>
                                        </form>
                                    </div>
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
                            <h2><?= $editingGoal ? 'Edit goal' : 'Create goal'; ?></h2>
                            <p class="muted-copy">Set the target, record current progress, and keep the year measurable.</p>
                        </div>
                        <?php if ($editingGoal): ?>
                            <a class="button button-secondary" href="<?= e(planner_redirect_url($activeYear, null, $editingEventId ?: null)); ?>">New Goal</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($goalFormError): ?>
                        <div class="flash flash-warning"><?= e($goalFormError); ?></div>
                    <?php endif; ?>

                    <form class="form-stack compact-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="<?= e($editingGoal ? 'update-goal' : 'create-goal'); ?>">
                        <?php if ($editingGoal): ?>
                            <input type="hidden" name="goal_id" value="<?= e((string) $editingGoal['id']); ?>">
                        <?php endif; ?>

                        <label>
                            Goal title
                            <input type="text" name="goal_title" value="<?= e($goalFormData['goal_title']); ?>" placeholder="Read the New Testament" required>
                        </label>

                        <label>
                            Goal type
                            <select name="goal_type">
                                <?php foreach (['reading', 'attendance', 'devotion', 'prayer', 'service', 'custom'] as $goalType): ?>
                                    <option value="<?= e($goalType); ?>" <?= $goalFormData['goal_type'] === $goalType ? 'selected' : ''; ?>>
                                        <?= e(ucfirst($goalType)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Year
                            <input type="number" name="year" min="2000" max="2100" value="<?= e($goalFormData['year']); ?>" required>
                        </label>

                        <label>
                            Target value
                            <input type="number" name="target_value" min="0" value="<?= e($goalFormData['target_value']); ?>" placeholder="260">
                        </label>

                        <label>
                            Current progress
                            <input type="number" name="current_value" min="0" value="<?= e($goalFormData['current_value']); ?>" required>
                        </label>

                        <label>
                            Status
                            <select name="status">
                                <?php foreach (['active', 'paused', 'completed'] as $status): ?>
                                    <option value="<?= e($status); ?>" <?= $goalFormData['status'] === $status ? 'selected' : ''; ?>>
                                        <?= e(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <button class="button button-primary" type="submit"><?= $editingGoal ? 'Update Goal' : 'Create Goal'; ?></button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2><?= $editingEvent ? 'Edit planner event' : 'Add planner event'; ?></h2>
                            <p class="muted-copy">Capture reminders, study sessions, and family plans on your personal schedule.</p>
                        </div>
                        <?php if ($editingEvent): ?>
                            <a class="button button-secondary" href="<?= e(planner_redirect_url($activeYear, $editingGoalId ?: null, null)); ?>">New Event</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($eventFormError): ?>
                        <div class="flash flash-warning"><?= e($eventFormError); ?></div>
                    <?php endif; ?>

                    <form class="form-stack compact-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="<?= e($editingEvent ? 'update-event' : 'create-event'); ?>">
                        <input type="hidden" name="year" value="<?= e((string) $activeYear); ?>">
                        <?php if ($editingEvent): ?>
                            <input type="hidden" name="event_id" value="<?= e((string) $editingEvent['id']); ?>">
                        <?php endif; ?>

                        <label>
                            Event title
                            <input type="text" name="title" value="<?= e($eventFormData['title']); ?>" placeholder="Family devotion night" required>
                        </label>

                        <label>
                            Event type
                            <select name="event_type">
                                <?php foreach (['study', 'prayer', 'service', 'family', 'community', 'goal', 'reminder'] as $eventType): ?>
                                    <option value="<?= e($eventType); ?>" <?= $eventFormData['event_type'] === $eventType ? 'selected' : ''; ?>>
                                        <?= e(ucfirst($eventType)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Date and time
                            <input type="datetime-local" name="event_date" value="<?= e($eventFormData['event_date']); ?>" required>
                        </label>

                        <label>
                            Notes
                            <textarea name="description" rows="4" placeholder="What should you remember for this event?"><?= e($eventFormData['description']); ?></textarea>
                        </label>

                        <button class="button button-primary" type="submit"><?= $editingEvent ? 'Update Event' : 'Add Event'; ?></button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
