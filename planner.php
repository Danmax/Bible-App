<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/openai.php';

require_login();

function planner_redirect_url(int $year, ?int $editGoalId = null, ?int $editEventId = null): string
{
    return planner_build_url($year, 'month', null, $editGoalId, $editEventId);
}

function planner_build_url(
    int $year,
    string $view = 'month',
    ?string $date = null,
    ?int $editGoalId = null,
    ?int $editEventId = null
): string {
    $params = [
        'year' => $year,
        'view' => $view,
    ];

    if ($date !== null && $date !== '') {
        $params['date'] = $date;
    }

    if ($editGoalId !== null) {
        $params['edit_goal'] = $editGoalId;
    }

    if ($editEventId !== null) {
        $params['edit_event'] = $editEventId;
    }

    return app_url('planner.php?' . http_build_query($params));
}

function planner_normalize_view(?string $value): string
{
    $view = strtolower(trim((string) $value));

    return in_array($view, ['month', 'week'], true) ? $view : 'month';
}

function planner_normalize_date(?string $value): string
{
    $trimmed = trim((string) $value);
    $timestamp = strtotime($trimmed === '' ? 'today' : $trimmed);

    if ($timestamp === false) {
        $timestamp = time();
    }

    return date('Y-m-d', $timestamp);
}

function planner_calendar_range(string $view, string $date): array
{
    $timestamp = strtotime($date) ?: time();

    if ($view === 'week') {
        $dayOfWeek = (int) date('w', $timestamp);
        $startTimestamp = strtotime('-' . $dayOfWeek . ' days', $timestamp);
        $endTimestamp = strtotime('+7 days', $startTimestamp);

        return [
            'start' => date('Y-m-d 00:00:00', $startTimestamp),
            'end' => date('Y-m-d 00:00:00', $endTimestamp),
            'label' => date('M j', $startTimestamp) . ' - ' . date('M j, Y', strtotime('-1 day', $endTimestamp)),
            'previous_date' => date('Y-m-d', strtotime('-7 days', $startTimestamp)),
            'next_date' => date('Y-m-d', strtotime('+7 days', $startTimestamp)),
        ];
    }

    $monthStart = strtotime(date('Y-m-01', $timestamp));
    $calendarStart = strtotime('-' . (int) date('w', $monthStart) . ' days', $monthStart);
    $calendarEndExclusive = strtotime('+42 days', $calendarStart);

    return [
        'start' => date('Y-m-d 00:00:00', $calendarStart),
        'end' => date('Y-m-d 00:00:00', $calendarEndExclusive),
        'label' => date('F Y', $monthStart),
        'previous_date' => date('Y-m-d', strtotime('-1 month', $monthStart)),
        'next_date' => date('Y-m-d', strtotime('+1 month', $monthStart)),
    ];
}

