<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
$plugin = (string)file_get_contents($root . '/plugin.php');
$integration = (string)file_get_contents($root . '/includes/integration.php');
$optimizer = (string)file_get_contents($root . '/includes/optimizer.php');
jyio_test_check(str_contains($plugin, "add_action('media_upload_preprocess'") && str_contains($plugin, "add_action('media_admin_upload_fields'"), 'entrypoint registers preprocessing and upload-field hooks');
jyio_test_check(str_contains($plugin, "class_exists('MediaUploadRejected')") && str_contains($plugin, "function_exists('media_upload_atomic_rewrite')"), 'entrypoint fails clearly when the Core contract is absent');
jyio_test_check(str_contains($optimizer, 'media_upload_atomic_rewrite('), 'all optimizer replacement uses the Core atomic rewrite helper');
jyio_test_check(str_contains($integration, 'media_extension[jyavani-image-optimizer][optimize]') && str_contains($integration, 'type="hidden"') && str_contains($integration, 'type="checkbox"'), 'bounded upload field supports an explicit unchecked value');
jyio_test_check(substr_count($integration, 'media_extension[jyavani-image-optimizer][optimize]') === 2, 'upload field uses one hidden value and one checkbox value');
jyio_test_check(str_contains($integration, "['optimize'] ?? '1'"), 'non-UI uploads inherit the globally enabled default');

$core = getenv('JYAVANI_CORE') ?: '/var/www/jyavani.lan';
if (is_file($core . '/dashboard/admin/upload_image.php')) {
    $upload = (string)file_get_contents($core . '/dashboard/admin/upload_image.php');
    $fields = (string)file_get_contents($core . '/dashboard/admin/media/add.php') . (string)file_get_contents($core . '/dashboard/admin/modal_img/add_modal.php');
    $preprocess = strpos($upload, 'media_upload_preprocess($stagePath');
    $final = strpos($upload, 'media_upload_inspect_image($stagePath)', $preprocess === false ? 0 : $preprocess + 1);
    jyio_test_check($preprocess !== false && $final !== false && $preprocess < $final, 'Core preprocesses the private stage before final validation');
    jyio_test_check(substr_count($fields, "do_action('media_admin_upload_fields'") === 2, 'both Core media upload surfaces expose the field hook');
} else {
    fwrite(STDOUT, "SKIP: Jyavani Core upload source is unavailable\n");
}
