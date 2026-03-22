<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function good_news_news_items(): array
{
    return [
        [
            'label' => 'Church News',
            'title' => 'Weekend worship focus',
            'summary' => 'Prepare your heart around Romans 15 and invite someone to join the service this week.',
            'action_label' => 'Open Bible',
            'action_url' => app_url('bible.php?q=' . urlencode('Romans 15') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION)),
        ],
        [
            'label' => 'Community Update',
            'title' => 'Shared study rhythm',
            'summary' => 'Use the new Bible reader tools to move verse by verse, switch reading modes, and save what stands out.',
            'action_label' => 'Open Bible',
            'action_url' => app_url('bible.php'),
        ],
        [
            'label' => 'Ministry News',
            'title' => 'Prayer and fellowship emphasis',
            'summary' => 'Gather your requests, celebrate answered prayers, and keep the community connected through the week.',
            'action_label' => 'Open Community',
            'action_url' => app_url('community.php'),
        ],
    ];
}

function good_news_devotionals(): array
{
    return [
        [
            'title' => 'Morning Mercy',
            'reference' => 'Lamentations 3:22-23',
            'summary' => 'Start the day by naming where the Lord has already been faithful and where you need fresh mercy today.',
        ],
        [
            'title' => 'Walk In Wisdom',
            'reference' => 'James 1:5',
            'summary' => 'Ask God for wisdom before decisions, conversations, and the quiet work that no one else sees.',
        ],
        [
            'title' => 'Peace In Practice',
            'reference' => 'Philippians 4:6-7',
            'summary' => 'Turn anxiety into prayer and move one burden at a time into God’s hands.',
        ],
    ];
}

function good_news_prayer_focuses(): array
{
    return [
        'Families and marriages',
        'Students, teachers, and schools',
        'Church leaders and ministry teams',
        'Healing, provision, and encouragement',
    ];
}

function good_news_celebrations(): array
{
    return [
        [
            'label' => 'Celebration',
            'title' => 'Answered prayer moments',
            'summary' => 'Capture what God has done so the community remembers His faithfulness.',
        ],
        [
            'label' => 'Milestone',
            'title' => 'Study goals completed',
            'summary' => 'Celebrate finished reading plans, memory verses, and prayer rhythms that stayed consistent.',
        ],
        [
            'label' => 'Community Joy',
            'title' => 'Gatherings worth sharing',
            'summary' => 'Surface baptisms, testimonies, fellowship nights, and service victories in one place.',
        ],
    ];
}

