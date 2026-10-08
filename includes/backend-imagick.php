<?php
declare(strict_types=1);

function jyio_imagick_capabilities(): array
{
    $available = extension_loaded('imagick') && class_exists('Imagick');
    $mapping = ['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP', 'image/avif' => 'AVIF'];
    $formats = array_fill_keys(array_keys($mapping), false);
    if (!$available) return ['available' => false, 'formats' => $formats];
    foreach ($mapping as $mime => $format) {
        try {
            if (Imagick::queryFormats($format) === []) continue;
            $probe = new Imagick();
            $probe->newImage(1, 1, new ImagickPixel('transparent'), $format);
            $probe->setImageFormat($format);
            $formats[$mime] = $probe->getImagesBlob() !== '';
            $probe->clear();
            $probe->destroy();
        } catch (Throwable) {
            $formats[$mime] = false;
        }
    }
    return ['available' => true, 'formats' => $formats];
}

function jyio_imagick_write(string $input, string $output, string $mime, array $config): bool
{
    $formats = ['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP', 'image/avif' => 'AVIF'];
    $format = $formats[$mime] ?? '';
    if ($format === '') throw new RuntimeException('The selected image backend cannot decode this format.');
    $image = new Imagick();
    try {
        $image->readImage($input);
        if ($image->getNumberImages() > 1) return false;
        $image->setIteratorIndex(0);
        $preserveAlpha = in_array($mime, ['image/png', 'image/webp', 'image/avif'], true);
        if ($preserveAlpha) {
            $image->setImageBackgroundColor(new ImagickPixel('transparent'));
            if (defined('Imagick::ALPHACHANNEL_ACTIVATE')) {
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            }
        }
        $orientationApplied = false;
        if ($config['auto_orient']) {
            $orientation = $image->getImageOrientation();
            $orientationApplied = !in_array($orientation, [Imagick::ORIENTATION_UNDEFINED, Imagick::ORIENTATION_TOPLEFT], true);
            if ($orientationApplied && method_exists($image, 'autoOrientImage')) $image->autoOrientImage();
            elseif ($orientationApplied) $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        }
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $scale = min($config['max_width'] / $width, $config['max_height'] / $height);
        if ($config['no_upscale']) $scale = min(1.0, $scale);
        $targetWidth = max(1, (int)floor($width * $scale));
        $targetHeight = max(1, (int)floor($height * $scale));
        $resizedImage = $targetWidth !== $width || $targetHeight !== $height;
        if ($resizedImage) {
            $image->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1.0, false);
        }
        if ($config['strip_metadata']) {
            $image->stripImage();
            $image->setImagePage(0, 0, 0, 0);
        }
        if ($preserveAlpha && defined('Imagick::ALPHACHANNEL_ACTIVATE')) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        }
        $image->setImageFormat($mime === 'image/png' ? 'PNG32' : $format);
        if ($mime === 'image/png') {
            $image->setOption('png:color-type', '6');
            $image->setOption('png:compression-level', (string)$config['png_compression']);
            $image->setImageCompressionQuality(max(1, 100 - ($config['png_compression'] * 10)));
        } else {
            $quality = $mime === 'image/jpeg' ? $config['jpeg_quality']
                : ($mime === 'image/webp' ? $config['webp_quality'] : $config['avif_quality']);
            $image->setImageCompressionQuality($quality);
        }
        if (!$image->writeImage($output)) throw new RuntimeException('The selected image backend could not encode the image.');
        clearstatcache(true, $output);
        if ($config['only_if_smaller'] && !$resizedImage && !$orientationApplied
            && (int)@filesize($output) >= (int)@filesize($input)) return false;
        return true;
    } finally {
        $image->clear();
        $image->destroy();
    }
}
