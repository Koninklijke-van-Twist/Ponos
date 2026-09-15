<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_db.php';
require_once __DIR__ . '/ponos_api_request.php';

/**
 * Constants
 */

const PONOS_API_KEY_PREFIX = 'ponos_';

const PONOS_API_KEY_LABEL_MAX_LENGTH = 80;

/**
 * Functies
 */

function ponos_api_key_generate_plaintext(): string
{
    return PONOS_API_KEY_PREFIX . bin2hex(random_bytes(24));
}

function ponos_api_key_hash(string $plaintext): string
{
    return hash('sha256', $plaintext);
}

function ponos_api_key_prefix(string $plaintext): string
{
    $plaintext = trim($plaintext);
    if ($plaintext === '') {
        return '';
    }

    return substr($plaintext, 0, 12);
}

function ponos_api_key_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function ponos_api_key_normalize_label(string $label): string
{
    $label = trim($label);
    if ($label === '') {
        return 'bot';
    }

    if (mb_strlen($label) > PONOS_API_KEY_LABEL_MAX_LENGTH) {
        return mb_substr($label, 0, PONOS_API_KEY_LABEL_MAX_LENGTH);
    }

    return $label;
}

function ponos_api_key_public_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'user_email' => ponos_api_key_normalize_email((string) ($row['user_email'] ?? '')),
        'label' => (string) ($row['label'] ?? ''),
        'key_prefix' => (string) ($row['key_prefix'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'last_used_at' => (string) ($row['last_used_at'] ?? ''),
        'revoked_at' => (string) ($row['revoked_at'] ?? ''),
        'revoked' => trim((string) ($row['revoked_at'] ?? '')) !== '',
    ];
}

function ponos_api_key_create(string $email, string $label = ''): ?array
{
    $email = ponos_api_key_normalize_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $plaintext = ponos_api_key_generate_plaintext();
    $now = gmdate('c');
    $pdo = ponos_db();
    $pdo->prepare(
        'INSERT INTO api_keys(user_email, label, key_prefix, key_hash, created_at)
         VALUES(?, ?, ?, ?, ?)'
    )->execute([
        $email,
        ponos_api_key_normalize_label($label),
        ponos_api_key_prefix($plaintext),
        ponos_api_key_hash($plaintext),
        $now,
    ]);

    $id = (int) $pdo->lastInsertId();

    return [
        'id' => $id,
        'user_email' => $email,
        'label' => ponos_api_key_normalize_label($label),
        'key_prefix' => ponos_api_key_prefix($plaintext),
        'api_key' => $plaintext,
        'created_at' => $now,
    ];
}

function ponos_api_key_authenticate(string $plaintext): ?array
{
    $plaintext = trim($plaintext);
    if ($plaintext === '') {
        return null;
    }

    $stmt = ponos_db()->prepare(
        'SELECT id, user_email, label, key_prefix, created_at, last_used_at, revoked_at
         FROM api_keys
         WHERE key_hash = ? AND (revoked_at IS NULL OR TRIM(revoked_at) = "")
         LIMIT 1'
    );
    $stmt->execute([ponos_api_key_hash($plaintext)]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $now = gmdate('c');
    ponos_db()->prepare('UPDATE api_keys SET last_used_at = ? WHERE id = ?')
        ->execute([$now, (int) $row['id']]);
    $row['last_used_at'] = $now;

    return ponos_api_key_public_row($row);
}

function ponos_api_key_list(string $email, bool $includeRevoked = false): array
{
    $email = ponos_api_key_normalize_email($email);
    if ($email === '') {
        return [];
    }

    $sql = 'SELECT id, user_email, label, key_prefix, created_at, last_used_at, revoked_at
            FROM api_keys
            WHERE LOWER(user_email) = ?';
    if (!$includeRevoked) {
        $sql .= ' AND (revoked_at IS NULL OR TRIM(revoked_at) = "")';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC';

    $stmt = ponos_db()->prepare($sql);
    $stmt->execute([$email]);

    $keys = [];
    foreach ($stmt->fetchAll() as $row) {
        $keys[] = ponos_api_key_public_row($row);
    }

    return $keys;
}

function ponos_api_key_revoke(int $id, string $email): bool
{
    if ($id <= 0) {
        return false;
    }

    $email = ponos_api_key_normalize_email($email);
    if ($email === '') {
        return false;
    }

    $stmt = ponos_db()->prepare(
        'SELECT id FROM api_keys
         WHERE id = ? AND LOWER(user_email) = ? AND (revoked_at IS NULL OR TRIM(revoked_at) = "")'
    );
    $stmt->execute([$id, $email]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }

    ponos_db()->prepare('UPDATE api_keys SET revoked_at = ? WHERE id = ?')
        ->execute([gmdate('c'), $id]);

    return true;
}

function ponos_api_key_email_is_allowed(string $email): bool
{
    global $allowedUsers;

    $email = ponos_api_key_normalize_email($email);
    if ($email === '') {
        return false;
    }

    if (!isset($allowedUsers) || !is_array($allowedUsers) || $allowedUsers === []) {
        return true;
    }

    foreach ($allowedUsers as $allowed) {
        if (strtolower(trim((string) $allowed)) === $email) {
            return true;
        }
    }

    return false;
}

function ponos_api_apply_authenticated_user(string $email): void
{
    ponos_set_request_user($email);
}
