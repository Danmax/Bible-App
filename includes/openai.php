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

function openai_generate_event_draft(string $prompt, array $categories): array
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

    $systemPrompt = implode("\n", [
        'You convert a church or community event request into a structured JSON draft.',
        'Return only valid JSON. Do not wrap it in markdown.',
        'Use this exact object shape:',
        '{',
        '  "title": string,',
        '  "category_id": string,',
        '  "event_type": string,',
        '  "visibility": "public" | "members" | "private",',
        '  "location_name": string,',
        '  "location_address": string,',
        '  "meeting_url": string,',
        '  "start_at": string,',
        '  "end_at": string,',
        '  "description": string,',
        '  "status": "published" | "draft" | "cancelled",',
        '  "is_featured": false',
        '}',
        'Rules:',
        '- category_id must match one of the provided category ids, or be an empty string if none fit.',
        '- start_at and end_at must use local datetime format YYYY-MM-DDTHH:MM when the prompt gives enough information.',
        '- If timing is unclear, leave start_at or end_at as an empty string.',
        '- meeting_url must be empty unless the prompt explicitly contains or clearly implies a URL.',
        '- Keep title concise and description usable as an event summary.',
        '- Do not invent precise street addresses or links.',
    ]);

    $userPrompt = implode("\n\n", [
        'Available categories: ' . json_encode($categorySummaries, JSON_UNESCAPED_SLASHES),
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

    $draft = json_decode($text, true);

    if (!is_array($draft)) {
        throw new RuntimeException('The AI response could not be parsed into an event draft.');
    }

    return $draft;
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

    $draft = json_decode($text, true);

    if (!is_array($draft)) {
        throw new RuntimeException('The AI response could not be parsed into a planner event draft.');
    }

    return $draft;
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

    $draft = json_decode($text, true);

    if (!is_array($draft)) {
        throw new RuntimeException('The AI response could not be parsed into a planner goal draft.');
    }

    return $draft;
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

    $draft = json_decode($text, true);

    if (!is_array($draft)) {
        throw new RuntimeException('The AI response could not be parsed into a prayer request draft.');
    }

    return $draft;
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
