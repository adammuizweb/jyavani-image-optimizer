<?php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT') && !defined('ADAM_THEME')) {
    http_response_code(404);
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

[$jyioUserId] = adiwira_require_permission($pdo, JYIO_PERMISSION, false);
$returnUrl = (defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '') . '/?page=admin/settings/jyavani-image-optimizer';
$token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
if (!function_exists('csrf_check') || !csrf_check($token)) {
    if (function_exists('adiwira_redirect_with_flash')) adiwira_redirect_with_flash($returnUrl, 'error', jyio_t('Invalid CSRF token.'), 303);
    http_response_code(403);
    exit;
}

$submitted = [
    'enabled' => isset($_POST['enabled']) ? '1' : '0',
    'backend' => $_POST['backend'] ?? null,
    'max_width' => $_POST['max_width'] ?? null,
    'max_height' => $_POST['max_height'] ?? null,
    'jpeg_quality' => $_POST['jpeg_quality'] ?? null,
    'webp_quality' => $_POST['webp_quality'] ?? null,
    'avif_quality' => $_POST['avif_quality'] ?? null,
    'png_compression' => $_POST['png_compression'] ?? null,
    'auto_orient' => isset($_POST['auto_orient']) ? '1' : '0',
    'strip_metadata' => isset($_POST['strip_metadata']) ? '1' : '0',
    'no_upscale' => isset($_POST['no_upscale']) ? '1' : '0',
    'only_if_smaller' => isset($_POST['only_if_smaller']) ? '1' : '0',
    'failure_policy' => $_POST['failure_policy'] ?? null,
];
$config = jyio_normalize_config($submitted);

$ownsTransaction = false;
try {
    if ($pdo->inTransaction()) throw new RuntimeException('Image Optimizer settings cannot join an existing transaction.');
    if (!$pdo->beginTransaction()) throw new RuntimeException('Image Optimizer settings transaction failed.');
    $ownsTransaction = true;
    if (!settings_set($pdo, JYIO_SETTING_KEY, jyio_config_json($config), 1)) {
        throw new RuntimeException('Image Optimizer settings write failed.');
    }
    $summary = [
        'enabled' => $config['enabled'],
        'backend' => $config['backend'],
        'maximum' => $config['max_width'] . 'x' . $config['max_height'],
        'qualities' => [$config['jpeg_quality'], $config['webp_quality'], $config['avif_quality'], $config['png_compression']],
        'policy' => $config['failure_policy'],
    ];
    if (!function_exists('authorization_audit') || !authorization_audit(
        $pdo,
        'plugin.jyavani-image-optimizer.settings.updated',
        (int)$jyioUserId,
        null,
        'plugin_setting',
        JYIO_SETTING_KEY,
        $summary
    )) throw new RuntimeException('Image Optimizer settings audit failed.');
    $pdo->commit();
    $ownsTransaction = false;
    if (function_exists('adiwira_redirect_with_flash')) adiwira_redirect_with_flash($returnUrl, 'success', jyio_t('Image Optimizer settings saved.'), 303);
    header('Location: ' . $returnUrl, true, 303);
    exit;
} catch (Throwable $error) {
    if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
    unset($GLOBALS['__jy_settings_autoload_cache']);
    error_log('[jyio] settings save failed');
    if (function_exists('adiwira_redirect_with_flash')) adiwira_redirect_with_flash($returnUrl, 'error', jyio_t('Image Optimizer settings could not be saved.'), 303);
    header('Location: ' . $returnUrl, true, 303);
    exit;
}
