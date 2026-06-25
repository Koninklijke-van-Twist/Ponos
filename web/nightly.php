<?php

declare(strict_types=1);

/**
 * Nightly Ponos jobs (cron om 02:00), bijv.:
 * 0 2 * * * /usr/bin/php /pad/naar/web/nightly.php
 *
 * Windows Taakplanner:
 * C:\xampp\php\php.exe C:\xampp\htdocs\Ponos\web\nightly.php
 */

require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/ponos_notify.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/logincheck.php';
    if (!function_exists('is_trusted_requester') || !is_trusted_requester()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$result = ponos_run_nightly_jobs();
$reminders = $result['reminders'];
$queue = $result['queue'];

if (PHP_SAPI === 'cli') {
    echo sprintf(
        "Ponos nightly %s: %d reminder recipient(s), %d reminder email(s) sent, %d skipped; "
        . "queue %d sent, %d cancelled, %d failed, %d pending\n",
        $reminders['date'],
        $reminders['recipients'],
        $reminders['sent'],
        $reminders['skipped'],
        $queue['sent'],
        $queue['cancelled'],
        $queue['failed'],
        $queue['pending']
    );
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'reminders' => $reminders, 'queue' => $queue], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
