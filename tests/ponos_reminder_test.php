<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';

ponos_test('ponos_task_can_remind respects hourly cooldown', function (): void {
    $task = [
        'assignee_email' => 'user@kvt.nl',
        'last_reminder_at' => gmdate('c', time() - 1800),
    ];
    assert_eq(false, ponos_task_can_remind($task));

    $task['last_reminder_at'] = gmdate('c', time() - 3700);
    assert_eq(true, ponos_task_can_remind($task));

    $task['last_reminder_at'] = '';
    assert_eq(true, ponos_task_can_remind($task));
});

ponos_test('ponos_send_task_email_reminder blocks repeat within one hour', function (): void {
    ponos_db_wipe_all();
    $groupId = 'reminder-group-01';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Reminder Group', gmdate('c')]);

    $task = ponos_create_task($groupId, 'assignee@kvt.nl', [
        'title' => 'Herinner taak',
        'description' => 'Beschrijving',
        'assignee_email' => 'assignee@kvt.nl',
    ]);
    assert_true(is_array($task));

    ponos_db()->prepare('UPDATE tasks SET last_reminder_at = ? WHERE id = ?')
        ->execute([gmdate('c'), $task['id']]);

    $result = ponos_send_task_email_reminder($groupId, $task['id'], 'actor@kvt.nl');
    assert_eq(false, $result['ok']);
});
