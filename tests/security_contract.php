<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
$save = (string)file_get_contents($root . '/admin/save.php');
$integration = (string)file_get_contents($root . '/includes/integration.php');
$allRuntime = $integration . (string)file_get_contents($root . '/includes/backend-gd.php') . (string)file_get_contents($root . '/includes/backend-imagick.php');
jyio_test_check(str_contains($save, "REQUEST_METHOD") && str_contains($save, "'POST'") && str_contains($save, "header('Allow: POST')"), 'save route is POST-only');
jyio_test_check(str_contains($save, "defined('DASHBOARD_CONTEXT')"), 'save route rejects execution outside the dashboard dispatcher');
jyio_test_check(str_contains($save, 'adiwira_require_permission($pdo, JYIO_PERMISSION') && str_contains($save, 'csrf_check($token)'), 'save route repeats permission and CSRF checks');
jyio_test_check(str_contains($save, 'authorization_audit(') && str_contains($save, "'qualities'"), 'settings changes audit a bounded configuration summary');
jyio_test_check(!str_contains($allRuntime, 'error_log('), 'image processing never logs paths, data, EXIF, or metadata');
jyio_test_check(!str_contains($integration, '$error->getMessage()'), 'upload errors never expose backend exception details');
jyio_test_check(str_contains($integration, "DELETE FROM settings WHERE `key` = :key") && !str_contains($integration, 'DROP TABLE'), 'complete uninstall deletes only the owned setting');
jyio_test_check(!str_contains((string)file_get_contents($root . '/plugin.json'), 'imagick"') && !str_contains((string)file_get_contents($root . '/plugin.json'), 'gd"'), 'optional image backends are not required extensions');
