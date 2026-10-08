<?php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT') && !defined('ADAM_THEME')) {
    if (function_exists('adiwira_admin_404')) adiwira_admin_404();
    http_response_code(404);
    return;
}

[$jyioUserId] = adiwira_require_permission($pdo, JYIO_PERMISSION, false);
$config = jyio_config($pdo);
$capabilities = jyio_capabilities();
$effective = jyio_effective_backend($config, $capabilities);
$base = defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '';
$saveUrl = $base . '/?page=admin/settings/jyavani-image-optimizer/save';
$flashItems = function_exists('adiwira_flash_pull') ? adiwira_flash_pull() : [];
$formatLabels = [
    'image/jpeg' => 'JPEG',
    'image/png' => 'PNG',
    'image/webp' => 'WebP',
    'image/avif' => 'AVIF',
];
?>
<section class="jyio-settings" aria-labelledby="jyio-title">
  <header class="jyio-hero">
    <span class="jyio-hero__mark" aria-hidden="true">IO</span>
    <div>
      <p class="jyio-eyebrow"><?= jyio_h(jyio_t('Upload pipeline')) ?></p>
      <h1 id="jyio-title"><?= jyio_h(jyio_t('Image Optimizer')) ?></h1>
      <p><?= jyio_h(jyio_t('Resize and recompress new images before Core publishes them. Existing media is never changed.')) ?></p>
    </div>
    <span class="jyio-state <?= $config['enabled'] ? 'is-on' : '' ?>">
      <?= jyio_h($config['enabled'] ? jyio_t('Enabled') : jyio_t('Disabled')) ?>
    </span>
  </header>

  <?php foreach ($flashItems as $flash): ?>
    <div class="jyio-flash jyio-flash--<?= jyio_h((string)($flash['type'] ?? 'info')) ?>" role="status">
      <?= jyio_h((string)($flash['message'] ?? '')) ?>
    </div>
  <?php endforeach; ?>

  <div class="jyio-grid">
    <form class="jyio-form" action="<?= jyio_h($saveUrl) ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= jyio_h(csrf_token()) ?>">

      <fieldset class="jyio-card jyio-card--switch">
        <legend><?= jyio_h(jyio_t('General')) ?></legend>
        <label class="jyio-toggle">
          <span><strong><?= jyio_h(jyio_t('Optimize new uploads')) ?></strong><small><?= jyio_h(jyio_t('Uploaders can opt out for an individual image.')) ?></small></span>
          <input type="checkbox" name="enabled" value="1"<?= $config['enabled'] ? ' checked' : '' ?>>
        </label>
        <label>
          <span><?= jyio_h(jyio_t('Backend')) ?></span>
          <select name="backend">
            <option value="auto"<?= $config['backend'] === 'auto' ? ' selected' : '' ?>><?= jyio_h(jyio_t('Auto (prefer Imagick)')) ?></option>
            <option value="imagick"<?= $config['backend'] === 'imagick' ? ' selected' : '' ?>>Imagick</option>
            <option value="gd"<?= $config['backend'] === 'gd' ? ' selected' : '' ?>>GD</option>
          </select>
        </label>
      </fieldset>

      <fieldset class="jyio-card">
        <legend><?= jyio_h(jyio_t('Dimensions and quality')) ?></legend>
        <div class="jyio-pair">
          <label><span><?= jyio_h(jyio_t('Maximum width')) ?></span><input type="number" name="max_width" min="320" max="10000" value="<?= (int)$config['max_width'] ?>"><small>px</small></label>
          <label><span><?= jyio_h(jyio_t('Maximum height')) ?></span><input type="number" name="max_height" min="320" max="10000" value="<?= (int)$config['max_height'] ?>"><small>px</small></label>
        </div>
        <div class="jyio-quality">
          <label><span>JPEG</span><input type="number" name="jpeg_quality" min="1" max="100" value="<?= (int)$config['jpeg_quality'] ?>"></label>
          <label><span>WebP</span><input type="number" name="webp_quality" min="1" max="100" value="<?= (int)$config['webp_quality'] ?>"></label>
          <label><span>AVIF</span><input type="number" name="avif_quality" min="1" max="100" value="<?= (int)$config['avif_quality'] ?>"></label>
          <label><span><?= jyio_h(jyio_t('PNG compression')) ?></span><input type="number" name="png_compression" min="0" max="9" value="<?= (int)$config['png_compression'] ?>"></label>
        </div>
      </fieldset>

      <fieldset class="jyio-card">
        <legend><?= jyio_h(jyio_t('Processing policy')) ?></legend>
        <?php foreach ([
          'auto_orient' => jyio_t('Apply JPEG EXIF orientation'),
          'strip_metadata' => jyio_t('Strip image metadata'),
          'no_upscale' => jyio_t('Never upscale images'),
          'only_if_smaller' => jyio_t('Keep the result only when it is smaller'),
        ] as $key => $label): ?>
          <label class="jyio-check"><input type="checkbox" name="<?= jyio_h($key) ?>" value="1"<?= $config[$key] ? ' checked' : '' ?>><span><?= jyio_h($label) ?></span></label>
        <?php endforeach; ?>
        <label>
          <span><?= jyio_h(jyio_t('When optimization is unavailable or fails')) ?></span>
          <select name="failure_policy">
            <option value="keep_original"<?= $config['failure_policy'] === 'keep_original' ? ' selected' : '' ?>><?= jyio_h(jyio_t('Keep the original upload')) ?></option>
            <option value="reject"<?= $config['failure_policy'] === 'reject' ? ' selected' : '' ?>><?= jyio_h(jyio_t('Reject the upload')) ?></option>
          </select>
        </label>
      </fieldset>

      <footer class="jyio-actions">
        <button type="submit" class="btn btn-primary"><?= jyio_h(jyio_t('Save settings')) ?></button>
      </footer>
    </form>

    <aside class="jyio-capabilities" aria-labelledby="jyio-capabilities-title">
      <div class="jyio-card">
        <p class="jyio-eyebrow"><?= jyio_h(jyio_t('Runtime')) ?></p>
        <h2 id="jyio-capabilities-title"><?= jyio_h(jyio_t('Backend capabilities')) ?></h2>
        <dl class="jyio-effective">
          <dt><?= jyio_h(jyio_t('Current effective backend')) ?></dt>
          <dd><?= jyio_h($effective === null ? jyio_t('None available') : ucfirst($effective)) ?></dd>
        </dl>
        <?php foreach (['imagick' => 'Imagick', 'gd' => 'GD'] as $backend => $label): ?>
          <section class="jyio-backend">
            <h3><?= jyio_h($label) ?><span class="<?= $capabilities[$backend]['available'] ? 'is-ready' : '' ?>"><?= jyio_h($capabilities[$backend]['available'] ? jyio_t('Available') : jyio_t('Unavailable')) ?></span></h3>
            <ul>
              <?php foreach ($formatLabels as $mime => $format): ?>
                <li class="<?= $capabilities[$backend]['formats'][$mime] ? 'is-ready' : '' ?>"><span aria-hidden="true"></span><?= jyio_h($format) ?></li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endforeach; ?>
        <p class="jyio-note"><?= jyio_h(jyio_t('A format is used only when the selected backend can decode and encode it. Animated images are left unchanged.')) ?></p>
      </div>
    </aside>
  </div>
</section>
