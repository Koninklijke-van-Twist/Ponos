<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_groups.php';

ponos_test('ponos_create_group adds creator as member', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Nieuwe groep', 'creator@kvt.nl');
    assert_true(is_array($group));

    $access = ponos_get_group_access_settings($group['id']);
    assert_true(is_array($access));
    assert_eq(false, $access['open_access']);
    assert_eq(['creator@kvt.nl'], $access['members']);
});

ponos_test('ponos_remove_group_member allows self removal', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Team', 'owner@kvt.nl');
    assert_true(is_array($group));

    ponos_add_group_member($group['id'], 'other@kvt.nl');
    assert_eq(true, ponos_remove_group_member($group['id'], 'owner@kvt.nl', 'owner@kvt.nl'));
    assert_eq(true, ponos_remove_group_member($group['id'], 'other@kvt.nl', 'owner@kvt.nl'));

    $access = ponos_get_group_access_settings($group['id']);
    assert_eq([], $access['members']);
});

ponos_test('ponos_user_has_group_access respects open access and members', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Restricted', 'admin@kvt.nl');
    assert_true(is_array($group));

    assert_eq(false, ponos_user_has_group_access('user@kvt.nl', $group['id'], false));
    assert_eq(false, ponos_user_has_group_access('user@kvt.nl', $group['id'], true));

    ponos_add_group_member($group['id'], 'user@kvt.nl');
    assert_eq(true, ponos_user_has_group_access('user@kvt.nl', $group['id'], false));

    ponos_set_group_open_access($group['id'], true);
    assert_eq(true, ponos_user_has_group_access('other@kvt.nl', $group['id'], false));
});

ponos_test('ponos_user_can_view_group lets admins see restricted groups', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Hidden', 'owner@kvt.nl');
    assert_true(is_array($group));

    assert_eq(false, ponos_user_can_view_group('outsider@kvt.nl', $group['id'], false));
    assert_eq(true, ponos_user_can_view_group('outsider@kvt.nl', $group['id'], true));
    assert_eq(false, ponos_user_has_group_access('outsider@kvt.nl', $group['id'], true));
});

ponos_test('ponos_filter_groups_by_user_access shows all groups to admins', function (): void {
    ponos_db_wipe_all();
    $open = ponos_create_group('Open', 'admin@kvt.nl');
    $closed = ponos_create_group('Closed', 'admin@kvt.nl');
    assert_true(is_array($open) && is_array($closed));

    ponos_set_group_open_access($open['id'], true);

    $groups = ponos_list_groups(true);
    $adminVisible = ponos_filter_groups_by_user_access($groups, 'outsider@kvt.nl', true);
    $adminIds = array_map(static fn(array $group): string => (string) $group['id'], $adminVisible);

    assert_true(in_array($open['id'], $adminIds, true));
    assert_true(in_array($closed['id'], $adminIds, true));
});

ponos_test('ponos_filter_groups_by_user_access hides restricted groups', function (): void {
    ponos_db_wipe_all();
    $open = ponos_create_group('Open', 'admin@kvt.nl');
    $closed = ponos_create_group('Closed', 'admin@kvt.nl');
    assert_true(is_array($open) && is_array($closed));

    ponos_set_group_open_access($open['id'], true);
    ponos_add_group_member($closed['id'], 'member@kvt.nl');

    $groups = ponos_list_groups(true);
    $visible = ponos_filter_groups_by_user_access($groups, 'member@kvt.nl', false);
    $ids = array_map(static fn(array $group): string => (string) $group['id'], $visible);

    assert_true(in_array(PONOS_GROUP_MY_TASKS, $ids, true));
    assert_true(in_array($open['id'], $ids, true));
    assert_true(in_array($closed['id'], $ids, true));
    assert_eq(false, in_array($closed['id'], array_map(
        static fn(array $group): string => (string) $group['id'],
        ponos_filter_groups_by_user_access($groups, 'outsider@kvt.nl', false)
    ), true));
});

ponos_test('ponos_normalize_assignee_for_group rejects non-members', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Team', 'admin@kvt.nl');
    assert_true(is_array($group));

    ponos_add_group_member($group['id'], 'member@kvt.nl');
    assert_eq('member@kvt.nl', ponos_normalize_assignee_for_group($group['id'], 'member@kvt.nl'));
    assert_eq('', ponos_normalize_assignee_for_group($group['id'], 'outsider@kvt.nl'));
});

ponos_test('ponos_move_task clears assignee without target access', function (): void {
    ponos_db_wipe_all();
    $groupA = ponos_create_group('Groep A', 'admin@kvt.nl');
    $groupB = ponos_create_group('Groep B', 'admin@kvt.nl');
    assert_true(is_array($groupA) && is_array($groupB));

    ponos_add_group_member($groupA['id'], 'worker@kvt.nl');

    $task = ponos_create_task($groupA['id'], 'admin@kvt.nl', [
        'title' => 'Verplaatsen',
        'description' => 'Beschrijving',
        'assignee_email' => 'worker@kvt.nl',
    ]);
    assert_true(is_array($task));

    $moved = ponos_move_task($groupA['id'], $groupB['id'], $task['id'], 'admin@kvt.nl', 'Groep B');
    assert_true(is_array($moved));
    assert_eq('', $moved['assignee_email']);
});

ponos_test('ponos_task_can_edit allows group members on normalized tasks', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Team', 'admin@kvt.nl');
    assert_true(is_array($group));

    ponos_add_group_member($group['id'], 'member@kvt.nl');

    $task = ponos_create_task($group['id'], 'admin@kvt.nl', [
        'title' => 'Taak',
        'description' => 'Beschrijving',
    ]);
    assert_true(is_array($task));

    $listed = ponos_list_tasks_for_view($group['id'], 'member@kvt.nl', false);
    assert_eq(1, count($listed));
    assert_eq(true, $listed[0]['can_edit']);

    $fullTask = ponos_get_task($group['id'], $task['id']);
    assert_true(is_array($fullTask));
    assert_eq(true, ponos_task_can_edit(ponos_normalize_task_row($fullTask), 'member@kvt.nl'));
    assert_eq(false, ponos_task_can_edit(ponos_normalize_task_row($fullTask), 'outsider@kvt.nl'));
});
