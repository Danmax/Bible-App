<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$activePage = 'home';
$backgroundSeed = home_daily_seed('home-background');
$verseSeed = home_daily_verse_index();
$dailyBackgrounds = home_daily_backgrounds();
$dailyBackground = $dailyBackgrounds[$backgroundSeed % count($dailyBackgrounds)];
$dailyVerse = home_daily_verse_payload(APP_DEFAULT_TRANSLATION, $verseSeed);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow">Good News Bible</p>
            <h1>A calmer place to read, save, and live Scripture.</h1>
            <p class="hero-copy">
                Build a daily rhythm around the Bible with reading, prayer, saved verses, notes, and community gatherings in one simple space.
            </p>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('register.php')); ?>">Start Free</a>
                <a class="button button-secondary" href="<?= e(app_url('bible.php')); ?>">Read the Bible</a>
            </div>

            <nav class="home-quick-access" aria-label="Quick access">
                <a class="home-quick-tile" href="<?= e(app_url('bible.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    Bible
                </a>
                <a class="home-quick-tile" href="<?= e(app_url('library.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                    Library
                </a>
                <a class="home-quick-tile" href="<?= e(app_url('studies.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M8 7h8"/><path d="M8 11h8"/></svg>
                    Plans
                </a>
                <a class="home-quick-tile" href="<?= e(app_url('community.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Community
                </a>
            </nav>
        </div>

        <aside class="showcase-card showcase-card-landscape showcase-card-daily">
            <div class="showcase-landscape" aria-hidden="true">
                <img
                    class="showcase-landscape-img"
                    src="<?= e((string) $dailyBackground['src']); ?>"
                    alt=""
                    loading="eager"
                    draggable="false"
                    style="object-position: <?= e((string) $dailyBackground['position']); ?>"
                >
                <div class="showcase-landscape-overlay"></div>
                <div class="showcase-abstract-lines"></div>
            </div>
            <div class="showcase-top">
                <span class="pill showcase-pill">Verse Of The Day</span>
                <span class="pill showcase-pill"><?= e((string) $dailyVerse['translation']); ?></span>
            </div>
            <div class="showcase-daily-copy">
                <p class="showcase-kicker"><?= e((string) $dailyVerse['kicker']); ?></p>
                <blockquote class="showcase-quote">
                    "<?= e((string) $dailyVerse['text']); ?>"
                </blockquote>
                <p class="showcase-reference"><?= e((string) $dailyVerse['reference']); ?></p>
                <p class="showcase-supporting-copy"><?= e((string) $dailyVerse['message']); ?></p>
                <div class="showcase-actions">
                    <a class="mini-card showcase-action" href="<?= e(app_url('bible.php?q=' . urlencode((string) $dailyVerse['query']) . '&translation=' . urlencode((string) $dailyVerse['translation']))); ?>">Open Verse</a>
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- ── Core features ─────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Start Here</p>
            <h2>The essentials for a steady Bible rhythm</h2>
        </div>

        <div class="card-grid card-grid-4 home-essential-grid">

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </span>
                <h3>Bible Reader</h3>
                <p>Browse books, chapters, and verses with space to search, save, and reflect — with multiple translations.</p>
                <a class="button button-secondary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                </span>
                <h3>Library</h3>
                <p>Bookmark passages, add notes and tags, and keep meaningful Scripture easy to return to.</p>
                <a class="button button-secondary" href="<?= e(app_url('library.php')); ?>">Open Library</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                </span>
                <h3>Plans</h3>
                <p>Follow Bible reading plans, devotionals, and group studies that keep daily growth focused.</p>
                <a class="button button-secondary" href="<?= e(app_url('studies.php')); ?>">Explore Plans</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <h3>Community</h3>
                <p>Follow services, Bible studies, Zoom calls, meals, and celebrations in one shared feed.</p>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">See Events</a>
            </article>

        </div>
    </div>
</section>

<!-- ── Resources ─────────────────────────────────────────────────────── -->
<section class="section section-contrast">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Resources</p>
            <h2>Helpful paths beside the text</h2>
        </div>

        <div class="home-new-strip">

            <a class="home-new-card" href="<?= e(app_url('good-news.php')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <p class="eyebrow">Resource Path</p>
                <h3>Gospel Path</h3>
                <p>Walk through key passages about grace, salvation, prayer, and following Jesus.</p>
            </a>

            <a class="home-new-card" href="<?= e(app_url('dictionary.php')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
                </div>
                <p class="eyebrow">Study Help</p>
                <h3>Dictionary</h3>
                <p>Look up people, places, themes, and terms while you study a passage.</p>
            </a>

            <a class="home-new-card" href="<?= e(app_url('library.php?view=sermons')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                </div>
                <p class="eyebrow">Teaching</p>
                <h3>Sermon Docs</h3>
                <p>Keep longer teaching notes, reference groups, and shareable study documents in your Library.</p>
            </a>

        </div>
    </div>
