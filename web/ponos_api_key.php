<?php

declare(strict_types=1);

/**
 * Mint / list / revoke durable Ponos API keys (CLI only).
 *
 * php web/ponos_api_key.php create EMAIL [label] [actor_name]
 * php web/ponos_api_key.php list [EMAIL]
 * php web/ponos_api_key.php revoke ID [EMAIL]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/ponos_api_keys.php';

$command = strtolower(trim((string) ($argv[1] ?? 'help')));

if ($command === 'help' || $command === '-h' || $command === '--help') {
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php web/ponos_api_key.php create EMAIL [label] [actor_name]\n");
    fwrite(STDOUT, "  php web/ponos_api_key.php list [EMAIL]\n");
    fwrite(STDOUT, "  php web/ponos_api_key.php revoke ID [EMAIL]\n");
    exit(0);
}

if ($command === 'create') {
    $email = trim((string) ($argv[2] ?? ''));
    $label = trim((string) ($argv[3] ?? 'bot'));
    $actorName = trim((string) ($argv[4] ?? ''));
    $created = ponos_api_key_create($email, $label, $actorName);
    if ($created === null) {
        fwrite(STDERR, "Invalid email.\n");
        exit(1);
    }

    $actorSuffix = $created['actor_name'] !== '' ? " actor_name={$created['actor_name']}" : '';
    fwrite(STDOUT, "Created API key id={$created['id']} for {$created['user_email']} ({$created['label']}){$actorSuffix}\n");
    fwrite(STDOUT, "Prefix: {$created['key_prefix']}…\n");
    fwrite(STDOUT, "Store this secret once; it is not saved in plaintext:\n");
    fwrite(STDOUT, $created['api_key'] . "\n");
    exit(0);
}

if ($command === 'list') {
    $email = trim((string) ($argv[2] ?? ''));
    if ($email === '') {
        $stmt = ponos_db()->query(
            'SELECT id, user_email, label, actor_name, key_prefix, created_at, last_used_at, revoked_at
             FROM api_keys
             ORDER BY user_email ASC, created_at DESC, id DESC'
        );
        $rows = $stmt->fetchAll();
    } else {
        $rows = ponos_api_key_list($email, true);
    }

    if ($rows === []) {
        fwrite(STDOUT, "No API keys.\n");
        exit(0);
    }

    foreach ($rows as $row) {
        $public = ponos_api_key_public_row($row);
        $state = !empty($public['revoked']) ? 'revoked' : 'active';
        fwrite(STDOUT, sprintf(
            "#%d %s %s %s %s%s last_used=%s\n",
            (int) $public['id'],
            $state,
            $public['user_email'],
            $public['label'],
            $public['key_prefix'] . '…',
            $public['actor_name'] !== '' ? ' actor_name=' . $public['actor_name'] : '',
            $public['last_used_at'] !== '' ? $public['last_used_at'] : '-'
        ));
    }
    exit(0);
}

if ($command === 'revoke') {
    $id = (int) ($argv[2] ?? 0);
    $email = trim((string) ($argv[3] ?? ''));
    if ($id <= 0) {
        fwrite(STDERR, "Key id required.\n");
        exit(1);
    }

    if ($email === '') {
        $stmt = ponos_db()->prepare('SELECT user_email FROM api_keys WHERE id = ?');
        $stmt->execute([$id]);
        $email = (string) ($stmt->fetchColumn() ?: '');
    }

    if (!ponos_api_key_revoke($id, $email)) {
        fwrite(STDERR, "Key not found or already revoked.\n");
        exit(1);
    }

    fwrite(STDOUT, "Revoked API key #{$id}\n");
    exit(0);
}

fwrite(STDERR, "Unknown command. Use help.\n");
exit(1);
