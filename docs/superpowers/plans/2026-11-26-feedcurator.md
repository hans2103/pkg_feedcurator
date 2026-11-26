# pkg_feedcurator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A reusable Joomla package that imports on-topic RSS/Atom items as unpublished articles, mails the board, and lets a board member publish or discard each item from a link in that mail.

**Architecture:** Two plugins in one package, built exactly like `pkg_informanagement`. `plg_task_feedcurator` does fetch → parse → filter → article (concept) → mail. `plg_system_feedcurator` serves the review page through `com_ajax`: GET shows, POST acts under the recipient's own identity. Everything that does not need Joomla (parsing, filtering, summary, mapping, signing) lives in Joomla-free classes with plain-PHP unit tests.

**Tech Stack:** Joomla 5.2+/6, PHP 8.3+, MySQL/MariaDB, the HKweb `bin/` tooling, plain-PHP tests (`tests/bootstrap.php`), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-11-26-feedcurator-design.md` in gehandicaptenvalkenswaard (Task 1 copies it into the new repo as `docs/superpowers/specs/`).

**Reference repo (read it, copy its patterns):** `~/Development/Repos/pkg_informanagement` (first pull `main`: the local clone is 1 commit behind).

## Global Constraints

- New repo at `~/Development/Repos/pkg_feedcurator`, GitHub name `hans2103/pkg_feedcurator` (public). **Do not create the GitHub repo, push, or release without an explicit go from Hans.** Everything stays local until then.
- Element name `feedcurator` everywhere: `plg_task_feedcurator`, `plg_system_feedcurator`, `pkg_feedcurator`, tables `#__feedcurator_items` and `#__feedcurator_failures`, language prefixes `PLG_TASK_FEEDCURATOR_`, `PLG_SYSTEM_FEEDCURATOR_`, `PKG_FEEDCURATOR_`.
- Namespaces `HKweb\Plugin\Task\Feedcurator\` and `HKweb\Plugin\System\Feedcurator\`.
- Requirements Joomla 5.2+ (6 ready), PHP 8.3+; `UPDATE_TARGETPLATFORM='(5\.([2-9]|[1-9][0-9])|6\.[0-9])'`, `UPDATE_PHP_MINIMUM="8.3.0"`.
- Calendar versioning `YY.WW.PATCH`; first version `26.48.0`. `<version>` in `package.xml` is the only source.
- `bin/` byte-identical to the reference. Repo differences go in `scripts.conf` only.
- Joomla coding standard (tabs), `declare(strict_types=1);` in every class file, docblocks in English with `@since __DEPLOY_VERSION__`.
- Commits in Dutch, conventional, `#000` without an issue, no `Co-Authored-By`. Branches `main` / `develop` / `feature/…`.
- Every user-facing string in en-GB **and** nl-NL. Import log messages in Dutch, as in the reference.
- Articles contain no full text and no images from the source: title, summary of at most 300 characters, source line with link. Meta robots `noindex, follow`.
- GET on the review URL never changes anything.
- Test fixtures are made up; never commit real feed content.

## Review Focus

1. **A link scanner opens the mail link** (GET, no cookies, possibly twice): nothing changes, the page renders. Covered in Task 7, step "GET is read-only".
2. **A feed that is temporarily down or returns an HTML maintenance page** next to working feeds: the other sources still import, the run is not KNOCKOUT before `notify_after`. Covered in Task 5 (`FeedUnavailableException` per source) and Task 2 (HTML body → exception).
3. **Board member clicks Publish after someone else already discarded or published**: no state change, a clear message with the current state. Covered in Task 7.
4. **Feed with odd encoding/markup** (CDATA, HTML entities, `&amp;` in links, WordPress "Het bericht … verscheen eerst op" footer, no description): summary is clean text, link is decoded once. Covered in Task 2 and Task 3.
5. **First run on a feed with 25+ old items**: without the look-back, only items within `max_age_days` and at most `max_per_run` become concepts. With `first_run_weeks`, all matching items in that period become concepts once, with their original date. A source that is down on its first run looks back later. Covered in Task 5, step 5b.

---

### Task 1: Repository scaffold and tooling

