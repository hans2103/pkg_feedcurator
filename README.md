# Feed Curator for Joomla

Joomla package that imports on-topic RSS and Atom items as unpublished articles. A board member then publishes or discards each item from a link in a mail. Works with Joomla 5.2+ and 6.

| Extension | What it does |
|---|---|
| `plg_task_feedcurator` | Scheduled task *Import feed items*: fetches the configured feeds and saves matching items as unpublished articles |
| `plg_system_feedcurator` | The review page behind the mail link, where an item is published or discarded |

## Requirements

- Joomla 5.2 or newer, or Joomla 6
- PHP 8.3+
- MySQL or MariaDB
- The Joomla Task Scheduler running, preferably from cron: `php cli/joomla.php scheduler:run`

## Installation

1. Download `pkg_feedcurator-{version}.zip` from the [Releases](https://github.com/hans2103/pkg_feedcurator/releases) page.
2. In the administrator go to **System → Install → Extensions** and upload the zip.
3. The task and system plugins are enabled on the first install. Updates then arrive through Joomla's update manager.

## Status

Work in progress: the package installs and creates its tables; the import and the review page follow. See `docs/superpowers/specs/2026-11-26-feedcurator-design.md`.

## Development

```bash
git config core.hooksPath .githooks
for f in tests/unit/*.php; do php "$f" || echo "FAIL $f"; done   # unit tests, no PHPUnit
bash tests/shell/run-all.sh
bash bin/preflight.sh
bash package.sh                                                  # packages/{version}/pkg_feedcurator-{version}.zip
```

Workflow, branching and releases are documented in Dutch in [`docs/`](docs/). Versions follow `YY.WW.PATCH` (year, ISO week, patch).

## License

GNU General Public License version 2 or later, see [LICENSE.txt](LICENSE.txt). © 2026 [HKweb](https://hkweb.nl).
