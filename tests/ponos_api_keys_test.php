<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';
require_once dirname(__DIR__) . '/web/ponos_api_request.php';
require_once dirname(__DIR__) . '/web/ponos_api_keys.php';
require_once dirname(__DIR__) . '/web/ponos_storage.php';
require_once dirname(__DIR__) . '/web/ponos_data.php';

ponos_test('ponos_api_key_create hashes secret and authenticates', function (): void {
    ponos_db_wipe_all();
    $created = ponos_api_key_create('Tim.Falken@kvt.nl', 'Sec-Bot');
    assert_true(is_array($created));
    assert_eq('tim.falken@kvt.nl', $created['user_email']);
    assert_eq('Sec-Bot', $created['label']);
    assert_true(str_starts_with((string) $created['api_key'], 'ponos_'));
    assert_eq(ponos_api_key_prefix((string) $created['api_key']), $created['key_prefix']);

    $row = ponos_db()->query('SELECT key_hash, key_prefix FROM api_keys LIMIT 1')->fetch();
    assert_true(is_array($row));
    assert_eq(hash('sha256', (string) $created['api_key']), $row['key_hash']);
    assert_false(str_contains((string) $row['key_hash'], (string) $created['api_key']));

    $authed = ponos_api_key_authenticate((string) $created['api_key']);
    assert_true(is_array($authed));
    assert_eq('tim.falken@kvt.nl', $authed['user_email']);
    assert_eq((int) $created['id'], (int) $authed['id']);
    assert_true($authed['last_used_at'] !== '');

    assert_eq(null, ponos_api_key_authenticate('ponos_this_is_not_a_real_key_value_000000'));
});

ponos_test('ponos_api_key_create rejects invalid email', function (): void {
    ponos_db_wipe_all();
    assert_eq(null, ponos_api_key_create('not-an-email', 'bot'));
    assert_eq(null, ponos_api_key_create('', 'bot'));
});

ponos_test('ponos_api_key_list and revoke hide secrets and disable auth', function (): void {
    ponos_db_wipe_all();
    $created = ponos_api_key_create('secbot@kvt.nl', 'Sec-Bot');
    $listed = ponos_api_key_list('secbot@kvt.nl');
    assert_eq(1, count($listed));
    assert_false(array_key_exists('api_key', $listed[0]));
    assert_false(array_key_exists('key_hash', $listed[0]));
    assert_eq('Sec-Bot', $listed[0]['label']);

    assert_true(ponos_api_key_revoke((int) $created['id'], 'secbot@kvt.nl'));
    assert_eq(null, ponos_api_key_authenticate((string) $created['api_key']));
    assert_eq([], ponos_api_key_list('secbot@kvt.nl'));
    assert_eq(1, count(ponos_api_key_list('secbot@kvt.nl', true)));
    assert_false(ponos_api_key_revoke((int) $created['id'], 'secbot@kvt.nl'));
});

ponos_test('ponos_api_key_email_is_allowed respects allowedUsers when set', function (): void {
    $previous = $GLOBALS['allowedUsers'] ?? null;
    $GLOBALS['allowedUsers'] = ['tfalken@kvt.nl', 'other@kvt.nl'];
    assert_true(ponos_api_key_email_is_allowed('TFALKEN@kvt.nl'));
    assert_false(ponos_api_key_email_is_allowed('stranger@kvt.nl'));
    unset($GLOBALS['allowedUsers']);
    assert_true(ponos_api_key_email_is_allowed('anyone@kvt.nl'));
    if ($previous !== null) {
        $GLOBALS['allowedUsers'] = $previous;
    }
});

ponos_test('ponos_api_request_api_key reads header query and bearer', function (): void {
    $_SERVER['HTTP_X_API_KEY'] = ' ponos_header ';
    $_GET['api_key'] = 'from-query';
    assert_eq('ponos_header', ponos_api_request_api_key());
    unset($_SERVER['HTTP_X_API_KEY']);

    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ponos_bearer_token';
    assert_eq('ponos_bearer_token', ponos_api_request_api_key());
    unset($_SERVER['HTTP_AUTHORIZATION']);

    $_POST = [];
    $_GET = ['api_key' => 'from-query'];
    assert_eq('from-query', ponos_api_request_api_key());
    unset($_GET['api_key']);
});

ponos_test('ponos_set_request_user overrides session email for API-key identity', function (): void {
    ponos_ensure_session();
    $_SESSION['user'] = ['email' => 'session@kvt.nl'];
    ponos_set_request_user('api-bot@kvt.nl');
    assert_eq('api-bot@kvt.nl', ponos_current_user_email());
    ponos_set_request_user('');
    assert_eq('session@kvt.nl', ponos_current_user_email());
    unset($_SESSION['user']);
});

ponos_test('API-key identity can create and list Ponos tasks', function (): void {
    ponos_db_wipe_all();
    $email = 'secbot@kvt.nl';
    $createdKey = ponos_api_key_create($email, 'Sec-Bot');
    $authed = ponos_api_key_authenticate((string) $createdKey['api_key']);
    assert_true(is_array($authed));
    ponos_set_request_user((string) $authed['user_email']);

    $groupId = 'secbot-group';
    ponos_db()->prepare(
        'INSERT INTO groups(id, name, created_at, sort_order, can_create_tasks, open_access) VALUES(?, ?, ?, 0, 1, 1)'
    )->execute([$groupId, 'SecBot', gmdate('c')]);

    $task = ponos_create_task($groupId, ponos_current_user_email(), [
        'title' => 'Voorbereiden overleg',
        'description' => 'Notities voor Tim',
        'assignee_email' => $email,
        'due_date' => '2026-09-16',
        'status' => PONOS_STATUS_TODO,
    ]);
    assert_true(is_array($task));
    assert_eq($email, $task['assignee_email']);
    assert_eq('2026-09-16', $task['due_date']);

    $done = ponos_update_task_status($groupId, $task['id'], PONOS_STATUS_DONE, ponos_current_user_email());
    assert_eq(PONOS_STATUS_DONE, $done['status']);

    $listed = ponos_list_assigned_tasks(ponos_current_user_email());
    $ids = array_map(static fn(array $item): string => (string) $item['id'], $listed);
    assert_true(in_array($task['id'], $ids, true));

    ponos_set_request_user('');
});

ponos_test('ponos_api_key CLI mints a hashed key in an isolated db', function (): void {
    $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ponos_cli_keys_' . getmypid() . '.sqlite';
    @unlink($dbPath);
    $script = dirname(__DIR__) . '/web/ponos_api_key.php';
    $cmd = 'PONOS_DB_PATH=' . escapeshellarg($dbPath)
        . ' php ' . escapeshellarg($script)
        . ' create secbot@kvt.nl Sec-Bot';
    $output = [];
    $exit = 0;
    exec($cmd . ' 2>&1', $output, $exit);
    $text = implode("\n", $output);
    assert_eq(0, $exit, $text);
    assert_true(preg_match('/ponos_[0-9a-f]{48}/', $text, $matches) === 1, $text);
    $plaintext = $matches[0];

    $pdo = new PDO('sqlite:' . $dbPath);
    $hash = (string) $pdo->query('SELECT key_hash FROM api_keys LIMIT 1')->fetchColumn();
    assert_eq(hash('sha256', $plaintext), $hash);
    assert_false(str_contains($hash, $plaintext));
    @unlink($dbPath);
});
