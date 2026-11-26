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

## Setting up the task

1. In the administrator go to **System → Scheduled Tasks → New** and choose *Import feed items*.
2. Add one or more **sources** (feed URL, https only). Each source can use its own include and exclude keywords or the global list. A keyword matches whole words, case-insensitively; a trailing `*` is a word-ending wildcard; an exclude term always wins.
3. Choose the **category** and the **author** of the imported articles, and the **user groups** that receive the mail.
4. Fill in **Site URL** (the public address of the site, for example `https://example.org`). It is required: the task runs on the command line, where Joomla does not know the public URL, and the review links are built from it.
5. Optional: look-back (`first_run_weeks`, a one-time look-back per source), `max_age_days`, `max_per_run`, the link lifetime in days and after how many failed runs in a row the failure mail is sent.

Articles are stored as unpublished concepts: a title, a short summary and a source line with a link. No full text and no images are copied from the source.

## Mail and review flow

1. After a run that created articles, every recipient gets one HTML mail with a signed link per article.
2. The link opens the review page: it shows the concept and two buttons, **Publish** and **Discard**. Opening the link (GET) changes nothing; only the button (POST, with a form token) publishes the article or moves it to the trash, as the user the link was signed for.
3. A discarded item is not imported again. Links expire after the configured number of days and stop working once the article has been handled.

The links are signed with the site secret (`secret` in `configuration.php`); with an empty secret no mail is sent.

## Notes for site builders

- **Rights:** every recipient needs `core.edit.state` on the target category; without it the review page refuses to publish or discard.
- **Workflows:** com_content workflows must be off (v1). With workflows on, the workflow plugin refuses the state change from the review page, and the page points to the administrator. Articles are still associated with the default stage, so they show up in Content → Articles.
- **Site URL:** the task needs `site_url` because it runs on the CLI.
- **Itemid:** the review page is `index.php?option=com_ajax&group=system&plugin=feedcurator&format=html` without an Itemid. If the site template treats a URL without Itemid as the homepage, the review page may show the homepage; this is a known integration point, solve it in the template (override `html/plg_system_feedcurator/review.php` or the layout choice).
- **Page cache:** the review page sends `Cache-Control: no-store` and `noindex`. *System - Page Cache* ignores `no-store`, so the system plugin excludes the review page from the page cache itself (`onPageCacheIsExcluded`); no setting needed.
- The scheduler must run (cron: `php cli/joomla.php scheduler:run`), otherwise nothing is imported.

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