</section>

<!-- ── How it works ──────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Getting Started</p>
            <h2>Up and running in three steps</h2>
        </div>

        <div class="home-steps">
            <div class="home-step">
                <h3>Create a free account</h3>
                <p>Sign up in seconds. No payment required. Your data stays private and stays yours.</p>
            </div>
            <div class="home-step">
                <h3>Find your verses</h3>
                <p>Search or browse the Bible, save passages with notes and tags, and build your personal library.</p>
            </div>
            <div class="home-step">
                <h3>Join your community</h3>
                <p>Follow events, add friends, share verses, and keep your faith rhythms visible alongside the people you care about.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php

function home_daily_verse_moment(): string
{
    return (int) home_daily_now()->format('G') >= 18 ? 'evening' : 'day';
}

function home_daily_seed(string $namespace): int
{
    return abs((int) crc32(home_daily_now()->format('Y-m-d') . '|' . $namespace));
}

function home_daily_verse_index(): int
{
    return (int) home_daily_now()->format('z') % 365;
}

function home_daily_now(): DateTimeImmutable
{
    static $now = null;

    if ($now instanceof DateTimeImmutable) {
        return $now;
    }

    $configuredTimezone = trim((string) (getenv('APP_TIMEZONE') ?: 'America/New_York'));

    try {
        $timezone = new DateTimeZone($configuredTimezone);
    } catch (Throwable $exception) {
        $timezone = new DateTimeZone('America/New_York');
    }

    $now = new DateTimeImmutable('now', $timezone);

    return $now;
}

function home_daily_backgrounds(): array
{
    return [
        [
            'src' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?w=1200&q=80&auto=format&fit=crop',
            'position' => 'center 45%',
        ],
        [
            'src' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?w=1200&q=80&auto=format&fit=crop',
            'position' => 'center 30%',
        ],
        [
            'src' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200&q=80&auto=format&fit=crop',
            'position' => 'center 60%',
        ],
        [
            'src' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=1200&q=80&auto=format&fit=crop',
            'position' => 'center 45%',
        ],
        [
            'src' => 'https://images.unsplash.com/photo-1511497584788-876760111969?w=1200&q=80&auto=format&fit=crop',
            'position' => 'center 50%',
        ],
        [
            'src' => 'https://images.unsplash.com/photo-1519125323398-675f0ddb6308?w=1200&q=80&auto=format&fit=crop',
            'position' => 'center 50%',
        ],
    ];
}

function home_daily_verse_payload(string $translation, int $seed): array
{
    $isEvening = home_daily_verse_moment() === 'evening';
    $fallbacks = home_daily_emergency_verses($isEvening);
    $selected = $fallbacks[$seed % count($fallbacks)];
    $payload = $selected + ['translation' => $translation];

    try {
        $dailyVerse = home_fetch_daily_verse_from_library($translation, $seed);

        if ($dailyVerse !== null) {
            return $dailyVerse;
        }

        $books = fetch_books();
        $reference = parse_reference_query((string) $selected['query'], $books);

        if ($reference !== null) {
            $results = fetch_reference_verses($reference, $translation);
            $verses = (array) ($results['results'] ?? []);

            if ($verses !== []) {
                $text = trim(implode(' ', array_map(
                    static fn(array $verse): string => trim((string) ($verse['verse_text'] ?? '')),
                    $verses
                )));

                if ($text !== '') {
                    $payload['text'] = truncate_text($text, 220);
                    $payload['reference'] = (string) ($results['heading'] ?? $selected['reference']);
                    $payload['translation'] = (string) (($verses[0]['translation'] ?? $translation));
                }
            }
        }
    } catch (Throwable $exception) {
        // Keep curated fallback content for the home page when the database is unavailable.
    }

    return $payload;
}

