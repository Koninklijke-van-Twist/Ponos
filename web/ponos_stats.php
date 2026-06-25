<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';
require_once __DIR__ . '/ponos_db.php';
require_once __DIR__ . '/ponos_archive.php';
require_once __DIR__ . '/ponos_groups.php';
require_once __DIR__ . '/ponos_categories.php';

/**
 * Functies
 */

function ponos_stats_local_date(string $iso): string
{
    $iso = trim($iso);
    if ($iso === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($iso))
            ->setTimezone(new DateTimeZone('Europe/Amsterdam'))
            ->format('Y-m-d');
    } catch (Exception) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $iso, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

function ponos_group_board_revision_from_tasks(array $tasks): string
{
    $parts = [];
    foreach ($tasks as $task) {
        $parts[] = implode(':', [
            (string) ($task['id'] ?? ''),
            (string) ($task['status'] ?? ''),
            (string) ($task['updated_at'] ?? ''),
            (string) ($task['sort_order'] ?? 0),
            (string) ($task['unread_count'] ?? 0),
        ]);
    }

    sort($parts, SORT_STRING);

    return hash('sha256', implode('|', $parts));
}

function ponos_group_board_revision(string $groupId, string $userEmail, ?bool $isAdmin = null): string
{
    return ponos_group_board_revision_from_tasks(
        ponos_list_tasks_for_view($groupId, $userEmail, $isAdmin)
    );
}

function ponos_group_stats(string $groupId): array
{
    if (ponos_is_my_tasks_group($groupId)) {
        return [
            'users' => [],
            'on_time' => [
                'total' => 0,
                'on_time' => 0,
                'percent' => null,
            ],
            'categories' => [],
            'task_total' => 0,
        ];
    }

    $pdo = ponos_db();
    $stmt = $pdo->prepare(
        'SELECT id, status, due_date, done_at, updated_at, created_by, assignee_email, category_label
         FROM tasks
         WHERE group_id = ?'
    );
    $stmt->execute([$groupId]);
    $rows = $stmt->fetchAll();

    $createdByUser = [];
    $handledByUser = [];
    $categoryCounts = [];
    $onTimeTotal = 0;
    $onTimeCount = 0;

    foreach ($rows as $row) {
        $createdBy = strtolower(trim((string) ($row['created_by'] ?? '')));
        if ($createdBy !== '') {
            $createdByUser[$createdBy] = ($createdByUser[$createdBy] ?? 0) + 1;
        }

        $assignee = strtolower(trim((string) ($row['assignee_email'] ?? '')));
        $status = (string) ($row['status'] ?? '');
        if ($assignee !== '' && $status === PONOS_STATUS_DONE) {
            $handledByUser[$assignee] = ($handledByUser[$assignee] ?? 0) + 1;
        }

        $categoryLabel = trim((string) ($row['category_label'] ?? ''));
        if ($categoryLabel === '') {
            $categoryLabel = ponos_category_display_label('');
        }
        $categoryCounts[$categoryLabel] = ($categoryCounts[$categoryLabel] ?? 0) + 1;

        $dueDate = trim((string) ($row['due_date'] ?? ''));
        if ($status === PONOS_STATUS_DONE && $dueDate !== '') {
            $doneDate = ponos_stats_local_date(ponos_task_effective_done_at([
                'status' => $status,
                'done_at' => (string) ($row['done_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ]));
            if ($doneDate !== '') {
                $onTimeTotal++;
                if ($doneDate <= $dueDate) {
                    $onTimeCount++;
                }
            }
        }
    }

    $emails = array_unique(array_merge(array_keys($createdByUser), array_keys($handledByUser)));
    sort($emails, SORT_STRING);
    $users = [];
    foreach ($emails as $email) {
        $users[] = [
            'email' => $email,
            'created' => (int) ($createdByUser[$email] ?? 0),
            'handled' => (int) ($handledByUser[$email] ?? 0),
        ];
    }

    usort($users, static function (array $left, array $right): int {
        $leftTotal = $left['created'] + $left['handled'];
        $rightTotal = $right['created'] + $right['handled'];

        return $rightTotal <=> $leftTotal ?: strcmp($left['email'], $right['email']);
    });

    $categories = [];
    foreach ($categoryCounts as $label => $count) {
        $categories[] = [
            'label' => $label,
            'count' => (int) $count,
        ];
    }
    usort($categories, static function (array $left, array $right): int {
        return $right['count'] <=> $left['count'] ?: strcmp($left['label'], $right['label']);
    });

    $percent = null;
    if ($onTimeTotal > 0) {
        $percent = (int) round(($onTimeCount / $onTimeTotal) * 100);
    }

    return [
        'users' => $users,
        'on_time' => [
            'total' => $onTimeTotal,
            'on_time' => $onTimeCount,
            'percent' => $percent,
        ],
        'categories' => $categories,
        'task_total' => count($rows),
    ];
}
