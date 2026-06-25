<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/ponos_data.php';

ponos_test('ponos_hash_text_for_color is deterministic', function (): void {
    assert_eq(ponos_hash_text_for_color('test@example.com'), ponos_hash_text_for_color('test@example.com'));
    assert_true(ponos_hash_text_for_color('a') !== ponos_hash_text_for_color('b'));
});

ponos_test('ponos_color_from_text returns defaults for empty input', function (): void {
    $colors = ponos_color_from_text('');
    assert_eq('#cbd5e1', $colors['border']);
    assert_array_has_key('dark', $colors);
    assert_array_has_key('light', $colors);
});

ponos_test('ponos_save_user_navigation_prefs stores group preference', function (): void {
    require_once dirname(__DIR__) . '/web/localization.php';

    $email = 'ponos-prefs-group-test@kvt.nl';
    $path = getUserPrefsPath($email);
    if ($path !== null && is_file($path)) {
        unlink($path);
    }

    ponos_save_user_navigation_prefs($email, 'group-abc');
    $prefs = ponos_load_user_navigation_prefs($email);
    assert_eq('group-abc', $prefs['group']);

    ponos_save_user_navigation_prefs($email, '');
    $prefs = ponos_load_user_navigation_prefs($email);
    assert_eq('', $prefs['group']);

    if ($path !== null && is_file($path)) {
        unlink($path);
    }
});

ponos_test('ponos_format_display_date formats ISO dates readably', function (): void {
    require_once dirname(__DIR__) . '/web/localization.php';

    $formatted = ponos_format_display_date('2026-06-15');
    assert_true(str_contains($formatted, '15'));
    assert_true(str_contains($formatted, '2026'));
    assert_true(str_contains(strtolower($formatted), 'jun'));
});

ponos_test('ponos_localhost admin toggle overrides admin status', function (): void {
    if (!ponos_is_localhost_request()) {
        return;
    }

    ponos_set_localhost_admin(true);
    assert_true(ponos_current_user_is_admin());
    ponos_set_localhost_admin(false);
    assert_false(ponos_current_user_is_admin());
    ponos_set_localhost_admin(true);
});

ponos_test('ponos_current_user_is_admin defaults true on localhost', function (): void {
    if (!ponos_is_localhost_request()) {
        return;
    }

    ponos_set_localhost_admin(true);
    assert_true(ponos_current_user_is_admin());
});
