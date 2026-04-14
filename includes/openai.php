<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function openai_event_drafts_enabled(): bool
{
    return trim((string) OPENAI_API_KEY) !== '';
}

function openai_event_model(): string
{
    $model = trim((string) OPENAI_EVENT_MODEL);

    return $model !== '' ? $model : 'gpt-5-mini';
}

function openai_generate_event_draft(string $prompt, array $categories, array $context = []): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI event drafting is not configured yet.');
    }

    $normalizedPrompt = trim($prompt);

    if ($normalizedPrompt === '') {
        throw new RuntimeException('Enter a prompt first.');
    }

    $categorySummaries = array_map(
        static fn(array $category): array => [
            'id' => (string) ($category['id'] ?? ''),
            'label' => (string) ($category['label'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
        ],
        $categories
    );
    $requestedEventFormat = trim((string) ($context['event_format'] ?? ''));

    $systemPrompt = implode("\n", [
        'You convert a church or community event request into a structured JSON draft.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "title": string,',
        '  "category_id": string,',
        '  "event_type": string,',
        '  "event_format": "standard" | "potluck" | "study" | "prayer" | "worship" | "discipleship" | "outreach" | "service" | "fellowship" | "scripture-memory",',
        '  "visibility": "public" | "members" | "private",',
        '  "location_name": string,',
        '  "location_address": string,',
        '  "meeting_url": string,',
        '  "start_at": string,',
        '  "end_at": string,',
        '  "potluck_items_text": string,',
        '  "description": string,',
        '  "status": "published" | "draft" | "cancelled",',
        '  "is_featured": false',
        '}',
        'Rules:',
        '- category_id must match one of the provided category ids, or be an empty string if none fit.',
        '- event_format must match the event described in the prompt.',
        '- If the request context says event_format is potluck, set event_format to potluck unless the user clearly asks for something else.',
        '- start_at and end_at must use local datetime format YYYY-MM-DDTHH:MM when the prompt gives enough information.',
        '- If timing is unclear, leave start_at or end_at as an empty string.',
        '- meeting_url must be empty unless the prompt explicitly contains or clearly implies a URL.',
        '- potluck_items_text must be empty unless the event_format is potluck.',
        '- For potluck drafts, include starter items in potluck_items_text using one line per item in the format "Type | Detail".',
        '- Potluck starter items should reflect the gathering style in the prompt. Infer the potluck profile when possible, such as BBQ, picnic, community potluck, 4th of July block party, Thanksgiving dinner, Christmas dinner, lunch, brunch, chili cook-off, pizza party, birthday, anniversary, or celebration meal.',
        '- For potluck drafts, generate a practical starter list with common categories when they fit the gathering, such as Main dish, Side, Appetizer, Dessert, Drinks, Utensils, Plates, Napkins, Condiments, Ice, or Serving table.',
        '- For BBQ or block party prompts, prefer grill items, sides, drinks, condiments, ice, plates, utensils, and desserts.',
        '- For picnic or lunch prompts, prefer sandwiches or mains, fruit, salads, chips, drinks, blankets or serving supplies, and desserts.',
        '- For brunch prompts, prefer egg dishes, pastries, fruit, coffee, juice, and paper goods.',
        '- For Thanksgiving or Christmas dinner prompts, prefer turkey or ham, dressing, mashed potatoes, vegetables, rolls, dessert, drinks, and serving supplies.',
        '- For chili contest prompts, prefer Chili entry rows, toppings, cornbread, drinks, tasting spoons, and score sheets or table supplies.',
        '- For pizza party prompts, prefer Pizza, Salad, Drinks, Dessert, Plates, and Napkins.',
        '- Keep potluck_items_text to useful bringable items only, usually around 6 to 12 lines, and do not include placeholders or meta-instructions.',
        '- Keep title concise and description usable as an event summary.',
        '- Do not invent precise street addresses or links.',
    ]);

    $userPrompt = implode("\n\n", [
        'Available categories: ' . json_encode($categorySummaries, JSON_UNESCAPED_SLASHES),
        'Request context: ' . json_encode([
            'requested_event_format' => $requestedEventFormat,
        ], JSON_UNESCAPED_SLASHES),
        'User event request:',
        $normalizedPrompt,
    ]);

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'max_output_tokens' => 900,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'an event draft');
}

