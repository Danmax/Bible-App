<?php

declare(strict_types=1);

function bible_dictionary_entries(): array
{
    return [
        [
            'term' => 'Atonement',
            'slug' => 'atonement',
            'summary' => 'The work of God that covers sin and restores fellowship between God and people.',
            'details' => 'In Scripture, atonement is tied to sacrifice, forgiveness, cleansing, and reconciliation. The Old Testament sacrificial system points forward to the finished work of Christ.',
            'references' => ['Leviticus 16:30', 'Romans 3:23-25', 'Hebrews 9:12'],
            'related' => ['Sacrifice', 'Redemption', 'Justification'],
        ],
        [
            'term' => 'Baptism',
            'slug' => 'baptism',
            'summary' => 'A public sign of identification with Christ, repentance, and new life.',
            'details' => 'Baptism marks allegiance to Jesus and points to burial and resurrection with Him. It is closely connected with discipleship and confession of faith.',
            'references' => ['Matthew 28:19', 'Acts 2:38', 'Romans 6:3-4'],
            'related' => ['Disciple', 'Repentance', 'Resurrection'],
        ],
        [
            'term' => 'Covenant',
            'slug' => 'covenant',
            'summary' => 'A binding relationship established by God with promises, obligations, and signs.',
            'details' => 'Biblical covenants reveal God\'s faithfulness and His unfolding plan of redemption, including covenants with Noah, Abraham, Israel, David, and the new covenant in Christ.',
            'references' => ['Genesis 12:1-3', 'Jeremiah 31:31-34', 'Luke 22:20'],
            'related' => ['Promise', 'Redemption', 'Kingdom'],
        ],
        [
            'term' => 'Disciple',
            'slug' => 'disciple',
            'summary' => 'A learner and follower who is formed by the teaching and way of Jesus.',
            'details' => 'Discipleship includes hearing Christ, obeying Him, bearing fruit, and helping others follow Him.',
            'references' => ['Luke 9:23', 'John 15:8', 'Matthew 28:18-20'],
            'related' => ['Apostle', 'Baptism', 'Kingdom'],
        ],
        [
            'term' => 'Faith',
            'slug' => 'faith',
            'summary' => 'Trusting God, receiving His word as true, and resting in His promises.',
            'details' => 'Biblical faith is more than agreement with facts. It is reliance on God that leads to obedience, endurance, and worship.',
            'references' => ['Hebrews 11:1', 'Romans 10:17', 'James 2:17'],
            'related' => ['Grace', 'Promise', 'Righteousness'],
        ],
        [
            'term' => 'Gospel',
            'slug' => 'gospel',
            'summary' => 'The good news that God saves sinners through the life, death, and resurrection of Jesus Christ.',
            'details' => 'The gospel announces the kingdom of God, forgiveness of sins, reconciliation with God, and eternal life through Christ.',
            'references' => ['Mark 1:14-15', '1 Corinthians 15:1-4', 'Romans 1:16'],
            'related' => ['Grace', 'Kingdom', 'Redemption'],
        ],
        [
            'term' => 'Grace',
            'slug' => 'grace',
            'summary' => 'God\'s undeserved favor and powerful help given freely to His people.',
            'details' => 'Grace saves, trains, strengthens, and sustains believers. It is received by faith and displayed supremely in Jesus Christ.',
            'references' => ['Ephesians 2:8-9', 'Titus 2:11-12', '2 Corinthians 12:9'],
            'related' => ['Faith', 'Gospel', 'Justification'],
        ],
        [
            'term' => 'Justification',
            'slug' => 'justification',
            'summary' => 'God\'s act of declaring sinners righteous through faith in Christ.',
            'details' => 'Justification is grounded in the work of Jesus, not human merit. It brings peace with God and a secure standing before Him.',
            'references' => ['Romans 3:28', 'Romans 5:1', 'Galatians 2:16'],
            'related' => ['Atonement', 'Grace', 'Righteousness'],
        ],
        [
            'term' => 'Kingdom of God',
            'slug' => 'kingdom-of-god',
            'summary' => 'God\'s reign and saving rule, revealed in Christ and awaited in fullness.',
            'details' => 'The kingdom is present in Jesus\' ministry and continues through His people, while believers still pray for its final fulfillment.',
            'references' => ['Matthew 6:10', 'Luke 17:20-21', 'Revelation 11:15'],
            'related' => ['Gospel', 'Messiah', 'Parable'],
        ],
        [
            'term' => 'Messiah',
            'slug' => 'messiah',
            'summary' => 'The Anointed One promised by God, fulfilled in Jesus Christ.',
            'details' => 'Messiah language points to kingly, priestly, and prophetic hope. The New Testament identifies Jesus as the Christ, the promised deliverer.',
            'references' => ['Psalm 2:2', 'John 1:41', 'Acts 2:36'],
            'related' => ['Kingdom of God', 'Prophet', 'Resurrection'],
        ],
        [
            'term' => 'Parable',
            'slug' => 'parable',
            'summary' => 'A teaching story or comparison that reveals spiritual truth.',
            'details' => 'Jesus often used parables to reveal the kingdom, expose hearts, invite repentance, and challenge shallow hearing.',
            'references' => ['Matthew 13:10-17', 'Luke 15:3-7', 'Mark 4:33-34'],
            'related' => ['Kingdom of God', 'Repentance', 'Wisdom'],
        ],
        [
            'term' => 'Redemption',
            'slug' => 'redemption',
            'summary' => 'Deliverance by payment of a price, especially from sin and bondage.',
            'details' => 'Redemption language draws from rescue and release. In Christ, believers are bought, forgiven, and brought into freedom.',
            'references' => ['Exodus 6:6', 'Ephesians 1:7', '1 Peter 1:18-19'],
            'related' => ['Atonement', 'Grace', 'Sacrifice'],
        ],
        [
            'term' => 'Repentance',
            'slug' => 'repentance',
            'summary' => 'A Spirit-worked turning from sin toward God.',
            'details' => 'Repentance includes a changed mind, sorrow over sin, and a new direction of life. It is joined to faith in the message of the gospel.',
            'references' => ['Acts 3:19', '2 Corinthians 7:10', 'Mark 1:15'],
            'related' => ['Faith', 'Gospel', 'Sanctification'],
        ],
        [
            'term' => 'Resurrection',
            'slug' => 'resurrection',
            'summary' => 'God raising the dead to life, centered on Jesus\' victory over death.',
            'details' => 'Christ\'s resurrection confirms His identity, secures salvation, and guarantees the future resurrection of His people.',
            'references' => ['Luke 24:6-7', '1 Corinthians 15:20-22', '1 Peter 1:3'],
            'related' => ['Gospel', 'Messiah', 'Redemption'],
        ],
        [
            'term' => 'Righteousness',
            'slug' => 'righteousness',
            'summary' => 'Right standing and right conduct before God according to His character.',
            'details' => 'Scripture speaks of God\'s righteousness, the righteousness credited to believers by faith, and righteous living produced by grace.',
            'references' => ['Genesis 15:6', 'Matthew 6:33', 'Romans 1:17'],
            'related' => ['Faith', 'Justification', 'Sanctification'],
        ],
        [
            'term' => 'Sacrifice',
            'slug' => 'sacrifice',
            'summary' => 'An offering given to God, often connected with worship, cleansing, and atonement.',
            'details' => 'Old Testament sacrifices teach the seriousness of sin and the need for substitution. Jesus is presented as the final and sufficient sacrifice.',
            'references' => ['Leviticus 1:3-4', 'John 1:29', 'Hebrews 10:10'],
            'related' => ['Atonement', 'Redemption', 'Worship'],
        ],
        [
            'term' => 'Sanctification',
            'slug' => 'sanctification',
            'summary' => 'God setting His people apart and making them holy.',
            'details' => 'Sanctification is both a status believers receive in Christ and an ongoing work of growth in holiness by the Spirit.',
            'references' => ['John 17:17', '1 Thessalonians 4:3', 'Hebrews 10:14'],
            'related' => ['Grace', 'Repentance', 'Righteousness'],
        ],
        [
            'term' => 'Wisdom',
            'slug' => 'wisdom',
            'summary' => 'Skill for living faithfully under the fear and instruction of the Lord.',
            'details' => 'Biblical wisdom is moral and worshipful, not merely intellectual. It begins with reverence for God and is embodied perfectly in Christ.',
            'references' => ['Proverbs 1:7', 'James 1:5', '1 Corinthians 1:24'],
            'related' => ['Parable', 'Righteousness', 'Worship'],
        ],
        [
            'term' => 'Worship',
            'slug' => 'worship',
            'summary' => 'The reverent response of the whole person to God\'s worth.',
            'details' => 'Worship includes praise, obedience, sacrifice, prayer, and a life offered to God through Jesus Christ.',
            'references' => ['Psalm 95:6', 'John 4:23-24', 'Romans 12:1'],
            'related' => ['Sacrifice', 'Wisdom', 'Grace'],
        ],
    ];
}

