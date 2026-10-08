<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 32, JSON_THROW_ON_ERROR);
jyio_test_check($manifest['name'] === JYIO_PLUGIN_NAME && $manifest['version'] === JYIO_VERSION, 'manifest identity and version are exact');
jyio_test_check($manifest['requires']['jyavani'] === '>=2.3.175' && $manifest['requires']['php'] === '>=8.1', 'future Core and PHP requirements are declared');
jyio_test_check($manifest['requires']['extensions'] === ['json', 'mbstring', 'pdo'], 'only required baseline extensions are declared');
jyio_test_check($manifest['permissions'][0]['delegable'] === false && $manifest['permissions'][0]['default_roles'] === ['admin'], 'settings permission is admin-default and nondelegable');
jyio_test_check($manifest['assets'] === ['css' => [], 'js' => []], 'no dashboard-wide plugin assets are declared');

$core = getenv('JYAVANI_CORE') ?: '/var/www/jyavani.lan';
if (is_file($core . '/plugins/index.php')) {
    define('BACKEND_PATH', $core . '/cfg');
    define('PUBLIC_PATH', $core . '/public');
    define('DASH_PATH', $core . '/dashboard');
    define('PLUGIN_PATH', dirname($root));
    define('PLUGIN_DISABLED_JSON', sys_get_temp_dir() . '/jyio-disabled-' . getmypid() . '.json');
    require_once $core . '/cfg/helpers/hooks.php';
    require_once $core . '/plugins/index.php';
    jyio_test_check(plugin_manifest_contract_errors($manifest) === [], 'actual Jyavani manifest validator accepts the manifest');
} else {
    fwrite(STDOUT, "SKIP: Jyavani Core validator is unavailable\n");
}
