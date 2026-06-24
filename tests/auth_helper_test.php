<?php

declare(strict_types=1);

$authPath = dirname(__DIR__) . '/web/auth.php';
if (!is_file($authPath)) {
    return;
}

require_once $authPath;
require_once dirname(__DIR__) . '/web/auth_helper.php';
require_once dirname(__DIR__) . '/web/odata.php';

ponos_test('auth_odata_get_all can reach BC Companies endpoint', function (): void {
    $environment = auth_get_primary_environment();
    $auth = auth_get_auth_for_environment($environment);
    $urls = auth_build_companies_urls($environment);

    $rows = [];
    foreach ($urls as $url) {
        try {
            $rows = auth_odata_get_all($url, $auth, 60);
            if ($rows !== []) {
                break;
            }
        } catch (Throwable $ignored) {
        }
    }

    assert_true(is_array($rows));
    assert_true(count($rows) > 0);
});
