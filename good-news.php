<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function gospel_story_steps(): array
{
    return [
        [
            'number' => '01',
            'short_title' => 'God loves you',
            'eyebrow' => 'The beginning',
            'title' => 'You were made to know and be loved by God.',
            'summary' => 'The Bible begins with a good God creating people in His image. Your life has dignity, purpose, and a place in His story.',
            'reference' => 'Genesis 1:27',
            'quote' => 'God created mankind in his own image.',
            'reflection' => 'What might change if you believed your life was made with purpose?',
        ],
        [
            'number' => '02',
            'short_title' => 'We wandered',
            'eyebrow' => 'The problem',
            'title' => 'Sin separates us from the life God intended.',
            'summary' => 'All of us have chosen our own way. The Bible calls this sin. It damages our relationship with God, with others, and even with ourselves.',
            'reference' => 'Romans 3:23',
            'quote' => 'For all have sinned, and come short of the glory of God.',
            'reflection' => 'Where do you most long for forgiveness, healing, or a new beginning?',
        ],
        [
            'number' => '03',
            'short_title' => 'Jesus came',
            'eyebrow' => 'The rescue',
            'title' => 'Jesus came to bring us home to God.',
            'summary' => 'God did not leave us on our own. Jesus lived with perfect love, gave His life for our sin, and rose again—opening the way to forgiveness and new life.',
            'reference' => 'John 3:16',
            'quote' => 'For God so loved the world, that he gave his only begotten Son.',
            'reflection' => 'What does it tell you about God that He moved toward us first?',
        ],
        [
            'number' => '04',
            'short_title' => 'You can respond',
            'eyebrow' => 'The invitation',
            'title' => 'Grace is a gift you can receive today.',
            'summary' => 'You do not earn your way to God. Turn toward Jesus, trust what He has done, and receive the new life He freely offers.',
            'reference' => 'Ephesians 2:8-9',
            'quote' => 'For by grace are ye saved through faith; and that not of yourselves.',
            'reflection' => 'Are you ready to trust Jesus, or would you like more time to explore?',
        ],
    ];
}

function gospel_questions(): array
{
    return [
        [
            'question' => 'What is the Bible?',
            'answer' => 'The Bible is a collection of writings that tells one connected story: God creating, pursuing, rescuing, and restoring people. It includes history, poetry, wisdom, letters, and eyewitness accounts of Jesus.',
        ],
        [
            'question' => 'Who is Jesus?',
            'answer' => 'Jesus is the Son of God at the center of the Christian faith. Christians believe He reveals what God is like, died for our sins, rose from the dead, and invites every person into life with God.',
        ],
        [
            'question' => 'Do I need to fix myself first?',
            'answer' => 'No. The Gospel is not a reward for people who have everything together. Jesus welcomes us as we are, and His grace begins the work of changing us from the inside out.',
        ],
        [
            'question' => 'What if I still have doubts?',
            'answer' => 'Questions are welcome here. Faith can begin with an honest desire to know what is true. Read one of the Gospels, talk with God honestly, and keep exploring at a thoughtful pace.',
        ],
    ];
}

$pageTitle = 'Explore the Gospel';
$pageDescription = 'A welcoming, interactive introduction to the Gospel, the story of Jesus, and how to begin reading the Bible.';
$activePage = 'good-news';
$user = is_logged_in() ? refresh_current_user() : null;
$storySteps = gospel_story_steps();
$questions = gospel_questions();
$prayerUrl = app_url($user !== null ? 'library.php?view=prayer' : 'login.php');
$pageScripts = ['assets/js/gospel-track.js'];