function openai_generate_planner_event_draft(string $prompt, ?string $eventDateHint = null): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI event drafting is not configured yet.');
    }

    $normalizedPrompt = trim($prompt);

    if ($normalizedPrompt === '') {
        throw new RuntimeException('Enter a prompt first.');
    }

    $hint = trim((string) $eventDateHint);

    $systemPrompt = implode("\n", [
        'You convert a planner request into a compact structured JSON draft for a personal calendar event.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "title": string,',
        '  "event_type": "study" | "prayer" | "service" | "family" | "community" | "goal" | "reminder",',
        '  "event_date": string,',
        '  "description": string',
        '}',
        'Rules:',
        '- event_date must use local datetime format YYYY-MM-DDTHH:MM when enough information exists.',
        '- If a default datetime hint is provided, use it unless the user clearly specifies a better date or time.',
        '- Keep title concise and practical.',
        '- Do not invent addresses, URLs, or unnecessary details.',
        '- Use "study" for Bible studies or discipleship meetings unless another type fits better.',
        '- Keep description short and useful for the planner.',
    ]);

    $userPrompt = implode("\n\n", array_filter([
        $hint !== '' ? 'Default datetime hint: ' . $hint : null,
        'Planner request:',
        $normalizedPrompt,
    ]));

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'max_output_tokens' => 500,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'a planner event draft');
}

function openai_generate_planner_goal_draft(string $prompt, ?int $yearHint = null): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI event drafting is not configured yet.');
    }

    $normalizedPrompt = trim($prompt);

    if ($normalizedPrompt === '') {
        throw new RuntimeException('Enter a prompt first.');
    }

    $systemPrompt = implode("\n", [
        'You convert a planner goal request into a compact structured JSON draft for a personal goal tracker.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "goal_title": string,',
        '  "goal_type": "reading" | "attendance" | "devotion" | "prayer" | "service" | "custom",',
        '  "year": string,',
        '  "target_value": string,',
        '  "current_value": string,',
        '  "status": "active" | "paused" | "completed"',
        '}',
        'Rules:',
        '- goal_title should be concise and actionable.',
        '- Use the provided year hint unless the request clearly specifies another year.',
        '- target_value should be an integer string when the request implies a measurable target, otherwise empty string.',
        '- current_value should usually be "0" unless the user clearly gives progress already made.',
        '- Default status to "active" unless the prompt clearly implies otherwise.',
        '- Choose "reading" for Bible reading goals, "devotion" for daily devotion habits, "prayer" for prayer rhythm goals.',
    ]);

    $userPrompt = implode("\n\n", array_filter([
        $yearHint !== null ? 'Default year hint: ' . (string) $yearHint : null,
        'Planner goal request:',
        $normalizedPrompt,
    ]));

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'max_output_tokens' => 450,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'a planner goal draft');
}

function openai_generate_prayer_request_draft(string $prompt): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI event drafting is not configured yet.');
    }

    $normalizedPrompt = trim($prompt);

    if ($normalizedPrompt === '') {
        throw new RuntimeException('Enter a prompt first.');
    }

    $systemPrompt = implode("\n", [
        'You convert a prayer request into a compact structured JSON draft.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "title": string,',
        '  "details": string,',
        '  "status": "active" | "answered"',
        '}',
        'Rules:',
        '- Keep the title concise and pastoral.',
        '- Keep details clear, respectful, and usable as a prayer note.',
        '- Default status to "active" unless the request clearly describes an answered prayer.',
        '- Do not invent names, diagnoses, or extra facts.',
    ]);

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $normalizedPrompt,
            ],
        ],
        'max_output_tokens' => 350,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'a prayer request draft');
}

