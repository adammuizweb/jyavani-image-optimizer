<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$synthetic = [
    'imagick' => [
        'available' => true,
        'decode' => ['image/jpeg' => true, 'image/png' => true],
        'encode' => ['image/jpeg' => true, 'image/png' => false, 'image/webp' => false],
    ],
    'gd' => [
        'available' => true,
        'decode' => ['image/jpeg' => true, 'image/png' => true],
        'encode' => ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true],
    ],
];
jyio_test_check(jyio_select_backend('auto', 'image/jpeg', 'image/jpeg', $synthetic) === 'imagick', 'automatic backend prefers capable Imagick');
jyio_test_check(jyio_select_backend('auto', 'image/png', 'image/png', $synthetic) === 'gd', 'automatic backend falls back for target encode support');
jyio_test_check(jyio_select_backend('auto', 'image/png', 'image/webp', $synthetic) === 'gd', 'backend selection combines source decode and target encode support');
jyio_test_check(jyio_select_backend('imagick', 'image/png', 'image/png', $synthetic) === null, 'explicit backend never silently falls back');
jyio_test_check(jyio_backend_candidates('auto', 'image/png', 'image/jpeg', $synthetic) === ['imagick', 'gd'], 'automatic mode retains every capable backend for runtime fallback');
jyio_test_check(jyio_backend_candidates('imagick', 'image/png', 'image/jpeg', $synthetic) === ['imagick'], 'explicit backend selection remains strict');
jyio_test_check(jyio_target_mime('image/png', 'avif') === 'image/avif' && jyio_target_mime('image/jpeg', 'avif') === 'image/jpeg', 'PNG output mapping leaves other source formats unchanged');

