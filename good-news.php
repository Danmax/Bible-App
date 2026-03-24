<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function good_news_foundation_cards(): array
{
    return [
        [
            'eyebrow' => 'Trust',
            'title' => 'Put your full trust in the Lord',
            'summary' => 'Lay down self-reliance and turn your heart toward the Lord with confidence, surrender, and expectation.',
        ],
        [
            'eyebrow' => 'Salvation',
            'title' => 'Receive the free gift of salvation',
            'summary' => 'Salvation is not something you earn. It is the mercy of God offered through Jesus Christ to everyone who believes.',
        ],
        [
            'eyebrow' => 'Jesus Christ',
            'title' => 'The way, the truth, and the life',
            'summary' => 'The love of God is revealed in His Son. Jesus is the door to life, forgiveness, peace, and a new beginning.',
        ],
    ];
}

function good_news_scripture_path(): array
{
    return [
        [
            'reference' => 'Proverbs 3:5-6',
            'title' => 'Trust the Lord with all your heart',
            'summary' => 'When your understanding is limited, the Lord is still faithful to direct your path.',
        ],
        [
            'reference' => 'John 3:16',
            'title' => 'God loved the world and gave His Son',
            'summary' => 'The gospel begins with the love of God and the gift of His Son for eternal life.',
        ],
        [
            'reference' => 'Ephesians 2:8-9',
            'title' => 'Saved by grace through faith',
            'summary' => 'Salvation is the gift of God, not a reward for human effort or religious performance.',
        ],
        [
            'reference' => 'John 14:6',
            'title' => 'Jesus Christ is the only way',
            'summary' => 'He is the way, the truth, and the life, and He brings us to the Father.',
        ],
    ];
}

function good_news_response_steps(bool $isLoggedIn, string $prayerPageUrl): array
{
    return [
        [
            'label' => 'Believe',
            'title' => 'Believe the gospel personally',
            'summary' => 'Do not leave the message at a distance. Receive Christ by faith and call on the Lord today.',
            'action_label' => 'Read Romans 10',
            'action_url' => app_url('bible.php?q=' . urlencode('Romans 10') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION)),
        ],
        [
            'label' => 'Pray',
            'title' => 'Respond to God with a sincere prayer',
            'summary' => 'Thank God for His love, confess your need, and ask Jesus Christ to lead your life in truth.',
            'action_label' => $isLoggedIn ? 'Open Prayer' : 'Pray and Sign In',
            'action_url' => $prayerPageUrl,
        ],
        [
            'label' => 'Walk',
            'title' => 'Keep walking with the Lord daily',
            'summary' => 'Open Scripture, save what God is showing you, and stay connected to prayer, fellowship, and obedience.',
            'action_label' => 'Open Bible',
            'action_url' => app_url('bible.php'),
        ],
    ];
}

