<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$activePage = 'home';
$backgroundSeed = home_daily_seed('home-background');
$messageSeed = home_daily_seed('home-messages');
$verseSeed = home_daily_seed('home-verse');
$dailyBackgrounds = home_daily_backgrounds();
$dailyBackground = $dailyBackgrounds[$backgroundSeed % count($dailyBackgrounds)];
$dailyMessages = home_daily_rotating_items(home_curated_home_messages(), $messageSeed, 3);
$dailyVerse = home_daily_verse_payload(APP_DEFAULT_TRANSLATION, $verseSeed);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow">Warm, bold, mobile-first Bible app</p>
            <h1>Study the Word.<br>Strengthen your community.</h1>
            <p class="hero-copy">
                Scripture study, sermon notes, personal planning, and church-centered events — all in one app built for phones first.
            </p>

            <div class="hero-verse-block">
                <blockquote>"<?= e((string) $dailyVerse['text']); ?>"</blockquote>
                <cite><?= e((string) $dailyVerse['reference']); ?> &middot; <?= e((string) $dailyVerse['translation']); ?></cite>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('register.php')); ?>">Start Free</a>
                <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode((string) $dailyVerse['query']) . '&translation=' . urlencode((string) $dailyVerse['translation']))); ?>">Open Today's Verse</a>
            </div>

            <!-- Quick access tiles -->
            <nav class="home-quick-access" aria-label="Quick access">
                <a class="home-quick-tile" href="<?= e(app_url('bible.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    Bible
                </a>
                <a class="home-quick-tile" href="<?= e(app_url('bookmarks.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                    Verses
                </a>
                <a class="home-quick-tile" href="<?= e(app_url('community.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Community
                </a>
                <a class="home-quick-tile" href="<?= e(app_url('sermon-notes.php')); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Notes
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
            <div class="showcase-daily-grid">
                <div class="showcase-daily-copy">
                    <p class="showcase-kicker"><?= e((string) $dailyVerse['kicker']); ?></p>
                    <blockquote class="showcase-quote">
                        "<?= e((string) $dailyVerse['text']); ?>"
                    </blockquote>
                    <p class="showcase-reference"><?= e((string) $dailyVerse['reference']); ?></p>
                    <p class="showcase-supporting-copy"><?= e((string) $dailyVerse['message']); ?></p>
                    <div class="showcase-actions">
                        <a class="mini-card showcase-action" href="<?= e(app_url('bible.php?q=' . urlencode((string) $dailyVerse['query']) . '&translation=' . urlencode((string) $dailyVerse['translation']))); ?>">Open Verse</a>
                        <a class="mini-card showcase-action" href="<?= e(app_url('bookmarks.php')); ?>">Saved Verses</a>
                        <a class="mini-card showcase-action" href="<?= e(app_url('sermon-notes.php')); ?>">Sermon Notes</a>
                    </div>
                </div>

                <div class="showcase-bubble-stack">
                    <?php foreach ($dailyMessages as $message): ?>
                        <a class="showcase-message-bubble" href="<?= e(app_url((string) $message['href'])); ?>">
                            <span class="showcase-message-label"><?= e((string) $message['eyebrow']); ?></span>
                            <strong><?= e((string) $message['title']); ?></strong>
                            <span><?= e((string) $message['copy']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- ── Core features ─────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Everything You Need</p>
            <h2>Scripture, study, community — in one place</h2>
        </div>

        <div class="card-grid card-grid-3">

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
                <h3>Saved Verses</h3>
                <p>Bookmark passages, add personal notes and tags, and build verse collections around prayer, family, and strength.</p>
                <a class="button button-secondary" href="<?= e(app_url('bookmarks.php')); ?>">View Bookmarks</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </span>
                <h3>Yearly Planner</h3>
                <p>Set reading goals, plan events, and keep spiritual routines visible every month with AI-assisted planning.</p>
                <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <h3>Community</h3>
                <p>Share services, Bible studies, Zoom calls, pot lucks, and celebrations — all in a live community feed.</p>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">See Events</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                <h3>Sermon Notes</h3>
                <p>Write verse-based reflections with AI-assisted reference suggestions, summaries, and a storm board for ideas.</p>
                <a class="button button-secondary" href="<?= e(app_url('sermon-notes.php')); ?>">Start a Note</a>
            </article>

            <article class="feature-card feature-card-new">
                <span class="feature-icon-svg" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                </span>
                <h3>Prayer Rhythm</h3>
                <p>Organize requests, set reminders, and keep community prayer focus visible throughout the week.</p>
                <a class="button button-secondary" href="<?= e(app_url('prayer.php')); ?>">View Prayers</a>
            </article>

        </div>
    </div>
</section>

<!-- ── New &amp; AI-powered features ──────────────────────────────────── -->
<section class="section section-contrast">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Recent Additions</p>
            <h2>New features built for deeper engagement</h2>
        </div>

        <div class="home-new-strip">

            <a class="home-new-card" href="<?= e(app_url('good-news.php')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <p class="eyebrow">Media Hub</p>
                <h3>Good News Hub</h3>
                <p>Daily encouragement, devotional content, Christian radio streams, and faith-building videos — all in one feed.</p>
            </a>

            <a class="home-new-card" href="<?= e(app_url('sermon-notes.php')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                </div>
                <p class="eyebrow">AI-Powered</p>
                <h3>AI Sermon Notes</h3>
                <p>Get instant reference suggestions, verse paraphrases, and sermon summaries powered by AI — right inside your notes.</p>
            </a>

            <a class="home-new-card" href="<?= e(app_url('bible.php')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <p class="eyebrow">Share the Word</p>
                <h3>Verse Share Composer</h3>
                <p>Design beautiful verse posts for social media with custom themes, fonts, and captions — then download or share instantly.</p>
            </a>

            <a class="home-new-card" href="<?= e(app_url('community-event-calendar.php')); ?>">
                <div class="home-new-card-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
                </div>
                <p class="eyebrow">Community</p>
                <h3>Event Calendar</h3>
                <p>Browse and RSVP to community events on a full calendar view — services, studies, meetups, and celebrations in one place.</p>
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

<!-- ── Community ─────────────────────────────────────────────────────── -->
<section class="section section-contrast">
    <div class="container two-column">
        <div>
            <p class="eyebrow">Community Module</p>
            <h2>More than a Bible reader</h2>
            <p>
                Good News Bible helps people gather, not just read. Community events support dev meetups, ministry meetings,
                Zoom calls, Sunday services, education tracks, career nights, pot lucks, and celebrations.
            </p>
            <ul class="check-list">
                <li>Live event feed with categories and RSVP</li>
                <li>In-person and online event support</li>
                <li>Personal planner sync</li>
                <li>Leader-managed publishing &amp; AI event drafts</li>
                <li>Full calendar view with event types</li>
                <li>Friend connections and shared activities</li>
            </ul>
            <div class="hero-actions" style="margin-top:1.25rem">
                <a class="button button-primary" href="<?= e(app_url('register.php')); ?>">Join the Community</a>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">See Events</a>
            </div>
        </div>

        <div class="home-timeline">
            <div class="home-timeline-item">
                <span class="home-timeline-dot"></span>
                <div>
                    <strong>Sunday Service</strong>
                    <span>Community worship and announcements</span>
                </div>
            </div>
            <div class="home-timeline-item">
                <span class="home-timeline-dot"></span>
                <div>
                    <strong>Zoom Bible Study</strong>
                    <span>Midweek remote gathering and notes</span>
                </div>
            </div>
            <div class="home-timeline-item">
                <span class="home-timeline-dot"></span>
                <div>
                    <strong>Dev &amp; Tech Meetup</strong>
                    <span>Career and technology fellowship event</span>
                </div>
            </div>
            <div class="home-timeline-item">
                <span class="home-timeline-dot"></span>
                <div>
                    <strong>Pot Luck &amp; Fellowship</strong>
                    <span>Shared meal with AI-generated sign-up list</span>
                </div>
            </div>
            <div class="home-timeline-item">
                <span class="home-timeline-dot"></span>
                <div>
                    <strong>Prayer &amp; Worship Night</strong>
                    <span>Open mic intercession and praise gathering</span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php

function home_daily_seed(string $namespace): int
{
    return abs((int) crc32(date('Y-m-d') . '|' . $namespace));
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

function home_curated_home_messages(): array
{
    return [
        [
            'eyebrow' => 'Central Media',
            'title' => 'Good News Hub',
            'copy' => 'Daily encouragement, devotion, radio, and faith-building content in one place.',
            'href' => 'good-news.php',
        ],
        [
            'eyebrow' => 'Announcements',
            'title' => 'Community Feed',
            'copy' => 'See church-centered gatherings, prayer meetups, classes, and shared life updates.',
            'href' => 'community.php',
        ],
        [
            'eyebrow' => 'Teaching',
            'title' => 'Public Sessions',
            'copy' => 'Browse public Bible studies, workshops, and upcoming teaching sessions.',
            'href' => 'sessions.php',
        ],
        [
            'eyebrow' => 'Study Rhythm',
            'title' => 'Saved Verses',
            'copy' => 'Keep verses, themes, and reminders close for the rest of the week.',
            'href' => 'bookmarks.php',
        ],
        [
            'eyebrow' => 'Write It Down',
            'title' => 'Study Notes',
            'copy' => 'Capture reflections, sermon insights, and linked Scripture in one archive.',
            'href' => 'notes.php',
        ],
        [
            'eyebrow' => 'Prayer',
            'title' => 'Prayer Rhythm',
            'copy' => 'Track requests, remember people, and keep practical prayer focus in view.',
            'href' => 'prayer.php',
        ],
    ];
}

function home_daily_rotating_items(array $items, int $seed, int $count): array
{
    if ($items === []) {
        return [];
    }

    $selected = [];
    $total = count($items);

    for ($index = 0; $index < min($count, $total); $index++) {
        $selected[] = $items[($seed + $index) % $total];
    }

    return $selected;
}

function home_daily_verse_payload(string $translation, int $seed): array
{
    $fallbacks = [
        [
            'query' => 'Proverbs 3:5-6',
            'reference' => 'Proverbs 3:5-6',
            'text' => 'Trust in the Lord with all your heart, and do not lean on your own understanding.',
            'kicker' => 'For today',
            'message' => 'Let the day start from surrender instead of strain.',
        ],
        [
            'query' => 'Isaiah 40:31',
            'reference' => 'Isaiah 40:31',
            'text' => 'Those who hope in the Lord will renew their strength.',
            'kicker' => 'For today',
            'message' => 'Wait with expectancy and keep moving in quiet confidence.',
        ],
        [
            'query' => 'Philippians 4:6-7',
            'reference' => 'Philippians 4:6-7',
            'text' => 'Do not be anxious about anything, but in everything by prayer and petition present your requests to God.',
            'kicker' => 'For today',
            'message' => 'Turn pressure into prayer and let peace guard the mind.',
        ],
        [
            'query' => 'Romans 15:13',
            'reference' => 'Romans 15:13',
            'text' => 'May the God of hope fill you with all joy and peace as you trust in Him.',
            'kicker' => 'For today',
            'message' => 'Hope grows where trust stays rooted in God.',
        ],
        [
            'query' => 'Joshua 1:9',
            'reference' => 'Joshua 1:9',
            'text' => 'Be strong and courageous. Do not be afraid; do not be discouraged, for the Lord your God will be with you.',
            'kicker' => 'For today',
            'message' => 'Walk forward with courage that comes from God\'s presence.',
        ],
        [
            'query' => 'Lamentations 3:22-23',
            'reference' => 'Lamentations 3:22-23',
            'text' => 'Because of the Lord\'s faithful love we do not perish, for His mercies never end. They are new every morning.',
            'kicker' => 'For today',
            'message' => 'Start again with mercy that has already met the morning.',
        ],
    ];
    $selected = $fallbacks[$seed % count($fallbacks)];
    $payload = $selected + ['translation' => $translation];

    try {
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
