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
- `src/Feed/FeedException.php`, `FeedUnavailableException.php`: error types.
- `forms/import.xml`: task settings (empty for now).
- `language/{en-GB,nl-NL}/`: `plg_task_feedcurator.ini` and `.sys.ini`.

## Database

`sql/mysql/install.sql` equals `updates/26.48.0.sql` (idempotent):

- `#__feedcurator_items`: `item_key` CHAR(64) primary key (sha256), `source`, `article_id` (unique, NULL when discarded), `imported`.
- `#__feedcurator_failures`: `task_id` primary key, `failures`, `since`.
- `#__feedcurator_sources`: primary key (`task_id`, `url_hash`); a row means the one-time look-back of that source in that task is done.

`uninstall.sql` drops all three.
