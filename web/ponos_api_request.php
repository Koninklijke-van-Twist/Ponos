<?php

/**
 * Functies
 */

function ponos_api_merge_json_body(?string $rawBody = null): void
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if ($contentType !== '' && !str_contains($contentType, 'application/json')) {
        return;
    }

    $raw = $rawBody;
    if ($raw === null) {
        $raw = file_get_contents('php://input');
    }
    if (!is_string($raw) || trim($raw) === '') {
        return;
    }

    $trimmed = ltrim($raw);
    if ($contentType === '' && !str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '[')) {
        return;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return;
    }

    foreach ($decoded as $key => $value) {
        $key = (string) $key;
        if (array_key_exists($key, $_POST)) {
            continue;
        }

        if (is_array($value)) {
            if ($key === 'checklist') {
                $_POST[$key] = json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            continue;
        }

        if (is_bool($value)) {
            $scalar = $value ? '1' : '0';
        } elseif ($value === null) {
            $scalar = '';
        } else {
            $scalar = (string) $value;
        }

        $_POST[$key] = $scalar;
        if (!array_key_exists($key, $_GET)) {
            $_GET[$key] = $scalar;
        }
    }
}

function ponos_api_request_api_key(): string
{
    $headerKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($headerKey !== '') {
        return $headerKey;
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'x-api-key') {
                return trim((string) $value);
            }
        }
    }

    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($authorization === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                $authorization = trim((string) $value);
                break;
            }
        }
    }

    if ($authorization !== '' && preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches) === 1) {
        return trim((string) ($matches[1] ?? ''));
    }

    return trim((string) ($_POST['api_key'] ?? $_GET['api_key'] ?? ''));
}
