# System plugin (plg_system_feedcurator)

### History
- 2026-11-26: added `src/Review/ReviewToken.php` (signed review links)
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
- `src/Review/ReviewToken.php`: Joomla-free. Key = `hash_hmac('sha256', 'feedcurator-review', siteSecret)`; `sign(a, u, e)` = hex HMAC-SHA256 of `a|u|e`; `verify(a, u, e, s, now)` compares with `hash_equals` and is false after `e`; static `url(siteUrl, a, u, e, s)` builds `<site>/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a=&u=&e=&s=`.
- `language/{en-GB,nl-NL}/`: `plg_system_feedcurator.ini` and `.sys.ini`.
