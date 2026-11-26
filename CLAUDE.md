# CLAUDE.md

> **Document length monitoring:** check at the start of every session the number of lines with
> `wc -l $(git rev-parse --show-toplevel)/CLAUDE.md`. Report to the user when this is **longer than 200 lines**.

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Feed Curator is a Joomla package (`pkg_feedcurator`) that imports on-topic RSS and Atom items as **unpublished** articles. A board member publishes or discards each item from a link in a mail. Built for Platform Gehandicapten Valkenswaard (gehandicaptenvalkenswaard.nl), usable by any Joomla site.

- `plg_task_feedcurator`: scheduled task *Import feed items* (routine `feedcurator.import`): fetches the configured feeds and saves matching items as unpublished articles.
- `plg_system_feedcurator`: the review page (`onAjaxFeedcurator`, via `com_ajax`) behind the mail link.

Design and plan: `docs/superpowers/specs/2026-11-26-feedcurator-design.md` and `docs/superpowers/plans/2026-11-26-feedcurator.md`. Read the spec before changing behaviour.

**Requirements:** Joomla 5.2+ (Joomla 6 ready), PHP 8.3+, MySQL/MariaDB.

Language convention: PHP docblocks in English; commit messages and the workflow docs in `docs/` in Dutch; `README.md` and this file in English. Import log messages are Dutch.

## Repository Layout

```
Src/
├── plugins/task/feedcurator/         # task plugin (manifest feedcurator.xml)
│   ├── src/Extension/Feedcurator.php # routine feedcurator.import
│   ├── forms/import.xml  sql/mysql/{install,uninstall}.sql  sql/mysql/updates/
│   └── language/{en-GB,nl-NL}/
├── plugins/system/feedcurator/       # review page
└── language/{en-GB,nl-NL}/pkg_feedcurator.sys.ini
package.xml  package_script.php  package.sh  .deployignore  cliff.toml  updates.xml  scripts.conf
bin/  docs/  AIdocs/  tests/{unit,shell}/  .githooks/  .github/
```

Never edit `packages/` (build output) or `updates.xml` by hand: `bin/add-update-entry.php` appends the release entries.

## AIdocs

`AIdocs/` holds verified documentation for AI assistants. Read `AIdocs/_index.md` first, then the page for the code you are about to change.

- Every content page starts with `History`, `Last updated`, `Verified against` (a commit) and `Covers:`.
- When you change files a page covers, update that page in the same PR.
- `bash bin/preflight.sh` checks coverage and structure (check 14) and the 200-line limit (check 7, an ERROR here).

## Commands

```bash
bash package.sh [version]          # build packages/{version}/pkg_feedcurator-{version}.zip
bash bin/preflight.sh              # lint, manifests, versions, secrets, AIdocs, strict_types (read-only; --all: whole repo)
bash bin/run-release.sh            # full release: merge develop → main, build, commit, tag, push, wait for the release
bash bin/deploy-ddev.sh [-n] [-y]  # rsync the extensions (DEV_LINKS) into the local Joomla site
bash tests/shell/run-all.sh        # tests for bin/
bash bin/ci-local.sh               # the CI checks locally; stamps HEAD for the pre-push hook and gh pr ready (10 min timeout)
for f in tests/unit/*.php; do php "$f" || echo "FAIL $f"; done   # plain-PHP unit tests, no PHPUnit
```

`bin/` is byte-identical in every HKweb Joomla repo (reference: `pkg_informanagement`). Everything that differs per repo is a variable in `scripts.conf`; change a script in one repo and copy it to the others, never fork it.

Each unit test is a standalone script that includes `tests/bootstrap.php` (autoloader for both plugin namespaces, `check()`, `thrown()`, `fixture()`, `finish()`) and exits non-zero on failure. Fixtures in `tests/unit/fixtures/` are made up; never commit real feed content.

## Branch Strategy

- `main` — production; only receives tagged releases
- `develop` — integration branch; all feature PRs merge here first
- Feature branches: `feature/{issue}-{short-description}`
- Never commit directly to `main` or `develop`
- Full workflow: `docs/Ontwikkelworkflow.md`

## Committing

Commit messages in Dutch, conventional and with an issue number: `feat(import): #12 omschrijving` (`#000` when there is no issue). No `Co-Authored-By` line. Activate the hook once per clone: `git config core.hooksPath .githooks`.

## PR's

Do **not** add a 'Generated with Claude Code' line in a PR.

Open a PR as a draft (`gh pr create --draft --base develop`), run `bash bin/ci-local.sh` until green, then `gh pr ready`; the PreToolUse hook in `.claude/settings.json` refuses `gh pr ready` and a non-draft `gh pr create` without a stamp. Details: `docs/Ontwikkelworkflow.md`.

## Versioning & Release

Calendar versioning `YY.WW.PATCH` (first version `26.48.0`). The single source of truth is `<version>` in `package.xml`; `package.sh {version}` stamps it into the two extension manifests and replaces `__DEPLOY_VERSION__`.

- `bash bin/run-release.sh` runs the release. The tag `v{version}` triggers `.github/workflows/release.yml`: GitHub Release with git-cliff notes, then `updates.xml` on main. GitHub is the update server: `package.xml` points to `https://raw.githubusercontent.com/hans2103/pkg_feedcurator/main/updates.xml` and the zip downloads from the GitHub Release (`UPDATE_DOWNLOAD_BASE` empty). No own server, no secrets, no download key.
- Details: `docs/Release en deployment procedure.md`.

## Code Standards

- Joomla coding standard (tabs, braces on their own line); `declare(strict_types=1);` in every class file
- DocBlocks with `@since`; new code gets `@since __DEPLOY_VERSION__`
- Database changes: a new `sql/mysql/updates/{version}.sql` (idempotent, `CREATE TABLE IF NOT EXISTS`) plus the same in `install.sql` and a `DROP` in `uninstall.sql`. A file deploy alone does not run SQL: sites must install the package.
- New task settings: field in `forms/import.xml` + en-GB and nl-NL strings + `$params->get()` with the same default; `tests/unit/LanguageContractTest.php` checks the strings.

## Important Implementation Details

- Links: `ReviewToken::url()` builds `index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a=&u=&e=&s=` (article, user, expiry, HMAC of `a|u|e` with a key derived from the site secret). GET shows, POST (with form token) acts. An empty site secret means no mail and no valid link.
- Known limits (v1): com_content workflows must be off; recipients need `core.edit.state`; the task needs `site_url` (CLI); the review page can be page-cached and a template may treat a URL without Itemid as the homepage. See the README.
- The review action sets the request option to `com_content` and the current user while it calls the Article model, so the action log records it; both are restored afterwards.

- Tables: `#__feedcurator_items` (item key, sha256 of the link, to article), `#__feedcurator_failures` (failed runs in a row per task) and `#__feedcurator_sources` (sources whose one-time look-back is done, per task).
- The task provider registers the namespace of the system plugin (`JLoader::registerNamespace`), because the routine uses `ReviewToken` from there.
- Articles hold no full text and no images from the source: title, a short summary and a source line with the link.
