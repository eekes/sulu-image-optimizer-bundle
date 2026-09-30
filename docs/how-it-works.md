# How an upload is optimized

The bundle listens for the requests the administration interface uploads a file with:

- `sulu_media.post_media`: a new media;
- `sulu_media.post_media_trigger` with `action=new-version`: a new version of an existing one.
  Moving a media uses this route too, but carries no file and is left alone;
- `sulu_media.post_media_preview`: the preview image of a video or a document, which Sulu stores
  as a media of its own and generates its formats from.

The listener runs after the firewall, so only a request that is allowed to upload ever costs the
CPU. It changes the uploaded file where PHP put it, before Sulu reads it. Sulu then stores the
optimized file, records its size, and generates every image format from it.

## The steps

1. **Recognise the format** from the content of the file. Anything that is not a JPEG, PNG, GIF,
   WebP, AVIF or SVG is left to Sulu and not logged. An SVG is any XML document whose root element
   is `<svg>`, however long the prolog in front of it, so a padded file cannot get past the
   sanitizer. A web page with an inline SVG is not an image.
2. **Skip** it when the format is in `ignore_types`, or when it is an animated WebP (neither GD nor
   cwebp can process one without turning it into a still).
3. **Check the memory**: when decoding the image would take more memory than PHP may use, it is
   logged as failed and the original is stored. Running out of memory in GD is a fatal error that
   nothing can catch, so this check happens before GD sees the file.
4. **Re-encode with GD** when the image is larger than `max_size` (it is scaled down), or when it is
   a JPEG, WebP or AVIF and `strip_metadata` is on (at the configured `quality`). Loading applies
   the EXIF rotation first.
5. **Run the binaries** for the format, as far as the server has them.
6. **Compare**: when the result is not smaller, it is thrown away and the original is stored. A
   resized image is always kept - `max_size` is a limit, not a suggestion.

All of it happens on a copy. The upload itself is only overwritten at the very end, so whatever
fails halfway, the original is what goes into the media library.

An SVG is sanitized instead (and passed through svgo when it is available). A cleaned SVG is kept
even when it grew: the cleaning is the point.

## Sulu's maximum file size

Sulu checks `sulu_media.upload.max_filesize` after the bundle ran, against the optimized file. An
image that was over the limit as it came off the camera can therefore be accepted once it has been
resized. That is deliberate: the limit exists to keep the media library small, and the file that is
stored is.

The limit of PHP itself (`upload_max_filesize`, `post_max_size`) still applies to the upload as it
is sent - the bundle only sees files PHP accepted.

## Linking the log to the media

When the file is optimized, the media it becomes does not exist yet. The result waits until Sulu
announces the media it stored (`MediaCreatedEvent`) or the version it added
(`MediaVersionAddedEvent`), and is written when the response goes out. An upload Sulu refuses -
wrong type, too large, no permission for the collection - never becomes a media, and its entry is
dropped.
