<?php
declare(strict_types=1);

function jyio_capabilities(): array
{
    return ['imagick' => jyio_imagick_capabilities(), 'gd' => jyio_gd_capabilities()];
}

function jyio_select_backend(string $preference, string $mime, ?array $capabilities = null): ?string
{
    $capabilities ??= jyio_capabilities();
    $candidates = $preference === 'auto' ? ['imagick', 'gd'] : [$preference];
    foreach ($candidates as $backend) {
        if (($capabilities[$backend]['available'] ?? false) === true
            && ($capabilities[$backend]['formats'][$mime] ?? false) === true) return $backend;
    }
    return null;
}

function jyio_effective_backend(array $config, ?array $capabilities = null): ?string
{
    $capabilities ??= jyio_capabilities();
    foreach (['image/jpeg', 'image/png', 'image/webp', 'image/avif'] as $mime) {
        $backend = jyio_select_backend((string)$config['backend'], $mime, $capabilities);
        if ($backend !== null) return $backend;
    }
    return null;
}

function jyio_optimize_stage(string $stagePath, array $descriptor, array $config): array
{
    $config = jyio_normalize_config($config);
    $mime = is_string($descriptor['mime'] ?? null) ? $descriptor['mime'] : '';
    $backend = jyio_select_backend($config['backend'], $mime);
    if ($backend === null) return ['status' => 'unsupported', 'backend' => null];
    $changed = false;
    media_upload_atomic_rewrite(
        $stagePath,
        static function (string $input, string $output) use ($backend, $mime, $config, &$changed): bool {
            $changed = $backend === 'imagick'
                ? jyio_imagick_write($input, $output, $mime, $config)
                : jyio_gd_write($input, $output, $mime, $config);
            return $changed;
        }
    );
    return ['status' => $changed ? 'optimized' : 'skipped', 'backend' => $backend];
}
