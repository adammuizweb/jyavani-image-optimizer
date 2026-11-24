<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$defaults = jyio_default_config();
jyio_test_check($defaults['enabled'] === true && $defaults['backend'] === 'auto', 'activation-by-presence defaults are enabled and automatic');
jyio_test_check($defaults['png_output'] === 'preserve', 'PNG output defaults to backward-safe preservation');
jyio_test_check($defaults['max_width'] === 2560 && $defaults['max_height'] === 2560, 'default dimensions are conservative');
jyio_test_check($defaults['failure_policy'] === 'keep_original' && $defaults['only_if_smaller'] === true, 'default failure and size policies preserve uploads');

$normalized = jyio_normalize_config([
    'enabled' => '0', 'backend' => 'GD', 'png_output' => 'WebP', 'max_width' => '800', 'max_height' => '900',
    'jpeg_quality' => '95', 'webp_quality' => '75', 'avif_quality' => '44',
    'png_compression' => '9', 'auto_orient' => 'false', 'strip_metadata' => '1',
    'no_upscale' => '0', 'only_if_smaller' => 'yes', 'failure_policy' => 'reject',
]);
jyio_test_check($normalized['enabled'] === false && $normalized['backend'] === 'gd' && $normalized['png_output'] === 'webp', 'boolean and enum inputs normalize');
jyio_test_check($normalized['max_width'] === 800 && $normalized['png_compression'] === 9, 'bounded numeric inputs normalize');
jyio_test_check($normalized['auto_orient'] === false && $normalized['failure_policy'] === 'reject', 'policy inputs normalize');
jyio_test_check(jyio_normalize_config(['max_width' => '../../etc'])['max_width'] === 2560, 'invalid settings fall back without coercion');
jyio_test_check(jyio_normalize_config(['png_output' => 'gif'])['png_output'] === 'preserve', 'unsupported PNG output falls back to preservation');

$root = dirname(__DIR__);
$settingsSource = (string)file_get_contents($root . '/admin/settings.php');
$saveSource = (string)file_get_contents($root . '/admin/save.php');
jyio_test_check(str_contains($settingsSource, 'name="png_output"') && str_contains($saveSource, "'png_output'"), 'PNG output mode is rendered and saved');
jyio_test_check(str_contains($settingsSource, 'JPEG converts transparent pixels to white.'), 'settings explicitly warn about JPEG transparency flattening');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $GLOBALS['jyio_test_settings'][JYIO_SETTING_KEY] = jyio_config_json($normalized);
    jyio_test_check(jyio_config($pdo) === $normalized, 'stored JSON configuration loads through the setting API');
    $GLOBALS['jyio_test_settings'][JYIO_SETTING_KEY] = '{bad json';
    jyio_test_check(jyio_config($pdo) === $defaults, 'malformed stored JSON safely returns defaults');
} else {
    fwrite(STDOUT, "SKIP: PDO SQLite is unavailable for setting-load behavior\n");
}
