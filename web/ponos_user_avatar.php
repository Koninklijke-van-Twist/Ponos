<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/ponos_avatars.php';

$email = strtolower(trim((string) ($_GET['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit;
}

if (!ponos_ensure_user_avatar($email)) {
    http_response_code(500);
    exit;
}

$path = ponos_user_avatar_path($email);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
