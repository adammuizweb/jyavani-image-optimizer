<?php
declare(strict_types=1);

const JYIO_VERSION = '0.1.0';
const JYIO_PLUGIN_NAME = 'jyavani-image-optimizer';
const JYIO_SETTING_KEY = 'jyavani_image_optimizer_config';
const JYIO_PERMISSION = 'plugin.jyavani-image-optimizer.settings.manage';

final class MediaUploadRejected extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
    public function status(): int { return $this->httpStatus; }
}

$GLOBALS['jyio_test_settings'] = [];
function settings_get(PDO $pdo, string $key, ?string $default = null): ?string
{
    return $GLOBALS['jyio_test_settings'][$key] ?? $default;
}
function media_upload_atomic_rewrite(string $stagePath, callable $writer, int $maxBytes = 20971520, int $maxPixels = 100000000): array
{
    $output = dirname($stagePath) . '/rewrite-' . bin2hex(random_bytes(4));
    touch($output);
    chmod($output, 0600);
    try {
        $result = $writer($stagePath, $output);
        if ($result !== false) rename($output, $stagePath);
        $size = @getimagesize($stagePath);
        return ['mime' => $size['mime'] ?? '', 'size' => filesize($stagePath)];
    } finally {
        @unlink($output);
    }
}
function jyio_test_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    fwrite(STDOUT, "PASS: {$message}\n");
}
function jyio_test_temp_dir(): string
{
    $dir = sys_get_temp_dir() . '/jyio-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0700, true)) throw new RuntimeException('Unable to create test directory.');
    return $dir;
}
function jyio_test_remove_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $directory . '/' . $entry;
        if (is_dir($path)) jyio_test_remove_tree($path); else @unlink($path);
    }
    @rmdir($directory);
}

require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/backend-gd.php';
require_once dirname(__DIR__) . '/includes/backend-imagick.php';
require_once dirname(__DIR__) . '/includes/optimizer.php';