**Files:**
- Create `~/Development/Repos/pkg_feedcurator/` with: `bin/` (copied unchanged), `.githooks/`, `.github/workflows/{validate,release}.yml`, `.editorconfig`, `.gitignore`, `.deployignore`, `cliff.toml`, `LICENSE.txt`, `docs/Ontwikkelworkflow.md`, `docs/Release en deployment procedure.md` (copied, `informanagement` → `feedcurator`, jacc → gehandicaptenvalkenswaard), `docs/superpowers/specs/2026-11-26-feedcurator-design.md`, `docs/superpowers/plans/2026-11-26-feedcurator.md`
- Create `scripts.conf`, `package.xml`, `package_script.php`, `package.sh`, `updates.xml` (empty `<updates/>`), `Src/language/{en-GB,nl-NL}/pkg_feedcurator.sys.ini`
- Create minimal installable plugins: `Src/plugins/task/feedcurator/{feedcurator.xml,services/provider.php,src/Extension/Feedcurator.php,forms/import.xml,sql/mysql/{install,uninstall}.sql,sql/mysql/updates/26.48.0.sql,language/{en-GB,nl-NL}/plg_task_feedcurator{,.sys}.ini}` and `Src/plugins/system/feedcurator/{feedcurator.xml,services/provider.php,src/Extension/Feedcurator.php,language/{en-GB,nl-NL}/plg_system_feedcurator{,.sys}.ini}`
- Create `CLAUDE.md`, `README.md`, `AIdocs/_index.md`, `AIdocs/pkg_feedcurator.md`, `tests/bootstrap.php`, `tests/unit/LanguageContractTest.php`, `tests/shell/` (copied; `test_package_sh.sh` adapted to the two zips)