require_once __DIR__ . '/includes/header.php';
?>
<section class="gospel-landing" data-gospel-track>
    <div class="container gospel-landing-shell">
        <nav class="gospel-subnav" aria-label="Gospel page sections">
            <a class="gospel-subnav-brand" href="#top" aria-label="The Gospel, back to top">
                <span aria-hidden="true">✦</span>
                <strong>The Gospel</strong>
            </a>
            <div>
                <a href="#gospel-story">The story</a>
                <a href="#explore-bible">Explore</a>
                <a class="button button-primary" href="#respond">Take a next step</a>
            </div>
        </nav>

        <section class="gospel-hero" id="top" aria-labelledby="gospel-hero-title">
            <div class="gospel-hero-copy">
                <p class="gospel-kicker"><span aria-hidden="true"></span> You are welcome here</p>
                <h1 id="gospel-hero-title">There is good news<br><em>for your story.</em></h1>
                <p class="gospel-hero-lead">
                    The Gospel is the story of a God who loves you, came near in Jesus,
                    and offers a life made new. You do not need all the answers to begin.
                </p>

                <div class="gospel-hero-actions">
                    <a class="button gospel-button-light" href="#gospel-story">Explore the story <span aria-hidden="true">↓</span></a>
                    <a class="gospel-text-link" href="<?= e(scripture_reference_reader_url('John 1')); ?>">Meet Jesus in John 1 <span aria-hidden="true">→</span></a>
                </div>

                <div class="gospel-welcome-picker" aria-labelledby="welcome-picker-label">
                    <p id="welcome-picker-label">What brings you here today?</p>
                    <div class="gospel-choice-row" role="group" aria-label="Choose what brings you here">
                        <button type="button" data-gospel-choice="curious">I’m curious</button>
                        <button type="button" data-gospel-choice="restart">I need a fresh start</button>
                        <button type="button" data-gospel-choice="questions">I have questions</button>
                    </div>
                    <p class="gospel-choice-response" data-gospel-choice-response aria-live="polite">Choose what feels closest. There is no wrong place to begin.</p>
                </div>
            </div>

            <div class="gospel-hero-art" aria-label="An open door filled with light, representing hope and a new beginning">
                <div class="gospel-light-rays" aria-hidden="true"></div>
                <div class="gospel-door" aria-hidden="true">
                    <span></span>
                </div>
                <blockquote>
                    <p>“I am come that they might have life, and that they might have it more abundantly.”</p>
                    <cite>Jesus · John 10:10</cite>
                </blockquote>
            </div>
        </section>

        <section class="gospel-intro" id="gospel-story" aria-labelledby="gospel-story-title">
            <div>
                <p class="eyebrow">The big story</p>
                <h2 id="gospel-story-title">The Gospel in four movements</h2>
            </div>
            <p>You can move through these at your own pace. Each part connects the story of the Bible to the questions we all carry.</p>
        </section>

        <section class="gospel-journey" aria-label="Interactive Gospel story">
            <div class="gospel-journey-tabs" role="tablist" aria-label="Gospel story steps">
                <?php foreach ($storySteps as $index => $step): ?>
                    <button
                        type="button"
                        id="gospel-tab-<?= e((string) $index); ?>"
                        role="tab"
                        aria-selected="<?= $index === 0 ? 'true' : 'false'; ?>"
                        aria-controls="gospel-panel-<?= e((string) $index); ?>"
                        tabindex="<?= $index === 0 ? '0' : '-1'; ?>"
                        data-gospel-step="<?= e((string) $index); ?>"
                    >
                        <span><?= e($step['number']); ?></span>
                        <strong><?= e($step['short_title']); ?></strong>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="gospel-progress" aria-hidden="true"><span data-gospel-progress></span></div>

            <?php foreach ($storySteps as $index => $step): ?>
                <article
                    class="gospel-story-panel"
                    id="gospel-panel-<?= e((string) $index); ?>"
                    role="tabpanel"
                    aria-labelledby="gospel-tab-<?= e((string) $index); ?>"
                    <?= $index === 0 ? '' : 'hidden'; ?>
                    data-gospel-panel="<?= e((string) $index); ?>"
                >
                    <div class="gospel-story-copy">
                        <p class="eyebrow"><?= e($step['eyebrow']); ?></p>
                        <h3><?= e($step['title']); ?></h3>
                        <p><?= e($step['summary']); ?></p>
                        <a class="button button-primary" href="<?= e(scripture_reference_reader_url((string) $step['reference'])); ?>">Read <?= e($step['reference']); ?> <span aria-hidden="true">→</span></a>
                    </div>
                    <aside class="gospel-scripture-note">
                        <span class="gospel-scripture-mark" aria-hidden="true">“</span>
                        <blockquote><?= e($step['quote']); ?></blockquote>
                        <a href="<?= e(scripture_reference_reader_url((string) $step['reference'])); ?>"><?= e($step['reference']); ?></a>
                        <div>
                            <span>Pause and reflect</span>
                            <p><?= e($step['reflection']); ?></p>
                        </div>
                    </aside>
                </article>
            <?php endforeach; ?>

            <div class="gospel-journey-controls">
                <button class="gospel-round-button" type="button" data-gospel-previous disabled aria-label="Previous Gospel story step">←</button>
                <p><span data-gospel-current>1</span> of <?= e((string) count($storySteps)); ?></p>
                <button class="gospel-round-button" type="button" data-gospel-next aria-label="Next Gospel story step">→</button>
            </div>
        </section>

        <section class="gospel-explore" id="explore-bible" aria-labelledby="explore-bible-title">
            <div class="gospel-section-heading">
                <p class="eyebrow">A safe place to ask</p>
                <h2 id="explore-bible-title">New to the Bible?</h2>
                <p>You are not behind. Open a question to get a clear, simple starting point.</p>
            </div>
            <div class="gospel-question-grid">
                <?php foreach ($questions as $index => $item): ?>
                    <details class="gospel-question" <?= $index === 0 ? 'open' : ''; ?>>
                        <summary>
                            <span><?= e($item['question']); ?></span>
                            <i aria-hidden="true"></i>
                        </summary>
                        <p><?= e($item['answer']); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
            <div class="gospel-reading-invite">
                <div class="gospel-reading-icon" aria-hidden="true">☼</div>
                <div>
                    <span>Try a 5-minute read</span>
                    <h3>Meet Jesus through a real conversation.</h3>
                    <p>John 4 follows Jesus as He speaks with someone who felt unseen and out of place.</p>
                </div>
                <a class="button button-secondary" href="<?= e(scripture_reference_reader_url('John 4')); ?>">Read John 4 <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="gospel-response" id="respond" aria-labelledby="gospel-response-title">
            <div class="gospel-response-copy">
                <p class="eyebrow">Your next step</p>
                <h2 id="gospel-response-title">You can talk to God right now.</h2>
                <p>Prayer is simply honest conversation with God. You do not need special words. If you are ready, you can begin here:</p>
                <blockquote>
                    “God, I want to know You. Thank You for loving me and for sending Jesus. Forgive me, lead me, and help me trust You one day at a time. Amen.”
                </blockquote>
                <p class="gospel-prayer-note">This prayer is not a formula—what matters is an honest heart turning toward God.</p>
            </div>
            <div class="gospel-next-steps">
                <a href="<?= e(scripture_reference_reader_url('John 1')); ?>">
                    <span>01</span>
                    <div><strong>Start with John</strong><small>Discover who Jesus is</small></div>
                    <i aria-hidden="true">→</i>
                </a>
                <a href="<?= e($prayerUrl); ?>">
                    <span>02</span>
                    <div><strong><?= $user !== null ? 'Write a prayer' : 'Create a prayer space'; ?></strong><small><?= $user !== null ? 'Respond honestly to God' : 'Sign in to save private prayers'; ?></small></div>
                    <i aria-hidden="true">→</i>
                </a>
                <a href="<?= e(app_url('studies.php')); ?>">
                    <span>03</span>
                    <div><strong>Explore a Bible plan</strong><small>Take one step each day</small></div>
                    <i aria-hidden="true">→</i>
                </a>
            </div>
        </section>

        <section class="gospel-final-invite" aria-label="Invitation to keep exploring">
            <span aria-hidden="true">✦</span>
            <p>Wherever you are in your story, you are invited to keep seeking.</p>
            <a href="<?= e(app_url('bible.php')); ?>">Open the Bible <span aria-hidden="true">→</span></a>
        </section>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
