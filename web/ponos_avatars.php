<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/ponos_data.php';

/**
 * Constants
 */

const PONOS_AVATAR_WIDTH = 30;

const PONOS_AVATAR_HEIGHT = 30;

const PONOS_AVATAR_HALF_WIDTH = 15;

/**
 * Functies
 */

function ponos_user_avatar_dir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function ponos_user_avatar_path(string $email): string
{
    $email = strtolower(trim($email));
    $filename = preg_replace('/[^a-z0-9._\-]/', '_', $email) . '.png';

    return ponos_user_avatar_dir() . DIRECTORY_SEPARATOR . $filename;
}

function ponos_user_avatar_seed(string $email): int
{
    return ponos_hash_text_for_color(strtolower(trim($email)));
}

function ponos_avatar_lattice_value(int $seed, int $x, int $y): float
{
    $n = $seed & 0x7FFFFFFF;
    $n ^= (int) (($x * 374761393) & 0x7FFFFFFF);
    $n ^= (int) (($y * 668265263) & 0x7FFFFFFF);
    $n &= 0x7FFFFFFF;
    $n ^= ($n >> 13);
    $n = (int) (($n * 1274126177) & 0x7FFFFFFF);
    $n ^= ($n >> 16);

    return ($n & 0x7FFFFFFF) / 2147483647;
}

function ponos_avatar_smoothstep(float $value): float
{
    return $value * $value * (3 - (2 * $value));
}

function ponos_avatar_smooth_noise(float $x, float $y, int $seed): float
{
    $x0 = (int) floor($x);
    $y0 = (int) floor($y);
    $fx = ponos_avatar_smoothstep($x - $x0);
    $fy = ponos_avatar_smoothstep($y - $y0);

    $n00 = ponos_avatar_lattice_value($seed, $x0, $y0);
    $n10 = ponos_avatar_lattice_value($seed, $x0 + 1, $y0);
    $n01 = ponos_avatar_lattice_value($seed, $x0, $y0 + 1);
    $n11 = ponos_avatar_lattice_value($seed, $x0 + 1, $y0 + 1);
    $nx0 = $n00 + (($n10 - $n00) * $fx);
    $nx1 = $n01 + (($n11 - $n01) * $fx);

    return $nx0 + (($nx1 - $nx0) * $fy);
}

function ponos_avatar_fractal_noise(float $x, float $y, int $seed): float
{
    $value = 0.0;
    $amplitude = 1.0;
    $frequency = 1.0;
    $total = 0.0;

    for ($octave = 0; $octave < 4; $octave++) {
        $value += ponos_avatar_smooth_noise($x * $frequency, $y * $frequency, $seed + ($octave * 1013)) * $amplitude;
        $total += $amplitude;
        $amplitude *= 0.5;
        $frequency *= 2.0;
    }

    return $total > 0 ? $value / $total : 0.0;
}

function ponos_generate_user_avatar_png(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $path = ponos_user_avatar_path($email);
    $seed = ponos_user_avatar_seed($email);
    $colors = ponos_color_from_text($email);
    [$red, $green, $blue] = ponos_color_dark_rgb($colors);

    $image = imagecreatetruecolor(PONOS_AVATAR_WIDTH, PONOS_AVATAR_HEIGHT);
    if ($image === false) {
        return false;
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $white = imagecolorallocate($image, 255, 255, 255);
    $fill = imagecolorallocate($image, $red, $green, $blue);
    imagefilledrectangle($image, 0, 0, PONOS_AVATAR_WIDTH - 1, PONOS_AVATAR_HEIGHT - 1, $white);

    for ($y = 0; $y < PONOS_AVATAR_HEIGHT; $y++) {
        for ($x = 0; $x < PONOS_AVATAR_HALF_WIDTH; $x++) {
            $noise = ponos_avatar_fractal_noise($x / 4.5, $y / 7.5, $seed);
            if ($noise < 0.54) {
                continue;
            }

            imagesetpixel($image, $x, $y, $fill);
            imagesetpixel($image, (PONOS_AVATAR_WIDTH - 1) - $x, $y, $fill);
        }
    }

    $saved = imagepng($image, $path);
    imagedestroy($image);

    return $saved;
}

function ponos_ensure_user_avatar(string $email): bool
{
    $path = ponos_user_avatar_path($email);
    if (is_file($path)) {
        return true;
    }

    return ponos_generate_user_avatar_png($email);
}

function ponos_user_avatar_url(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    ponos_ensure_user_avatar($email);

    return 'ponos_user_avatar.php?email=' . rawurlencode($email);
}

function ponos_enrich_user_for_client(array $user): array
{
    $email = strtolower(trim((string) ($user['Email'] ?? '')));
    if ($email === '') {
        return $user;
    }

    $user['Email'] = $email;
    $user['colors'] = ponos_color_from_text($email);
    $user['avatar_url'] = ponos_user_avatar_url($email);

    return $user;
}
