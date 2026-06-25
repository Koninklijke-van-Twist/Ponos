<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';

/**
 * Functies
 */

function ponos_app_base_url(): string
{
    $env = getenv('PONOS_BASE_URL');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }

    static $cached = null;
    if (is_string($cached)) {
        return $cached;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $cached = 'http://localhost/Ponos/web';

        return $cached;
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    if (preg_match('/_(api|reminder|nightly|email)\.php$/', $script)) {
        $script = 'index.php';
    }

    $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    $cached = $scheme . '://' . $host . ($path !== '' && $path !== '/' ? $path : '') . '/' . $script;

    return $cached;
}

function ponos_email_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ponos_email_brand(): array
{
    return [
        'perkins_blue' => '#00529B',
        'main_blue' => '#0099cc',
        'page_bg' => '#eef6fc',
        'panel_bg' => '#ffffff',
        'text' => '#111827',
        'muted' => '#475569',
        'line' => '#c9d7eb',
    ];
}

function ponos_email_task_link(array $task): string
{
    $groupId = (string) ($task['group_id'] ?? $task['home_group_id'] ?? '');
    $taskId = (string) ($task['id'] ?? '');
    $base = ponos_app_base_url();
    $query = http_build_query([
        'group' => $groupId,
        'task' => $taskId,
        'lang' => function_exists('getCurrentLanguage') ? getCurrentLanguage() : 'nl',
    ]);

    return $base . '?' . $query;
}

function ponos_email_status_label(string $status): string
{
    return ponos_status_label($status);
}

function ponos_email_task_card_html(array $task): string
{
    $brand = ponos_email_brand();
    $groupName = (string) ($task['group_name'] ?? '');
    $categoryLabel = trim((string) ($task['category_label'] ?? ''));
    $colors = ponos_category_color_from_text($categoryLabel !== '' ? $categoryLabel : $groupName);
    $link = ponos_email_task_link($task);
    $title = ponos_email_h((string) ($task['title'] ?? ''));
    $description = trim((string) ($task['description'] ?? ''));
    if (mb_strlen($description) > 220) {
        $description = mb_substr($description, 0, 217) . '...';
    }
    $descriptionHtml = ponos_email_h($description);
    if ($descriptionHtml !== '') {
        $descriptionHtml = nl2br($descriptionHtml, false);
    }

    $status = (string) ($task['status'] ?? PONOS_STATUS_TODO);
    $statusLabel = ponos_email_h(ponos_email_status_label($status));
    $dueDate = trim((string) ($task['due_date'] ?? ''));
    $checklistTotal = (int) ($task['checklist_total'] ?? 0);
    $checklistDone = (int) ($task['checklist_done'] ?? 0);
    $assigneeEmail = strtolower(trim((string) ($task['assignee_email'] ?? '')));
    $progress = $checklistTotal > 0 ? (int) round(($checklistDone / $checklistTotal) * 100) : 0;

    $metaParts = [];
    if ($groupName !== '') {
        $metaParts[] = ponos_email_h($groupName);
    }
    if ($dueDate !== '') {
        $metaParts[] = ponos_email_h(ponos_format_display_date($dueDate));
    }
    if ($checklistTotal > 0) {
        $metaParts[] = ponos_email_h($checklistDone . '/' . $checklistTotal);
    }
    $metaHtml = $metaParts !== [] ? implode(' &middot; ', $metaParts) : '';

    $assigneeHtml = '';
    if ($assigneeEmail !== '') {
        $assigneeHtml = '<div style="margin-top:10px;text-align:right;font-size:12px;color:' . $brand['muted'] . ';line-height:1.35;">'
            . '<span style="display:block;word-break:break-all;">' . ponos_email_h($assigneeEmail) . '</span></div>';
    }

    $barFill = $checklistTotal > 0
        ? '<div style="position:absolute;left:0;top:0;bottom:0;width:' . $progress . '%;background:' . ponos_email_h($colors['light']) . ';"></div>'
        : '';

    return '<a href="' . ponos_email_h($link) . '" style="display:block;text-decoration:none;color:inherit;margin:0 0 12px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid ' . $brand['line'] . ';border-radius:12px;overflow:hidden;background:' . $brand['panel_bg'] . ';box-shadow:0 4px 14px rgba(0,82,155,.08);">'
        . '<tr><td style="padding:0;">'
        . '<div style="position:relative;height:18px;background:' . ponos_email_h($colors['dark']) . ';">' . $barFill . '</div>'
        . '<div style="padding:12px 14px 14px;font-family:Montserrat,Segoe UI,Arial,sans-serif;">'
        . '<div style="font-size:11px;font-weight:700;color:' . $brand['main_blue'] . ';text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px;">' . $statusLabel . '</div>'
        . '<div style="font-size:16px;font-weight:700;color:' . $brand['perkins_blue'] . ';margin:0 0 6px;">' . $title . '</div>'
        . ($descriptionHtml !== '' ? '<div style="font-size:13px;color:' . $brand['text'] . ';line-height:1.45;margin:0 0 8px;">' . $descriptionHtml . '</div>' : '')
        . ($metaHtml !== '' ? '<div style="font-size:12px;color:' . $brand['muted'] . ';margin:0;">' . $metaHtml . '</div>' : '')
        . $assigneeHtml
        . '</div></td></tr></table></a>';
}

