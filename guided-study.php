<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Guided Study';
$pageDescription = 'Record what you observe, understand, apply, and pray from a Bible passage.';
$activePage = 'tools';
$user = refresh_current_user();

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

$available = guided_studies_available();
$studyId = (int) ($_GET['study'] ?? 0);
$bookId = max(0, (int) ($_GET['book_id'] ?? 0));
$chapter = max(0, (int) ($_GET['chapter'] ?? 0));
$startVerse = max(0, (int) ($_GET['verse'] ?? 0));
$endVerse = max(0, (int) ($_GET['verse_end'] ?? 0));
$translation = strtoupper(trim((string) ($_GET['translation'] ?? APP_DEFAULT_TRANSLATION)));
$formError = null;
$study = null;
$studies = [];
$book = null;

if ($available) {
    try {
        if ($studyId > 0) {
            $study = fetch_guided_study($studyId, (int) $user['id']);
            if ($study !== null) {
                $bookId = (int) $study['book_id'];
                $chapter = (int) $study['chapter_number'];
                $startVerse = (int) $study['start_verse'];
                $endVerse = (int) $study['end_verse'];
                $translation = (string) $study['translation'];
            }
        }

        if ($bookId > 0) {
            $book = fetch_book_by_id($bookId);
        }

        $studies = fetch_guided_studies((int) $user['id']);
    } catch (Throwable $exception) {
        $formError = 'Your guided studies could not be loaded right now.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$available) {
        $formError = 'Run the guided studies migration before saving a study.';
    } else {
        $studyId = max(0, (int) ($_POST['study_id'] ?? 0));
        $bookId = max(0, (int) ($_POST['book_id'] ?? 0));
        $chapter = max(0, (int) ($_POST['chapter'] ?? 0));
        $startVerse = max(0, (int) ($_POST['start_verse'] ?? 0));
        $endVerse = max(0, (int) ($_POST['end_verse'] ?? 0));
        $translation = strtoupper(trim((string) ($_POST['translation'] ?? APP_DEFAULT_TRANSLATION)));
        $book = $bookId > 0 ? fetch_book_by_id($bookId) : null;
        $studyData = [
            'book_id' => $bookId,
            'chapter_number' => $chapter,
            'start_verse' => $startVerse,
            'end_verse' => $endVerse,
            'translation' => $translation,
            'observation' => (string) ($_POST['observation'] ?? ''),
            'interpretation' => (string) ($_POST['interpretation'] ?? ''),
            'application' => (string) ($_POST['application'] ?? ''),
            'prayer' => (string) ($_POST['prayer'] ?? ''),
        ];

        if ($book === null || $chapter < 1 || $translation === '') {
            $formError = 'Choose a valid Bible passage before saving.';
        } elseif (trim($studyData['observation']) === '' && trim($studyData['interpretation']) === '' && trim($studyData['application']) === '' && trim($studyData['prayer']) === '') {
            $formError = 'Write in at least one part of the study before saving.';
        } else {
            try {
                if ($studyId > 0 && fetch_guided_study($studyId, (int) $user['id']) !== null) {
                    update_guided_study($studyId, (int) $user['id'], $studyData);
                    set_flash('Guided study updated.', 'success');
                } else {
                    $studyId = create_guided_study((int) $user['id'], $studyData);
                    set_flash('Guided study saved.', 'success');
                }

                redirect('guided-study.php?study=' . $studyId);
            } catch (Throwable $exception) {
                $formError = 'This study could not be saved right now.';
            }
        }

        $study = $studyData + ['id' => $studyId];
    }
}

$reference = $book !== null
    ? (string) $book['name'] . ' ' . $chapter . ($startVerse > 0 ? ':' . $startVerse . ($endVerse > $startVerse ? '-' . $endVerse : '') : '')
    : 'Choose a passage in the Bible reader';
$readerUrl = $book !== null
    ? app_url('bible.php?' . http_build_query([
        'translation' => $translation,
        'book_id' => $bookId,
        'chapter' => $chapter,
        'verse' => $startVerse ?: null,
        'verse_end' => $endVerse > $startVerse ? $endVerse : null,
    ]))
    : app_url('bible.php');
