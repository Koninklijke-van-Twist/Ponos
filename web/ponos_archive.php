<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_db.php';

/**
 * Constants
 */

const PONOS_DONE_VISIBLE_SECONDS = 604800;

const PONOS_ARCHIVE_PAGE_SIZE = 20;

/**
 * Functies
 */

function ponos_archive_cutoff_iso(): string
{
    return gmdate('c', time() - PONOS_DONE_VISIBLE_SECONDS);
}

function ponos_task_effective_done_at(array $task): string
{
    $doneAt = trim((string) ($task['done_at'] ?? ''));
    if ($doneAt !== '') {
        return $doneAt;
    }

    if ((string) ($task['status'] ?? '') === PONOS_STATUS_DONE) {
        return trim((string) ($task['updated_at'] ?? ''));
    }

    return '';
}

function ponos_task_is_archived(array $task): bool
{
    if ((string) ($task['status'] ?? '') !== PONOS_STATUS_DONE) {
        return false;
    }

    $doneAt = ponos_task_effective_done_at($task);
    if ($doneAt === '') {
        return false;
    }

    $timestamp = strtotime($doneAt);

    return $timestamp !== false && $timestamp < (time() - PONOS_DONE_VISIBLE_SECONDS);
}

function ponos_task_is_board_visible(array $task): bool
{
    return !ponos_task_is_archived($task);
}

function ponos_backfill_done_at(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE tasks SET done_at = updated_at
         WHERE status = '" . PONOS_STATUS_DONE . "'
           AND (done_at IS NULL OR TRIM(done_at) = '')"
    );
}

function ponos_done_at_for_status(string $newStatus): ?string
{
    if ($newStatus === PONOS_STATUS_DONE) {
        return gmdate('c');
    }

    return null;
}
