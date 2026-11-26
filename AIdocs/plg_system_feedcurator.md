# System plugin (plg_system_feedcurator)

### History
- 2026-11-26: page created with the scaffold; the review handler is empty

Last updated: 2026-11-26
Verified against: b62791a
Covers:
- Src/plugins/system/feedcurator/

## Purpose

The review page behind the mail link, reached through `com_ajax` (`plugin=feedcurator&group=system`). In the scaffold `onAjaxFeedcurator` is empty.

## Files

- `feedcurator.xml`: manifest, namespace `HKweb\Plugin\System\Feedcurator` on `src/`.
- `services/provider.php`: registers the plugin with application and database.
- `src/Extension/Feedcurator.php`: subscriber for `onAjaxFeedcurator` (method `review()`).
- `language/{en-GB,nl-NL}/`: `plg_system_feedcurator.ini` and `.sys.ini`.
