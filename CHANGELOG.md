# Changelog

## 0.2.1 - 2026-11-26

- Keep opaque WebP and AVIF sources opaque when Imagick recompresses them.
- Add regression coverage for WebP-to-WebP alpha corruption.

## 0.2.0 - 2026-11-24

- Add administrator-selectable PNG output modes: preserve, JPEG, WebP, and AVIF.
- Preserve transparency for WebP and AVIF output and flatten JPEG transparency to white.
- Select backends by source decode and target encode capability using the staged file's actual MIME.
- Retry a capable GD backend when Imagick fails at runtime in automatic mode.
- Skip APNG, animated WebP, and AVIF sequences safely, and validate converted output before Core's final inspection.
- Expand conversion contracts and run the standalone manifest contract in CI.

## 0.1.0 - 2026-10-08

- Add upload-time optimization with Imagick and GD backends.
- Add JPEG, PNG, WebP, and AVIF capability detection.
- Add conservative defaults, per-upload opt-out, and guarded settings UI.
- Add Indonesian and German plugin-local translations.
- Add complete-uninstall setting cleanup, contracts, and allowlisted ZIP tooling.