function planner_group_events_by_day(array $events): array
{
    $grouped = [];

    foreach ($events as $event) {
        $day = date('Y-m-d', strtotime((string) $event['event_date']));
        $grouped[$day] ??= [];
        $grouped[$day][] = $event;
    }

    return $grouped;
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

function planner_quick_event_datetime(string $date): string
{
    $baseTimestamp = strtotime($date . ' 19:00');

    if ($baseTimestamp === false) {
        $baseTimestamp = strtotime('today 19:00') ?: time();
    }

    if ($date === date('Y-m-d')) {
        $roundedHour = (int) date('G') + 1;
        $roundedHour = max(8, min($roundedHour, 21));
        $todayTimestamp = strtotime($date . sprintf(' %02d:00', $roundedHour));

        if ($todayTimestamp !== false) {
            $baseTimestamp = $todayTimestamp;
        }
    }

    return date('Y-m-d\TH:i', $baseTimestamp);
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
$calendarView = planner_normalize_view($_GET['view'] ?? 'month');
$calendarDate = planner_normalize_date($_GET['date'] ?? date('Y-m-d'));
$editingGoalId = filter_input(INPUT_GET, 'edit_goal', FILTER_VALIDATE_INT);
$editingEventId = filter_input(INPUT_GET, 'edit_event', FILTER_VALIDATE_INT);
$pageError = null;
$goalFormError = null;
$eventFormError = null;
$goals = [];
$schedule = [];
$editingGoal = null;
$editingEvent = null;
$showGoalPanel = false;
$showEventPanel = false;
$goalFormData = planner_goal_form_defaults($activeYear);
$eventFormData = planner_event_form_defaults();
$calendarRange = planner_calendar_range($calendarView, $calendarDate);
$calendarEvents = [];
$calendarEventsByDay = [];

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
            redirect(planner_build_url($activeYear, $calendarView, $calendarDate));
        }

        if ($action === 'delete-event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            delete_planner_event_record($eventId, (int) $user['id']);
            set_flash('Planner event removed.', 'success');
            redirect(planner_build_url($activeYear, $calendarView, $calendarDate));
        }

        if ($action === 'create-goal' || $action === 'update-goal') {
            [$payload, $goalFormData, $goalFormError] = planner_validate_goal_payload($_POST);

            if ($goalFormError === null && $payload !== null) {
                if ($action === 'create-goal') {
                    create_yearly_goal_record((int) $user['id'], $payload);
                    set_flash('Planner goal created.', 'success');
                    redirect(planner_build_url((int) $payload['year'], $calendarView, $calendarDate));
                }

                $goalId = (int) ($_POST['goal_id'] ?? 0);

                if (fetch_yearly_goal_by_id($goalId, (int) $user['id']) === null) {
                    throw new RuntimeException('That goal is no longer available.');
                }

                update_yearly_goal_record($goalId, (int) $user['id'], $payload);
                set_flash('Planner goal updated.', 'success');
                redirect(planner_build_url((int) $payload['year'], $calendarView, $calendarDate));
            }

            $editingGoalId = (int) ($_POST['goal_id'] ?? $editingGoalId ?? 0);
        } elseif ($action === 'create-event' || $action === 'update-event') {
            [$payload, $eventFormData, $eventFormError] = planner_validate_event_payload($_POST);

            if ($eventFormError === null && $payload !== null) {
                if ($action === 'create-event') {
                    create_planner_event_record((int) $user['id'], $payload);
                    set_flash('Planner event created.', 'success');
                    redirect(planner_build_url($activeYear, $calendarView, $calendarDate));
                }

                $eventId = (int) ($_POST['event_id'] ?? 0);

                if (fetch_planner_event_by_id($eventId, (int) $user['id']) === null) {
                    throw new RuntimeException('That planner event is no longer available.');
                }

                update_planner_event_record($eventId, (int) $user['id'], $payload);
                set_flash('Planner event updated.', 'success');
                redirect(planner_build_url($activeYear, $calendarView, $calendarDate));
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
    $calendarEvents = fetch_planner_events_between((int) $user['id'], $calendarRange['start'], $calendarRange['end']);
    $calendarEventsByDay = planner_group_events_by_day($calendarEvents);

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

$showGoalPanel = $editingGoal !== null || $goalFormError !== null;
$showEventPanel = $editingEvent !== null || $eventFormError !== null;
$plannerAiEnabled = openai_event_drafts_enabled();

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
                <a class="filter-chip <?= $yearOption === $activeYear ? 'is-active' : ''; ?>" href="<?= e(planner_build_url($yearOption, $calendarView, $calendarDate)); ?>">
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

        <section class="panel top-gap">
            <div class="panel-heading">
                <div>
                    <h2>Planner calendar</h2>
                    <p class="muted-copy">Switch between monthly and weekly views of your personal planner events.</p>
                </div>
                <span class="mini-card"><?= e($calendarRange['label']); ?></span>
            </div>

            <div class="planner-calendar-toolbar top-gap-sm">
                <div class="planner-view-switch">
                    <a class="filter-chip <?= $calendarView === 'month' ? 'is-active' : ''; ?>" href="<?= e(planner_build_url($activeYear, 'month', $calendarDate, $editingGoalId ?: null, $editingEventId ?: null)); ?>">Month</a>
                    <a class="filter-chip <?= $calendarView === 'week' ? 'is-active' : ''; ?>" href="<?= e(planner_build_url($activeYear, 'week', $calendarDate, $editingGoalId ?: null, $editingEventId ?: null)); ?>">Week</a>
                </div>
                <div class="planner-nav-switch">
                    <a class="button button-secondary planner-nav-link" href="<?= e(planner_build_url($activeYear, $calendarView, $calendarRange['previous_date'], $editingGoalId ?: null, $editingEventId ?: null)); ?>">
                        <span class="planner-nav-icon" aria-hidden="true">&#8249;</span>
                        <span class="planner-nav-label">Prev</span>
                    </a>
                    <a class="button button-secondary planner-nav-link" href="<?= e(planner_build_url($activeYear, $calendarView, date('Y-m-d'), $editingGoalId ?: null, $editingEventId ?: null)); ?>">Today</a>
                    <a class="button button-secondary planner-nav-link" href="<?= e(planner_build_url($activeYear, $calendarView, $calendarRange['next_date'], $editingGoalId ?: null, $editingEventId ?: null)); ?>">
                        <span class="planner-nav-label">Next</span>
                        <span class="planner-nav-icon" aria-hidden="true">&#8250;</span>
                    </a>
                </div>
            </div>

            <?php if ($calendarView === 'month'): ?>
                <div class="planner-month-grid top-gap-sm">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekdayLabel): ?>
                        <div class="planner-calendar-head"><?= e($weekdayLabel); ?></div>
                    <?php endforeach; ?>

                    <?php
                    $monthLoopDate = date('Y-m-d', strtotime($calendarRange['start']));
                    for ($index = 0; $index < 42; $index++):
                        $dayKey = $monthLoopDate;
                        $isCurrentMonth = date('Y-m', strtotime($dayKey)) === date('Y-m', strtotime($calendarDate));
                        $isToday = $dayKey === date('Y-m-d');
                        $dayEvents = $calendarEventsByDay[$dayKey] ?? [];
                    ?>
                        <div class="planner-calendar-cell <?= $isCurrentMonth ? '' : 'is-muted'; ?> <?= $isToday ? 'is-today' : ''; ?>">
                            <div class="planner-day-head">
                                <div class="planner-calendar-day-group">
                                    <span class="planner-mobile-weekday"><?= e(date('D', strtotime($dayKey))); ?></span>
                                    <div class="planner-calendar-day"><?= e(date('j', strtotime($dayKey))); ?></div>
                                    <span class="planner-mobile-date"><?= e(date('M j', strtotime($dayKey))); ?></span>
                                </div>
                                <div class="planner-day-quick-actions">
                                    <?php if ($plannerAiEnabled): ?>
                                        <button
                                            class="planner-quick-trigger planner-quick-trigger-voice"
                                            type="button"
                                            data-planner-quick-event
                                            data-planner-quick-mode="voice"
                                            data-planner-event-date="<?= e(planner_quick_event_datetime($dayKey)); ?>"
                                            aria-label="Draft event with voice for <?= e(date('F j, Y', strtotime($dayKey))); ?>"
                                        >
                                            <svg class="planner-quick-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M12 4a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z" />
                                                <path d="M19 11a7 7 0 0 1-14 0" />
                                                <path d="M12 18v3" />
                                                <path d="M9 21h6" />
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="planner-calendar-events">
                                <?php if ($dayEvents === []): ?>
                                    <span class="planner-calendar-empty">No events</span>
                                <?php else: ?>
                                    <?php foreach (array_slice($dayEvents, 0, 3) as $event): ?>
                                        <a class="planner-calendar-event" href="<?= e(planner_build_url($activeYear, $calendarView, $calendarDate, $editingGoalId ?: null, (int) $event['id'])); ?>">
                                            <strong><?= e(date('g:i A', strtotime((string) $event['event_date']))); ?></strong>
                                            <span><?= e((string) $event['title']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if (count($dayEvents) > 3): ?>
                                        <span class="planner-calendar-more">+<?= e((string) (count($dayEvents) - 3)); ?> more</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php
                        $monthLoopDate = date('Y-m-d', strtotime($monthLoopDate . ' +1 day'));
                    endfor;
                    ?>
                </div>
            <?php else: ?>
                <div class="planner-week-grid top-gap-sm">
                    <?php
                    $weekLoopDate = date('Y-m-d', strtotime($calendarRange['start']));
                    for ($index = 0; $index < 7; $index++):
                        $dayKey = $weekLoopDate;
                        $isToday = $dayKey === date('Y-m-d');
                        $dayEvents = $calendarEventsByDay[$dayKey] ?? [];
                    ?>
                        <div class="planner-week-day <?= $isToday ? 'is-today' : ''; ?>">
                            <div class="planner-week-head">
                                <div>
                                    <strong><?= e(date('D', strtotime($dayKey))); ?></strong>
                                    <span><?= e(date('M j', strtotime($dayKey))); ?></span>
                                </div>
                                <div class="planner-day-quick-actions">
                                    <?php if ($plannerAiEnabled): ?>
                                        <button
                                            class="planner-quick-trigger planner-quick-trigger-voice"
                                            type="button"
                                            data-planner-quick-event
                                            data-planner-quick-mode="voice"
                                            data-planner-event-date="<?= e(planner_quick_event_datetime($dayKey)); ?>"
                                            aria-label="Draft event with voice for <?= e(date('F j, Y', strtotime($dayKey))); ?>"
                                        >
                                            <svg class="planner-quick-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M12 4a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z" />
                                                <path d="M19 11a7 7 0 0 1-14 0" />
                                                <path d="M12 18v3" />
                                                <path d="M9 21h6" />
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="planner-week-events">
                                <?php if ($dayEvents === []): ?>
                                    <span class="planner-calendar-empty">No events planned</span>
                                <?php else: ?>
                                    <?php foreach ($dayEvents as $event): ?>
                                        <a class="planner-calendar-event" href="<?= e(planner_build_url($activeYear, $calendarView, $calendarDate, $editingGoalId ?: null, (int) $event['id'])); ?>">
                                            <strong><?= e(date('g:i A', strtotime((string) $event['event_date']))); ?></strong>
                                            <span><?= e((string) $event['title']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php
                        $weekLoopDate = date('Y-m-d', strtotime($weekLoopDate . ' +1 day'));
                    endfor;
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="stack-list top-gap" data-community-panels data-planner-page>
                <div class="community-action-bar">
                    <button class="button button-primary" type="button" data-community-panel-toggle="goal" aria-expanded="<?= $showGoalPanel ? 'true' : 'false'; ?>">
                        <?= $editingGoal ? 'Edit Goal' : 'Create Goal'; ?>
                    </button>
                    <button class="button button-secondary" type="button" data-community-panel-toggle="event" aria-expanded="<?= $showEventPanel ? 'true' : 'false'; ?>">
                        <?= $editingEvent ? 'Edit Event' : '+ Add Event'; ?>
                    </button>
                </div>

                <section
                    class="panel-modal"
                    data-community-panel="goal"
                    data-panel-modal
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="planner-goal-modal-title"
                    <?= $showGoalPanel ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>
                >
                    <div class="panel community-manager-panel panel-modal-card" data-panel-modal-content>
                        <div class="panel-heading">
                            <div>
                                <h2 id="planner-goal-modal-title"><?= $editingGoal ? 'Edit goal' : 'Create goal'; ?></h2>
                                <p class="muted-copy">Set the target, record current progress, and keep the year measurable.</p>
                            </div>
                            <button class="button button-secondary" type="button" data-community-panel-close="goal">Close</button>
                        </div>

                        <?php if ($goalFormError): ?>
                            <div class="flash flash-warning"><?= e($goalFormError); ?></div>
                        <?php endif; ?>

                        <?php if ($plannerAiEnabled): ?>
                            <div
                                class="community-ai-panel planner-ai-panel"
                                data-ai-event-builder
                                data-ai-endpoint="<?= e(app_url('planner-ai-goal.php')); ?>"
                            >
                                <div class="panel-heading">
                                    <div>
                                        <h3>Quick goal draft</h3>
                                        <p class="muted-copy">Speak or type a goal prompt and let the planner fill the goal details for review.</p>
                                    </div>
                                    <span class="pill pill-dark"><?= e(strtoupper(openai_event_model())); ?></span>
                                </div>

                                <label>
                                    Prompt
                                    <textarea rows="3" placeholder="Read the New Testament this year with a target of 260 reading days" data-ai-prompt></textarea>
                                </label>

                                <div class="inline-actions">
                                    <button class="button button-secondary" type="button" data-ai-voice-start>Start Voice</button>
                                    <button class="button button-secondary" type="button" data-ai-voice-stop hidden>Stop</button>
                                    <button class="button button-primary" type="button" data-ai-generate>Create Draft</button>
                                </div>

                                <p class="muted-copy" data-ai-status>Ready to draft a planner goal.</p>
                            </div>
                        <?php endif; ?>

                        <form class="form-stack compact-form" method="post" data-ai-event-form>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="<?= e($editingGoal ? 'update-goal' : 'create-goal'); ?>">
                            <?php if ($editingGoal): ?>
                                <input type="hidden" name="goal_id" value="<?= e((string) $editingGoal['id']); ?>">
                            <?php endif; ?>

                            <label>
                                Goal title
                                <input type="text" name="goal_title" value="<?= e($goalFormData['goal_title']); ?>" placeholder="Read the New Testament" required data-ai-field="goal_title">
                            </label>

                            <label>
                                Goal type
                                <select name="goal_type" data-ai-field="goal_type">
                                    <?php foreach (['reading', 'attendance', 'devotion', 'prayer', 'service', 'custom'] as $goalType): ?>
                                        <option value="<?= e($goalType); ?>" <?= $goalFormData['goal_type'] === $goalType ? 'selected' : ''; ?>>
                                            <?= e(ucfirst($goalType)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Year
                                <input type="number" name="year" min="2000" max="2100" value="<?= e($goalFormData['year']); ?>" required data-ai-field="year" data-ai-context-field="year">
                            </label>

                            <label>
                                Target value
                                <input type="number" name="target_value" min="0" value="<?= e($goalFormData['target_value']); ?>" placeholder="260" data-ai-field="target_value">
                            </label>

                            <label>
                                Current progress
                                <input type="number" name="current_value" min="0" value="<?= e($goalFormData['current_value']); ?>" required data-ai-field="current_value">
                            </label>

                            <label>
                                Status
                                <select name="status" data-ai-field="status">
                                    <?php foreach (['active', 'paused', 'completed'] as $status): ?>
                                        <option value="<?= e($status); ?>" <?= $goalFormData['status'] === $status ? 'selected' : ''; ?>>
                                            <?= e(ucfirst($status)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div class="inline-actions">
                                <button class="button button-primary" type="submit"><?= $editingGoal ? 'Update Goal' : 'Create Goal'; ?></button>
                                <button class="button button-secondary" type="button" data-community-panel-close="goal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section
                    class="panel-modal"
                    data-community-panel="event"
                    data-panel-modal
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="planner-event-modal-title"
                    <?= $showEventPanel ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>
                >
                    <div class="panel community-manager-panel panel-modal-card" data-panel-modal-content>
                        <div class="panel-heading">
                            <div>
                                <h2 id="planner-event-modal-title" data-planner-event-heading><?= $editingEvent ? 'Edit planner event' : 'Add planner event'; ?></h2>
                                <p class="muted-copy">Capture reminders, study sessions, and family plans on your personal schedule.</p>
                            </div>
                            <button class="button button-secondary" type="button" data-community-panel-close="event">Close</button>
                        </div>

                        <?php if ($eventFormError): ?>
                            <div class="flash flash-warning"><?= e($eventFormError); ?></div>
                        <?php endif; ?>

                        <?php if ($plannerAiEnabled): ?>
                            <div
                                class="community-ai-panel planner-ai-panel"
                                data-ai-event-builder
                                data-ai-endpoint="<?= e(app_url('planner-ai-event.php')); ?>"
                            >
                                <div class="panel-heading">
                                    <div>
                                        <h3>Quick event draft</h3>
                                        <p class="muted-copy">Speak or type a short prompt and the planner will draft the event for the selected date.</p>
                                    </div>
                                    <span class="pill pill-dark"><?= e(strtoupper(openai_event_model())); ?></span>
                                </div>

                                <label>
                                    Prompt
                                    <textarea rows="3" placeholder="Team prayer huddle next Wednesday at 7pm in the fellowship hall" data-ai-prompt></textarea>
                                </label>

                                <div class="inline-actions">
                                    <button class="button button-secondary" type="button" data-ai-voice-start>Start Voice</button>
                                    <button class="button button-secondary" type="button" data-ai-voice-stop hidden>Stop</button>
                                    <button class="button button-primary" type="button" data-ai-generate>Create Draft</button>
                                </div>

                                <p class="muted-copy" data-ai-status>Ready to draft a planner event.</p>
                            </div>
                        <?php endif; ?>

                        <form class="form-stack compact-form" method="post" data-ai-event-form data-planner-event-form>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input
                            type="hidden"
                            name="action"
                            value="<?= e($editingEvent ? 'update-event' : 'create-event'); ?>"
                            data-planner-event-action
                            data-create-value="create-event"
                            data-update-value="update-event"
                        >
                        <input type="hidden" name="year" value="<?= e((string) $activeYear); ?>">
                        <input type="hidden" name="event_id" value="<?= e($editingEvent ? (string) $editingEvent['id'] : ''); ?>" data-planner-event-id>

                        <label>
                            Event title
                            <input
                                type="text"
                                name="title"
                                value="<?= e($eventFormData['title']); ?>"
                                placeholder="Family devotion night"
                                required
                                data-ai-field="title"
                                data-default-value=""
                            >
                        </label>

                        <label>
                            Event type
                            <select name="event_type" data-ai-field="event_type" data-default-value="study">
                                <?php foreach (['study', 'prayer', 'service', 'family', 'community', 'goal', 'reminder'] as $eventType): ?>
                                    <option value="<?= e($eventType); ?>" <?= $eventFormData['event_type'] === $eventType ? 'selected' : ''; ?>>
                                        <?= e(ucfirst($eventType)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Date and time
                            <input
                                type="datetime-local"
                                name="event_date"
                                value="<?= e($eventFormData['event_date']); ?>"
                                required
                                data-ai-field="event_date"
                                data-ai-context-field="event_date"
                                data-default-value="<?= e(planner_quick_event_datetime($calendarDate)); ?>"
                            >
                        </label>

                        <label>
                            Notes
                            <textarea name="description" rows="4" placeholder="What should you remember for this event?" data-ai-field="description" data-default-value=""><?= e($eventFormData['description']); ?></textarea>
                        </label>

                            <div class="inline-actions">
                                <button class="button button-primary" type="submit" data-planner-event-submit><?= $editingEvent ? 'Update Event' : 'Add Event'; ?></button>
                                <button class="button button-secondary" type="button" data-community-panel-close="event">Cancel</button>
                            </div>
                        </form>
                    </div>
                </section>

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
                                        <a class="button button-secondary" href="<?= e(planner_build_url($activeYear, $calendarView, $calendarDate, (int) $goal['id'], $editingEventId ?: null)); ?>">Edit Goal</a>
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
                                        <a class="button button-secondary" href="<?= e(planner_build_url($activeYear, $calendarView, $calendarDate, $editingGoalId ?: null, (int) $event['id'])); ?>">Edit Event</a>
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
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