function openai_generate_sermon_summary(string $speakerNotes, string $currentNote = ''): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI sermon drafting is not configured yet.');
    }

    $normalizedSpeakerNotes = trim($speakerNotes);
    $normalizedCurrentNote = trim($currentNote);

    if ($normalizedSpeakerNotes === '' && $normalizedCurrentNote === '') {
        throw new RuntimeException('Add speaker notes or sermon content first.');
    }

    $systemPrompt = implode("\n", [
        'You summarize sermon notes into structured JSON for a Bible study app.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "summary": string,',
        '  "key_points": string[],',
        '  "application_points": string[],',
        '  "prayer_focus": string,',
        '  "title": string',
        '}',
        'Rules:',
        '- Keep summary concise and pastoral.',
        '- key_points should contain 3 to 6 short bullets without numbering.',
        '- application_points should contain 2 to 5 practical responses.',
        '- prayer_focus should be one short sentence.',
        '- title should be concise and usable as a sermon note title.',
        '- Do not invent Bible references or facts that are not clearly supported.',
    ]);

    $userPrompt = implode("\n\n", array_filter([
        $normalizedSpeakerNotes !== '' ? 'Speaker notes or transcript:' . "\n" . $normalizedSpeakerNotes : null,
        $normalizedCurrentNote !== '' ? 'Current sermon note draft:' . "\n" . $normalizedCurrentNote : null,
    ]));

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'max_output_tokens' => 900,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'a sermon summary');
}

function openai_generate_sermon_reference_suggestions(string $noteText): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI sermon drafting is not configured yet.');
    }

    $normalizedNoteText = trim($noteText);

    if ($normalizedNoteText === '') {
        throw new RuntimeException('Add sermon content first.');
    }

    $systemPrompt = implode("\n", [
        'You extract structured Bible study metadata from sermon notes.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "verse_queries": string[],',
        '  "reference_tags": {',
        '    "character": string[],',
        '    "place": string[],',
        '    "item": string[],',
        '    "scene": string[],',
        '    "history": string[],',
        '    "promise": string[],',
        '    "prophecy": string[],',
        '    "book": string[],',
        '    "gospel": string[],',
        '    "theme": string[]',
        '  }',
        '}',
        'Rules:',
        '- verse_queries should include 0 to 6 likely Bible references such as "Romans 12:2".',
        '- Only suggest verse_queries when the notes clearly point toward them.',
        '- Keep each tag short and specific.',
        '- Use empty arrays when there is no strong match.',
        '- Do not invent niche historical claims.',
    ]);

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $normalizedNoteText,
            ],
        ],
        'max_output_tokens' => 900,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        if (is_local_environment()) {
            $preview = substr(json_encode($response, JSON_UNESCAPED_SLASHES) ?: '{}', 0, 400);
            throw new RuntimeException('The AI response was empty. Response: ' . $preview);
        }

        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'reference suggestions');
}

function openai_generate_sermon_paraphrase(string $verseReference, string $verseText, string $context = ''): array
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI sermon drafting is not configured yet.');
    }

    $normalizedReference = trim($verseReference);
    $normalizedVerseText = trim($verseText);
    $normalizedContext = trim($context);

    if ($normalizedReference === '' || $normalizedVerseText === '') {
        throw new RuntimeException('A verse reference and verse text are required.');
    }

    $systemPrompt = implode("\n", [
        'You paraphrase a Bible verse for sermon-note study use.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "paraphrase": string,',
        '  "summary": string',
        '}',
        'Rules:',
        '- Do not quote the verse exactly.',
        '- Make the paraphrase clearly derivative rather than a translation.',
        '- Keep summary to one sentence.',
        '- Do not add doctrine or claims that are not grounded in the verse.',
    ]);

    $userPrompt = implode("\n\n", array_filter([
        'Reference: ' . $normalizedReference,
        'Verse text: ' . $normalizedVerseText,
        $normalizedContext !== '' ? 'Sermon context: ' . $normalizedContext : null,
    ]));

    $response = openai_post_responses([
        'model' => openai_event_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'max_output_tokens' => 500,
    ]);

    $text = trim(openai_extract_response_text($response));

    if ($text === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    return openai_decode_json_object($text, 'a verse paraphrase');
}

