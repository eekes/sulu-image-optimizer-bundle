# Configuration

Every option has a working default, so a project only writes down what it wants to change.
`bin/adminconsole config:dump-reference eekes_sulu_image_optimizer` prints the full tree.

```yaml
# config/packages/eekes_sulu_image_optimizer.yaml
eekes_sulu_image_optimizer:
    enabled: true
    table_name: 'eekes_image_optimization'
    resize:
        enabled: true
        max_size: 4000
    quality:
        jpeg: 85
        png: 85
        webp: 80
        avif: 60
    strip_metadata: true
    sanitize_svg: true
    ignore_types: []
    binary_path: null
    timeout: 60
    admin:
        enabled: true
        navigation_position: 45
```

## `enabled`

Whether uploads to the media library are optimized. Switching it off leaves the log and the console
commands in place, which is handy to compare with and without on the same project.

## `table_name`

The table the log is stored in. Change it before the first migration; afterwards it needs a rename
migration of your own. The entity declares its table in a PHP attribute, which can only hold a
constant, so the bundle applies the configured name through a Doctrine `loadClassMetadata` listener.

## `resize`

An image whose longest side is larger than `max_size` pixels is scaled down to exactly that,
keeping its aspect ratio. Portrait, landscape and square images all count by their longest side.

4000 pixels is enough for a full-width image on a 4K screen. Most sites can go down to 2500 and
save a lot more.

Animated GIFs are never resized: GD only reads the first frame, so the animation would become a
still. They are still handed to gifsicle.

## `quality`

The quality JPEG, WebP and AVIF are written with, from 1 to 100. For PNG it is the upper bound
pngquant may use; PNG itself is always written lossless, at the highest compression.

85 for JPEG is the value at which the difference is hard to see and the file is typically a third
of what a camera writes.

## `strip_metadata`

On: a JPEG, WebP or AVIF is always re-encoded, which drops its EXIF data - camera, date and GPS
coordinates included. The rotation the EXIF data asked for is applied to the pixels first, so
portrait photos stay upright. The binaries are told to strip what is left.

Off: the metadata is kept, which means a lossy image within `max_size` is only handed to the
binaries and not re-encoded - re-encoding would drop the metadata regardless. An image that has to
be resized loses it anyway.

## `sanitize_svg`

Removes scripts, event handler attributes and references to other domains from uploaded SVGs, with
[enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer). Sulu serves an uploaded SVG
from the site's own domain, so an SVG with a script in it runs that script for anyone who opens its
URL.

An SVG that cannot be parsed is stored as it is and logged as failed. Refusing the upload is left to
Sulu's own `blocked_file_types`.

## `ignore_types`

Formats to leave alone: `jpg` (or `jpeg`), `png`, `gif`, `webp`, `avif`, `svg`. The format is
detected from the content of the file, never from its name. A skipped upload is logged with the
status "Skipped".

An unknown format is refused when the container is built, so a typo does not silently optimize
the format it meant to exclude.

## `binary_path`

The directory holding the optimizer binaries when they are not on the `PATH` PHP runs with - common
on shared hosting, where they are installed in the home directory. A binary in this directory wins
over one on the `PATH`.

```yaml
eekes_sulu_image_optimizer:
    binary_path: '%env(HOME)%/bin'
```

## `timeout`

How many seconds a single binary may run before it is stopped. The upload then continues with
whatever the steps before it produced.

## `admin`

`enabled: false` removes the log from the administration interface. The log is still written, so
the totals are there when it is switched on again. `navigation_position` places the item within
Settings.

## Logging

The bundle logs to the `image_optimizer` Monolog channel: what each binary did, and every failure
with its exception. Give the channel a handler of its own to keep it apart:

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        image_optimizer:
            type: rotating_file
            path: '%kernel.logs_dir%/image_optimizer.log'
            max_files: 14
            channels: ['image_optimizer']
```
