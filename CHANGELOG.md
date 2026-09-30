# Changelog

## 1.0.0

First release, as the successor of `innomedio/sulu-image-optimizer-bundle`. See
[docs/migrating.md](docs/migrating.md) for what changed.

- Optimizes and resizes uploads to the Sulu 3 media library: new media, new versions, and the
  preview images of videos and documents.
- Keeps the original whenever the result is not smaller or anything fails.
- Resizes square images too, and never flattens an animated GIF.
- Removes EXIF data after applying its rotation (`strip_metadata`), with a quality per format.
- Sanitizes SVG uploads (`sanitize_svg`), recognised by their root element however long the prolog
  in front of it.
- Refuses an image too large for PHP's memory limit before GD touches it.
- Uses only the optimizer binaries the server has, also from a directory outside the `PATH`
  (`binary_path`).
- Logs every upload under Settings → Image optimization: size and dimensions before and after, a
  status badge, the tools that ran, and a link to the media.
- Shows the totals and the available binaries in the admin, and with `eekes:image-optimizer:check`.
- Optimizes the existing media library with `eekes:image-optimizer:optimize-existing`, as a new
  version of each media.
- Admin translations in English, Dutch and German.
- Runs on Symfony 7.2 to 8.x, with DoctrineBundle 2.13 or 3.x.
