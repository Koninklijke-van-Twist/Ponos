<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/localization.php';

const PONOS_API_KEY_UI_I18N_KEYS = [
    'ponos.settings.title',
    'ponos.settings.email_title',
    'ponos.settings.api_keys.title',
    'ponos.settings.api_keys.hint',
    'ponos.settings.api_keys.label',
    'ponos.settings.api_keys.label_placeholder',
    'ponos.settings.api_keys.create',
    'ponos.settings.api_keys.empty',
    'ponos.settings.api_keys.id',
    'ponos.settings.api_keys.created',
    'ponos.settings.api_keys.revoke',
    'ponos.settings.api_keys.revoke_confirm',
    'ponos.settings.api_keys.reveal_title',
    'ponos.settings.api_keys.reveal_hint',
    'ponos.settings.api_keys.copy',
    'ponos.settings.api_keys.copied',
    'ponos.settings.api_keys.dismiss',
];

ponos_test('API-key settings i18n exists in all locales', function (): void {
    foreach (array_keys(SUPPORTED_LANGUAGES) as $lang) {
        assert_true(isset(TRANSLATIONS[$lang]), $lang);
        foreach (PONOS_API_KEY_UI_I18N_KEYS as $key) {
            assert_true(isset(TRANSLATIONS[$lang][$key]), $lang . ' missing ' . $key);
            assert_true(trim((string) TRANSLATIONS[$lang][$key]) !== '', $lang . ' empty ' . $key);
        }
    }
});

ponos_test('settings modal HTML exposes create/list/revoke UI without a stored plaintext key', function (): void {
    $html = (string) file_get_contents(dirname(__DIR__) . '/web/index.php');
    assert_true(str_contains($html, 'id="ponos-settings-modal"'));
    assert_true(str_contains($html, 'id="ponos-api-keys-title"'));
    assert_true(str_contains($html, 'id="ponos-api-key-list"'));
    assert_true(str_contains($html, 'id="ponos-api-key-create"'));
    assert_true(str_contains($html, 'id="ponos-api-key-label"'));
    assert_true(str_contains($html, 'id="ponos-api-key-reveal"'));
    assert_true(str_contains($html, 'id="ponos-api-key-plaintext"'));
    assert_true(str_contains($html, 'id="ponos-api-key-dismiss"'));
    assert_true(str_contains($html, 'ponos.settings.api_keys.create'));
    assert_false(preg_match('/id="ponos-api-key-plaintext"[^>]*>\s*ponos_/', $html) === 1);
    assert_false(str_contains($html, 'name="api_key"'));
    foreach (PONOS_API_KEY_UI_I18N_KEYS as $key) {
        assert_true(str_contains($html, "'" . $key . "'"), 'i18nKeys missing ' . $key);
    }
});

ponos_test('settings JS mints and revokes via POST body and clears the one-time reveal', function (): void {
    $js = (string) file_get_contents(dirname(__DIR__) . '/web/ponos.js');
    assert_true(str_contains($js, "fetch(apiUrl('create_api_key')"));
    assert_false(str_contains($js, "apiUrl('create_api_key',"));
    assert_true(str_contains($js, "body.set('action', 'create_api_key')"));
    assert_true(str_contains($js, "body.set('label', label)"));
    assert_true(str_contains($js, "fetch(apiFetchUrl('list_api_keys')"));
    assert_true(str_contains($js, "fetch(apiUrl('revoke_api_key')"));
    assert_false(str_contains($js, "apiUrl('revoke_api_key',"));
    assert_true(str_contains($js, "body.set('action', 'revoke_api_key')"));
    assert_true(str_contains($js, "body.set('id', String(keyId))"));
    assert_true(str_contains($js, 'function clearApiKeyReveal()'));
    assert_true(str_contains($js, 'el.apiKeyPlaintext.textContent = \'\''));
    assert_true(preg_match('/function hideSettingsModal\(\) \{\s*clearApiKeyReveal\(\)/s', $js) === 1);
    assert_true(str_contains($js, "el.apiKeyDismiss.addEventListener('click', clearApiKeyReveal)"));
    assert_false(str_contains($js, 'state.apiKey'));
    assert_false(str_contains($js, 'state.api_key'));
    assert_false(str_contains($js, "searchParams.set('api_key'"));
});