function home_fetch_daily_verse_from_library(string $translation, int $dayIndex): ?array
{
    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.translation = :translation
            AND CHAR_LENGTH(verses.verse_text) BETWEEN 45 AND 220
            AND verses.book_id IN (
                19, 20, 23, 24, 25, 33,
                40, 41, 42, 43, 44, 45, 46, 47,
                48, 49, 50, 51, 52, 53, 54, 55,
                56, 58, 59, 60, 61, 62, 65
            )
        ORDER BY MOD(
            ((verses.book_id * 1000000) + (verses.chapter_number * 1000) + verses.verse_number) * 1103515245 + 12345,
            2147483647
        ) ASC
        LIMIT 365'
    );
    $statement->execute(['translation' => $translation]);
    $verses = $statement->fetchAll();

    if ($verses === []) {
        return null;
    }

    $verse = $verses[$dayIndex % count($verses)];
    $bookName = (string) ($verse['book_name'] ?? 'Scripture');
    $chapter = (int) ($verse['chapter_number'] ?? 0);
    $verseNumber = (int) ($verse['verse_number'] ?? 0);
    $reference = sprintf('%s %d:%d', $bookName, $chapter, $verseNumber);

    return [
        'query' => $reference,
        'reference' => $reference,
        'text' => truncate_text(trim((string) ($verse['verse_text'] ?? '')), 220),
        'kicker' => home_daily_verse_kicker(),
        'message' => home_daily_verse_message($dayIndex),
        'translation' => (string) ($verse['translation'] ?? $translation),
    ];
}

function home_daily_verse_kicker(): string
{
    return home_daily_verse_moment() === 'evening' ? 'For tonight' : 'For today';
}

function home_daily_verse_message(int $dayIndex): string
{
    $messages = [
        'Let Scripture steady the next step in front of you.',
        'Carry this promise into the ordinary parts of the day.',
        'Let this word shape your attention before the noise does.',
        'Return to this truth when the day asks for patience.',
        'Keep this verse close as a prayer and a practice.',
        'Let God\'s Word give language to faith, hope, and obedience.',
        'Receive this passage slowly and answer it with trust.',
        'Let this truth interrupt worry and call you back to peace.',
        'Build today around what God has already spoken.',
        'Hold this verse with humility, courage, and expectation.',
        'Let the Word of God renew your mind and direct your path.',
        'Make room for this promise to become obedience today.',
    ];

    return $messages[$dayIndex % count($messages)];
}

function home_daily_emergency_verses(bool $isEvening): array
{
    return [
        [
            'query' => 'Proverbs 3:5-6',
            'reference' => 'Proverbs 3:5-6',
            'text' => 'Trust in the Lord with all your heart, and do not lean on your own understanding.',
            'kicker' => $isEvening ? 'For tonight' : 'For today',
            'message' => 'Let the day start from surrender instead of strain.',
        ],
        [
            'query' => 'Isaiah 40:31',
            'reference' => 'Isaiah 40:31',
            'text' => 'Those who hope in the Lord will renew their strength.',
            'kicker' => $isEvening ? 'Evening strength' : 'For today',
            'message' => 'Wait with expectancy and keep moving in quiet confidence.',
        ],
        [
            'query' => 'Philippians 4:6-7',
            'reference' => 'Philippians 4:6-7',
            'text' => 'Do not be anxious about anything, but in everything by prayer and petition present your requests to God.',
            'kicker' => $isEvening ? 'Evening peace' : 'For today',
            'message' => 'Turn pressure into prayer and let peace guard the mind.',
        ],
        [
            'query' => 'Romans 15:13',
            'reference' => 'Romans 15:13',
            'text' => 'May the God of hope fill you with all joy and peace as you trust in Him.',
            'kicker' => $isEvening ? 'Evening hope' : 'For today',
            'message' => 'Hope grows where trust stays rooted in God.',
        ],
        [
            'query' => 'Joshua 1:9',
            'reference' => 'Joshua 1:9',
            'text' => 'Be strong and courageous. Do not be afraid; do not be discouraged, for the Lord your God will be with you.',
            'kicker' => $isEvening ? 'For the evening' : 'For today',
            'message' => 'Walk forward with courage that comes from God\'s presence.',
        ],
        [
            'query' => 'Lamentations 3:22-23',
            'reference' => 'Lamentations 3:22-23',
            'text' => 'Because of the Lord\'s faithful love we do not perish, for His mercies never end. They are new every morning.',
            'kicker' => $isEvening ? 'Before rest' : 'For today',
            'message' => 'Start again with mercy that has already met the morning.',
        ],
    ];
}
