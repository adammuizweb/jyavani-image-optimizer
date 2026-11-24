# Jyavani Image Optimizer Repository Contract

Jyavani Image Optimizer is a public MIT-licensed upload-time image processor for Jyavani CMS.

- Require Jyavani Core 2.3.175 or newer and PHP 8.1 or newer.
- Never modify Jyavani Core from this repository.
- Process only Core's private upload stage through `media_upload_atomic_rewrite()`.
- Preserve non-PNG input formats. Convert PNG only when the configured output mode requests JPEG, WebP, or AVIF, then let Core establish the final MIME and extension.
- Preserve PNG alpha in WebP and AVIF; flatten PNG alpha to white in JPEG.
- Never add batch replacement, existing-media mutation, or original retention.
- Keep Imagick and GD optional; processing requires source decode and target encode capability.
- Never log paths, filenames, raw image data, EXIF values, or private metadata.
- Store configuration only in `jyavani_image_optimizer_config`; no migration is allowed.
- Keep all identifiers under the `jyio_` prefix except manifest-owned permission and setting keys.
- Keep browser assets plugin-owned under `static/plugins/jyavani-image-optimizer/` via `static.copy`.
- Build release ZIPs only outside the source tree with `tools/build-package.php`.
- Run PHP lint, every `tests/*_contract.php`, and package reopen verification before release.
