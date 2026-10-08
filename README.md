# Jyavani Image Optimizer

Jyavani Image Optimizer optimizes supported images in Jyavani Core's private upload stage before hashing, deduplication, final naming, and publication. It never modifies existing media and does not retain originals after a successful rewrite.

## Requirements

- Jyavani Core 2.3.175 or newer
- PHP 8.1 or newer
- PHP extensions `json`, `mbstring`, and `pdo`
- At least one optional processing backend: Imagick or GD

Imagick and GD are intentionally optional package requirements. The plugin detects decode and encode support separately for JPEG, PNG, WebP, and AVIF. Automatic selection prefers Imagick for a format and then tries GD.

## Behavior

- Enabled by default when the plugin is active, even before settings are saved.
- Preserves the input format.
- Defaults to a 2560 by 2560 maximum without upscaling.
- Applies JPEG EXIF orientation when the backend supports it.
- Preserves alpha for PNG, WebP, and AVIF.
- Strips metadata by re-encoding.
- Keeps the original stage when an encoded result is not smaller.
- Always applies required dimension or orientation changes even when that output is larger.
- Leaves animated WebP and multi-frame Imagick images unchanged.
- Lets uploaders opt out per image on both Core media upload surfaces.
- Uses Core's atomic rewrite helper, after which Core revalidates the image.

The default failure policy keeps the original upload. Administrators can instead reject an upload with a generic translated error when the selected backend cannot process its format or optimization fails.

GD necessarily removes embedded metadata whenever it re-encodes an image. Imagick honors the metadata setting explicitly. Neither backend retains the original file after Core accepts a replacement.

## Settings

Open **Settings > Image Optimizer**. The permission `plugin.jyavani-image-optimizer.settings.manage` is nondelegable and granted to the system admin role by default. Configuration is stored as one autoloaded JSON value under `jyavani_image_optimizer_config`.

A keep-data uninstall leaves that setting in place. A complete uninstall deletes only that setting.

## Privacy and security

The plugin does not log image paths, filenames, image bytes, EXIF values, or private metadata. Settings audit records contain only bounded booleans, enum choices, dimensions, and numeric quality values.

## Development

Run all checks:

```sh
for file in $(find . -name '*.php' -type f); do php -l "$file"; done
for test in tests/*_contract.php; do php "$test"; done
php tools/build-package.php /tmp/opencode/jyavani-image-optimizer-0.1.0.zip
```

The package builder uses an explicit allowlist and creates a flat plugin ZIP, with `plugin.json` at its root.

## License

MIT. See `LICENSE`.
