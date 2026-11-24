<?php
declare(strict_types=1);

function jyio_capabilities(): array
{
    return ['imagick' => jyio_imagick_capabilities(), 'gd' => jyio_gd_capabilities()];
}

function jyio_select_backend(string $preference, string $sourceMime, string $targetMime, ?array $capabilities = null): ?string
{
    return jyio_backend_candidates($preference, $sourceMime, $targetMime, $capabilities)[0] ?? null;
}

function jyio_backend_candidates(string $preference, string $sourceMime, string $targetMime, ?array $capabilities = null): array
{
    $capabilities ??= jyio_capabilities();
    $candidates = $preference === 'auto' ? ['imagick', 'gd'] : [$preference];
    $supported = [];
    foreach ($candidates as $backend) {
        if (($capabilities[$backend]['available'] ?? false) === true
            && ($capabilities[$backend]['decode'][$sourceMime] ?? false) === true
            && ($capabilities[$backend]['encode'][$targetMime] ?? false) === true) $supported[] = $backend;
    }
    return $supported;
}

function jyio_effective_backend(array $config, ?array $capabilities = null): ?string
{
    $capabilities ??= jyio_capabilities();
    foreach (['image/jpeg', 'image/png', 'image/webp', 'image/avif'] as $mime) {
        $targetMime = jyio_target_mime($mime, (string)($config['png_output'] ?? 'preserve'));
        $backend = jyio_select_backend((string)$config['backend'], $mime, $targetMime, $capabilities);
        if ($backend !== null) return $backend;
    }
    return null;
}

function jyio_target_mime(string $sourceMime, string $pngOutput): string
{
    if ($sourceMime !== 'image/png') return $sourceMime;
    return match ($pngOutput) {
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        default => 'image/png',
    };
}

function jyio_png_is_animated(string $path): bool
{
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) return false;
    try {
        if (fread($handle, 8) !== "\x89PNG\r\n\x1a\n") return false;
        while (!feof($handle)) {
            $header = fread($handle, 8);
            if (!is_string($header) || strlen($header) !== 8) return false;
            $length = unpack('Nlength', substr($header, 0, 4))['length'] ?? -1;
            $type = substr($header, 4, 4);
            if ($type === 'acTL') return true;
            if ($type === 'IDAT' || $type === 'IEND') return false;
            if (!is_int($length) || $length < 0 || $length > 0x7fffffff || fseek($handle, $length + 4, SEEK_CUR) !== 0) return false;
        }
        return false;
    } finally {
        fclose($handle);
    }
}

function jyio_avif_is_animated(string $path): bool
{
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) return false;
    try {
        while (!feof($handle)) {
            $header = fread($handle, 8);
            if (!is_string($header) || strlen($header) !== 8) return false;
            $size = unpack('Nsize', substr($header, 0, 4))['size'] ?? -1;
            $type = substr($header, 4, 4);
            if (!is_int($size) || $size < 8 || $size > 1024 * 1024) return false;
            if ($type !== 'ftyp') {
                if (fseek($handle, $size - 8, SEEK_CUR) !== 0) return false;
                continue;
            }
            $payload = fread($handle, $size - 8);
            if (!is_string($payload) || strlen($payload) !== $size - 8 || strlen($payload) < 8) return false;
            $brands = [substr($payload, 0, 4)];
            for ($offset = 8; $offset + 4 <= strlen($payload); $offset += 4) {
                $brands[] = substr($payload, $offset, 4);
            }
            return in_array('avis', $brands, true);
        }
        return false;
    } finally {
        fclose($handle);
    }
}

function jyio_optimize_stage(string $stagePath, array $descriptor, array $config): array
{
    $config = jyio_normalize_config($config);
    $inspected = @getimagesize($stagePath);
    $sourceMime = is_array($inspected) && is_string($inspected['mime'] ?? null) ? $inspected['mime'] : '';
    if ($sourceMime === 'image/png' && jyio_png_is_animated($stagePath)) {
        return ['status' => 'skipped', 'backend' => null, 'source_mime' => $sourceMime, 'target_mime' => $sourceMime];
    }
    if ($sourceMime === 'image/webp' && jyio_webp_is_animated($stagePath)) {
        return ['status' => 'skipped', 'backend' => null, 'source_mime' => $sourceMime, 'target_mime' => $sourceMime];
    }
    if ($sourceMime === 'image/avif' && jyio_avif_is_animated($stagePath)) {
        return ['status' => 'skipped', 'backend' => null, 'source_mime' => $sourceMime, 'target_mime' => $sourceMime];
    }
    $targetMime = jyio_target_mime($sourceMime, $config['png_output']);
    $backends = jyio_backend_candidates($config['backend'], $sourceMime, $targetMime);
    if ($backends === []) return ['status' => 'unsupported', 'backend' => null];
    $lastError = null;
    foreach ($backends as $backend) {
        $changed = false;
        try {
            media_upload_atomic_rewrite(
                $stagePath,
                static function (string $input, string $output) use ($backend, $sourceMime, $targetMime, $config, &$changed): bool {
                    $changed = $backend === 'imagick'
                        ? jyio_imagick_write($input, $output, $sourceMime, $targetMime, $config)
                        : jyio_gd_write($input, $output, $sourceMime, $targetMime, $config);
                    if ($changed) {
                        $outputInfo = @getimagesize($output);
                        if (!is_array($outputInfo) || ($outputInfo['mime'] ?? '') !== $targetMime) {
                            throw new RuntimeException('The selected image backend produced an unexpected format.');
                        }
                    }
                    return $changed;
                }
            );
            return [
                'status' => $changed ? 'optimized' : 'skipped',
                'backend' => $backend,
                'source_mime' => $sourceMime,
                'target_mime' => $changed ? $targetMime : $sourceMime,
            ];
        } catch (Throwable $error) {
            $lastError = $error;
        }
    }
    throw $lastError ?? new RuntimeException('Image optimization failed.');
}
