# Sulu Image Optimizer Bundle

Optimizes and resizes images **before** they are stored in the Sulu media library, and keeps a log
of what every upload saved under **Settings → Image optimization**.

Sulu generates its image formats from the original upload. A 40 megapixel photo straight from a
phone is what makes cropping hit the memory or time limit on shared hosting, and what fills the
disk. This bundle makes the upload itself small before Sulu ever sees it, so every format Sulu
creates later starts from a sensible file.

## Features

- **Optimizes every upload** in the media library - new media, new versions and the preview images
  of videos and documents - with GD and the optimizer binaries the server has (jpegoptim, pngquant,
  optipng, gifsicle, cwebp, avifenc, svgo).
- **Scales down** images larger than a maximum size, keeping the aspect ratio.
- **Removes EXIF data** - GPS coordinates included - after applying its rotation, so a portrait
  photo stays upright.
- **Sanitizes SVG uploads**: scripts, event handlers and remote references are removed, because Sulu
  serves an SVG from the site's own domain.
- **Never makes things worse**: a result that is not smaller is thrown away, and whatever goes
  wrong, the original is stored. An image too large for PHP's memory limit is refused before GD
  touches it, instead of taking the upload down with a fatal error.
- **Leaves animations alone**: an animated GIF is never flattened into a still.
- **Logs every upload** with its size and dimensions before and after, the tools that ran, and a
  link to the media. Failed optimizations are logged too, with the reason.
- **Shows what the server can do**: which optimizer binaries are missing, in the admin and with
  `eekes:image-optimizer:check`.
- **Optimizes the existing library** with `eekes:image-optimizer:optimize-existing`, as a new version
  of each media, so every change can be undone from the media's history.

## Installation

```bash
composer require eekes/sulu-image-optimizer-bundle
```

Register the bundle in `config/bundles.php`:

```php
Eekes\Sulu\ImageOptimizerBundle\EekesSuluImageOptimizerBundle::class => ['all' => true],
```

Coming from `innomedio/sulu-image-optimizer-bundle`? See [docs/migrating.md](docs/migrating.md).

### 1. Database

The entity mapping is registered by the bundle, so only a migration is left:

```bash
bin/adminconsole doctrine:migrations:diff
bin/adminconsole doctrine:migrations:migrate
```

### 2. Admin routes

```yaml
# config/routes/sulu_admin.yaml
eekes_sulu_image_optimizer_api:
    resource: "@EekesSuluImageOptimizerBundle/config/routing_admin_api.php"
    prefix: /admin/api
```

### 3. Admin JavaScript

The log shows its status as a coloured badge and has an overview button, which need a small
JavaScript package. Add it to `assets/admin/package.json`:

```json
"sulu-image-optimizer-bundle": "file:../../vendor/eekes/sulu-image-optimizer-bundle/assets/admin"
```

Import it in `assets/admin/app.js`:

```js
import 'sulu-image-optimizer-bundle';
```

Then rebuild the administration interface:

```bash
bin/adminconsole sulu:admin:update-build
```

### 4. Permissions and binaries

Give the role permission on **Settings → Image optimization** in the Sulu user management, and check
what the server has:

```bash
bin/adminconsole eekes:image-optimizer:check
```

Without any binary the bundle still resizes, re-encodes and strips metadata with GD. The binaries
squeeze out the rest.

## Documentation

- [Configuration](docs/configuration.md)
- [How an upload is optimized](docs/how-it-works.md)
- [The log in the admin](docs/log.md)
- [Optimizing the existing media library](docs/existing-media.md)
- [Migrating from innomedio/sulu-image-optimizer-bundle](docs/migrating.md)

## Compatibility

| | |
|:---|:---|
| PHP | 8.3 or newer, with GD |
| Sulu | 3.0 (Sulu 2 is not supported) |
| Symfony | 7.2 or newer, 8.x |
| Doctrine | ORM 2.17.3 or newer, 3.3 or newer, DoctrineBundle 2.13 or newer, 3.x |

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan   # PHPStan, level max
composer cs        # PHP CS Fixer, dry run (composer cs-fix applies it)
composer psr       # fails when a class does not match its PSR-4 path
```

The test application in `tests/Application` registers no Sulu bundles: booting Sulu needs PHPCR,
webspaces and a content repository. The relation to Sulu's media is resolved to a test entity, the
services that need Sulu are built by hand with the real collaborators, and the optimizer binaries
are replaced by a double, so the suite gives the same answers whatever the machine has installed.
The Sulu admin classes, `SuluMediaLibrary` and the JavaScript are therefore best checked in a real
Sulu project as well.

## License

MIT. See [LICENSE](LICENSE).
