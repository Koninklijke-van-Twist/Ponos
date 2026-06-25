<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_groups.php';

ponos_test('ponos_list_groups includes virtual my tasks group', function (): void {
    ponos_db_wipe_all();
    $groups = ponos_list_groups(true);
    assert_true(count($groups) >= 1);
    assert_eq(PONOS_GROUP_MY_TASKS, $groups[0]['id']);
    assert_true($groups[0]['virtual']);
    assert_eq(false, $groups[0]['can_create_tasks']);
});

ponos_test('ponos_create_group stores group in database', function (): void {
    ponos_db_wipe_all();

    $group = ponos_create_group('Sprint 1', 'admin@kvt.nl');
    assert_true(is_array($group));
    assert_eq('Sprint 1', $group['name']);
    assert_true($group['can_create_tasks']);

    $found = ponos_find_group($group['id']);
    assert_true(is_array($found));
    assert_eq('Sprint 1', $found['name']);

    $reloaded = ponos_list_groups(false);
    assert_eq(1, count($reloaded));
});

ponos_test('ponos_delete_group requires confirmation when tasks exist', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Te verwijderen', 'admin@kvt.nl');
    assert_true(is_array($group));

    ponos_create_task($group['id'], 'creator@kvt.nl', [
        'title' => 'Taak',
        'description' => 'Omschrijving',
    ]);

    $blocked = ponos_delete_group($group['id'], false);
    assert_eq(false, $blocked['ok']);
    assert_true($blocked['needs_confirm']);

    $deleted = ponos_delete_group($group['id'], true);
    assert_eq(true, $deleted['ok']);
    assert_eq(null, ponos_find_group($group['id']));
});

ponos_test('ponos_list_assigned_tasks filters by assignee', function (): void {
    ponos_db_wipe_all();

    $groupA = ponos_create_group('Groep A', 'admin@kvt.nl');
    $groupB = ponos_create_group('Groep B', 'admin@kvt.nl');
    assert_true(is_array($groupA) && is_array($groupB));

    ponos_set_group_open_access($groupA['id'], true);
    ponos_set_group_open_access($groupB['id'], true);

    ponos_create_task($groupA['id'], 'creator@kvt.nl', [
        'title' => 'Voor mij',
        'description' => 'Omschrijving',
        'assignee_email' => 'me@kvt.nl',
    ]);
    ponos_create_task($groupB['id'], 'creator@kvt.nl', [
        'title' => 'Voor ander',
        'description' => 'Omschrijving',
        'assignee_email' => 'other@kvt.nl',
    ]);

    $mine = ponos_list_assigned_tasks('me@kvt.nl');
    assert_eq(1, count($mine));
    assert_eq('Voor mij', $mine[0]['title']);
    assert_eq($groupA['id'], $mine[0]['home_group_id']);
});

ponos_test('ponos_create_group persists in sqlite', function (): void {
    ponos_db_wipe_all();
    $group = ponos_create_group('Persistent', 'admin@kvt.nl');
    assert_true(is_array($group));

    $groups = ponos_list_groups(false);
    assert_eq(1, count($groups));
    assert_eq('Persistent', $groups[0]['name']);
});
