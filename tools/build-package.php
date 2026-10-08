<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$output = $argv[1] ?? '/tmp/opencode/jyavani-image-optimizer-0.1.0.zip';
$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 32, JSON_THROW_ON_ERROR);
$name = (string)($manifest['name'] ?? '');
$version = (string)($manifest['version'] ?? '');
if ($name !== 'jyavani-image-optimizer'
    || preg_match('/\A\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?\z/D', $version) !== 1) {
    throw new RuntimeException('Invalid plugin identity or version.');
}
$allowed = [
    'plugin.json',
    'plugin.php',
    'README.md',
    'CHANGELOG.md',
    'LICENSE',
    'icon.svg',
    'assets/icon-sidebar.svg',
    'assets/css/admin.css',
    'admin/settings.php',
    'admin/save.php',
    'includes/i18n.php',
    'includes/config.php',
    'includes/backend-gd.php',
    'includes/backend-imagick.php',
    'includes/optimizer.php',
    'includes/integration.php',
    'translations/id.php',
    'translations/de.php',
];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive is required to build the package.\n");
    exit(1);
}
$rootReal = realpath($root);
$outputDirectory = realpath(dirname($output));
if ($rootReal === false || $outputDirectory === false || !is_writable($outputDirectory)) {
    fwrite(STDERR, "The source or output directory does not exist.\n");
    exit(1);
}
$outputAbsolute = $outputDirectory . DIRECTORY_SEPARATOR . basename($output);
if (!str_ends_with(strtolower($outputAbsolute), '.zip')) {
    fwrite(STDERR, "The package path must end in .zip.\n");
    exit(1);
}
if ($outputAbsolute === $rootReal || str_starts_with($outputAbsolute, $rootReal . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Packages must be built outside the source repository.\n");
    exit(1);
}
if (file_exists($outputAbsolute) || is_link($outputAbsolute)) {
    fwrite(STDERR, "The package output already exists.\n");
    exit(1);
}
foreach ($allowed as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path) || is_link($path)) {
        fwrite(STDERR, "Missing or unsafe allowlisted file: {$relative}\n");
        exit(1);
    }
}

$temporary = $outputAbsolute . '.tmp-' . bin2hex(random_bytes(5));
register_shutdown_function(static function () use ($temporary): void {
    if (is_file($temporary) || is_link($temporary)) @unlink($temporary);
});
$zip = new ZipArchive();
if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Unable to create package.\n");
    exit(1);
}
sort($allowed, SORT_STRING);
foreach ($allowed as $relative) {
    if (!$zip->addFile($root . '/' . $relative, $relative)) {
        $zip->close();
        @unlink($temporary);
        fwrite(STDERR, "Unable to add allowlisted file: {$relative}\n");
        exit(1);
    }
}
if (!$zip->close()) {
    @unlink($temporary);
    fwrite(STDERR, "Unable to finalize package.\n");
    exit(1);
}

$verify = new ZipArchive();
if ($verify->open($temporary) !== true) {
    fwrite(STDERR, "Unable to reopen package.\n");
    exit(1);
}
$entries = [];
for ($index = 0; $index < $verify->numFiles; $index++) $entries[] = $verify->getNameIndex($index);
$embedded = json_decode((string)$verify->getFromName('plugin.json'), true, 32, JSON_THROW_ON_ERROR);
$verify->close();
sort($entries, SORT_STRING);
if ($entries !== $allowed || ($embedded['name'] ?? '') !== $name || ($embedded['version'] ?? '') !== $version) {
    @unlink($temporary);
    fwrite(STDERR, "Package reopen verification failed.\n");
    exit(1);
}
if (!@rename($temporary, $outputAbsolute)) {
    @unlink($temporary);
    fwrite(STDERR, "Unable to publish package.\n");
    exit(1);
}
fwrite(STDOUT, json_encode([
    'path' => $outputAbsolute,
    'version' => $version,
    'entries' => count($entries),
    'bytes' => filesize($outputAbsolute),
    'sha256' => hash_file('sha256', $outputAbsolute),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
