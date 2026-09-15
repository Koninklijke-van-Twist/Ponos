<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/ponos_api_spec.php';
require_once dirname(__DIR__) . '/web/ponos_api_request.php';

ponos_test('ponos_api_help documents task CRUD and durable API-key auth', function (): void {
    $spec = ponos_api_help();
    assert_eq(true, $spec['ok']);
    assert_eq('Ponos', $spec['name']);
    assert_eq(1, $spec['spec_version']);
    assert_eq('X-API-Key', $spec['auth']['header']);
    assert_true(str_contains((string) $spec['auth']['or'], 'not query string'));
    assert_false(str_contains(strtolower((string) $spec['auth']['or']), 'query/body'));
    assert_true(str_contains((string) $spec['auth']['durable_keys'], 'sha256'));
    assert_true(str_contains((string) $spec['auth']['mint']['cli'], 'ponos_api_key.php create'));

    $requiredActions = [
        'help', 'spec', 'whoami', 'navigation',
        'list_tasks', 'get_task', 'create_task', 'update_task',
        'update_status', 'complete_task',
        'list_archived_tasks', 'unarchive_task',
        'add_message', 'create_api_key',
    ];
    foreach ($requiredActions as $action) {
        assert_array_has_key($action, $spec['actions']);
        assert_true(!empty($spec['actions'][$action]['methods']));
    }

    assert_eq(false, $spec['actions']['help']['auth_required']);
    assert_eq(true, $spec['actions']['list_tasks']['auth_required']);
    assert_true(str_contains((string) $spec['task_model']['description'], 'notes') || $spec['task_model']['description'] === 'string notes/body');
    assert_true(str_contains((string) $spec['task_model']['no_priority_field'], 'priority'));
});

ponos_test('ponos_api.php help is public and does not load auth.php', function (): void {
    $script = dirname(__DIR__) . '/web/ponos_api.php';
    $output = [];
    $exit = 0;
    exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $exit);
    $raw = implode("\n", $output);
    assert_eq(0, $exit, $raw);
    $decoded = json_decode($raw, true);
    assert_true(is_array($decoded), $raw);
    assert_eq(true, $decoded['ok'] ?? false);
    assert_eq('Ponos', $decoded['name'] ?? '');
    assert_array_has_key('create_task', $decoded['actions'] ?? []);
    assert_false(str_contains($raw, 'auth.php'));
});

ponos_test('ponos_api_merge_json_body copies scalars and checklist into POST', function (): void {
    $_POST = [];
    $_GET = [];
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $status = ponos_api_merge_json_body(json_encode([
        'action' => 'create_task',
        'group' => 'g1',
        'title' => 'Bot taak',
        'done' => true,
        'checklist' => ['A', 'B'],
        'api_key' => 'ponos_secret',
    ], JSON_UNESCAPED_UNICODE));

    assert_eq('ok', $status);
    assert_eq('create_task', $_POST['action']);
    assert_eq('Bot taak', $_POST['title']);
    assert_eq('1', $_POST['done']);
    assert_eq('["A","B"]', $_POST['checklist']);
    assert_eq('ponos_secret', $_POST['api_key']);
    assert_false(array_key_exists('api_key', $_GET));
    unset($_SERVER['CONTENT_TYPE']);
    $_POST = [];
    $_GET = [];
});

ponos_test('ponos_api_merge_json_body rejects malformed JSON and JSON arrays', function (): void {
    $_POST = [];
    $_GET = [];
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    assert_eq('invalid', ponos_api_merge_json_body('{not json'));
    assert_eq('invalid', ponos_api_merge_json_body('[]'));
    assert_eq('invalid', ponos_api_merge_json_body('"scalar"'));
    assert_eq('empty', ponos_api_merge_json_body(''));
    unset($_SERVER['CONTENT_TYPE']);
    $_POST = [];
    $_GET = [];
});
