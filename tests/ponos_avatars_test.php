<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/ponos_data.php';
require_once dirname(__DIR__) . '/web/ponos_avatars.php';

function ponos_test_parse_hue(string $hsl): float
{
    $parts = ponos_parse_hsl_color($hsl);
    assert_true($parts !== null);

    return $parts[0];
}

function ponos_test_hue_distance(float $a, float $b): float
{
    $distance = abs($a - $b);
    if ($distance > 180) {
        $distance = 360 - $distance;
    }

    return $distance;
}

ponos_test('ponos_category_color_from_text spreads hues more than user colors', function (): void {
    $labels = [];
    for ($index = 0; $index < 8; $index++) {
        $labels[] = 'category-' . $index;
    }

    $categoryDistances = [];
    $userDistances = [];

    for ($left = 0; $left < count($labels) - 1; $left++) {
        for ($right = $left + 1; $right < count($labels); $right++) {
            $categoryDistances[] = ponos_test_hue_distance(
                ponos_test_parse_hue(ponos_category_color_from_text($labels[$left])['dark']),
                ponos_test_parse_hue(ponos_category_color_from_text($labels[$right])['dark'])
            );
            $userDistances[] = ponos_test_hue_distance(
                ponos_test_parse_hue(ponos_color_from_text($labels[$left])['dark']),
                ponos_test_parse_hue(ponos_color_from_text($labels[$right])['dark'])
            );
        }
    }

    $categoryAverage = array_sum($categoryDistances) / count($categoryDistances);
    $userAverage = array_sum($userDistances) / count($userDistances);

    assert_true($categoryAverage > $userAverage);
    assert_true(min($categoryDistances) >= 24);
});

ponos_test('ponos_category_color_from_text is deterministic', function (): void {
    $first = ponos_category_color_from_text('release');
    $second = ponos_category_color_from_text('release');
    assert_eq($first['dark'], $second['dark']);
});

ponos_test('ponos_generate_user_avatar_png creates a symmetric icon once', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }

    $email = 'avatar-test-' . getmypid() . '@kvt.nl';
    $path = ponos_user_avatar_path($email);
    if (is_file($path)) {
        unlink($path);
    }

    assert_true(ponos_generate_user_avatar_png($email));
    assert_true(is_file($path));
    assert_true(filesize($path) > 100);
    assert_true(ponos_ensure_user_avatar($email));

    $image = imagecreatefrompng($path);
    assert_true($image !== false);

    $width = imagesx($image);
    $height = imagesy($image);
    assert_eq(PONOS_AVATAR_WIDTH, $width);
    assert_eq(PONOS_AVATAR_HEIGHT, $height);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < (int) floor($width / 2); $x++) {
            $left = imagecolorat($image, $x, $y);
            $right = imagecolorat($image, $width - 1 - $x, $y);
            assert_eq($left, $right);
        }
    }

    imagedestroy($image);
    unlink($path);
});

ponos_test('ponos_user_avatar_url returns endpoint for valid email', function (): void {
    $url = ponos_user_avatar_url('avatar-url-test@kvt.nl');
    assert_true(str_contains($url, 'ponos_user_avatar.php?email='));
});