function openai_decode_json_object(string $text, string $contextLabel): array
{
    $trimmed = trim($text);

    if ($trimmed === '') {
        throw new RuntimeException('The AI response was empty.');
    }

    $candidates = [$trimmed];

    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $trimmed, $matches) === 1) {
        $fenced = trim((string) ($matches[1] ?? ''));

        if ($fenced !== '') {
            $candidates[] = $fenced;
        }
    }

    $firstBrace = strpos($trimmed, '{');
    $lastBrace = strrpos($trimmed, '}');

    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $embeddedObject = trim(substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1));

        if ($embeddedObject !== '') {
            $candidates[] = $embeddedObject;
        }
    }

    $seen = [];

    foreach ($candidates as $candidate) {
        foreach (openai_json_candidate_variants($candidate) as $variant) {
            $normalizedVariant = trim($variant);

            if ($normalizedVariant === '' || isset($seen[$normalizedVariant])) {
                continue;
            }

            $seen[$normalizedVariant] = true;
            $decoded = json_decode($normalizedVariant, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    $message = 'The AI response could not be parsed into ' . $contextLabel . '.';

    if (is_local_environment()) {
        $preview = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
        $preview = trim(mb_substr($preview, 0, 220));

        if ($preview !== '') {
            $message .= ' Raw preview: ' . $preview;
        }
    }

    throw new RuntimeException($message);
}

function openai_json_candidate_variants(string $candidate): array
{
    $variants = [];
    $trimmed = trim($candidate);

    if ($trimmed === '') {
        return $variants;
    }

    $variants[] = $trimmed;

    $normalizedQuotes = str_replace(
        ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"],
        ['"', '"', "'", "'"],
        $trimmed
    );
    $normalizedQuotes = preg_replace('/,\s*([}\]])/', '$1', $normalizedQuotes) ?? $normalizedQuotes;
    $normalizedQuotes = preg_replace('/^\s*json\s*/i', '', $normalizedQuotes) ?? $normalizedQuotes;
    $variants[] = trim($normalizedQuotes);

    return $variants;
}

function openai_post_responses(array $payload): array
{
    if (function_exists('curl_init')) {
        return openai_post_responses_with_curl($payload);
    }

    return openai_post_responses_with_stream($payload);
}

function openai_post_responses_with_curl(array $payload): array
{
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if (!is_string($jsonPayload)) {
        throw new RuntimeException('Unable to encode the OpenAI request.');
    }

    $ch = curl_init('https://api.openai.com/v1/responses');

    if ($ch === false) {
        throw new RuntimeException('Unable to initialize the OpenAI request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim((string) OPENAI_API_KEY),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawBody = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);

    if (!is_string($rawBody)) {
        throw new RuntimeException($curlError !== '' ? $curlError : 'The OpenAI request failed.');
    }

    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('OpenAI returned an unreadable response.');
    }

    if ($statusCode >= 400) {
        $message = trim((string) ($decoded['error']['message'] ?? 'OpenAI returned an error.'));
        throw new RuntimeException($message !== '' ? $message : 'OpenAI returned an error.');
    }

    if (($decoded['status'] ?? '') === 'failed') {
        $message = trim((string) ($decoded['error']['message'] ?? 'The OpenAI request failed.'));
        throw new RuntimeException($message !== '' ? $message : 'The OpenAI request failed.');
    }

    if (isset($decoded['error']) && is_array($decoded['error'])) {
        $message = trim((string) ($decoded['error']['message'] ?? 'OpenAI returned an error.'));
        throw new RuntimeException($message !== '' ? $message : 'OpenAI returned an error.');
    }

    return $decoded;
}

function openai_post_responses_with_stream(array $payload): array
{
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if (!is_string($jsonPayload)) {
        throw new RuntimeException('Unable to encode the OpenAI request.');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . trim((string) OPENAI_API_KEY),
                'Content-Type: application/json',
            ]),
            'content' => $jsonPayload,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $rawBody = @file_get_contents('https://api.openai.com/v1/responses', false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = 0;

    foreach ($responseHeaders as $headerLine) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $headerLine, $matches)) {
            $statusCode = (int) $matches[1];
            break;
        }
    }

    if (!is_string($rawBody)) {
        throw new RuntimeException('The OpenAI request failed.');
    }

    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('OpenAI returned an unreadable response.');
    }

    if ($statusCode >= 400) {
        $message = trim((string) ($decoded['error']['message'] ?? 'OpenAI returned an error.'));
        throw new RuntimeException($message !== '' ? $message : 'OpenAI returned an error.');
    }

    if (($decoded['status'] ?? '') === 'failed') {
        $message = trim((string) ($decoded['error']['message'] ?? 'The OpenAI request failed.'));
        throw new RuntimeException($message !== '' ? $message : 'The OpenAI request failed.');
    }

    if (isset($decoded['error']) && is_array($decoded['error'])) {
        $message = trim((string) ($decoded['error']['message'] ?? 'OpenAI returned an error.'));
        throw new RuntimeException($message !== '' ? $message : 'OpenAI returned an error.');
    }

    return $decoded;
}

