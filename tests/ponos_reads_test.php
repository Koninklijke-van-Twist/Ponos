<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_groups.php';

ponos_test('ponos_unread_count excludes own messages', function (): void {
    ponos_db_wipe_all();
    $groupId = 'unread-group';
    $pdo = ponos_db();
    $pdo->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks) VALUES(?, ?, ?, 0, 1)'
    )->execute([$groupId, 'Unread', gmdate('c')]);

    $task = ponos_create_task($groupId, 'creator@kvt.nl', [
        'title' => 'Chat',
        'description' => 'Test',
    ]);
    assert_true(is_array($task));

    ponos_add_task_message($groupId, $task['id'], 'creator@kvt.nl', 'Eigen bericht');
    assert_eq(0, ponos_count_unread_for_task('creator@kvt.nl', $task['id']));

    ponos_add_task_message($groupId, $task['id'], 'other@kvt.nl', 'Van ander');
    assert_eq(1, ponos_count_unread_for_task('creator@kvt.nl', $task['id']));

    ponos_mark_task_messages_read('creator@kvt.nl', $task['id']);
    assert_eq(0, ponos_count_unread_for_task('creator@kvt.nl', $task['id']));
});

ponos_test('ponos_sort_groups_for_user pins and assigned groups first', function (): void {
    ponos_db_wipe_all();
    require_once dirname(__DIR__) . '/web/ponos_notify.php';

    $email = 'sorter@kvt.nl';
    $path = getUserPrefsPath($email);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }

    $alpha = ponos_create_group('Alpha', 'admin@kvt.nl');
    $beta = ponos_create_group('Beta', 'admin@kvt.nl');
    $gamma = ponos_create_group('Gamma', 'admin@kvt.nl');
    assert_true(is_array($alpha) && is_array($beta) && is_array($gamma));

    ponos_save_pinned_groups($email, [$gamma['id']]);

    ponos_set_group_open_access($beta['id'], true);
    ponos_create_task($beta['id'], 'admin@kvt.nl', [
        'title' => 'Open taak',
        'description' => 'Omschrijving',
        'assignee_email' => $email,
    ]);

    $sorted = ponos_sort_groups_for_user(ponos_list_groups(false), $email);
    $names = array_map(static fn(array $group): string => (string) $group['name'], $sorted);
    assert_eq(['Gamma', 'Beta', 'Alpha'], $names);

    if ($path !== null && is_file($path)) {
        unlink($path);
    }
});

ponos_test('ponos_should_send_email skips self actions', function (): void {
    require_once dirname(__DIR__) . '/web/ponos_notify.php';

    $email = 'notify-self@kvt.nl';
    $path = getUserPrefsPath($email);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }

    ponos_save_email_prefs($email, ['assigned' => true]);
    assert_eq(false, ponos_should_send_email($email, $email, 'assigned'));
    assert_eq(true, ponos_should_send_email($email, 'other@kvt.nl', 'assigned'));

    if ($path !== null && is_file($path)) {
        unlink($path);
    }
});