function ponos_email_task_cards_html(array $tasks): string
{
    $html = '';
    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }
        $html .= ponos_email_task_card_html($task);
    }

    return $html;
}

function ponos_email_plain_tasks(array $tasks): string
{
    $lines = [];
    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }
        $lines[] = '- ' . (string) ($task['title'] ?? '')
            . ' (' . (string) ($task['group_name'] ?? '') . ')'
            . "\n  " . ponos_email_task_link($task);
    }

    return implode("\n", $lines);
}

function ponos_email_layout_html(string $heading, string $introHtml, string $bodyHtml): string
{
    $brand = ponos_email_brand();
    $year = gmdate('Y');

    return '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:' . $brand['page_bg'] . ';font-family:Montserrat,Segoe UI,Arial,sans-serif;color:' . $brand['text'] . ';">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:' . $brand['page_bg'] . ';padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;background:' . $brand['panel_bg'] . ';border:1px solid ' . $brand['line'] . ';border-radius:16px;overflow:hidden;">'
        . '<tr><td style="padding:0;border-bottom:3px solid ' . $brand['main_blue'] . ';background:linear-gradient(180deg,#ffffff 0%,#f7fbff 100%);">'
        . '<div style="padding:18px 22px 16px;font-size:22px;font-weight:800;color:' . $brand['perkins_blue'] . ';">Ponos</div>'
        . '</td></tr>'
        . '<tr><td style="padding:22px 22px 8px;">'
        . '<h1 style="margin:0 0 12px;font-size:18px;color:' . $brand['perkins_blue'] . ';">' . ponos_email_h($heading) . '</h1>'
        . '<div style="font-size:14px;line-height:1.55;color:' . $brand['text'] . ';margin:0 0 18px;">' . $introHtml . '</div>'
        . $bodyHtml
        . '</td></tr>'
        . '<tr><td style="padding:8px 22px 20px;font-size:12px;color:' . $brand['muted'] . ';">'
        . ponos_email_h(LOC('ponos.email.footer'))
        . '</td></tr></table>'
        . '<div style="max-width:560px;margin:12px auto 0;font-size:11px;color:' . $brand['muted'] . ';text-align:center;">&copy; ' . $year . ' KVT</div>'
        . '</td></tr></table></body></html>';
}

