<?php
declare(strict_types=1);

function jyio_gd_capabilities(): array
{
    static $capabilities = null;
    if (is_array($capabilities)) return $capabilities;
    $available = extension_loaded('gd') && function_exists('imagecreatetruecolor');
    $pairs = [
        'image/jpeg' => ['imagecreatefromjpeg', 'imagejpeg'],
        'image/png' => ['imagecreatefrompng', 'imagepng'],
        'image/webp' => ['imagecreatefromwebp', 'imagewebp'],
        'image/avif' => ['imagecreatefromavif', 'imageavif'],
    ];
    $formats = [];
    foreach ($pairs as $mime => [$decoder, $encoder]) {
        $formats[$mime] = false;
        if (!$available || !function_exists($decoder) || !function_exists($encoder)) continue;
        $probe = imagecreatetruecolor(8, 6);
        if (!$probe instanceof GdImage) continue;
        if ($mime === 'image/jpeg') {
            $color = imagecolorallocate($probe, 38, 92, 150);
        } else {
            imagealphablending($probe, false);
            imagesavealpha($probe, true);
            $color = imagecolorallocatealpha($probe, 38, 92, 150, 64);
        }
        imagefilledrectangle($probe, 0, 0, 7, 5, $color);
        ob_start();
        try {
            $encoded = match ($mime) {
                'image/jpeg' => @imagejpeg($probe, null, 82),
                'image/png' => @imagepng($probe, null, 7),
                'image/webp' => @imagewebp($probe, null, 82),
                'image/avif' => @imageavif($probe, null, 50),
                default => false,
            };
            $blob = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($probe);
        }
        $inspected = $encoded && is_string($blob) && $blob !== '' ? @getimagesizefromstring($blob) : false;
        $formats[$mime] = is_array($inspected) && ($inspected['mime'] ?? '') === $mime
            && (int)($inspected[0] ?? 0) === 8 && (int)($inspected[1] ?? 0) === 6;
    }
    return $capabilities = ['available' => $available, 'formats' => $formats];
}

function jyio_webp_is_animated(string $path): bool
{
    $bytes = @file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) < 16) return false;
    return strpos($bytes, 'ANIM') !== false || strpos($bytes, 'ANMF') !== false;
}

function jyio_gd_memory_limit_bytes(): ?int
{
    $value = trim((string)ini_get('memory_limit'));
    if ($value === '' || $value === '-1') return null;
    if (preg_match('/\A([0-9]+)\s*([KMG]?)\z/i', $value, $match) !== 1) return 0;
    $bytes = (int)$match[1];
    $unit = strtoupper($match[2]);
    if ($unit === 'G') $bytes *= 1024 * 1024 * 1024;
    elseif ($unit === 'M') $bytes *= 1024 * 1024;
    elseif ($unit === 'K') $bytes *= 1024;
    return $bytes;
}

function jyio_gd_assert_memory_safe(string $path, array $config): void
{
    $info = @getimagesize($path);
    $width = is_array($info) ? (int)($info[0] ?? 0) : 0;
    $height = is_array($info) ? (int)($info[1] ?? 0) : 0;
    if ($width < 1 || $height < 1) throw new RuntimeException('The selected image backend could not inspect the image.');
    $scale = min($config['max_width'] / $width, $config['max_height'] / $height);
    if ($config['no_upscale']) $scale = min(1.0, $scale);
    $targetWidth = max(1, (int)floor($width * $scale));
    $targetHeight = max(1, (int)floor($height * $scale));
    $estimated = ($width * $height * 6) + ($targetWidth * $targetHeight * 6) + (12 * 1024 * 1024);
    $limit = jyio_gd_memory_limit_bytes();
    $available = $limit === null ? 384 * 1024 * 1024 : max(0, $limit - memory_get_usage(true));
    if ($estimated > min(384 * 1024 * 1024, $available)) {
        throw new RuntimeException('The image is too large for safe processing by the selected backend.');
    }
}

function jyio_gd_orient(GdImage $image, int $orientation): GdImage
{
    if ($orientation === 2) imageflip($image, IMG_FLIP_HORIZONTAL);
    elseif ($orientation === 3) $image = jyio_gd_rotate($image, 180);
    elseif ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
    elseif ($orientation === 5) { imageflip($image, IMG_FLIP_HORIZONTAL); $image = jyio_gd_rotate($image, -90); }
    elseif ($orientation === 6) $image = jyio_gd_rotate($image, -90);
    elseif ($orientation === 7) { imageflip($image, IMG_FLIP_HORIZONTAL); $image = jyio_gd_rotate($image, 90); }
    elseif ($orientation === 8) $image = jyio_gd_rotate($image, 90);
    return $image;
}

function jyio_gd_rotate(GdImage $image, int $angle): GdImage
{
    $rotated = imagerotate($image, $angle, 0);
    if (!$rotated instanceof GdImage) throw new RuntimeException('The selected image backend could not orient the image.');
    imagedestroy($image);
    return $rotated;
}

function jyio_gd_write(string $input, string $output, string $mime, array $config): bool
{
    if ($mime === 'image/webp' && jyio_webp_is_animated($input)) return false;
    jyio_gd_assert_memory_safe($input, $config);
    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/avif' => 'imagecreatefromavif',
    ];
    $loader = $loaders[$mime] ?? '';
    if ($loader === '' || !function_exists($loader)) throw new RuntimeException('The selected image backend cannot decode this format.');
    $image = @$loader($input);
    if (!$image instanceof GdImage) throw new RuntimeException('The selected image backend could not decode the image.');
    try {
        $orientationApplied = false;
        if ($mime === 'image/jpeg' && $config['auto_orient'] && function_exists('exif_read_data')) {
            $exif = @exif_read_data($input, 'IFD0', true, false);
            $orientation = is_array($exif) ? (int)($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1) : 1;
            if ($orientation >= 2 && $orientation <= 8) {
                $image = jyio_gd_orient($image, $orientation);
                $orientationApplied = true;
            }
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min($config['max_width'] / $width, $config['max_height'] / $height);
        if ($config['no_upscale']) $scale = min(1.0, $scale);
        $targetWidth = max(1, (int)floor($width * $scale));
        $targetHeight = max(1, (int)floor($height * $scale));
        $resizedImage = $targetWidth !== $width || $targetHeight !== $height;
        if ($resizedImage) {
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$resized instanceof GdImage) throw new RuntimeException('The selected image backend could not allocate the resized image.');
            if ($mime !== 'image/jpeg') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
            }
            if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
                imagedestroy($resized);
                throw new RuntimeException('The selected image backend could not resize the image.');
            }
            imagedestroy($image);
            $image = $resized;
        }
        if ($mime !== 'image/jpeg') { imagealphablending($image, false); imagesavealpha($image, true); }
        $written = match ($mime) {
            'image/jpeg' => imagejpeg($image, $output, $config['jpeg_quality']),
            'image/png' => imagepng($image, $output, $config['png_compression']),
            'image/webp' => imagewebp($image, $output, $config['webp_quality']),
            'image/avif' => imageavif($image, $output, $config['avif_quality']),
            default => false,
        };
        if (!$written) throw new RuntimeException('The selected image backend could not encode the image.');
        clearstatcache(true, $output);
        if ($config['only_if_smaller'] && !$resizedImage && !$orientationApplied
            && (int)@filesize($output) >= (int)@filesize($input)) return false;
        return true;
    } finally {
        imagedestroy($image);
    }
}
