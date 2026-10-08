<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$synthetic = [
    'imagick' => ['available' => true, 'formats' => ['image/jpeg' => true, 'image/png' => false]],
    'gd' => ['available' => true, 'formats' => ['image/jpeg' => true, 'image/png' => true]],
];
jyio_test_check(jyio_select_backend('auto', 'image/jpeg', $synthetic) === 'imagick', 'automatic backend prefers capable Imagick');
jyio_test_check(jyio_select_backend('auto', 'image/png', $synthetic) === 'gd', 'automatic backend falls back per format');
jyio_test_check(jyio_select_backend('imagick', 'image/png', $synthetic) === null, 'explicit backend never silently falls back');

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
            if (!($capabilities[$backend]['formats'][$mime] ?? false)) {
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
        $backend = jyio_select_backend('auto', 'image/jpeg');
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

        if (($capabilities['gd']['formats']['image/jpeg'] ?? false) === true) {
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
    jyio_test_check(jyio_webp_is_animated($temp . '/missing.webp') === false, 'invalid or absent WebP is not treated as animated');
    $animatedMarker = $temp . '/animated.webp';
    file_put_contents($animatedMarker, 'RIFFxxxxWEBPANIMxxxxANMF');
    jyio_test_check(jyio_webp_is_animated($animatedMarker), 'animated WebP chunks are detected');
    jyio_test_check(jyio_gd_memory_limit_bytes() === null || jyio_gd_memory_limit_bytes() >= 0, 'GD memory limit parsing is bounded');
} finally {
    jyio_test_remove_tree($temp);
}
