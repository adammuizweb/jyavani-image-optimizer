<?php
declare(strict_types=1);

function jyio_t(string $source, mixed ...$arguments): string
{
    static $catalogs = [];
    $locale = function_exists('get_locale') ? strtolower((string)get_locale()) : 'en';
    $locale = substr($locale, 0, 2);
    if (!array_key_exists($locale, $catalogs)) {
        $file = dirname(__DIR__) . '/translations/' . $locale . '.php';
        $catalogs[$locale] = is_file($file) ? require $file : [];
        if (!is_array($catalogs[$locale])) $catalogs[$locale] = [];
    }
    $translated = $catalogs[$locale][$source] ?? (function_exists('__') ? __($source) : $source);
    return $arguments === [] ? (string)$translated : sprintf((string)$translated, ...$arguments);
}

function jyio_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