function openai_extract_response_text(array $response): string
{
    $outputText = trim((string) ($response['output_text'] ?? ''));

    if ($outputText !== '') {
        return $outputText;
    }

    $output = $response['output'] ?? null;

    if (!is_array($output)) {
        return '';
    }

    $parts = [];

    foreach ($output as $item) {
        if (!is_array($item)) {
            continue;
        }

        $content = $item['content'] ?? null;

        if (!is_array($content)) {
            continue;
        }

        foreach ($content as $contentItem) {
            if (!is_array($contentItem)) {
                continue;
            }

            $text = trim((string) ($contentItem['text'] ?? ''));

            if ($text !== '') {
                $parts[] = $text;
            }
        }
    }

    return trim(implode("\n", $parts));
}

function openai_transcribe_audio_upload(string $filePath, string $filename, string $mimeType = 'audio/webm'): string
{
    if (!openai_event_drafts_enabled()) {
        throw new RuntimeException('OpenAI transcription is not configured yet.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Audio transcription requires cURL support on the server.');
    }

    if (!is_file($filePath) || !is_readable($filePath)) {
        throw new RuntimeException('The uploaded audio file could not be read.');
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $safeMimeType = trim($mimeType) !== '' ? $mimeType : 'application/octet-stream';
    $safeFilename = trim($filename) !== '' ? $filename : 'recording' . ($extension !== '' ? '.' . $extension : '.webm');

    $payload = [
        'model' => 'gpt-4o-mini-transcribe',
        'file' => new CURLFile($filePath, $safeMimeType, $safeFilename),
    ];

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');

    if ($ch === false) {
        throw new RuntimeException('Unable to initialize the OpenAI transcription request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim((string) OPENAI_API_KEY),
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 60,
    ]);

    $rawBody = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);

    if (!is_string($rawBody)) {
        throw new RuntimeException($curlError !== '' ? $curlError : 'The OpenAI transcription request failed.');
    }

    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('OpenAI returned an unreadable transcription response.');
    }

    if ($statusCode >= 400) {
        $message = trim((string) ($decoded['error']['message'] ?? 'OpenAI returned a transcription error.'));
        throw new RuntimeException($message !== '' ? $message : 'OpenAI returned a transcription error.');
    }

    $text = trim((string) ($decoded['text'] ?? ''));

    if ($text === '') {
        throw new RuntimeException('The audio transcription was empty.');
    }

    return $text;
}
