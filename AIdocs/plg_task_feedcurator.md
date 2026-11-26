# Task plugin (plg_task_feedcurator)

### History
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
- `forms/import.xml`: task settings (empty for now).
- `language/{en-GB,nl-NL}/`: `plg_task_feedcurator.ini` and `.sys.ini`.

## Database

`sql/mysql/install.sql` equals `updates/26.48.0.sql` (idempotent):

- `#__feedcurator_items`: `item_key` CHAR(64) primary key (sha256), `source`, `article_id` (unique, NULL when discarded), `imported`.
- `#__feedcurator_failures`: `task_id` primary key, `failures`, `since`.
- `#__feedcurator_sources`: primary key (`task_id`, `url_hash`); a row means the one-time look-back of that source in that task is done.

`uninstall.sql` drops all three.
