<?php
declare(strict_types=1);

function jyio_default_config(): array
{
    return [
        'enabled' => true,
        'backend' => 'auto',
        'max_width' => 2560,
        'max_height' => 2560,
        'jpeg_quality' => 82,
        'webp_quality' => 82,
        'avif_quality' => 50,
        'png_compression' => 7,
        'auto_orient' => true,
        'strip_metadata' => true,
        'no_upscale' => true,
        'only_if_smaller' => true,
        'failure_policy' => 'keep_original',
    ];
}

function jyio_bool(mixed $value, bool $default): bool
{
    if (is_bool($value)) return $value;
    if (is_int($value) && ($value === 0 || $value === 1)) return $value === 1;
    if (is_string($value)) {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed !== null) return $parsed;
    }
    return $default;
}

function jyio_int(mixed $value, int $default, int $minimum, int $maximum): int
{
    if (is_int($value)) $number = $value;
    elseif (is_string($value) && preg_match('/\A[0-9]{1,6}\z/D', $value) === 1) $number = (int)$value;
    else return $default;
    return $number >= $minimum && $number <= $maximum ? $number : $default;
}

function jyio_normalize_config(mixed $input): array
{
    $defaults = jyio_default_config();
    if (!is_array($input) || array_is_list($input)) return $defaults;
    $backend = is_string($input['backend'] ?? null) ? strtolower(trim($input['backend'])) : '';
    $failure = is_string($input['failure_policy'] ?? null) ? strtolower(trim($input['failure_policy'])) : '';
    return [
        'enabled' => jyio_bool($input['enabled'] ?? null, $defaults['enabled']),
        'backend' => in_array($backend, ['auto', 'imagick', 'gd'], true) ? $backend : $defaults['backend'],
        'max_width' => jyio_int($input['max_width'] ?? null, $defaults['max_width'], 320, 10000),
        'max_height' => jyio_int($input['max_height'] ?? null, $defaults['max_height'], 320, 10000),
        'jpeg_quality' => jyio_int($input['jpeg_quality'] ?? null, $defaults['jpeg_quality'], 1, 100),
        'webp_quality' => jyio_int($input['webp_quality'] ?? null, $defaults['webp_quality'], 1, 100),
        'avif_quality' => jyio_int($input['avif_quality'] ?? null, $defaults['avif_quality'], 1, 100),
        'png_compression' => jyio_int($input['png_compression'] ?? null, $defaults['png_compression'], 0, 9),
        'auto_orient' => jyio_bool($input['auto_orient'] ?? null, $defaults['auto_orient']),
        'strip_metadata' => jyio_bool($input['strip_metadata'] ?? null, $defaults['strip_metadata']),
        'no_upscale' => jyio_bool($input['no_upscale'] ?? null, $defaults['no_upscale']),
        'only_if_smaller' => jyio_bool($input['only_if_smaller'] ?? null, $defaults['only_if_smaller']),
        'failure_policy' => in_array($failure, ['keep_original', 'reject'], true) ? $failure : $defaults['failure_policy'],
    ];
}

function jyio_config(?PDO $pdo = null): array
{
    $pdo ??= (($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null);
    if (!$pdo instanceof PDO || !function_exists('settings_get')) return jyio_default_config();
    $raw = settings_get($pdo, JYIO_SETTING_KEY, null);
    if (!is_string($raw) || $raw === '') return jyio_default_config();
    try {
        return jyio_normalize_config(json_decode($raw, true, 32, JSON_THROW_ON_ERROR));
    } catch (JsonException) {
        return jyio_default_config();
    }
}

function jyio_config_json(array $config): string
{
    return json_encode(jyio_normalize_config($config), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