function bible_dictionary_find(?string $slugOrTerm): ?array
{
    $needle = bible_dictionary_normalize((string) $slugOrTerm);

    if ($needle === '') {
        return null;
    }

    foreach (bible_dictionary_entries() as $entry) {
        if (
            bible_dictionary_normalize((string) $entry['slug']) === $needle
            || bible_dictionary_normalize((string) $entry['term']) === $needle
        ) {
            return $entry;
        }
    }

    return null;
}

function bible_dictionary_search(string $query, int $limit = 12): array
{
    $normalizedQuery = bible_dictionary_normalize($query);
    $entries = bible_dictionary_entries();

    if ($normalizedQuery === '') {
        return array_slice($entries, 0, $limit);
    }

    $matches = [];

    foreach ($entries as $entry) {
        $haystack = bible_dictionary_normalize(implode(' ', [
            (string) $entry['term'],
            (string) $entry['summary'],
            (string) $entry['details'],
            implode(' ', (array) $entry['related']),
        ]));

        if (str_contains($haystack, $normalizedQuery)) {
            $matches[] = $entry;
        }
    }

    return array_slice($matches, 0, $limit);
}

function bible_dictionary_featured_terms(int $limit = 8): array
{
    return array_slice(bible_dictionary_entries(), 0, $limit);
}