**Interfaces:**
- Produces: `tests/bootstrap.php` with `check()`, `thrown()`, `fixture()`, `finish()` as in the reference, autoloading **both** namespaces (`HKweb\Plugin\Task\Feedcurator\` → `Src/plugins/task/feedcurator/src/`, `HKweb\Plugin\System\Feedcurator\` → `Src/plugins/system/feedcurator/src/`).
- Produces: SQL tables (install.sql = updates/26.48.0.sql, idempotent):
  - `#__feedcurator_items`: `item_key CHAR(64) PK` (sha256), `source VARCHAR(255)`, `article_id INT UNSIGNED NULL` (unique), `imported DATETIME`
  - `#__feedcurator_failures`: same as `#__informanagement_failures` (`task_id` PK, `failures`, `since`)
  - `#__feedcurator_sources`: `task_id INT UNSIGNED`, `url_hash CHAR(64)` (sha256 of the source URL), `first_run DATETIME`; PK (`task_id`, `url_hash`). A row means the one-time look-back of that source in that task is done.

- [ ] **Step 1:** `git -C ~/Development/Repos/pkg_informanagement pull --ff-only`, then `git init` the new repo with `main`, an empty first commit, and branch `develop`. `git config core.hooksPath .githooks`.
- [ ] **Step 2:** Copy the files listed above; adapt `scripts.conf` (PROJECT_NAME `feedcurator`, PACKAGE_ELEMENT `pkg_feedcurator`, ENV_PREFIX `FEEDCURATOR`, GITHUB_REPO `hans2103/pkg_feedcurator`, JOOMLA_DIR_DEFAULT `$HOME/Development/Sites/gehandicaptenvalkenswaard/public_html`, DDEV_PROJECT `gehandicaptenvalkenswaard`, two manifests, two DEV_LINKS, `UPDATE_NAME="Feed Curator"`, the description in English). In the workflows, replace the informanagement zip and file lists with the two feedcurator zips (keep the structure of each check).
- [ ] **Step 3:** `package.xml` like the reference with two `<file type="plugin">` entries and the update server `https://raw.githubusercontent.com/hans2103/pkg_feedcurator/main/updates.xml`. `package_script.php` class `Pkg_FeedcuratorInstallerScript`, same PHP/Joomla floors, enables `task/feedcurator` and `system/feedcurator` on install and discover_install.
- [ ] **Step 4:** The task routine is registered but returns `TaskStatus::OK` for now; the system plugin has an empty `onAjaxFeedcurator`. Both providers follow the reference provider. The task provider also calls `\JLoader::registerNamespace('HKweb\\Plugin\\System\\Feedcurator', JPATH_PLUGINS . '/system/feedcurator/src')`, because Task 6 uses `ReviewToken` from the system plugin.
- [ ] **Step 5:** Run `bash bin/preflight.sh --all`, `bash tests/shell/run-all.sh`, `bash package.sh`, `php tests/unit/LanguageContractTest.php`. Expected: all green, and `packages/26.48.0/pkg_feedcurator-26.48.0.zip` holds `pkg_feedcurator.xml`, `package_script.php`, both language files, `plg_task_feedcurator.zip` and `plg_system_feedcurator.zip`.
- [ ] **Step 6:** Install the zip on the local site (`ddev launch` admin → Install) and check that both plugins are enabled and both tables exist (`ddev mysql -e "SHOW TABLES LIKE '%feedcurator%'"`).
- [ ] **Step 7:** Commit on `develop`: `chore: #000 repo opgezet naar het voorbeeld van pkg_informanagement`.

### Task 2: Feed parsing (RSS 2.0 and Atom)

**Files:**
- Create: `Src/plugins/task/feedcurator/src/Feed/{FeedItem,FeedParser,FeedException,FeedUnavailableException}.php`
- Test: `tests/unit/FeedParserTest.php`, fixtures `tests/unit/fixtures/{wordpress.xml,municipality.xml,atom.xml}`

**Interfaces:**
- Produces: `final readonly class FeedItem { string $guid; string $link; string $title; string $description; list<string> $categories; ?\DateTimeImmutable $published; public function key(): string }`. `key()` = sha256 of the normalised link (trimmed, scheme and host lowercased, fragment removed); when `link` is empty, sha256 of `guid`.
- Produces: `final class FeedParser { /** @return list<FeedItem> */ public function parse(string $xml): array }`. Throws `FeedUnavailableException` (extends `FeedException`) for a body that is neither an RSS `channel` nor an Atom `feed`.

- [ ] **Step 1: Write the failing test.** Fixtures modelled on the three real formats (made-up content): WordPress RSS with CDATA description, `<category>`, "Het bericht … verscheen eerst op …" footer; the municipality's plain RSS with `<enclosure>` and a guid that is a URL; Atom with `<entry>`, `<link rel="alternate" href>`, `<updated>`, `<summary type="html">`. Assertions:
  - `check('rss item count', count($parser->parse(fixture('wordpress.xml'))), 2)`
  - title entities decoded: `"Fietsers & voetgangers"` from `Fietsers &amp; voetgangers`
  - `categories` of the first WordPress item `['Onderwijs']`
  - `published` in UTC for `Wed, 25 Nov 2026 10:45:33 +0000` → `'2026-11-25 10:45:33'`
  - link with `&amp;` is decoded once: `'https://example.org/a?x=1&y=2'`
  - Atom: link from `rel="alternate"`, `published` from `<updated>` when `<published>` is missing
  - item without a title or without both link and guid is skipped
  - `key()` equal for `https://Example.org/a#top` and `https://example.org/a`; 64 characters
  - `thrown(fn () => $parser->parse('<html>Onderhoud</html>'))` === `FeedUnavailableException::class`
  - XXE test as in the reference FeedParserTest
- [ ] **Step 2:** `php tests/unit/FeedParserTest.php`. Expected: fails (class not found).
- [ ] **Step 3:** Implement with `simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA)` and libxml errors suppressed, as in the reference FeedParser. No Joomla calls in `src/Feed/`.
- [ ] **Step 4:** Run the test. Expected: `All FeedParser assertions passed.`
- [ ] **Step 5:** Commit `feat(feed): #000 RSS- en Atom-feeds inlezen`.

### Task 3: Source settings, keyword filter and summary

**Files:**
- Create: `Src/plugins/task/feedcurator/src/Feed/{Source,KeywordFilter,Summary}.php`
- Test: `tests/unit/KeywordFilterTest.php`, `tests/unit/SummaryTest.php`

**Interfaces:**
- Consumes: `FeedItem` (Task 2).
- Produces: `final readonly class Source { string $name; string $url; bool $filter; list<string> $keywords; list<string> $exclude; bool $enabled; public static function fromArray(array $row): self }`. `$row` is one subform row (`name`, `url`, `filter`, `keywords`, `exclude`, `enabled`); keyword fields are textareas, one term per line, via `KeywordFilter::parseList()`.
- Produces: `final class KeywordFilter { public function __construct(list<string> $include, list<string> $exclude); public function matches(FeedItem $item): bool; public static function parseList(string $text): list<string> }`
- Produces: `final class Summary { public static function fromHtml(string $html, int $max = 300): string }`

- [ ] **Step 1: Write the failing tests.**
  - `parseList("Wmo\n  toegankelijk* \n\nWmo")` → `['Wmo', 'toegankelijk*']` (trimmed, empty lines dropped, duplicates case-insensitively removed)
  - matching runs on title + description (tags stripped) + categories, case-insensitively, on word boundaries: `wmo` matches "De Wmo-consulent"; `blind*` matches "blinden"; `blind` does **not** match "verblindend"; phrase `Agenda 22` matches "agenda 22"; `toegankelijk*` matches "Toegankelijkheid"
  - an exclude term wins: include `beperking`, exclude `snelheidsbeperking`, title "Snelheidsbeperking Eindhovenseweg" → false
  - an empty include list → `matches()` false (a source with `filter` on and no terms imports nothing)
  - Unicode: `ziek*` matches "Chronisch zieken", with `/u`
  - `Summary::fromHtml('<p>Een passende plek&nbsp;is niet vanzelfsprekend.</p><p>Het bericht <a href="x">Titel</a> verscheen eerst op <a>Iederin</a>.</p>')` → `'Een passende plek is niet vanzelfsprekend.'`
  - a 400-character text is cut at the last space before 300 and ends with `'…'`; result length ≤ 301
  - an empty description → `''`
- [ ] **Step 2:** Run both tests. Expected: they fail.
- [ ] **Step 3:** Implement. Each term becomes a regex: `preg_quote`, a trailing `*` becomes `\w*`, whitespace in a phrase becomes `\s+`, wrapped in `(?<!\w)…(?!\w)` with flags `iu`. Summary: remove the WordPress footer paragraph (`/<p>\s*Het bericht .*? verscheen eerst op .*?<\/p>/isu`), `strip_tags`, `html_entity_decode(ENT_QUOTES | ENT_HTML5, 'UTF-8')`, collapse whitespace (including `\x{00A0}`), cut with `mb_` functions.
- [ ] **Step 4:** Run both tests. Expected: pass.
- [ ] **Step 5:** Commit `feat(feed): #000 trefwoordfilter en samenvatting`.

### Task 4: Article mapping

**Files:**
- Create: `Src/plugins/task/feedcurator/src/Feed/ArticleMapper.php`
- Test: `tests/unit/ArticleMapperTest.php`

**Interfaces:**
- Consumes: `FeedItem`, `Source`, `Summary`.
- Produces: `final class ArticleMapper { /** @return array{title: string, introtext: string, metadesc: string, created: string, note: string} */ public function map(FeedItem $item, Source $source, string $sourceLine): array }`. `$sourceLine` is the already-translated, already-escaped HTML of the source line (built in Joomla code in Task 5, because of the language strings and date format). `created` = `published` in UTC `Y-m-d H:i:s` (or the current UTC time when null). `note` = `'feedcurator:' . $source->name`, cut to 255 characters.

- [ ] **Step 1: Write the failing test:**
  - `introtext` === `'<p>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p>' . $sourceLine` for a fixed item; with an empty summary, only `$sourceLine`
  - `metadesc` = summary cut to 160 characters, without `…` beyond 160
  - title with `<script>` is stored as text: the `title` value has tags stripped
  - `created` for `published` 2026-11-25 10:45:33 UTC → `'2026-11-25 10:45:33'`
  - `note` === `'feedcurator:Ieder(in)'`
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run. Expected: pass.
- [ ] **Step 5:** Commit `feat(feed): #000 feeditem omzetten naar artikelvelden`.

### Task 5: Import run (task routine)

**Files:**
- Create: `Src/plugins/task/feedcurator/src/Import/{Importer,ImportResult,FailureCounter}.php`
- Modify: `src/Extension/Feedcurator.php`, `forms/import.xml`, task language files
- Test: `tests/unit/FailureCounterTest.php` (as in the reference), `tests/unit/LanguageContractTest.php` (already exists, must stay green)

**Interfaces:**
- Consumes: Tasks 2–4.
- Produces: `final class ImportResult { /** @var list<array{id: int, title: string, source: string}> */ public array $created; public int $skipped; public int $known; /** @var list<string> */ public array $unavailable; public int $failed }`.
- Produces: `Importer::__construct(CMSApplicationInterface $app, DatabaseInterface $db, HttpFactory $http)` and `Importer::run(Registry $params, int $taskId): ImportResult`. Throws `FeedException` for incomplete settings (no enabled source, no `catid`, no `site_url`).
- Produces for Task 6: `Extension\Feedcurator::import()` calls `Notifier::notify(ImportResult $result, Registry $params): int` after a run with `created` not empty.

- [ ] **Step 1:** `forms/import.xml` fields exactly as in the spec: `sources` (subform `multiple="true"`, fields `name` text required, `url` url required `validate="url"` with `schemes="https"`, `filter` switcher default 1, `keywords` textarea, `exclude` textarea, `enabled` switcher default 1), `keywords` textarea, `catid` category required, `created_by` user, `max_age_days` number 1–365 default 30, `max_per_run` number 1–100 default 10, `first_run_weeks` number 0–52 default 0, `notify_groups` usergrouplist multiple, `site_url` url required, `link_days` number 1–30 default 7, `notify_after` number 1–24 default 3. Language strings in en-GB and nl-NL; `php tests/unit/LanguageContractTest.php` passes.
- [ ] **Step 2:** Write `FailureCounterTest.php` like the reference (table `#__feedcurator_failures`), run it (fail), copy `FailureCounter` with the new table and namespace, run it (pass).
- [ ] **Step 3:** Implement `Importer::run()`:
  1. Per enabled source: fetch with a 30 s timeout and the Joomla user agent. No connection, non-200 or `FeedUnavailableException` → name in `unavailable`, log a warning in Dutch, continue with the next source.
  2. Look-back window per source: with `first_run_weeks` > 0 and no `#__feedcurator_sources` row for (`task_id`, sha256(url)), the window is `first_run_weeks * 7` days and the items of this source are **exempt from `max_per_run`**. Otherwise the window is `max_age_days`. Insert the row only after the source has been fetched and its items stored without an exception, so an unavailable source looks back on its next successful run. `Importer::run()` therefore gets the task id: `run(Registry $params, int $taskId)`.
     Keep items with `published` within that window (null counts as now). Apply `KeywordFilter` when `filter` is on (global `keywords` + the source's `keywords`, the source's `exclude`); otherwise everything. Non-matching → `skipped`.
  3. Merge all sources, drop duplicate `key()`s, then drop keys already in `#__feedcurator_items` (`known`). Sort oldest first; the look-back items all go through, and of the others take `max_per_run`; the rest waits for the next run.
  4. Per item: build `$sourceLine` with `Text::sprintf('PLG_TASK_FEEDCURATOR_SOURCE_LINE', esc(name), HTMLHelper::date(published, Text::_('DATE_FORMAT_LC3')), esc(link))`, where nl-NL is `<p class="feedcurator-source">Bron: %1$s, %2$s — <a href="%3$s" rel="noopener" target="_blank">Lees het volledige bericht</a></p>`. Store through `Table\Content` with `state = 0`, `catid`, unique alias, `created`/`publish_up` = mapped `created`, `created_by`, `language = '*'`, `access = 1`, `metadata = {"robots":"noindex, follow"}`, workflow association and `onContentBeforeSave`/`onContentAfterSave`. Copy that block from reference `Importer.php` (around lines 380–415). Then insert the `#__feedcurator_items` row. An exception counts as `failed` and is logged; the run continues.
- [ ] **Step 4:** `Extension\Feedcurator::import()` maps results as in the reference: `FeedException` (settings) → KNOCKOUT; `unavailable` not empty → `FailureCounter::increment()`, KNOCKOUT only from `notify_after` on, otherwise OK with a warning; otherwise `reset()`. Result line in the log: `"{n} nieuw, {skipped} niet op onderwerp, {known} al bekend, {failed} mislukt"`.
- [ ] **Step 5:** Verify on the local site: `bash bin/deploy-ddev.sh -y`; create a task with the three real sources from the spec, `notify_groups` empty and the keyword list from the spec; `ddev exec php public_html/cli/joomla.php scheduler:run --id={id}`. Expected: concept articles (state 0) in the chosen category with summary + source line and no images, rows in `#__feedcurator_items`, the result line in `administrator/logs`. Run it again: `0 nieuw` and the same number of articles.
- [ ] **Step 5b: One-time look-back:** empty the three feedcurator tables and delete the test articles, then set `first_run_weeks = 12` and `max_per_run = 2` and run the task. Expected: every matching item of the last 12 weeks that the feed still offers becomes a concept, more than 2. `created` and `publish_up` equal the original `pubDate` (check one against the feed). There is one `#__feedcurator_sources` row per source. Run it again: only `max_age_days` applies and the count shows `0 nieuw`.
- [ ] **Step 6:** Verify the failure path: set one source URL to `https://example.org/geen-feed`; run. Expected: the other sources still import and the log shows a warning, status OK; after 3 runs KNOCKOUT.
- [ ] **Step 7:** Commit `feat(import): #000 feeds importeren als conceptartikel`.

### Task 6: Signed review links and the notification mail

**Files:**
- Create: `Src/plugins/system/feedcurator/src/Review/ReviewToken.php` (Joomla-free), `Src/plugins/task/feedcurator/src/Mail/{Notifier,MailBody}.php`
- Test: `tests/unit/ReviewTokenTest.php`, `tests/unit/MailBodyTest.php`

**Interfaces:**
- Produces: `final class ReviewToken { public function __construct(string $siteSecret); public function sign(int $articleId, int $userId, int $expires): string; public function verify(int $articleId, int $userId, int $expires, string $signature, int $now): bool; public static function url(string $siteUrl, int $articleId, int $userId, int $expires, string $signature): string }`. Key = `hash_hmac('sha256', 'feedcurator-review', $siteSecret)`; signature = hex HMAC-SHA256 of `"{$articleId}|{$userId}|{$expires}"`. `url()` = `rtrim($siteUrl, '/') . '/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a={a}&u={u}&e={e}&s={s}'`.
- Produces: `final class MailBody { /** @param list<array{title: string, source: string, url: string}> $items */ public static function text(string $intro, array $items, string $linkLabel): string; public static function html(string $intro, array $items, string $linkLabel): string }`
- Produces: `Notifier::__construct(CMSApplicationInterface $app, DatabaseInterface $db)`, `Notifier::notify(ImportResult $result, Registry $params): int` (number of mails sent).

- [ ] **Step 1: Write the failing tests:**
  - `verify()` true for its own signature before `$expires`; false at `$now === $expires + 1`; false with another `$userId`, another `$articleId`, a changed `$expires`, or a different secret; the comparison uses `hash_equals`
  - `url()` for `https://example.org/` contains `index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a=12&u=5&e=` and no double slash after the host
  - `MailBody::html()` escapes `<b>` in a title; one `<li>` per item with an `<a href>` to its URL; `text()` puts each URL on its own line
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement `ReviewToken` and `MailBody`. `Notifier`: recipients = users with `block = 0` and `activation` empty (or `'0'`) in `notify_groups` (join `#__user_usergroup_map`), unique by id; none → log a warning, return 0. Per recipient: `$expires = time() + link_days * 86400`, one mail through `MailerFactoryInterface` (`isHtml(true)`, `AltBody` = text version), subject `Text::plural('PLG_TASK_FEEDCURATOR_MAIL_SUBJECT', n, sitename)`. A mail exception is logged and does not fail the run.
- [ ] **Step 4:** Run the tests. Expected: pass.
- [ ] **Step 5:** Verify locally: give the task `notify_groups` = a group with one test user (mail caught by DDEV Mailpit, `ddev launch -m`), delete one item row and its article, run the task. Expected: one mail in Mailpit with one link per new article, and the link contains `a`, `u`, `e`, `s`.
- [ ] **Step 6:** Commit `feat(mail): #000 bestuur mailen met ondertekende beoordelingslinks`.

### Task 7: Review page (system plugin)

**Files:**
- Modify: `Src/plugins/system/feedcurator/src/Extension/Feedcurator.php`, system language files
- Create: `Src/plugins/system/feedcurator/src/Review/ReviewDecision.php` (Joomla-free), `Src/plugins/system/feedcurator/tmpl/review.php`
- Test: `tests/unit/ReviewDecisionTest.php`

**Interfaces:**
- Consumes: `ReviewToken` (Task 6).
- Produces: `enum ReviewOutcome: string { case Show = 'show'; case Invalid = 'invalid'; case Expired = 'expired'; case AlreadyDone = 'done'; case Act = 'act'; }` and `final class ReviewDecision { public static function decide(bool $signatureValid, bool $expired, int $articleState, bool $isPost, ?string $action): ReviewOutcome }`. `$action` is `'publish'` or `'discard'`; anything else counts as Invalid on POST.

- [ ] **Step 1: Write the failing test** for the full matrix: invalid signature → Invalid (also when expired); valid + expired → Expired; valid, state ≠ 0 → AlreadyDone (GET and POST); valid, state 0, GET → Show; valid, state 0, POST + `publish` / `discard` → Act; POST + `delete` → Invalid.
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement `ReviewDecision`, then `onAjaxFeedcurator(Event $event)`:
  - Read `a`, `u`, `e` (int) and `s` (cmd) from the input; load the article (`Table\Content`) and the user (`User::getInstance($u)`, must exist and not be blocked, otherwise Invalid).
  - GET: render `tmpl/review.php` (title, summary from the introtext, source line, current state, a POST form with hidden `a`/`u`/`e`/`s`, `HTMLHelper::_('form.token')`, buttons `name="action" value="publish"` and `value="discard"`). Never change anything.
  - POST: `Session::checkToken()` or a message; on Act: `$app->loadIdentity($user)`, then `$app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true])->publish($pks, $action === 'publish' ? 1 : -2)`. A false return (no `core.edit.state`, or workflows on) → show the model error plus `PLG_SYSTEM_FEEDCURATOR_USE_ADMIN` with a link to the article in the administrator. Success → a confirmation with a link to the article (publish) or the remark that it is in the trash (discard).
  - Set the result as the event result (`$event->addResult($html)`), so com_ajax renders it inside the site template. Set `X-Robots-Tag: noindex` and `Cache-Control: no-store`.
  - The template: one `<h1>`, a `<main>`-friendly structure, buttons as real `<button>`s at least 44 px high, and no JavaScript needed.
- [ ] **Step 4:** Run the unit test. Expected: pass.
- [ ] **Step 5: GET is read-only:** run `curl -s -o /dev/null -w '%{http_code}' '{link from Mailpit}'` twice without cookies. Expected: 200 both times, article still state 0.
- [ ] **Step 6:** In a browser, open the link → Publiceren. Expected: the article is published, the action log shows the test user's name, and opening the link again shows "al gepubliceerd". Second link → Weggooien → article in the trash; a new import run does not bring it back.
- [ ] **Step 7:** Expected refusals: change `e` in the URL → "ongeldige link"; a link for a user without `core.edit.state` → a rights message, no change; turn on com_content workflows → Publiceren gives the admin message, no change (turn them off again).
- [ ] **Step 8:** Commit `feat(review): #000 beoordelingspagina met publiceren en weggooien`.

### Task 8: Documentation and local CI

**Files:**
- Create: `AIdocs/plg_task_feedcurator.md`, `AIdocs/plg_system_feedcurator.md`
- Modify: `AIdocs/_index.md`, `AIdocs/pkg_feedcurator.md`, `README.md`, `CLAUDE.md`

- [ ] **Step 1:** Write the AIdocs pages in the reference format (`History`, `Last updated`, `Verified against`, `Covers:`). They describe the settings, the run, the tables, the link format, GET/POST and the workflow limitation. README in English: what it does, requirements, installation, setting up a task, the mail and review flow, the limitation with workflows. CLAUDE.md like the reference, under 200 lines.
- [ ] **Step 2:** `bash bin/ci-local.sh`. Expected: green. This includes preflight check 14 (AIdocs coverage) and check 7 (length).
- [ ] **Step 3:** Commit `docs: #000 AIdocs, README en CLAUDE.md`.
- [ ] **Step 4:** Stop. Report to Hans and ask for a go on: creating `hans2103/pkg_feedcurator` on GitHub, pushing `main`/`develop`, and the first release with `bash bin/run-release.sh`. The site setup (sources, keywords, category, the scheduler on live, the template on `/nieuws`) is a separate plan in the gehandicaptenvalkenswaard repo.
