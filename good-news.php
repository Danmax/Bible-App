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

function good_news_resource_terms(string $text, int $limit = 3): array
{
    return scripture_focus_terms($text, $limit, 4);
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
$dashboardFeed = [];
$foundationCards = good_news_foundation_cards();
$scripturePath = good_news_scripture_path();
$prayerPageUrl = app_url($user !== null ? 'library.php?view=prayer' : 'login.php');
$responseSteps = good_news_response_steps($user !== null, $prayerPageUrl);
$guestEncouragement = good_news_guest_encouragement();

try {
    if ($user !== null) {
        $dashboardFeed = fetch_dashboard_feed((int) $user['id']);
    }
} catch (Throwable $exception) {
    $pageError = 'Your dashboard could not be loaded right now. Please try again in a moment.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section good-news-page">
    <div class="container good-news-shell">
        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if ($user !== null): ?>
            <?php $firstName = trim(explode(' ', trim((string) ($user['name'] ?? 'Friend')))[0] ?? 'Friend'); ?>
            <header class="good-news-dashboard-header">
                <div>
                    <p class="eyebrow">Your Good News</p>
                    <h1>Welcome back, <?= e($firstName !== '' ? $firstName : 'Friend'); ?>.</h1>
                    <p>Here is what is happening in your study life and community.</p>
                </div>
                <nav class="good-news-dashboard-actions" aria-label="Dashboard shortcuts">
                    <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
                    <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">View Planner</a>
                </nav>
            </header>

            <?php if ($dashboardFeed === []): ?>
                <section class="good-news-feed-empty" aria-labelledby="quiet-feed-heading">
                    <span class="good-news-feed-empty-icon" aria-hidden="true">☀️</span>
                    <div>
                        <p class="eyebrow">A fresh start</p>
                        <h2 id="quiet-feed-heading">Your feed is quiet for now.</h2>
                        <p>Try bookmarking a verse, adding something to your planner, or joining a community event.</p>
                    </div>
                    <div class="inline-actions">
                        <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Find a Verse</a>
                        <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Explore Events</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="good-news-dashboard-feed" aria-labelledby="dashboard-feed-heading">
                    <div class="good-news-feed-heading">
                        <div>
                            <p class="eyebrow">For You</p>
                            <h2 id="dashboard-feed-heading">Your latest updates</h2>
                        </div>
                        <span class="pill"><?= e((string) count($dashboardFeed)); ?> update<?= count($dashboardFeed) === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="good-news-feed-list">
                        <?php foreach ($dashboardFeed as $item): ?>
                            <?php
                            $data = is_array($item['data'] ?? null) ? $item['data'] : [];
                            $type = (string) ($item['type'] ?? '');
                            switch ($type):
                                case 'planner_event':
                            ?>
                                <article class="good-news-feed-card good-news-feed-card--planner">
                                    <div class="good-news-feed-icon" aria-hidden="true">📅</div>
                                    <div class="good-news-feed-content">
                                        <div class="good-news-feed-meta">
                                            <span class="pill">Today</span>
                                            <time datetime="<?= e((string) $data['event_date']); ?>"><?= e(date('g:i A', strtotime((string) $data['event_date']))); ?></time>
                                        </div>
                                        <h3><?= e((string) $data['title']); ?></h3>
                                        <p>You have this event on today’s personal planner.</p>
                                    </div>
                                    <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
                                </article>
                            <?php
                                    break;
                                case 'community_event':
                            ?>
                                <article class="good-news-feed-card good-news-feed-card--community">
                                    <div class="good-news-feed-icon" aria-hidden="true">🤝</div>
                                    <div class="good-news-feed-content">
                                        <div class="good-news-feed-meta">
                                            <span class="pill"><?= e(ucfirst((string) $data['response'])); ?></span>
                                            <time datetime="<?= e((string) $data['start_at']); ?>"><?= e(date('D, M j · g:i A', strtotime((string) $data['start_at']))); ?></time>
                                        </div>
                                        <h3><?= e((string) $data['title']); ?></h3>
                                        <p>
                                            This community event is coming up in the next seven days.
                                            <?php if ((string) ($data['location_name'] ?? '') !== ''): ?>
                                                <span class="good-news-feed-detail">At <?= e((string) $data['location_name']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">View Event</a>
                                </article>
                            <?php
                                    break;
                                case 'friend_request':
                            ?>
                                <article class="good-news-feed-card good-news-feed-card--friend">
                                    <div class="good-news-feed-icon" aria-hidden="true">👋</div>
                                    <div class="good-news-feed-content">
                                        <div class="good-news-feed-meta">
                                            <span class="pill">Friend request</span>
                                            <time datetime="<?= e((string) $data['created_at']); ?>"><?= e(date('M j, g:i A', (int) $item['timestamp'])); ?></time>
                                        </div>
                                        <h3><?= e((string) $data['sender_name']); ?> wants to connect.</h3>
                                        <p>Review the request and grow your study community.</p>
                                    </div>
                                    <a class="button button-primary" href="<?= e(app_url('friends.php')); ?>">Review Request</a>
                                </article>
                            <?php
                                    break;
                                case 'friend_bookmark':
                                    $verseReference = sprintf(
                                        '%s %d:%d',
                                        (string) $data['book_name'],
                                        (int) $data['chapter_number'],
                                        (int) $data['verse_number']
                                    );
                                    $verseUrl = app_url(
                                        'bible.php?translation=' . urlencode((string) $data['translation'])
                                        . '&book_id=' . (int) $data['book_id']
                                        . '&chapter=' . (int) $data['chapter_number']
                                        . '&verse=' . (int) $data['verse_number']
                                    );
                            ?>
                                <article class="good-news-feed-card good-news-feed-card--bookmark">
                                    <div class="good-news-feed-icon" aria-hidden="true">🔖</div>
                                    <div class="good-news-feed-content">
                                        <div class="good-news-feed-meta">
                                            <span class="pill">Friend activity</span>
                                            <time datetime="<?= e((string) $data['created_at']); ?>"><?= e(date('M j, g:i A', (int) $item['timestamp'])); ?></time>
                                        </div>
                                        <h3><?= e((string) $data['friend_name']); ?> bookmarked <?= e($verseReference); ?>.</h3>
                                        <p>“<?= e(truncate_text((string) $data['verse_text'], 145)); ?>”</p>
                                    </div>
                                    <a class="button button-secondary" href="<?= e($verseUrl); ?>">Read Verse</a>
                                </article>
                            <?php
                                    break;
                            endswitch;
                            ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: ?>
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
                    <a class="button button-primary" href="<?= e(scripture_reference_reader_url('John 3:16')); ?>">Read John 3:16</a>
                    <a class="button button-secondary" href="<?= e(scripture_reference_reader_url('John 14:6')); ?>">Read John 14:6</a>
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
                    <a href="<?= e(scripture_reference_reader_url('Proverbs 3:5-6')); ?>">Proverbs 3:5-6</a>
                    <a href="<?= e(scripture_reference_reader_url('Ephesians 2:8-9')); ?>">Ephesians 2:8-9</a>
                    <a href="<?= e(scripture_reference_reader_url('Romans 10:9-10')); ?>">Romans 10:9-10</a>
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
                    <?php
                    $passageReference = (string) $passage['reference'];
                    $passageTerms = good_news_resource_terms((string) $passage['title'] . ' ' . (string) $passage['summary']);
                    ?>
                    <article class="good-news-scripture-card">
                        <span class="pill"><?= e($passageReference); ?></span>
                        <h3><?= e($passage['title']); ?></h3>
                        <p><?= e($passage['summary']); ?></p>
                        <nav class="good-news-resource-row" aria-label="<?= e($passageReference); ?> resources">
                            <a href="<?= e(scripture_reference_reader_url($passageReference)); ?>">Read Passage</a>
                            <a href="<?= e(app_url('dictionary.php?q=' . urlencode($passageReference))); ?>">Reference</a>
                            <?php foreach ($passageTerms as $term): ?>
                                <a href="<?= e(app_url('dictionary.php?q=' . urlencode($term))); ?>"><?= e(mb_convert_case($term, MB_CASE_TITLE, 'UTF-8')); ?></a>
                            <?php endforeach; ?>
                        </nav>
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
                                <div class="inline-actions top-gap-sm">
                                    <a class="button button-secondary" href="<?= e(app_url('bible.php?translation=' . urlencode((string) $bookmark['translation']) . '&book_id=' . (int) $bookmark['book_id'] . '&chapter=' . (int) $bookmark['chapter_number'] . '&verse=' . (int) $bookmark['verse_number'])); ?>">Open</a>
                                    <a class="button button-secondary" href="<?= e(app_url('library.php?view=saved')); ?>">Library</a>
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
                                <div class="inline-actions top-gap-sm">
                                    <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes&edit_note=' . (int) $note['id'])); ?>">Open</a>
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
                                <div class="inline-actions top-gap-sm">
                                    <a class="button button-secondary" href="<?= e(app_url('library.php?view=prayer')); ?>">Open Prayer</a>
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
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
