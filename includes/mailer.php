<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function mailer_enabled(): bool
{
    return trim((string) SMTP_HOST) !== '' && (int) SMTP_PORT > 0;
}

function send_app_email(string $toEmail, string $subject, string $textBody, ?string $htmlBody = null): void
{
    $recipientEmail = mb_strtolower(trim($toEmail));

    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid recipient email address is required.');
    }

    if (!mailer_enabled()) {
        throw new RuntimeException('Email delivery is not configured.');
    }

    $fromEmail = trim((string) APP_MAIL_FROM_EMAIL);

    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid sender email address is required.');
    }

    $fromName = trim((string) APP_MAIL_FROM_NAME);
    $encodedSubject = mailer_encode_header($subject);
    $encodedFromName = mailer_encode_header($fromName);
    $messageIdDomain = mailer_message_id_domain();
    $messageId = sprintf(
        '<%s@%s>',
        bin2hex(random_bytes(16)),
        $messageIdDomain
    );

    $headers = [
        'Date: ' . gmdate('D, d M Y H:i:s O'),
        'From: ' . mailer_format_address($fromEmail, $encodedFromName),
        'Reply-To: ' . mailer_format_address($fromEmail, $encodedFromName),
        'To: ' . mailer_format_address($recipientEmail),
        'Subject: ' . $encodedSubject,
        'Message-ID: ' . $messageId,
        'MIME-Version: 1.0',
        'X-Mailer: Good News Bible SMTP Mailer',
    ];

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $boundary = 'bible-app-' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = implode(
            "\r\n",
            [
                'This is a multi-part message in MIME format.',
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($textBody), 76, "\r\n"),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($htmlBody), 76, "\r\n"),
                '--' . $boundary . '--',
                '',
            ]
        );
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($textBody), 76, "\r\n");
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

    smtp_send_message($fromEmail, $recipientEmail, $message);
}

function send_password_reset_email(string $recipientName, string $recipientEmail, string $resetLink): void
{
    $safeName = trim($recipientName) !== '' ? trim($recipientName) : 'there';
    $subject = APP_NAME . ' password reset';
    $textBody = implode(
        "\n\n",
        [
            'Hi ' . $safeName . ',',
            'A password reset was requested for your ' . APP_NAME . ' account.',
            'Use this secure link to choose a new password:',
            $resetLink,
            'If you did not request this, you can ignore this email.',
            'Support: ' . app_support_email(),
        ]
    );
    $htmlBody = '<p>Hi ' . e($safeName) . ',</p>'
        . '<p>A password reset was requested for your ' . e(APP_NAME) . ' account.</p>'
        . '<p><a href="' . e($resetLink) . '">Reset your password</a></p>'
        . '<p>If you did not request this, you can ignore this email.</p>'
        . '<p>Support: <a href="mailto:' . e(app_support_email()) . '">' . e(app_support_email()) . '</a></p>';

    send_app_email($recipientEmail, $subject, $textBody, $htmlBody);
}

function send_email_change_confirmation_email(
    string $recipientName,
    string $newEmail,
    string $currentEmail,
    string $confirmationLink
): void {
    $safeName = trim($recipientName) !== '' ? trim($recipientName) : 'there';
    $subject = APP_NAME . ' email change confirmation';
    $textBody = implode(
        "\n\n",
        [
            'Hi ' . $safeName . ',',
            'A request was made to change your ' . APP_NAME . ' sign-in email.',
            'Current email: ' . $currentEmail,
            'Requested new email: ' . $newEmail,
            'Approve the change here:',
            $confirmationLink,
            'If you did not request this, do not open the link.',
            'Support: ' . app_support_email(),
        ]
    );
    $htmlBody = '<p>Hi ' . e($safeName) . ',</p>'
        . '<p>A request was made to change your ' . e(APP_NAME) . ' sign-in email.</p>'
        . '<p><strong>Current email:</strong> ' . e($currentEmail) . '<br><strong>Requested new email:</strong> ' . e($newEmail) . '</p>'
        . '<p><a href="' . e($confirmationLink) . '">Approve the email change</a></p>'
        . '<p>If you did not request this, do not open the link.</p>'
        . '<p>Support: <a href="mailto:' . e(app_support_email()) . '">' . e(app_support_email()) . '</a></p>';

    send_app_email($newEmail, $subject, $textBody, $htmlBody);
}

function send_friend_invite_email(string $senderName, string $recipientEmail, string $inviteLink): void
{
    $safeSender = trim($senderName) !== '' ? trim($senderName) : 'A friend';
    $subject = $safeSender . ' invited you to connect on ' . APP_NAME;
    $textBody = implode(
        "\n\n",
        [
            $safeSender . ' invited you to connect on ' . APP_NAME . '.',
            'Open the invite here:',
            $inviteLink,
            'If you were not expecting this invite, you can ignore this email.',
            'Support: ' . app_support_email(),
        ]
    );
    $htmlBody = '<p>' . e($safeSender) . ' invited you to connect on ' . e(APP_NAME) . '.</p>'
        . '<p><a href="' . e($inviteLink) . '">Open the invite</a></p>'
        . '<p>If you were not expecting this invite, you can ignore this email.</p>'
        . '<p>Support: <a href="mailto:' . e(app_support_email()) . '">' . e(app_support_email()) . '</a></p>';

    send_app_email($recipientEmail, $subject, $textBody, $htmlBody);
}

