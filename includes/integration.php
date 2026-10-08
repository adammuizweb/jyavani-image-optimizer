<?php
declare(strict_types=1);

function jyio_media_upload_preprocess(string $stagePath, array $descriptor, array $context, array $extensionInput): void
{
    $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    $config = jyio_config($pdo);
    if (!$config['enabled']) return;
    $choice = $extensionInput[JYIO_PLUGIN_NAME]['optimize'] ?? '1';
    if (!is_scalar($choice) || !in_array((string)$choice, ['1', 'true', 'on', 'yes'], true)) return;
    try {
        $result = jyio_optimize_stage($stagePath, $descriptor, $config);
        if ($result['status'] === 'unsupported' && $config['failure_policy'] === 'reject') {
            throw new MediaUploadRejected(jyio_t('This image cannot be optimized with the selected backend.'), 422);
        }
    } catch (MediaUploadRejected $error) {
        throw $error;
    } catch (Throwable $error) {
        if ($config['failure_policy'] === 'reject') {
            throw new MediaUploadRejected(jyio_t('Image optimization failed. Please try another image.'), 422);
        }
    }
}

function jyio_media_admin_upload_fields(array $context, PDO $pdo): void
{
    static $styled = false;
    $checked = jyio_config($pdo)['enabled'];
    if (!$styled) {
        echo '<style>.jyio-upload-option{margin:5px 0 10px;padding:9px 11px;border:1px solid var(--adam-border,#dfe5ec);border-radius:10px;background:var(--adam-surface-3,#f7f9fb)}.jyio-upload-option__label{display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:11px}.jyio-upload-option__label span{display:grid;gap:2px}.jyio-upload-option__label small{color:var(--adam-muted,#667085);font-weight:400}</style>';
        $styled = true;
    }
    echo '<div class="jyio-upload-option">';
    echo '<input type="hidden" name="media_extension[jyavani-image-optimizer][optimize]" value="0">';
    echo '<label class="jyio-upload-option__label">';
    echo '<input type="checkbox" class="adam-choice" name="media_extension[jyavani-image-optimizer][optimize]" value="1"' . ($checked ? ' checked' : '') . '>';
    echo '<span><strong>' . jyio_h(jyio_t('Optimize this image')) . '</strong>';
    echo '<small>' . jyio_h(jyio_t('Resize and compress before the upload is published.')) . '</small></span>';
    echo '</label></div>';
}

function jyio_admin_assets(): void
{
    $page = trim((string)($_GET['page'] ?? ''), '/');
    if ($page !== 'admin/settings/jyavani-image-optimizer') return;
    echo '<link rel="stylesheet" href="/static/plugins/jyavani-image-optimizer/admin.css?v=' . rawurlencode(JYIO_VERSION) . '">' . PHP_EOL;
}

function jyio_uninstall(string $name): void
{
    if ($name !== JYIO_PLUGIN_NAME) return;
    $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    if (!$pdo instanceof PDO) throw new RuntimeException('Image Optimizer cleanup requires a database connection.');
    $statement = $pdo->prepare('DELETE FROM settings WHERE `key` = :key');
    if (!$statement->execute([':key' => JYIO_SETTING_KEY])) throw new RuntimeException('Image Optimizer setting cleanup failed.');
    if (isset($GLOBALS['__jy_settings_autoload_cache']) && is_array($GLOBALS['__jy_settings_autoload_cache'])) {
        unset($GLOBALS['__jy_settings_autoload_cache'][JYIO_SETTING_KEY]);
    }
}
