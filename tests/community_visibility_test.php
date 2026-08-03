<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/community_repository.php';

function assert_visibility(bool $actual, bool $expected, string $scenario): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("FAIL: %s (expected %s, got %s)\n", $scenario, $expected ? 'visible' : 'hidden', $actual ? 'visible' : 'hidden'));
        exit(1);
    }
}

$publishedPublic = [
    'created_by_user_id' => 10,
    'status' => 'published',
    'visibility' => 'public',
];
$publishedMembers = array_merge($publishedPublic, ['visibility' => 'members']);
$publishedPrivate = array_merge($publishedPublic, ['visibility' => 'private']);
$draftPublic = array_merge($publishedPublic, ['status' => 'draft']);

assert_visibility(can_view_community_event_record($publishedPublic, null, false), true, 'anonymous users can view public events');
assert_visibility(can_view_community_event_record($publishedMembers, null, false), false, 'anonymous users cannot view member events');
assert_visibility(can_view_community_event_record($publishedMembers, 20, false), true, 'members can view member events');
assert_visibility(can_view_community_event_record($publishedPrivate, 20, false), false, 'unrelated members cannot view private events');
assert_visibility(can_view_community_event_record($publishedPrivate, 10, false), true, 'event creators can view their private events');
assert_visibility(can_view_community_event_record($draftPublic, 20, false), false, 'unrelated members cannot view draft events');
assert_visibility(can_view_community_event_record($draftPublic, 10, false), true, 'event creators can view their drafts');
assert_visibility(can_view_community_event_record($publishedPrivate, null, true), true, 'leaders and admins can view private events');

echo "Community visibility checks passed.\n";
