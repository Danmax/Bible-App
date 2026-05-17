<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$params = ['view' => 'notes'];
$verseId = (int) ($_GET['verse_id'] ?? 0);
$editNoteId = (int) ($_GET['edit'] ?? 0);

if ($verseId > 0) {
    $params['verse_id'] = (string) $verseId;
}

if ($editNoteId > 0) {
    $params['edit_note'] = (string) $editNoteId;
}

redirect('library.php?' . http_build_query($params));
