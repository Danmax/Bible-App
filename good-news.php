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
    $stopWords = array_fill_keys([
        'the', 'and', 'for', 'that', 'with', 'from', 'into', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'any', 'his', 'her', 'him', 'our', 'out',
        'who', 'what', 'when', 'where', 'why', 'how', 'this', 'these', 'those', 'will', 'shall',
        'would', 'could', 'should', 'about', 'over', 'under', 'through', 'after', 'before',
        'because', 'been', 'being', 'also', 'unto', 'upon', 'they', 'them', 'then', 'than',
        'said', 'says', 'say', 'did', 'does', 'doing', 'very', 'more', 'most', 'much', 'many',
        'each', 'every', 'some', 'such', 'just', 'like', 'make', 'made', 'again', 'still',
        'here', 'there', 'only', 'thou', 'thee', 'thy', 'thine', 'hast', 'hath', 'dost', 'doth',
    ], true);
    $matched = preg_match_all("/[\p{L}][\p{L}'-]*/u", $text, $matches);

    if ($matched === false) {
        return [];
    }

    $counts = [];

    foreach ($matches[0] ?? [] as $token) {
        $normalized = trim(mb_strtolower((string) $token), "'- ");

        if (mb_strlen($normalized) < 4 || isset($stopWords[$normalized])) {
            continue;
        }

        $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
    }

    arsort($counts);

    return array_slice(array_keys($counts), 0, $limit);
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
$recentNotes = [];
$recentBookmarks = [];
$prayerEntries = [];
$foundationCards = good_news_foundation_cards();
$scripturePath = good_news_scripture_path();
$prayerPageUrl = app_url($user !== null ? 'library.php?view=prayer' : 'login.php');
$responseSteps = good_news_response_steps($user !== null, $prayerPageUrl);
$guestEncouragement = good_news_guest_encouragement();

try {
    if ($user !== null) {
        $recentNotes = fetch_recent_notes((int) $user['id'], 2);
        $recentBookmarks = fetch_recent_bookmarks((int) $user['id'], 2);
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
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
