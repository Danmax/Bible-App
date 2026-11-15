<?php

declare(strict_types=1);

/** Authored starting points, presented separately from literal text matches. */
function scripture_search_topic_guides(): array
{
    return [
        ['title' => 'Worry & anxiety', 'aliases' => ['worry', 'anxiety', 'anxious', 'worried'], 'description' => 'Read passages about bringing your concerns to God.', 'passages' => ['Matthew 6:25-34', 'Philippians 4:6-9', '1 Peter 5:6-7']],
        ['title' => 'Peace', 'aliases' => ['peace', 'calm'], 'description' => 'Explore peace in Jesus and in daily relationships.', 'passages' => ['John 14:27', 'Romans 12:18', 'Philippians 4:6-9']],
        ['title' => 'Forgiveness', 'aliases' => ['forgiveness', 'forgive', 'forgiven'], 'description' => 'Consider receiving forgiveness and forgiving others.', 'passages' => ['Psalm 103:8-12', 'Matthew 18:21-35', 'Ephesians 4:32']],
        ['title' => 'Hope', 'aliases' => ['hope', 'hopeless'], 'description' => 'Read about hope through hardship.', 'passages' => ['Psalm 42:5', 'Romans 5:1-5', 'Romans 15:13']],
        ['title' => 'Prayer', 'aliases' => ['prayer', 'pray', 'praying'], 'description' => 'Explore how Jesus taught his followers to pray.', 'passages' => ['Matthew 6:5-13', 'Luke 11:1-13', '1 Thessalonians 5:16-18']],
        ['title' => 'Faith & trust', 'aliases' => ['faith', 'trust', 'believe'], 'description' => 'Reflect on trusting God and living out faith.', 'passages' => ['Proverbs 3:5-6', 'Hebrews 11:1-6', 'James 2:14-18']],
        ['title' => 'Love', 'aliases' => ['love', 'charity'], 'description' => 'Read about God’s love and loving one another.', 'passages' => ['John 3:16-17', '1 Corinthians 13:4-7', '1 John 4:7-12']],
        ['title' => 'Grief & comfort', 'aliases' => ['grief', 'grieving', 'loss', 'comfort', 'sad'], 'description' => 'Find passages of lament, compassion, and comfort.', 'passages' => ['Psalm 34:18', 'John 11:32-36', '2 Corinthians 1:3-7']],
        ['title' => 'Wisdom & guidance', 'aliases' => ['wisdom', 'guidance', 'direction', 'decisions'], 'description' => 'Consider wisdom and asking God for guidance.', 'passages' => ['Proverbs 3:5-6', 'James 1:5', 'Psalm 119:105']],
        ['title' => 'Strength & courage', 'aliases' => ['strength', 'courage', 'afraid', 'fear'], 'description' => 'Read about courage and reliance on God.', 'passages' => ['Joshua 1:9', 'Psalm 46:1-3', 'Isaiah 40:28-31']],
        ['title' => 'Jesus calms the storm', 'aliases' => ['storm', 'jesus calms the storm', 'calming the storm'], 'description' => 'Read the Gospel accounts of Jesus calming the storm.', 'passages' => ['Matthew 8:23-27', 'Mark 4:35-41', 'Luke 8:22-25']],
        ['title' => 'The good Samaritan', 'aliases' => ['samaritan', 'good samaritan', 'neighbor', 'neighbour'], 'description' => 'Explore Jesus’ teaching about becoming a neighbor.', 'passages' => ['Luke 10:25-37']],
    ];
}

function scripture_matching_topic_guides(string $query): array
{
    return array_values(array_filter(scripture_search_topic_guides(), static function (array $topic) use ($query): bool {
        foreach ($topic['aliases'] as $alias) {
            if (scripture_search_contains($query, $alias)) {
                return true;
            }
        }
        return false;
    }));
}
