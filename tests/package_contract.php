<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
$builder = (string)file_get_contents($root . '/tools/build-package.php');
jyio_test_check(str_contains($builder, '$allowed = [') && str_contains($builder, "'plugin.json'") && str_contains($builder, "'includes/optimizer.php'"), 'package builder uses an explicit source allowlist');
jyio_test_check(str_contains($builder, 'Packages must be built outside the source repository.'), 'package builder refuses in-tree output');
jyio_test_check(str_contains($builder, 'The package output already exists.'), 'package builder refuses to overwrite an existing artifact');
jyio_test_check(str_contains($builder, 'Package reopen verification failed.'), 'package builder verifies reopened ZIP entries');
jyio_test_check(str_contains($builder, "['name']") && str_contains($builder, "['version']"), 'package builder verifies embedded manifest identity');

$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 32, JSON_THROW_ON_ERROR);
foreach ($manifest['static']['copy'] as $copy) {
    jyio_test_check(is_file($root . '/' . $copy['from']), 'static.copy source exists: ' . $copy['from']);
    jyio_test_check(str_starts_with($copy['to'], 'static/plugins/' . JYIO_PLUGIN_NAME . '/'), 'static.copy destination is plugin-owned: ' . $copy['to']);
}
jyio_test_check($manifest['admin']['nav'][0]['icon_asset'] === $manifest['static']['copy'][0]['to'], 'sidebar icon exactly matches a static.copy destination');
