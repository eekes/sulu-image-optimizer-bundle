# The log in the admin

Every optimized upload is written to one table and shown under **Settings → Image optimization**.

## What is stored

| | |
|:---|:---|
| Date | When the file was uploaded, or processed by the command |
| File | The file name as it was uploaded |
| Status | Optimized, Already optimal, Skipped or Failed - see below |
| Before / After | The size of the upload and of the file that was stored |
| Saved, Saved % | The difference, stored so the list can sort and filter on it |
| Dimensions before / after | Width × height, so a resize is visible |
| Format | Detected from the content, not the file name |
| Note | Why a file was skipped, what went wrong, or that an animation was not resized |
| Uploaded by | The name of the user, copied at upload time |
| Tools | What processed the file: `gd`, `jpegoptim`, `svg-sanitizer`, ... |
| Source | Upload, or the console command |
| Media version | The file version of the media the entry is about |

Each row has an eye icon that opens its media, for users who may open the media library. The link
uses the locale the file was uploaded in.

## The statuses

| Status | Badge | Meaning |
|:---|:---|:---|
| Optimized | blue | The stored file is smaller, resized, or (for an SVG) cleaned |
| Already optimal | green | Everything ran, but nothing was gained, so the original was stored |
| Skipped | grey | Deliberately not touched: `ignore_types`, or an animated WebP |
| Failed | red | Something went wrong; the original was stored and the note says why |

"Already optimal" is not a problem: an image that was exported for the web usually cannot get
smaller without losing quality. Many "Already optimal" rows with only `gd` in the tools column,
however, usually mean the binaries are missing.

## The overview

The **Overview** button above the list shows the total saved, and opens:

- the totals: how many files, their size before and after, the counts per status;
- what the server has available: GD with its WebP and AVIF support, and every optimizer binary.

The same check is available on the command line, also for use in a deploy script:

```bash
bin/adminconsole eekes:image-optimizer:check
bin/adminconsole eekes:image-optimizer:check --strict   # fails when anything is missing
```

## When the media is deleted

The entry stays: what the upload saved keeps counting in the totals. Only the link to the media is
cleared, by the database itself (`ON DELETE SET NULL`), and the file name is still there.

## Permissions

The security context is `sulu.settings.eekes_image_optimizations`, with **view** and **delete**.
**view** shows the navigation item, the list and the overview; **delete** allows removing entries,
one at a time or as a selection.

There is deliberately no way to create or change an entry from the administration interface: it is
a log of what happened.