$pageTitle = 'Good News';
$activePage = 'good-news';
$user = is_logged_in() ? refresh_current_user() : null;
$pageError = null;
$upcomingEvents = [];
$recentNotes = [];
$recentBookmarks = [];
$activeGoals = [];
$prayerEntries = [];
$newsItems = good_news_news_items();
$devotionals = good_news_devotionals();
$prayerFocuses = good_news_prayer_focuses();
$celebrations = good_news_celebrations();
$currentYear = (int) date('Y');
try {
    $upcomingEvents = fetch_upcoming_events(4);

    if ($user !== null) {
        $recentNotes = fetch_recent_notes((int) $user['id'], 3);
        $recentBookmarks = fetch_recent_bookmarks((int) $user['id'], 3);
        $activeGoals = array_slice(fetch_yearly_goals_for_user((int) $user['id'], $currentYear), 0, 3);
        $prayerEntries = fetch_prayer_entries_for_user((int) $user['id'], 6);
    }
} catch (Throwable $exception) {
    $pageError = 'The Good News hub is available, but some live content could not be loaded right now.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Good News Hub</p>
                <h1>One place for church life, study rhythm, and daily encouragement</h1>
                <p>Track what is happening, what to pray, what to read, and what to celebrate without bouncing across the app.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($newsItems)); ?></strong>
                    <span>news items</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) count($upcomingEvents)); ?></strong>
                    <span>community events</span>
                </div>
                <div class="quick-stat">
                    <strong>4</strong>
                    <span>SOAP steps</span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community</a>
                <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="card-grid card-grid-4 top-gap">
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-news">
                <span class="feature-icon">NW</span>
                <h3>News</h3>
                <p>Ministry updates, weekly focus, and what the church should know right now.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-events">
                <span class="feature-icon">EV</span>
                <h3>Community Events</h3>
                <p>Upcoming gatherings, studies, services, and fellowship moments.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-devotionals">
                <span class="feature-icon">DV</span>
                <h3>Devotionals</h3>
                <p>Short prompts to keep your reading connected to daily life.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-soap">
                <span class="feature-icon">SP</span>
                <h3>SOAP</h3>
                <p>Scripture, observation, application, and prayer in one repeatable flow.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="<?= e(app_url('prayer.php')); ?>">
                <span class="feature-icon">PR</span>
                <h3>Prayer Request</h3>
                <p>Keep current burdens visible and move requests into prayer quickly.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-plans">
                <span class="feature-icon">PL</span>
                <h3>Plans</h3>
                <p>Reading goals, planner rhythms, and next study commitments.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-feed">
                <span class="feature-icon">FD</span>
                <h3>News Feed</h3>
                <p>Recent study activity, saved verses, and community movement at a glance.</p>
            </a>
            <a class="dashboard-card good-news-tile good-news-tile-link" href="#good-news-celebrations">
                <span class="feature-icon">CE</span>
                <h3>Celebrations</h3>
                <p>Answered prayers, milestones, and stories worth sharing back to the church.</p>
            </a>
        </div>

        <div class="two-column top-gap">
            <div class="panel" id="good-news-news">
                <div class="panel-heading">
                    <div>
                        <h2>News</h2>
                        <p class="muted-copy">Featured ministry updates and app-wide encouragement.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php foreach ($newsItems as $item): ?>
                        <article class="list-card list-card-block">
                            <div>
                                <span class="pill"><?= e($item['label']); ?></span>
                                <strong><?= e($item['title']); ?></strong>
                                <span><?= e($item['summary']); ?></span>
                            </div>
                            <a class="button button-secondary" href="<?= e($item['action_url']); ?>"><?= e($item['action_label']); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel" id="good-news-events">
                <div class="panel-heading">
                    <div>
                        <h2>Community events</h2>
                        <p class="muted-copy">What is coming up next for the shared church calendar.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community</a>
                </div>

                <?php if ($upcomingEvents === []): ?>
                    <p class="empty-state">No published events are scheduled yet.</p>
                <?php else: ?>
                    <div class="stack-list top-gap-sm">
                        <?php foreach ($upcomingEvents as $event): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill"><?= e((string) ($event['category_label'] ?? 'Community')); ?></span>
                                    <strong><?= e((string) $event['title']); ?></strong>
                                    <span><?= e(truncate_text((string) $event['description'], 120)); ?></span>
                                </div>
                                <div class="inline-actions">
                                    <span class="pill pill-dark"><?= e(format_event_date((string) $event['start_at'])); ?></span>
                                    <a class="button button-secondary" href="<?= e(app_url('community-event-calendar.php?event_id=' . (int) $event['id'])); ?>">Add to Calendar</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="two-column top-gap">
            <div class="panel" id="good-news-devotionals">
                <div class="panel-heading">
                    <div>
                        <h2>Devotionals</h2>
                        <p class="muted-copy">Short reading prompts for steady daily momentum.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php foreach ($devotionals as $devotional): ?>
                        <article class="good-news-spotlight">
                            <span class="pill"><?= e($devotional['reference']); ?></span>
                            <strong><?= e($devotional['title']); ?></strong>
                            <p><?= e($devotional['summary']); ?></p>
                            <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode($devotional['reference']) . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Read Passage</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel" id="good-news-soap">
                <div class="panel-heading">
                    <div>
                        <h2>SOAP</h2>
                        <p class="muted-copy">A simple structure for personal study and reflection.</p>
                    </div>
                </div>

                <div class="soap-steps top-gap-sm">
                    <article class="good-news-spotlight">
                        <span class="pill">S</span>
                        <strong>Scripture</strong>
                        <p>Read the passage slowly and name the verse that stands out most clearly.</p>
                    </article>
                    <article class="good-news-spotlight">
                        <span class="pill">O</span>
                        <strong>Observation</strong>
                        <p>Write what the passage is actually saying before jumping to your interpretation.</p>
                    </article>
                    <article class="good-news-spotlight">
                        <span class="pill">A</span>
                        <strong>Application</strong>
                        <p>Decide what needs to change in your thinking, schedule, or next response today.</p>
                    </article>
                    <article class="good-news-spotlight">
                        <span class="pill">P</span>
                        <strong>Prayer</strong>
                        <p>Turn the truth you just read into a direct prayer back to God.</p>
                    </article>
                </div>
            </div>
        </div>

        <div class="two-column top-gap">
            <div class="panel" id="good-news-prayer">
                <div class="panel-heading">
                    <div>
                        <h2>Prayer request</h2>
                        <p class="muted-copy">Open the full prayer request page to save burdens, speak drafts, and track answered prayer.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url($user !== null ? 'prayer.php' : 'login.php')); ?>"><?= $user !== null ? 'Open Prayer' : 'Sign In'; ?></a>
                </div>

                <?php if ($user !== null): ?>
                    <div class="stack-list top-gap-sm">
                        <?php if ($prayerEntries === []): ?>
                            <article class="good-news-spotlight">
                                <span class="pill">Prayer</span>
                                <strong>Your prayer requests live on the dedicated prayer page</strong>
                                <p>Open Prayer to speak a request, save it, and move it between active and answered.</p>
                                <a class="button button-secondary" href="<?= e(app_url('prayer.php')); ?>">Open Prayer</a>
                            </article>
                        <?php else: ?>
                            <?php foreach (array_slice($prayerEntries, 0, 3) as $entry): ?>
                                <article class="list-card list-card-block">
                                    <div>
                                        <span class="pill <?= (string) $entry['status'] === 'answered' ? 'pill-dark' : ''; ?>"><?= e(ucfirst((string) $entry['status'])); ?></span>
                                        <strong><?= e((string) $entry['title']); ?></strong>
                                        <?php if (!empty($entry['details'])): ?>
                                            <span><?= e(truncate_text((string) $entry['details'], 150)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <a class="button button-secondary" href="<?= e(app_url('prayer.php')); ?>">Manage Prayer Requests</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="stack-list top-gap-sm">
                        <?php foreach ($prayerFocuses as $focus): ?>
                            <article class="list-card">
                                <div>
                                    <strong><?= e($focus); ?></strong>
                                    <span>Bring this before God and record updates or answers as they come.</span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel" id="good-news-plans">
                <div class="panel-heading">
                    <div>
                        <h2>Plans</h2>
                        <p class="muted-copy">Personal reading and planner rhythm for <?= e((string) $currentYear); ?>.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
                </div>

                <?php if ($user !== null && $activeGoals !== []): ?>
                    <div class="stack-list top-gap-sm">
                        <?php foreach ($activeGoals as $goal): ?>
                            <?php $progress = calculate_goal_progress_percent($goal); ?>
                            <article class="good-news-spotlight">
                                <span class="pill"><?= e((string) ucfirst((string) $goal['goal_type'])); ?></span>
                                <strong><?= e((string) $goal['goal_title']); ?></strong>
                                <p><?= e((string) $goal['current_value']); ?> / <?= e((string) $goal['target_value']); ?> complete<?= $progress !== null ? ' · ' . e((string) $progress) . '%' : ''; ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="good-news-spotlight top-gap-sm">
                        <span class="pill">Plans</span>
                        <strong>Build your next reading rhythm</strong>
                        <p>Set a yearly goal, add planner events, and connect your study schedule to real dates.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="two-column top-gap">
            <div class="panel" id="good-news-feed">
                <div class="panel-heading">
                    <div>
                        <h2>News feed</h2>
                        <p class="muted-copy">Recent personal study movement from across your account.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php if ($user !== null && ($recentNotes !== [] || $recentBookmarks !== [])): ?>
                        <?php foreach ($recentNotes as $note): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill">Note</span>
                                    <strong><?= e((string) $note['title']); ?></strong>
                                    <span><?= e(truncate_text((string) $note['content'], 110)); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php foreach ($recentBookmarks as $bookmark): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill">Saved Verse</span>
                                    <strong><?= e(format_verse_reference($bookmark)); ?></strong>
                                    <span><?= e(truncate_text((string) $bookmark['verse_text'], 110)); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="good-news-spotlight">
                            <span class="pill">Feed</span>
                            <strong>Your next activity will show up here</strong>
                            <p>Save verses, write notes, and join events to build a living feed of study and community movement.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel" id="good-news-celebrations">
                <div class="panel-heading">
                    <div>
                        <h2>Celebrations</h2>
                        <p class="muted-copy">Joy, answered prayer, and milestones worth bringing forward.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php foreach ($celebrations as $celebration): ?>
                        <article class="good-news-spotlight celebration-card">
                            <span class="pill"><?= e($celebration['label']); ?></span>
                            <strong><?= e($celebration['title']); ?></strong>
                            <p><?= e($celebration['summary']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