$study = $study ?? [];

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container guided-study-layout">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Guided Study</p>
                <h1>Study a passage slowly</h1>
                <p>Notice what the text says, consider its meaning, choose a response, and close in prayer.</p>
            </div>
            <a class="button button-secondary" href="<?= e($readerUrl); ?>">Open <?= e($reference); ?></a>
        </div>

        <?php if (!$available): ?>
            <div class="flash flash-warning">Run <code>sql/add_guided_studies.sql</code> to enable saved guided studies.</div>
        <?php elseif ($formError !== null): ?>
            <div class="flash flash-warning"><?= e($formError); ?></div>
        <?php endif; ?>

        <div class="guided-study-grid top-gap">
            <form class="panel guided-study-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="study_id" value="<?= e((string) ($study['id'] ?? 0)); ?>">
                <input type="hidden" name="book_id" value="<?= e((string) $bookId); ?>">
                <input type="hidden" name="chapter" value="<?= e((string) $chapter); ?>">
                <input type="hidden" name="start_verse" value="<?= e((string) $startVerse); ?>">
                <input type="hidden" name="end_verse" value="<?= e((string) $endVerse); ?>">
                <input type="hidden" name="translation" value="<?= e($translation); ?>">

                <div class="guided-study-reference">
                    <span>Passage</span>
                    <strong><?= e($reference); ?> <small><?= e($translation); ?></small></strong>
                    <?php if ($book === null): ?><a href="<?= e($readerUrl); ?>">Choose a passage</a><?php endif; ?>
                </div>

                <label>
                    <span>Observe</span>
                    <small>What stands out in the passage? What do you notice about the words, people, or structure?</small>
                    <textarea name="observation" rows="5" placeholder="I notice..."><?= e((string) ($study['observation'] ?? '')); ?></textarea>
                </label>
                <label>
                    <span>Interpret</span>
                    <small>What does this passage communicate in its context?</small>
                    <textarea name="interpretation" rows="5" placeholder="This shows..."><?= e((string) ($study['interpretation'] ?? '')); ?></textarea>
                </label>
                <label>
                    <span>Apply</span>
                    <small>What is one faithful response you can make today?</small>
                    <textarea name="application" rows="4" placeholder="Today I will..."><?= e((string) ($study['application'] ?? '')); ?></textarea>
                </label>
                <label>
                    <span>Pray</span>
                    <small>Turn your response into a short prayer.</small>
                    <textarea name="prayer" rows="4" placeholder="God, help me..."><?= e((string) ($study['prayer'] ?? '')); ?></textarea>
                </label>
                <button class="button button-primary" type="submit" <?= $available && $book !== null ? '' : 'disabled'; ?>>Save Guided Study</button>
            </form>

            <aside class="panel guided-study-history" aria-label="Saved guided studies">
                <div class="passage-study-heading"><div><p class="eyebrow">Your work</p><h2>Recent studies</h2></div></div>
                <?php if ($studies === []): ?>
                    <p class="empty-state">Open a passage in the Bible reader and choose Guided Study to begin.</p>
                <?php else: ?>
                    <div class="guided-study-list">
                        <?php foreach ($studies as $savedStudy): ?>
                            <a href="<?= e(app_url('guided-study.php?study=' . (int) $savedStudy['id'])); ?>" class="guided-study-list-item <?= (int) ($study['id'] ?? 0) === (int) $savedStudy['id'] ? 'is-active' : ''; ?>">
                                <strong><?= e((string) $savedStudy['book_name'] . ' ' . (int) $savedStudy['chapter_number'] . ((int) $savedStudy['start_verse'] > 0 ? ':' . (int) $savedStudy['start_verse'] : '')); ?></strong>
                                <span><?= e((string) $savedStudy['translation']); ?> · Updated <?= e(date('M j', strtotime((string) $savedStudy['updated_at']))); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
