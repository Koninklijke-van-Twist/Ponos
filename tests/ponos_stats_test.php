<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_db.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';
require_once dirname(__DIR__) . '/web/ponos_stats.php';

ponos_test('ponos_group_board_revision_from_tasks changes when status changes', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Stats Group', 'creator@kvt.nl');
    $task = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Revision task',
        'description' => 'Body',
        'assignee_email' => 'worker@kvt.nl',
    ]);
    assert_true($task !== null);

    $before = ponos_group_board_revision_from_tasks([ponos_get_task($group['id'], $task['id'])]);
    ponos_update_task_status($group['id'], $task['id'], PONOS_STATUS_IN_PROGRESS, 'creator@kvt.nl');
    $after = ponos_group_board_revision_from_tasks([ponos_get_task($group['id'], $task['id'])]);

    assert_true($before !== $after);
});

ponos_test('ponos_group_stats reports created handled on time and categories', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Metrics', 'creator@kvt.nl');
    ponos_add_group_member($group['id'], 'worker@kvt.nl');
    ponos_add_group_member($group['id'], 'planner@kvt.nl');
    $category = ponos_create_category($group['id'], 'Backend');

    $onTime = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'On time task',
        'description' => 'Done before deadline',
        'assignee_email' => 'worker@kvt.nl',
        'due_date' => '2099-12-31',
        'category_id' => $category['id'],
    ]);
    assert_true($onTime !== null);
    ponos_update_task_status($group['id'], $onTime['id'], PONOS_STATUS_DONE, 'worker@kvt.nl');

    $late = ponos_create_task($group['id'], 'planner@kvt.nl', [
        'title' => 'Late task',
        'description' => 'Done after deadline',
        'assignee_email' => 'worker@kvt.nl',
        'due_date' => '2020-01-01',
    ]);
    assert_true($late !== null);
    ponos_update_task_status($group['id'], $late['id'], PONOS_STATUS_DONE, 'worker@kvt.nl');

    $open = ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Open task',
        'description' => 'Still open',
        'category_id' => $category['id'],
    ]);
    assert_true($open !== null);

    $stats = ponos_group_stats($group['id']);
    assert_eq(3, $stats['task_total']);

    $usersByEmail = [];
    foreach ($stats['users'] as $row) {
        $usersByEmail[$row['email']] = $row;
    }

    assert_eq(2, $usersByEmail['creator@kvt.nl']['created']);
    assert_eq(1, $usersByEmail['planner@kvt.nl']['created']);
    assert_eq(2, $usersByEmail['worker@kvt.nl']['handled']);

    assert_eq(2, $stats['on_time']['total']);
    assert_eq(1, $stats['on_time']['on_time']);
    assert_eq(50, $stats['on_time']['percent']);

    $categoriesByLabel = [];
    foreach ($stats['categories'] as $row) {
        $categoriesByLabel[$row['label']] = $row['count'];
    }
    assert_eq(2, $categoriesByLabel['Backend']);
    assert_eq(1, $categoriesByLabel[ponos_category_display_label('')]);
});
