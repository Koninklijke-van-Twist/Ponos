<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/ponos_avatars.php';

$users = include __DIR__ . '/getusers_fetch.php';
if (!is_array($users)) {
    $users = [];
}

$users = array_map(static fn(array $user): array => ponos_enrich_user_for_client($user), $users);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(is_array($users) ? $users : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
