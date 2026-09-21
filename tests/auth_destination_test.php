<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_destination.php';

$cases = [
    ['bible.php?q=John+1&translation=MSB', 'bible.php?q=John+1&translation=MSB'],
    ['guided-study.php?book_id=43&chapter=3', 'guided-study.php?book_id=43&chapter=3'],
    ['study-day.php?enrollment_id=4&day=2', 'study-day.php?enrollment_id=4&day=2'],
    ['library.php?view=notes', 'library.php?view=notes'],
    ['https://example.com', 'bible.php'],
    ['//example.com', 'bible.php'],
    ['https:bible.php', 'bible.php'],
    ['../admin/index.php', 'bible.php'],
    ['logout.php', 'bible.php'],
    ['bible.php\\example.com', 'bible.php'],
    ["bible.php\r\nLocation: https://example.com", 'bible.php'],
    [['bible.php'], 'bible.php'],
    [null, 'bible.php'],
];
foreach ($cases as [$input, $expected]) {
    if (auth_destination($input) !== $expected) {
        throw new RuntimeException('Unexpected destination for ' . json_encode($input));
    }
}
echo "Authentication destination checks passed.\n";