function send_community_event_email(
    string $recipientName,
    string $recipientEmail,
    string $eventTitle,
    string $eventDateLabel,
    string $subject,
    string $body,
    ?string $meetingUrl = null
): void {
    $safeName = trim($recipientName) !== '' ? trim($recipientName) : 'there';
    $safeSubject = trim($subject) !== '' ? trim($subject) : APP_NAME . ' community event update';
    $safeBody = trim($body);
    $safeMeetingUrl = trim((string) $meetingUrl);

    $textParts = [
        'Hi ' . $safeName . ',',
        $safeBody,
        'Event: ' . $eventTitle,
        'When: ' . $eventDateLabel,
    ];

    if ($safeMeetingUrl !== '') {
        $textParts[] = 'Join link: ' . $safeMeetingUrl;
    }

    $textParts[] = 'Support: ' . app_support_email();

    $textBody = implode("\n\n", $textParts);
    $htmlBody = '<p>Hi ' . e($safeName) . ',</p>'
        . '<p>' . nl2br(e($safeBody)) . '</p>'
        . '<p><strong>Event:</strong> ' . e($eventTitle) . '<br><strong>When:</strong> ' . e($eventDateLabel) . '</p>';

    if ($safeMeetingUrl !== '') {
        $htmlBody .= '<p><a href="' . e($safeMeetingUrl) . '">Open the event link</a></p>';
    }

    $htmlBody .= '<p>Support: <a href="mailto:' . e(app_support_email()) . '">' . e(app_support_email()) . '</a></p>';

    send_app_email($recipientEmail, $safeSubject, $textBody, $htmlBody);
}

function smtp_send_message(string $fromEmail, string $recipientEmail, string $message): void
{
    $host = trim((string) SMTP_HOST);
    $port = (int) SMTP_PORT;
    $encryption = strtolower(trim((string) SMTP_ENCRYPTION));
    $timeout = max(5, (int) SMTP_TIMEOUT);
    $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
        ],
    ]);

    $socket = @stream_socket_client(
        $transport . ':' . $port,
        $errorNumber,
        $errorString,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        throw new RuntimeException('SMTP connection failed: ' . $errorString);
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtp_expect($socket, [220]);

        $ehloHost = mailer_ehlo_host();
        smtp_write($socket, 'EHLO ' . $ehloHost);
        smtp_expect($socket, [250]);

        if (in_array($encryption, ['tls', 'starttls'], true)) {
            smtp_write($socket, 'STARTTLS');
            smtp_expect($socket, [220]);

            $cryptoEnabled = stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoEnabled !== true) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }

            smtp_write($socket, 'EHLO ' . $ehloHost);
            smtp_expect($socket, [250]);
        }

        $username = trim((string) SMTP_USER);
        $password = (string) SMTP_PASS;

        if ($username !== '') {
            smtp_write($socket, 'AUTH LOGIN');
            smtp_expect($socket, [334]);
            smtp_write($socket, base64_encode($username));
            smtp_expect($socket, [334]);
            smtp_write($socket, base64_encode($password));
            smtp_expect($socket, [235]);
        }

        smtp_write($socket, 'MAIL FROM:<' . $fromEmail . '>');
        smtp_expect($socket, [250]);
        smtp_write($socket, 'RCPT TO:<' . $recipientEmail . '>');
        smtp_expect($socket, [250, 251]);
        smtp_write($socket, 'DATA');
        smtp_expect($socket, [354]);

        $normalizedMessage = str_replace(["\r\n", "\r"], "\n", $message);
        $lines = explode("\n", $normalizedMessage);
        $escapedLines = [];

        foreach ($lines as $line) {
            $escapedLines[] = str_starts_with($line, '.') ? '.' . $line : $line;
        }

        fwrite($socket, implode("\r\n", $escapedLines) . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_write($socket, 'QUIT');
        smtp_expect($socket, [221]);
    } finally {
        fclose($socket);
    }
}

function smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function smtp_expect($socket, array $expectedCodes): string
{
    $response = smtp_read_response($socket);
    $statusCode = (int) substr($response, 0, 3);

    if (!in_array($statusCode, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }

    return $response;
}

function smtp_read_response($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP server did not return a response.');
    }

    return $response;
}

function mailer_encode_header(string $value): string
{
    $cleanValue = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');

    if ($cleanValue === '') {
        return '';
    }

    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($cleanValue, 'UTF-8', 'B', "\r\n");
    }

    return $cleanValue;
}

function mailer_format_address(string $email, string $name = ''): string
{
    $cleanEmail = trim(preg_replace('/[\r\n]+/', '', $email) ?? '');
    $cleanName = trim(preg_replace('/[\r\n]+/', ' ', $name) ?? '');

    if ($cleanName === '') {
        return '<' . $cleanEmail . '>';
    }

    return $cleanName . ' <' . $cleanEmail . '>';
}

function mailer_message_id_domain(): string
{
    $baseUrl = normalized_base_url(BASE_URL);

    if ($baseUrl !== '') {
        $parts = parse_url($baseUrl);
        $host = trim((string) ($parts['host'] ?? ''));

        if ($host !== '') {
            return $host;
        }
    }

    $fromDomain = (string) substr(strrchr(app_mail_from_email(), '@') ?: '', 1);

    return $fromDomain !== '' ? $fromDomain : 'localhost.localdomain';
}

function mailer_ehlo_host(): string
{
    $baseUrl = normalized_base_url(BASE_URL);

    if ($baseUrl !== '') {
        $parts = parse_url($baseUrl);
        $host = trim((string) ($parts['host'] ?? ''));

        if ($host !== '') {
            return $host;
        }
    }

    $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));

    return $serverName !== '' ? $serverName : 'localhost';
}
