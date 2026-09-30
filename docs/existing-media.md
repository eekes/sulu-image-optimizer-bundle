# Optimizing the existing media library

Images uploaded before the bundle was installed can be optimized afterwards:

```bash
bin/adminconsole eekes:image-optimizer:optimize-existing --dry-run
bin/adminconsole eekes:image-optimizer:optimize-existing
```

It goes over the current version of every image in the media library, oldest first, and runs the
same optimizer an upload gets.

## What it changes

An image that gets smaller is stored as a **new version** of its media, exactly the way uploading a
new version in the administration interface does:

- the previous version stays in the media's history and can be restored from there;
- Sulu clears the cached image formats of the old version itself;
- references to the media on pages keep working, since the media id does not change.

An image that cannot be improved is only logged. That entry is what lets the next run skip it.

Keeping the history takes disk space: the original file stays next to the new version. To win the
space back, delete the old versions from the media's history once you are happy with the result.

## Options

| Option | Meaning |
|:---|:---|
| `--dry-run` | Show what would be saved. Nothing is stored and nothing is logged. |
| `--limit=N` | Stop after N images, to spread a large library over several runs. |
| `--collection=ID` | Only the media in this collection (not its sub-collections). |
| `--min-size=KB` | Only files of at least this many kilobytes: the big ones are where the gain is. |
| `--force` | Also process images the log says were already handled. |

Running it again skips every version that was handled before, except the ones that failed: whatever
made them fail - a missing binary, too little memory - may be fixed by now.

## On shared hosting

The command runs with the memory limit of the PHP CLI, which is often higher than that of the web
server, so images that failed on upload may well succeed here. Run it with `--limit` from a cron
job to stay within the time a host allows a process:

```cron
*/10 * * * * cd /path/to/project && php bin/adminconsole eekes:image-optimizer:optimize-existing --limit=50 --min-size=200 >> var/log/optimize-existing.log 2>&1
```
