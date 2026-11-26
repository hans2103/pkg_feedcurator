# Task plugin (plg_task_feedcurator)

### History
- 2026-11-26: added `src/Feed/` (RSS 2.0 and Atom parser)
- 2026-11-26: page created with the scaffold; the routine is a placeholder that returns OK

Last updated: 2026-11-26
Verified against: b62791a
Covers:
- Src/plugins/task/feedcurator/

## Purpose

Scheduled task *Import feed items* (routine `feedcurator.import`). In the scaffold the routine only returns `TaskStatus::OK`; the feed parsing and import follow in later tasks of the plan.

## Files

- `feedcurator.xml`: manifest, namespace `HKweb\Plugin\Task\Feedcurator` on `src/`, SQL install/uninstall/update.
- `services/provider.php`: registers the plugin lazily with an `HttpFactory`. It also registers the namespace `HKweb\Plugin\System\Feedcurator` (`JLoader::registerNamespace`), because the routine will use `ReviewToken` from the system plugin.
- `src/Extension/Feedcurator.php`: `TASKS_MAP` with `feedcurator.import` (language prefix `PLG_TASK_FEEDCURATOR_TASK_IMPORT`, form `import`).
- `src/Feed/FeedParser.php`: parses RSS 2.0 (`channel/item`) and Atom (`feed/entry`) into `FeedItem` objects; throws `FeedUnavailableException` for anything else. Joomla-free. Items without a title, or without link and guid, are skipped. Atom: link from `rel="alternate"` (or no `rel`), date from `published`, else `updated`.
- `src/Feed/FeedItem.php`: readonly value object (`guid`, `link`, `title`, `description`, `categories`, `published`); `key()` is the sha256 of the normalised link (trimmed, scheme and host lowercased, no fragment), or of the guid when there is no link.
- `src/Feed/Source.php`: readonly settings of one source (`name`, `url`, `filter`, `keywords`, `exclude`, `enabled`); `fromArray()` builds it from a subform row, keyword textareas via `KeywordFilter::parseList()`.
- `src/Feed/KeywordFilter.php`: `matches(FeedItem)` tests title, description (tags stripped) and categories against include and exclude terms: whole words, case-insensitive, trailing `*` is a word-ending wildcard, phrases match any whitespace. An exclude term wins; no include terms means no match. `parseList()` splits a textarea (one term per line, deduplicated).
- `src/Feed/Summary.php`: `fromHtml()` removes the WordPress "Het bericht ... verscheen eerst op ..." footer, tags and entities, collapses whitespace and cuts at the last space before 300 characters with an ellipsis.
- `src/Feed/ArticleMapper.php`: `map(FeedItem, Source, sourceLine)` returns the article fields: `title` (tags stripped), `introtext` (`<p>` escaped summary + the ready-made source line; only the line when the summary is empty), `metadesc` (summary cut at 159 characters, so at most 160 with the ellipsis), `created` (UTC) and `note` (`feedcurator:<source>`, max 255).
- `src/Feed/FeedException.php`, `FeedUnavailableException.php`: error types.
- `src/Import/Importer.php`: `run(Registry, taskId): ImportResult`. Per enabled source: fetch (30 s, Joomla user agent); unreachable, non-200 or non-feed means the source goes into `unavailable` and the others continue. Look-back window per source: `max_age_days`, or `first_run_weeks * 7` days (exempt from `max_per_run`) while `#__feedcurator_sources` has no row for the task and URL; that row is written after the run, only when none of the source's items failed. Then filter (`KeywordFilter`), merge on `key()`, drop known keys, oldest first, `max_per_run` for the rest. Articles go through `Table\Content` (state 0, noindex, workflow association, `onContentBeforeSave`/`AfterSave`); items without title or with a non-http(s) link count as skipped.
- `src/Import/Selector.php` (no Joomla calls): collects items per source, rejects items without http(s) link or title on entry (counted as skipped, never take a `max_per_run` slot), dedups on `key()` (a look-back flag survives), drops known keys, sorts oldest first, look-back items exempt from `max_per_run`.
- Language: the source line (stored content) and the log lines use the site language (`com_languages` site), not the CLI's en-GB; `Extension\Feedcurator::siteLanguage()` loads the plugin strings from `administrator/language` or else from the plugin's own `language/` folder, and `Importer::formatDate()` translates month names itself.
- Each item gets its `#__feedcurator_items` row right after `Table\Content::store()`, before the workflow association, so a later error can't cause a duplicate.
- `src/Import/ImportResult.php`, `FailureCounter.php`: result counts; failed runs in a row per task (`shouldReport()` from `notify_after`).
- `src/Extension/Feedcurator.php`: routine `feedcurator.import`: incomplete settings (`FeedException`) is KNOCKOUT; unavailable sources count in `FailureCounter`, KNOCKOUT from `notify_after` on, otherwise OK with a warning.
- `forms/import.xml`: task settings (`sources` subform, `keywords`, `catid`, `created_by`, `max_age_days`, `max_per_run`, `first_run_weeks`, `notify_groups`, `site_url`, `link_days`, `notify_after`).
- `language/{en-GB,nl-NL}/`: `plg_task_feedcurator.ini` and `.sys.ini`.

## Database

`sql/mysql/install.sql` equals `updates/26.48.0.sql` (idempotent):

- `#__feedcurator_items`: `item_key` CHAR(64) primary key (sha256), `source`, `article_id` (unique, NULL when discarded), `imported`.
- `#__feedcurator_failures`: `task_id` primary key, `failures`, `since`.
- `#__feedcurator_sources`: primary key (`task_id`, `url_hash`); a row means the one-time look-back of that source in that task is done.

`uninstall.sql` drops all three.
