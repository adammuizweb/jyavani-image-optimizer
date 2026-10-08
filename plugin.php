<?php
declare(strict_types=1);

if (!defined('PLUGIN_SYSTEM_LOADED')) return;

const JYIO_VERSION = '0.1.0';
const JYIO_PLUGIN_NAME = 'jyavani-image-optimizer';
const JYIO_SETTING_KEY = 'jyavani_image_optimizer_config';
const JYIO_PERMISSION = 'plugin.jyavani-image-optimizer.settings.manage';

if (!function_exists('media_upload_atomic_rewrite')
    || !function_exists('media_upload_preprocess')
    || !class_exists('MediaUploadRejected')) {
    throw new RuntimeException('Jyavani Image Optimizer requires the media upload preprocessing contract in Jyavani Core 2.3.175 or newer.');
}

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/backend-gd.php';
require_once __DIR__ . '/includes/backend-imagick.php';
require_once __DIR__ . '/includes/optimizer.php';
require_once __DIR__ . '/includes/integration.php';

add_action('media_upload_preprocess', 'jyio_media_upload_preprocess', 10);
add_action('media_admin_upload_fields', 'jyio_media_admin_upload_fields', 10);
add_action('admin_head', 'jyio_admin_assets', 10);
add_action('plugin_uninstall', 'jyio_uninstall', 10);