$temp = jyio_test_temp_dir();
try {
    $fixtures = [];
    if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg') && function_exists('imagepng')) {
        $jpeg = $temp . '/fixture.jpg';
        $image = imagecreatetruecolor(640, 480);
        $background = imagecolorallocate($image, 38, 92, 150);
        imagefilledrectangle($image, 0, 0, 639, 479, $background);
        imagejpeg($image, $jpeg, 96);
        imagedestroy($image);
        chmod($jpeg, 0600);
        $fixtures['image/jpeg'] = $jpeg;

        $png = $temp . '/fixture.png';
        $image = imagecreatetruecolor(640, 480);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, 639, 479, $transparent);
        imagepng($image, $png, 3);
        imagedestroy($image);
        chmod($png, 0600);
        $fixtures['image/png'] = $png;

        foreach (['image/webp' => ['WEBP', 'imagewebp'], 'image/avif' => ['AVIF', 'imageavif']] as $mime => [$extension, $encoder]) {
            if (!function_exists($encoder)) continue;
            $path = $temp . '/fixture.' . strtolower($extension);
            $image = imagecreatetruecolor(640, 480);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, 639, 479, $transparent);
            $visible = imagecolorallocatealpha($image, 38, 92, 150, 64);
            imagesetpixel($image, 320, 240, $visible);
            if (!$encoder($image, $path, 80)) { imagedestroy($image); continue; }
            imagedestroy($image);
            chmod($path, 0600);
            $fixtures[$mime] = $path;
        }
    }
    if (class_exists('Imagick')) {
        foreach (['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP', 'image/avif' => 'AVIF'] as $mime => $format) {
            if (isset($fixtures[$mime])) continue;
            $path = $temp . '/fixture.' . strtolower($format);
            try {
                $image = new Imagick();
                $image->newImage(640, 480, new ImagickPixel($mime === 'image/jpeg' ? '#265c96' : 'transparent'), $format);
                if ($image->writeImage($path)) {
                    chmod($path, 0600);
                    $fixtures[$mime] = $path;
                }
                $image->destroy();
            } catch (Throwable) {
                if (isset($image) && $image instanceof Imagick) $image->destroy();
            }
        }
    }

    if ($fixtures === []) fwrite(STDOUT, "SKIP: Neither backend can generate test fixtures\n");
    $capabilities = jyio_capabilities();
    foreach ($fixtures as $mime => $fixture) {
        foreach (['imagick', 'gd'] as $backend) {
            if (!($capabilities[$backend]['decode'][$mime] ?? false) || !($capabilities[$backend]['encode'][$mime] ?? false)) {
                fwrite(STDOUT, "SKIP: {$backend} {$mime} decode/encode is unavailable\n");
                continue;
            }
            $stage = $temp . '/stage-' . $backend . '-' . str_replace('/', '-', $mime);
            copy($fixture, $stage);
            chmod($stage, 0600);
            $config = jyio_default_config();
            $config['backend'] = $backend;
            $config['max_width'] = 320;
            $config['max_height'] = 320;
            $config['only_if_smaller'] = false;
            $result = jyio_optimize_stage($stage, ['mime' => $mime], $config);
            $info = getimagesize($stage);
            jyio_test_check($result['status'] === 'optimized', "{$backend} optimizes generated {$mime} fixture");
            jyio_test_check(($info['mime'] ?? '') === $mime, "{$backend} preserves {$mime} format");
            $ratio = $info[1] > 0 ? $info[0] / $info[1] : 0.0;
            jyio_test_check($info[0] > 0 && $info[1] > 0 && $info[0] <= 320 && $info[1] <= 320
                && $info[0] < 640 && $info[1] < 480 && abs($ratio - (4 / 3)) < 0.02,
                "{$backend} applies bounded resize without distortion ({$info[0]}x{$info[1]})");
            $alphaLoaders = ['image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp', 'image/avif' => 'imagecreatefromavif'];
            $alphaLoader = $alphaLoaders[$mime] ?? '';
            if ($alphaLoader !== '' && function_exists($alphaLoader)) {
                $alphaImage = $alphaLoader($stage);
                $alpha = 0;
                if ($alphaImage instanceof GdImage) {
                    $pixel = imagecolorat($alphaImage, 0, 0);
                    $alpha = imageistruecolor($alphaImage)
                        ? (($pixel >> 24) & 0x7f)
                        : (int)(imagecolorsforindex($alphaImage, $pixel)['alpha'] ?? 0);
                }
                if ($alphaImage instanceof GdImage) imagedestroy($alphaImage);
                jyio_test_check($alpha > 0, "{$backend} preserves {$mime} alpha");
            }
        }
    }
    if (isset($fixtures['image/jpeg']) && function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
        $stage = $temp . '/no-upscale.jpg';
        $small = imagecreatetruecolor(100, 80);
        imagejpeg($small, $stage, 90);
        imagedestroy($small);
        chmod($stage, 0600);
        $backend = jyio_select_backend('auto', 'image/jpeg', 'image/jpeg');
        if ($backend !== null) {
            $config = jyio_default_config();
            $config['backend'] = $backend;
            $config['max_width'] = 320;
            $config['max_height'] = 320;
            $config['only_if_smaller'] = false;
            jyio_optimize_stage($stage, ['mime' => 'image/jpeg'], $config);
            $info = getimagesize($stage);
            jyio_test_check($info[0] === 100 && $info[1] === 80, 'no-upscale policy preserves smaller image dimensions');
        }

        if (($capabilities['gd']['decode']['image/jpeg'] ?? false) === true
            && ($capabilities['gd']['encode']['image/jpeg'] ?? false) === true) {
            $stage = $temp . '/only-smaller.jpg';
            $noisy = imagecreatetruecolor(80, 80);
            for ($y = 0; $y < 80; $y++) {
                for ($x = 0; $x < 80; $x++) {
                    $color = imagecolorallocate($noisy, ($x * 37 + $y * 11) % 256, ($x * 17 + $y * 29) % 256, ($x * 7 + $y * 43) % 256);
                    imagesetpixel($noisy, $x, $y, $color);
                }
            }
            imagejpeg($noisy, $stage, 8);
            imagedestroy($noisy);
            chmod($stage, 0600);
            $before = hash_file('sha256', $stage);
            $config = jyio_default_config();
            $config['backend'] = 'gd';
            $config['jpeg_quality'] = 100;
            $result = jyio_optimize_stage($stage, ['mime' => 'image/jpeg'], $config);
            jyio_test_check($result['status'] === 'skipped' && hash_file('sha256', $stage) === $before, 'only-if-smaller retains the exact original stage');

            $stage = $temp . '/required-resize.jpg';
            $large = imagecreatetruecolor(640, 480);
            $color = imagecolorallocate($large, 36, 91, 149);
            imagefilledrectangle($large, 0, 0, 639, 479, $color);
            imagejpeg($large, $stage, 5);
            imagedestroy($large);
            chmod($stage, 0600);
            $config = jyio_default_config();
            $config['backend'] = 'gd';
            $config['max_width'] = 320;
            $config['max_height'] = 320;
            $config['jpeg_quality'] = 100;
            $result = jyio_optimize_stage($stage, ['mime' => 'image/jpeg'], $config);
            $resized = getimagesize($stage);
            jyio_test_check($result['status'] === 'optimized' && $resized[0] === 320 && $resized[1] === 240,
                'required dimension limits take precedence over only-if-smaller');
        }
    }
    if (isset($fixtures['image/png'])) {
        foreach (['jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'avif' => 'image/avif'] as $mode => $targetMime) {
            foreach (['imagick', 'gd'] as $backend) {
                if (!($capabilities[$backend]['decode']['image/png'] ?? false)
                    || !($capabilities[$backend]['encode'][$targetMime] ?? false)) {
                    fwrite(STDOUT, "SKIP: {$backend} PNG to {$targetMime} is unavailable\n");
                    continue;
                }
                $stage = $temp . '/convert-' . $backend . '-' . $mode;
                copy($fixtures['image/png'], $stage);
                chmod($stage, 0600);
                $config = jyio_default_config();
                $config['backend'] = $backend;
                $config['png_output'] = $mode;
                $config['max_width'] = 320;
                $config['max_height'] = 320;
                $config['only_if_smaller'] = false;
                $result = jyio_optimize_stage($stage, ['mime' => 'image/jpeg'], $config);
                $info = getimagesize($stage);
                jyio_test_check($result['status'] === 'optimized' && $result['source_mime'] === 'image/png'
                    && $result['target_mime'] === $targetMime && ($info['mime'] ?? '') === $targetMime,
                    "{$backend} converts staged PNG bytes to {$targetMime} despite a stale descriptor");
                if ($mode === 'jpeg' && function_exists('imagecreatefromjpeg')) {
                    $converted = imagecreatefromjpeg($stage);
                    $pixel = $converted instanceof GdImage ? imagecolorsforindex($converted, imagecolorat($converted, 0, 0)) : [];
                    if ($converted instanceof GdImage) imagedestroy($converted);
                    jyio_test_check(($pixel['red'] ?? 0) >= 245 && ($pixel['green'] ?? 0) >= 245 && ($pixel['blue'] ?? 0) >= 245,
                        "{$backend} flattens transparent PNG pixels to white JPEG pixels");
                } elseif (in_array($mode, ['webp', 'avif'], true)) {
                    $loader = $mode === 'webp' ? 'imagecreatefromwebp' : 'imagecreatefromavif';
                    if (function_exists($loader)) {
                        $converted = @$loader($stage);
                        $pixel = $converted instanceof GdImage ? imagecolorat($converted, 0, 0) : 0;
                        $alpha = $converted instanceof GdImage ? (($pixel >> 24) & 0x7f) : 0;
                        if ($converted instanceof GdImage) imagedestroy($converted);
                        jyio_test_check($alpha > 0, "{$backend} preserves PNG transparency in {$targetMime}");
                    }
                }
            }
        }

        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            foreach (['imagick', 'gd'] as $backend) {
                if (!($capabilities[$backend]['decode']['image/png'] ?? false)
                    || !($capabilities[$backend]['encode']['image/jpeg'] ?? false)) continue;
                $stage = $temp . '/only-smaller-png-' . $backend;
                $tiny = imagecreatetruecolor(1, 1);
                imagealphablending($tiny, false);
                imagesavealpha($tiny, true);
                $transparent = imagecolorallocatealpha($tiny, 0, 0, 0, 127);
                imagesetpixel($tiny, 0, 0, $transparent);
                imagepng($tiny, $stage, 9);
                imagedestroy($tiny);
                chmod($stage, 0600);
                $before = hash_file('sha256', $stage);
                $config = jyio_default_config();
                $config['backend'] = $backend;
                $config['png_output'] = 'jpeg';
                $config['jpeg_quality'] = 100;
                $result = jyio_optimize_stage($stage, ['mime' => 'image/png'], $config);
                $info = getimagesize($stage);
                jyio_test_check($result['status'] === 'skipped' && hash_file('sha256', $stage) === $before
                    && ($info['mime'] ?? '') === 'image/png', "{$backend} retains the source PNG when conversion is not smaller");
            }
        }

        $pngBytes = (string)file_get_contents($fixtures['image/png']);
        $idat = strpos($pngBytes, 'IDAT');
        if ($idat !== false && $idat >= 4) {
            $chunkData = pack('NN', 2, 0);
            $chunkType = 'acTL';
            $chunk = pack('N', strlen($chunkData)) . $chunkType . $chunkData . pack('N', crc32($chunkType . $chunkData));
            $apng = $temp . '/animated.png';
            file_put_contents($apng, substr($pngBytes, 0, $idat - 4) . $chunk . substr($pngBytes, $idat - 4));
            chmod($apng, 0600);
            $before = hash_file('sha256', $apng);
            $config = jyio_default_config();
            $config['png_output'] = 'webp';
            $result = jyio_optimize_stage($apng, ['mime' => 'image/png'], $config);
            jyio_test_check(jyio_png_is_animated($apng) && $result['status'] === 'skipped'
                && hash_file('sha256', $apng) === $before, 'APNG is detected and left byte-for-byte unchanged');
        }
    }
    jyio_test_check(jyio_png_is_animated($temp . '/missing.png') === false, 'invalid or absent PNG is not treated as animated');
    jyio_test_check(jyio_webp_is_animated($temp . '/missing.webp') === false, 'invalid or absent WebP is not treated as animated');
    $animatedMarker = $temp . '/animated.webp';
    file_put_contents($animatedMarker, 'RIFF' . pack('V', 12) . 'WEBP' . 'ANIM' . pack('V', 0));
    jyio_test_check(jyio_webp_is_animated($animatedMarker), 'animated WebP chunks are detected');
    $animatedAvifMarker = $temp . '/animated.avif';
    file_put_contents($animatedAvifMarker, pack('N', 24) . 'ftyp' . 'avis' . pack('N', 0) . 'avif' . 'mif1');
    jyio_test_check(jyio_avif_is_animated($animatedAvifMarker), 'animated AVIF sequence branding is detected');
    jyio_test_check(!jyio_avif_is_animated($fixtures['image/avif'] ?? $temp . '/missing.avif'), 'still or absent AVIF is not treated as animated');
    jyio_test_check(jyio_gd_memory_limit_bytes() === null || jyio_gd_memory_limit_bytes() >= 0, 'GD memory limit parsing is bounded');
} finally {
    jyio_test_remove_tree($temp);
}