function ponos_email_smtp_settings(): ?array
{
    global $reportMail;
    if (!is_array($reportMail ?? null)) {
        return null;
    }

    $smtp = $reportMail['smtp'] ?? null;
    if (!is_array($smtp)) {
        return null;
    }

    $host = trim((string) ($smtp['host'] ?? ''));
    if ($host === '') {
        return null;
    }

    $timeout = (int) ($smtp['timeout'] ?? ($smtp['ticd meout'] ?? 20));

    return [
        'host' => $host,
        'port' => (int) ($smtp['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($smtp['encryption'] ?? 'tls'))),
        'username' => trim((string) ($smtp['username'] ?? '')),
        'password' => (string) ($smtp['password'] ?? ''),
        'timeout' => max(5, $timeout),
    ];
}

function ponos_email_smtp_read($socket): string
{
    $data = '';
    while (is_resource($socket) && !feof($socket)) {
        $line = fgets($socket, 8192);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function ponos_email_smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function ponos_email_smtp_expect(string $response, array $codes): bool
{
    if ($response === '') {
        return false;
    }

    $code = (int) substr($response, 0, 3);

    return in_array($code, $codes, true);
}

function ponos_email_smtp_dot_stuff(string $body): string
{
    $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
    $stuffed = [];
    foreach ($lines as $line) {
        if ($line !== '' && $line[0] === '.') {
            $line = '.' . $line;
        }
        $stuffed[] = $line;
    }

    return implode("\r\n", $stuffed);
}

function ponos_email_send_smtp(
    string $to,
    string $fromEmail,
    string $fromName,
    string $encodedSubject,
    string $mimeHeaders,
    string $body
): bool {
    $smtp = ponos_email_smtp_settings();
    if ($smtp === null) {
        return false;
    }

    $remote = ($smtp['encryption'] === 'ssl' ? 'ssl://' : 'tcp://')
        . $smtp['host'] . ':' . $smtp['port'];
    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        $smtp['timeout'],
        STREAM_CLIENT_CONNECT
    );
    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, $smtp['timeout']);

    $response = ponos_email_smtp_read($socket);
    if (!ponos_email_smtp_expect($response, [220])) {
        fclose($socket);

        return false;
    }

    ponos_email_smtp_write($socket, 'EHLO ponos.local');
    $response = ponos_email_smtp_read($socket);
    if (!ponos_email_smtp_expect($response, [250])) {
        fclose($socket);

        return false;
    }

    if ($smtp['encryption'] === 'tls') {
        ponos_email_smtp_write($socket, 'STARTTLS');
        $response = ponos_email_smtp_read($socket);
        if (!ponos_email_smtp_expect($response, [220])) {
            fclose($socket);

            return false;
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            fclose($socket);

            return false;
        }

        ponos_email_smtp_write($socket, 'EHLO ponos.local');
        $response = ponos_email_smtp_read($socket);
        if (!ponos_email_smtp_expect($response, [250])) {
            fclose($socket);

            return false;
        }
    }

    if ($smtp['username'] !== '' && $smtp['password'] !== '') {
        ponos_email_smtp_write($socket, 'AUTH LOGIN');
        $response = ponos_email_smtp_read($socket);
        if (!ponos_email_smtp_expect($response, [334])) {
            fclose($socket);

            return false;
        }

        ponos_email_smtp_write($socket, base64_encode($smtp['username']));
        $response = ponos_email_smtp_read($socket);
        if (!ponos_email_smtp_expect($response, [334])) {
            fclose($socket);

            return false;
        }

        ponos_email_smtp_write($socket, base64_encode($smtp['password']));
        $response = ponos_email_smtp_read($socket);
        if (!ponos_email_smtp_expect($response, [235])) {
            fclose($socket);

            return false;
        }
    }

    ponos_email_smtp_write($socket, 'MAIL FROM:<' . $fromEmail . '>');
    $response = ponos_email_smtp_read($socket);
    if (!ponos_email_smtp_expect($response, [250])) {
        fclose($socket);

        return false;
    }

    ponos_email_smtp_write($socket, 'RCPT TO:<' . $to . '>');
    $response = ponos_email_smtp_read($socket);
    if (!ponos_email_smtp_expect($response, [250, 251])) {
        fclose($socket);

        return false;
    }

    ponos_email_smtp_write($socket, 'DATA');
    $response = ponos_email_smtp_read($socket);
    if (!ponos_email_smtp_expect($response, [354])) {
        fclose($socket);

        return false;
    }

    $payload = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail) . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $encodedSubject . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n\r\n"
        . ponos_email_smtp_dot_stuff($body);
    fwrite($socket, $payload . "\r\n.\r\n");
    $response = ponos_email_smtp_read($socket);
    if (!ponos_email_smtp_expect($response, [250])) {
        fclose($socket);

        return false;
    }

    ponos_email_smtp_write($socket, 'QUIT');
    fclose($socket);

    return true;
}

function ponos_email_send(string $to, string $subject, string $plainBody, string $htmlBody): bool
{
    $to = strtolower(trim($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    global $reportMail;
    $fromEmail = 'kvtbot@kvt.nl';
    $fromName = 'Ponos';
    if (is_array($reportMail ?? null)) {
        $fromEmail = (string) ($reportMail['from_email'] ?? $fromEmail);
        $fromName = (string) ($reportMail['from_name'] ?? 'Ponos');
    }

    $boundary = 'ponos_' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $mimeHeaders = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $plainBody . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlBody . "\r\n"
        . '--' . $boundary . '--';

    if (ponos_email_smtp_settings() !== null) {
        if (ponos_email_send_smtp($to, $fromEmail, $fromName, $encodedSubject, $mimeHeaders, $body)) {
            return true;
        }
    }

    $mailHeaders = "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n"
        . 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);

    return @mail($to, $encodedSubject, $body, $mailHeaders);
}

function ponos_email_enrich_task(array $task, string $groupName): array
{
    if ($groupName !== '') {
        $task['group_name'] = $groupName;
    }
    if (!isset($task['group_id']) && isset($task['home_group_id'])) {
        $task['group_id'] = $task['home_group_id'];
    }

    return $task;
}

function ponos_email_send_task_notice(
    array $task,
    string $recipientEmail,
    string $subject,
    string $introText,
    string $groupName
): bool {
    $recipientEmail = strtolower(trim($recipientEmail));
    if ($recipientEmail === '') {
        return false;
    }

    $enriched = ponos_email_enrich_task($task, $groupName);
    $plain = $introText . "\n\n" . ponos_email_plain_tasks([$enriched]);
    $html = ponos_email_layout_html(
        $subject,
        ponos_email_h($introText),
        ponos_email_task_cards_html([$enriched])
    );

    return ponos_email_send($recipientEmail, $subject, $plain, $html);
}