function bible_reference_categories(): array
{
    return [
        'people' => [
            'label' => 'People',
            'description' => 'Major Bible characters and groups.',
            'items' => [
                ['name' => 'Adam', 'reference' => 'Genesis 2:7'],
                ['name' => 'Eve', 'reference' => 'Genesis 3:20'],
                ['name' => 'Noah', 'reference' => 'Genesis 6:9'],
                ['name' => 'Abraham', 'reference' => 'Genesis 12:1-3'],
                ['name' => 'Sarah', 'reference' => 'Genesis 17:15-16'],
                ['name' => 'Moses', 'reference' => 'Exodus 3:10'],
                ['name' => 'Joshua', 'reference' => 'Joshua 1:9'],
                ['name' => 'David', 'reference' => '1 Samuel 16:13'],
                ['name' => 'Solomon', 'reference' => '1 Kings 3:12'],
                ['name' => 'Elijah', 'reference' => '1 Kings 18:36'],
                ['name' => 'Mary', 'reference' => 'Luke 1:38'],
                ['name' => 'John the Baptist', 'reference' => 'Matthew 3:1-3'],
                ['name' => 'Peter', 'reference' => 'Matthew 16:18'],
                ['name' => 'Paul', 'reference' => 'Acts 9:15'],
                ['name' => 'Jesus Christ', 'reference' => 'John 1:14'],
            ],
        ],
        'places' => [
            'label' => 'Places',
            'description' => 'Cities, regions, and lands named in Scripture.',
            'items' => [
                ['name' => 'Eden', 'reference' => 'Genesis 2:8'],
                ['name' => 'Ararat', 'reference' => 'Genesis 8:4'],
                ['name' => 'Ur', 'reference' => 'Genesis 11:31'],
                ['name' => 'Canaan', 'reference' => 'Genesis 12:5'],
                ['name' => 'Egypt', 'reference' => 'Exodus 1:1'],
                ['name' => 'Sinai', 'reference' => 'Exodus 19:20'],
                ['name' => 'Jericho', 'reference' => 'Joshua 6:1'],
                ['name' => 'Bethlehem', 'reference' => 'Micah 5:2'],
                ['name' => 'Jerusalem', 'reference' => '2 Samuel 5:6-7'],
                ['name' => 'Nazareth', 'reference' => 'Luke 1:26'],
                ['name' => 'Galilee', 'reference' => 'Matthew 4:23'],
                ['name' => 'Samaria', 'reference' => 'John 4:4'],
                ['name' => 'Rome', 'reference' => 'Acts 28:16'],
            ],
        ],
        'locations' => [
            'label' => 'Locations',
            'description' => 'Specific landmarks and settings inside Bible narratives.',
            'items' => [
                ['name' => 'Garden of Eden', 'reference' => 'Genesis 2:15'],
                ['name' => 'Tower of Babel', 'reference' => 'Genesis 11:4'],
                ['name' => 'Mount Moriah', 'reference' => 'Genesis 22:2'],
                ['name' => 'Burning Bush', 'reference' => 'Exodus 3:2'],
                ['name' => 'Red Sea', 'reference' => 'Exodus 14:21'],
                ['name' => 'Tabernacle', 'reference' => 'Exodus 40:34'],
                ['name' => 'Temple', 'reference' => '1 Kings 8:10-11'],
                ['name' => 'Jordan River', 'reference' => 'Joshua 3:15-17'],
                ['name' => 'Mount Carmel', 'reference' => '1 Kings 18:19'],
                ['name' => 'Sea of Galilee', 'reference' => 'Matthew 4:18'],
                ['name' => 'Upper Room', 'reference' => 'Acts 1:13'],
                ['name' => 'Golgotha', 'reference' => 'John 19:17'],
            ],
        ],
        'animals' => [
            'label' => 'Animals',
            'description' => 'Animals used in narrative, law, wisdom, prophecy, and teaching.',
            'items' => [
                ['name' => 'Serpent', 'reference' => 'Genesis 3:1'],
                ['name' => 'Dove', 'reference' => 'Genesis 8:11'],
                ['name' => 'Ram', 'reference' => 'Genesis 22:13'],
                ['name' => 'Lamb', 'reference' => 'Exodus 12:3'],
                ['name' => 'Goat', 'reference' => 'Leviticus 16:7'],
                ['name' => 'Ox', 'reference' => 'Deuteronomy 25:4'],
                ['name' => 'Donkey', 'reference' => 'Numbers 22:28'],
                ['name' => 'Lion', 'reference' => 'Daniel 6:22'],
                ['name' => 'Raven', 'reference' => '1 Kings 17:4'],
                ['name' => 'Fish', 'reference' => 'Jonah 1:17'],
                ['name' => 'Locust', 'reference' => 'Exodus 10:12'],
                ['name' => 'Eagle', 'reference' => 'Isaiah 40:31'],
            ],
        ],
        'artifacts' => [
            'label' => 'Artifacts',
            'description' => 'Objects, furnishings, and sacred items in biblical history.',
            'items' => [
                ['name' => 'Ark of the Covenant', 'reference' => 'Exodus 25:10'],
                ['name' => 'Altar', 'reference' => 'Exodus 27:1'],
                ['name' => 'Lampstand', 'reference' => 'Exodus 25:31'],
                ['name' => 'Table of Showbread', 'reference' => 'Exodus 25:23'],
                ['name' => 'Bronze Basin', 'reference' => 'Exodus 30:18'],
                ['name' => 'Priestly Ephod', 'reference' => 'Exodus 28:6'],
                ['name' => 'Scroll', 'reference' => 'Jeremiah 36:2'],
                ['name' => 'Trumpet', 'reference' => 'Numbers 10:2'],
                ['name' => 'Crown', 'reference' => '2 Samuel 12:30'],
                ['name' => 'Cross', 'reference' => 'John 19:17'],
                ['name' => 'Cup', 'reference' => 'Luke 22:20'],
                ['name' => 'Tomb Stone', 'reference' => 'Matthew 27:60'],
            ],
        ],
        'tools' => [
            'label' => 'Tools',
            'description' => 'Everyday implements and trade tools mentioned in Scripture.',
            'items' => [
                ['name' => 'Plow', 'reference' => '1 Kings 19:19'],
                ['name' => 'Sickle', 'reference' => 'Deuteronomy 16:9'],
                ['name' => 'Threshing Sledge', 'reference' => 'Isaiah 41:15'],
                ['name' => 'Millstone', 'reference' => 'Matthew 18:6'],
                ['name' => 'Axe', 'reference' => '2 Kings 6:5'],
                ['name' => 'Hammer', 'reference' => 'Judges 5:26'],
                ['name' => 'Nets', 'reference' => 'Matthew 4:20'],
                ['name' => 'Tent Peg', 'reference' => 'Judges 4:21'],
                ['name' => 'Yoke', 'reference' => 'Matthew 11:29'],
                ['name' => 'Needle', 'reference' => 'Matthew 19:24'],
                ['name' => 'Measuring Line', 'reference' => 'Zechariah 2:1'],
                ['name' => 'Pruning Hook', 'reference' => 'Isaiah 2:4'],
            ],
        ],
        'measurements' => [
            'label' => 'Measurements',
            'description' => 'Weights, lengths, volumes, and money units used in Bible passages.',
            'items' => [
                ['name' => 'Cubit', 'reference' => 'Genesis 6:15'],
                ['name' => 'Span', 'reference' => 'Exodus 28:16'],
                ['name' => 'Handbreadth', 'reference' => 'Exodus 25:25'],
                ['name' => 'Reed', 'reference' => 'Ezekiel 40:3'],
                ['name' => 'Ephah', 'reference' => 'Exodus 16:36'],
                ['name' => 'Omer', 'reference' => 'Exodus 16:16'],
                ['name' => 'Hin', 'reference' => 'Exodus 29:40'],
                ['name' => 'Shekel', 'reference' => 'Genesis 23:16'],
                ['name' => 'Talent', 'reference' => 'Matthew 25:15'],
                ['name' => 'Denarius', 'reference' => 'Matthew 20:2'],
                ['name' => 'Mina', 'reference' => 'Luke 19:13'],
                ['name' => 'Stadium', 'reference' => 'Luke 24:13'],
            ],
        ],
        'foods' => [
            'label' => 'Foods',
            'description' => 'Foods and drinks connected to meals, offerings, provision, and teaching.',
            'items' => [
                ['name' => 'Bread', 'reference' => 'Exodus 16:4'],
                ['name' => 'Manna', 'reference' => 'Exodus 16:31'],
                ['name' => 'Wine', 'reference' => 'John 2:9'],
                ['name' => 'Olive Oil', 'reference' => '1 Kings 17:14'],
                ['name' => 'Honey', 'reference' => '1 Samuel 14:27'],
                ['name' => 'Milk', 'reference' => 'Genesis 18:8'],
                ['name' => 'Fish', 'reference' => 'Luke 24:42'],
                ['name' => 'Lentils', 'reference' => 'Genesis 25:34'],
                ['name' => 'Figs', 'reference' => '1 Samuel 25:18'],
                ['name' => 'Grapes', 'reference' => 'Numbers 13:23'],
                ['name' => 'Pomegranates', 'reference' => 'Numbers 13:23'],
                ['name' => 'Unleavened Bread', 'reference' => 'Exodus 12:8'],
            ],
        ],
    ];
}

function bible_reference_category(?string $categoryKey): ?array
{
    $key = bible_dictionary_normalize((string) $categoryKey);
    $categories = bible_reference_categories();

    return $categories[$key] ?? null;
}

function bible_reference_category_count(): int
{
    return array_sum(array_map(
        static fn(array $category): int => count((array) ($category['items'] ?? [])),
        bible_reference_categories()
    ));
}

function bible_dictionary_normalize(string $value): string
{
    $normalized = mb_strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/u', '-', $normalized) ?? $normalized;

    return trim($normalized, '-');
}
