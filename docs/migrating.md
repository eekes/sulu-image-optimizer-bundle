# Migrating from innomedio/sulu-image-optimizer-bundle

This bundle replaces `innomedio/sulu-image-optimizer-bundle`. The two cannot be installed together:
both would optimize every upload.

## 1. Swap the package

```bash
composer remove innomedio/sulu-image-optimizer-bundle
composer require eekes/sulu-image-optimizer-bundle
```

In `config/bundles.php`, replace

```php
Innomedio\Sulu\ImageOptimizerBundle\InnomedioSuluImageOptimizerBundle::class => ['all' => true],
```

with

```php
Eekes\Sulu\ImageOptimizerBundle\EekesSuluImageOptimizerBundle::class => ['all' => true],
```

## 2. Move the configuration

The configuration key changed from `innomedio_sulu_image_optimizer` to
`eekes_sulu_image_optimizer`. Rename the file and the key:

```yaml
# before: config/packages/innomedio_sulu_image_optimizer.yaml
innomedio_sulu_image_optimizer:
    enabled: true
    logger: 'monolog.logger.image_optimizer'
    resize:
        enabled: true
        max_size: 4000
    ignore_types:
        - gif

# after: config/packages/eekes_sulu_image_optimizer.yaml
eekes_sulu_image_optimizer:
    enabled: true
    resize:
        enabled: true
        max_size: 4000
    ignore_types:
        - gif
```

`logger` is gone: the bundle always logs to the `image_optimizer` Monolog channel. A handler that
already listens to that channel keeps working; see [configuration.md](configuration.md#logging).

`gif` was often put in `ignore_types` because the old bundle flattened animated GIFs. This one
leaves animations alone, so it can usually be taken out.

## 3. Finish the installation

The old bundle had no database table, admin routes or JavaScript. Follow steps 1 to 4 of the
[installation](../README.md#installation).

## What behaves differently

| | Old bundle | This bundle |
|:---|:---|:---|
| A result larger than the upload | Was stored anyway | Thrown away, the original is stored |
| A square image over `max_size` | Was not resized | Resized |
| A non-image file in the same upload | Stopped processing of the rest | Only that file is skipped |
| Animated GIFs | Flattened to the first frame | Left animated |
| A failure | Could break the upload | Logged; the original is stored |
| An image too large for the memory limit | Fatal error, the upload failed | Logged as failed; the original is stored |
| A PNG within `max_size` | Re-encoded by GD, often larger | Only handed to the binaries |
| SVG uploads | Untouched | Sanitized |
| Missing binaries | Silently skipped | Visible in the admin and with `eekes:image-optimizer:check` |
