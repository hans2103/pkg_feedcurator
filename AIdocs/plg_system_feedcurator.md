# System plugin (plg_system_feedcurator)

### History
- 2026-11-26: the review page: `review()`, `ReviewDecision`/`ReviewOutcome`, `ReviewArticle`, `tmpl/review.php`; `ReviewToken` refuses an empty secret and got `isGenuine()`
- 2026-11-26: added `src/Review/ReviewToken.php` (signed review links)
- 2026-11-26: page created with the scaffold; the review handler is empty

Last updated: 2026-11-26
Verified against: 00130a8
Covers:
- Src/plugins/system/feedcurator/

## Purpose

The review page behind the mail link: `index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a=&u=&e=&s=`. GET only shows the concept article and a form; a POST with the same signed fields and a form token publishes (state 1) or trashes (state -2) the article as the user the link was signed for.

## Files

- `feedcurator.xml`: manifest, namespace `HKweb\Plugin\System\Feedcurator` on `src/`; folders `services`, `src`, `tmpl`.
- `services/provider.php`: registers the plugin with application, database and user factory.
- `src/Extension/Feedcurator.php`: subscriber for `onAjaxFeedcurator` (method `review(AjaxEvent)`), site client only. Loads its language, sets `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store`, the page title and a robots meta, and adds the rendered HTML as the event result. com_ajax echoes that into the component area, so with `format=html` the page renders in the site template.
- `src/Review/ReviewToken.php`: Joomla-free. Key = `hash_hmac('sha256', 'feedcurator-review', siteSecret)`; `sign(a, u, e)` = hex HMAC-SHA256 of `a|u|e` (throws `\InvalidArgumentException` when the secret is empty or whitespace); `isGenuine(a, u, e, s)` checks the signature only (`hash_equals`); `verify(a, u, e, s, now)` adds the expiry (false after `e`); `isUsable()`; static `url(siteUrl, a, u, e, s)`. With an empty secret nothing verifies.
- `src/Review/ReviewOutcome.php`: enum `Show`, `Invalid`, `Expired`, `AlreadyDone` (`done`), `Act`.
- `src/Review/ReviewDecision.php`: Joomla-free `decide(signatureValid, expired, articleState, isPost, action)`: invalid signature → Invalid (before expiry); expired → Expired; POST with an action other than `publish`/`discard` → Invalid; state ≠ 0 → AlreadyDone; GET → Show; POST → Act.
- `src/Review/ReviewArticle.php`: Joomla-free `parse(introtext)`: plain-text summary, source line, link (http(s) only) and link text from the importer's `<p class="feedcurator-source">`.
- `tmpl/review.php`: the page (override: `templates/{tpl}/html/plg_system_feedcurator/review.php`). One `<h1>`, messages (`role="alert"` for errors and warnings, `status` otherwise), the article, the POST form (hidden `a`/`u`/`e`/`s`, `form.token`, two `<button name="action">`, at least 44 px high, inline CSS via the WebAssetManager), links. Everything escaped, no JavaScript.
- `language/{en-GB,nl-NL}/`: `plg_system_feedcurator.ini` (all `PLG_SYSTEM_FEEDCURATOR_*` page strings) and `.sys.ini`.

## Request flow (`Feedcurator::handle()`)

1. Read `a`, `u`, `e` (int), `s` (cmd), method, and on POST `action` (cmd). Signature genuine and `a`, `u` > 0; expired when `time() > e`.
2. Genuine and not expired: load the user (user factory; missing or blocked → Invalid) and the article (`Table\Content`; missing → `NOT_FOUND`, 404).
3. Outcome: Invalid → `INVALID` (403); Expired → `EXPIRED` (410); AlreadyDone → `DONE_PUBLISHED` / `DONE_TRASHED` / `DONE_OTHER` plus a link to a published article; Show → article and form. GET never writes anything.
4. Act (POST): `Session::checkToken()` (failure → `TOKEN`, 403; a new session gets Joomla's own redirect); `core.edit.state` on `com_content.article.{id}` for the user (missing → `NO_RIGHTS`, 403). Then `$app->loadIdentity($user)` and the input `option` set to `com_content` (the action log only logs loggable extensions), `createModel('Article', 'Administrator', ['ignore_request' => true])`, `setCurrentUser($user)`, `publish([$id], 1 | -2)`; both restored in `finally`.
5. A false return, an exception (com_content workflows on: the workflow plugin refuses) or an unchanged state (checked out by someone else) → the error, `USE_ADMIN` and a link to the article in the administrator. Success → `PUBLISHED` with a link to the article, or `DISCARDED` (trash; the item key stays in `#__feedcurator_items`, so the import does not bring it back).

## Known limits

- com_content workflows must be off (v1); with workflows on, publishing fails and the page points to the administrator.
- The page sends `Cache-Control: no-store`, but the System - Page Cache plugin can still cache it; a fix may follow.
- A site template that treats a URL without Itemid as the homepage may show the homepage instead of the review page.
