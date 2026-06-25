<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/localization.php';

/**
 * Constants
 */

const PONOS_DEFAULT_USER_EMAIL = 'localtester@kvt.nl';

const PONOS_GROUP_MY_TASKS = '__my_tasks__';

const PONOS_STATUS_TODO = 'todo';

const PONOS_STATUS_IN_PROGRESS = 'in_progress';

const PONOS_STATUS_DONE = 'done';

/**
 * Functies
 */

function ponos_current_user_email(): string
{
    $email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));

    return $email !== '' ? $email : PONOS_DEFAULT_USER_EMAIL;
}

function ponos_is_localhost_request(): bool
{
    if (function_exists('is_trusted_requester')) {
        return is_trusted_requester();
    }

    if (PHP_SAPI === 'cli') {
        return false;
    }

    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }

    $host = preg_replace('/:\d+$/', '', $host);

    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}

function ponos_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

function ponos_localhost_admin_enabled(): bool
{
    if (!ponos_is_localhost_request()) {
        return false;
    }

    ponos_ensure_session();
    if (!array_key_exists('ponos_dev_admin', $_SESSION)) {
        $_SESSION['ponos_dev_admin'] = true;
    }

    return !empty($_SESSION['ponos_dev_admin']);
}

function ponos_set_localhost_admin(bool $enabled): void
{
    if (!ponos_is_localhost_request()) {
        return;
    }

    ponos_ensure_session();
    $_SESSION['ponos_dev_admin'] = $enabled;
}

function ponos_current_user_is_admin(): bool
{
    if (ponos_is_localhost_request()) {
        return ponos_localhost_admin_enabled();
    }

    return !empty($_SESSION['user']['admin']);
}

function ponos_data_dir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ponos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_load_user_navigation_prefs(string $email): array
{
    if (!function_exists('loadUserPrefs')) {
        return [];
    }

    $prefs = loadUserPrefs($email);

    return [
        'group' => trim((string) ($prefs['ponos_group'] ?? '')),
    ];
}

function ponos_save_user_navigation_prefs(string $email, string $group): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    saveUserPref($email, 'ponos_group', $group);
}

function ponos_hash_text_for_color(string $text): int
{
    $hash = 0;
    $length = strlen($text);
    for ($index = 0; $index < $length; $index++) {
        $hash = (int) (ord($text[$index]) + (($hash << 5) - $hash));
        $hash &= 0x7FFFFFFF;
    }

    return $hash;
}

function ponos_color_from_text(string $text): array
{
    $normalized = strtolower(trim($text));
    if ($normalized === '') {
        return [
            'border' => '#cbd5e1',
            'dark' => '#64748b',
            'light' => '#94a3b8',
            'chipBackground' => '#e2e8f0',
            'cardBackground' => '#ffffff',
            'chipTextColor' => '#334155',
        ];
    }

    $hash = ponos_hash_text_for_color($normalized);
    $hue = abs($hash) % 360;
    $saturation = 72 + (abs($hash >> 8) % 14);
    $lightness = 56 + (abs($hash >> 16) % 10);
    $borderLightness = max($lightness - 6, 48);
    $darkLightness = max($lightness - 18, 32);
    $chipTextColor = $lightness >= 58 ? '#1e293b' : '#ffffff';

    return [
        'border' => "hsl({$hue}, {$saturation}%, {$borderLightness}%)",
        'dark' => "hsl({$hue}, {$saturation}%, {$darkLightness}%)",
        'light' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'chipBackground' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'cardBackground' => 'hsl(' . $hue . ', ' . min($saturation, 48) . '%, 96%)',
        'chipTextColor' => $chipTextColor,
    ];
}

function ponos_category_color_from_text(string $text): array
{
    $normalized = strtolower(trim($text));
    if ($normalized === '') {
        return ponos_color_from_text('');
    }

    $hash = ponos_hash_text_for_color($normalized);
    $hue = fmod(abs($hash) * 137.508, 360.0);
    $saturation = 68 + (abs($hash >> 4) % 14);
    $lightness = 46 + (abs($hash >> 10) % 16);
    $borderLightness = max($lightness - 6, 40);
    $darkLightness = max($lightness - 20, 30);
    $chipTextColor = $lightness >= 58 ? '#1e293b' : '#ffffff';

    return [
        'border' => 'hsl(' . round($hue, 2) . ', ' . $saturation . '%, ' . $borderLightness . '%)',
        'dark' => 'hsl(' . round($hue, 2) . ', ' . $saturation . '%, ' . $darkLightness . '%)',
        'light' => 'hsl(' . round($hue, 2) . ', ' . $saturation . '%, ' . $lightness . '%)',
        'chipBackground' => 'hsl(' . round($hue, 2) . ', ' . $saturation . '%, ' . $lightness . '%)',
        'cardBackground' => 'hsl(' . round($hue, 2) . ', ' . min($saturation, 48) . '%, 96%)',
        'chipTextColor' => $chipTextColor,
    ];
}

function ponos_parse_hsl_color(string $hsl): ?array
{
    if (!preg_match('/hsl\(\s*([\d.]+)\s*,\s*([\d.]+)%\s*,\s*([\d.]+)%\s*\)/', $hsl, $matches)) {
        return null;
    }

    return [
        (float) $matches[1],
        (float) $matches[2],
        (float) $matches[3],
    ];
}

function ponos_hsl_to_rgb(float $hue, float $saturation, float $lightness): array
{
    $saturation /= 100;
    $lightness /= 100;
    $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
    $huePrime = fmod($hue, 360.0) / 60.0;
    $second = $chroma * (1 - abs(fmod($huePrime, 2) - 1));

    $red = $green = $blue = 0.0;
    if ($huePrime >= 0 && $huePrime < 1) {
        $red = $chroma;
        $green = $second;
    } elseif ($huePrime < 2) {
        $red = $second;
        $green = $chroma;
    } elseif ($huePrime < 3) {
        $green = $chroma;
        $blue = $second;
    } elseif ($huePrime < 4) {
        $green = $second;
        $blue = $chroma;
    } elseif ($huePrime < 5) {
        $red = $second;
        $blue = $chroma;
    } else {
        $red = $chroma;
        $blue = $second;
    }

    $match = $lightness - ($chroma / 2);

    return [
        (int) round(($red + $match) * 255),
        (int) round(($green + $match) * 255),
        (int) round(($blue + $match) * 255),
    ];
}

function ponos_color_dark_rgb(array $colors): array
{
    $hsl = ponos_parse_hsl_color((string) ($colors['dark'] ?? ''));
    if ($hsl === null) {
        return [100, 116, 139];
    }

    return ponos_hsl_to_rgb($hsl[0], $hsl[1], $hsl[2]);
}

function ponos_status_label(string $status): string
{
    return match ($status) {
        PONOS_STATUS_IN_PROGRESS => LOC('ponos.status.in_progress'),
        PONOS_STATUS_DONE => LOC('ponos.status.done'),
        default => LOC('ponos.status.todo'),
    };
}

function ponos_all_statuses(): array
{
    return [PONOS_STATUS_TODO, PONOS_STATUS_IN_PROGRESS, PONOS_STATUS_DONE];
}

function ponos_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ponos_url(array $params = []): array
{
    $query = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }

    unset($query['lang']);
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'), '?') ?: 'index.php';
    $query['lang'] = getCurrentLanguage();

    return $path . '?' . http_build_query($query);
}

function ponos_new_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
}