function good_news_guest_encouragement(): array
{
    return [
        'Turn to the Lord in prayer and speak honestly from your heart.',
        'Read the gospel of John and let the words of Jesus stay with you.',
        'Keep coming back to Scripture until trust becomes your daily pattern.',
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
$foundationCards = good_news_foundation_cards();
$scripturePath = good_news_scripture_path();
$currentYear = (int) date('Y');
$prayerPageUrl = app_url($user !== null ? 'prayer.php' : 'login.php');
$responseSteps = good_news_response_steps($user !== null, $prayerPageUrl);
$guestEncouragement = good_news_guest_encouragement();

try {
    $upcomingEvents = fetch_upcoming_events(4);

    if ($user !== null) {
        $recentNotes = fetch_recent_notes((int) $user['id'], 2);
        $recentBookmarks = fetch_recent_bookmarks((int) $user['id'], 2);
        $activeGoals = array_slice(fetch_yearly_goals_for_user((int) $user['id'], $currentYear), 0, 2);
        $prayerEntries = array_slice(fetch_prayer_entries_for_user((int) $user['id'], 4), 0, 2);
    }
} catch (Throwable $exception) {
    $pageError = 'The Good News page is available, but some live content could not be loaded right now.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section good-news-page">
    <div class="container good-news-shell">
        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <section class="good-news-hero">
            <div class="good-news-hero-copy">
                <p class="eyebrow">The Good News</p>
                <h1>Put your trust in the Lord and receive the free gift of salvation through Jesus Christ.</h1>
                <p class="good-news-lead">
                    The heart of this page is simple: believe in the true love of God revealed in His Son.
                    Jesus Christ is the way, the truth, and the life. Turn to Him, trust Him, and walk in the
                    hope only He can give.
                </p>

                <div class="hero-actions">
                    <a class="button button-primary" href="<?= e(app_url('bible.php?q=' . urlencode('John 3:16') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Read John 3:16</a>
                    <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode('John 14:6') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Read John 14:6</a>
                    <a class="button button-secondary" href="<?= e($prayerPageUrl); ?>"><?= $user !== null ? 'Pray Now' : 'Open Prayer' ?></a>
                </div>

                <div class="good-news-anchor-row">
                    <span class="pill">Trust in the Lord</span>
                    <span class="pill">Believe in Jesus Christ</span>
                    <span class="pill">Receive salvation by grace</span>
                </div>
            </div>

            <aside class="good-news-hero-panel">
                <span class="pill pill-dark">Hope for today</span>
                <h2>Jesus Christ is the way, the truth, and the life.</h2>
                <p>
                    God is not asking you to save yourself. He is calling you to trust His Son.
                    The invitation is open right now: believe, receive mercy, and begin a new life with the Lord.
                </p>

                <div class="good-news-hero-scripture">
                    <strong>Start here</strong>
                    <a href="<?= e(app_url('bible.php?q=' . urlencode('Proverbs 3:5-6') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Proverbs 3:5-6</a>
                    <a href="<?= e(app_url('bible.php?q=' . urlencode('Ephesians 2:8-9') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Ephesians 2:8-9</a>
                    <a href="<?= e(app_url('bible.php?q=' . urlencode('Romans 10:9-10') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Romans 10:9-10</a>
                </div>
            </aside>
        </section>

        <section class="good-news-foundation-grid" aria-label="Good News foundations">
            <?php foreach ($foundationCards as $card): ?>
                <article class="good-news-foundation-card">
                    <span class="pill"><?= e($card['eyebrow']); ?></span>
                    <h2><?= e($card['title']); ?></h2>
                    <p><?= e($card['summary']); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="good-news-scripture-band">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Scripture Path</p>
                    <h2>Read the message in Scripture</h2>
                    <p class="muted-copy">These passages move from trust, to grace, to the person of Jesus Christ.</p>
                </div>
            </div>

            <div class="good-news-scripture-grid top-gap-sm">
                <?php foreach ($scripturePath as $passage): ?>
                    <article class="good-news-scripture-card">
                        <span class="pill"><?= e($passage['reference']); ?></span>
                        <h3><?= e($passage['title']); ?></h3>
                        <p><?= e($passage['summary']); ?></p>
                        <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode($passage['reference']) . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Open Passage</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="two-column top-gap">
            <section class="panel good-news-panel-emphasis">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Respond</p>
                        <h2>What to do with the Good News</h2>
                        <p class="muted-copy">Receive it by faith, answer God in prayer, and keep walking in His Word.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php foreach ($responseSteps as $step): ?>
                        <article class="good-news-step-card">
                            <span class="pill"><?= e($step['label']); ?></span>
                            <strong><?= e($step['title']); ?></strong>
                            <p><?= e($step['summary']); ?></p>
                            <a class="button button-secondary" href="<?= e($step['action_url']); ?>"><?= e($step['action_label']); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Daily Walk</p>
                        <h2>Keep growing in the Lord</h2>
                        <p class="muted-copy">The gospel is the beginning of a life of prayer, Scripture, obedience, and fellowship.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php if ($user !== null && ($recentNotes !== [] || $recentBookmarks !== [] || $prayerEntries !== [])): ?>
                        <?php foreach ($recentBookmarks as $bookmark): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill">Saved Verse</span>
                                    <strong><?= e(format_verse_reference($bookmark)); ?></strong>
                                    <span><?= e(truncate_text((string) $bookmark['verse_text'], 115)); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($recentNotes as $note): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill">Study Note</span>
                                    <strong><?= e((string) $note['title']); ?></strong>
                                    <span><?= e(truncate_text((string) $note['content'], 115)); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($prayerEntries as $entry): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill <?= (string) $entry['status'] === 'answered' ? 'pill-dark' : ''; ?>"><?= e(ucfirst((string) $entry['status'])); ?></span>
                                    <strong><?= e((string) $entry['title']); ?></strong>
                                    <?php if (!empty($entry['details'])): ?>
                                        <span><?= e(truncate_text((string) $entry['details'], 115)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($guestEncouragement as $encouragement): ?>
                            <article class="good-news-mini-card">
                                <strong>Stay near to the Word</strong>
                                <p><?= e($encouragement); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="two-column top-gap">
            <section class="panel" id="good-news-events">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Fellowship</p>
                        <h2>Gather with other believers</h2>
                        <p class="muted-copy">Faith grows in Scripture, prayer, worship, and shared life with the body of Christ.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community</a>
                </div>

                <?php if ($upcomingEvents === []): ?>
                    <article class="good-news-mini-card top-gap-sm">
                        <strong>No upcoming events are published yet</strong>
                        <p>Return soon for worship nights, Bible studies, service opportunities, and fellowship gatherings.</p>
                    </article>
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
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Next Step</p>
                        <h2>Build a steady rhythm with God</h2>
                        <p class="muted-copy">Let salvation lead into a life shaped by Scripture, prayer, and faithful habits.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php if ($user !== null && $activeGoals !== []): ?>
                        <?php foreach ($activeGoals as $goal): ?>
                            <?php $progress = calculate_goal_progress_percent($goal); ?>
                            <article class="good-news-mini-card">
                                <span class="pill"><?= e((string) ucfirst((string) $goal['goal_type'])); ?></span>
                                <strong><?= e((string) $goal['goal_title']); ?></strong>
                                <p><?= e((string) $goal['current_value']); ?> / <?= e((string) $goal['target_value']); ?> complete<?= $progress !== null ? ' · ' . e((string) $progress) . '%' : ''; ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="good-news-mini-card">
                            <span class="pill">Begin</span>
                            <strong>Open the Bible and start with the words of Jesus</strong>
                            <p>Read John, save the verses that shape your faith, and return daily with expectation.</p>
                        </article>
                        <article class="good-news-mini-card">
                            <span class="pill">Continue</span>
                            <strong>Keep prayer close to your reading life</strong>
                            <p>Talk to the Lord as you read, ask for understanding, and record what He is teaching you.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
