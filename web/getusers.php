<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';

$users = include __DIR__ . '/getusers_fetch.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(is_array($users) ? $users : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
