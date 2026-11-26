# Package (pkg_feedcurator)

### History
- 2026-11-26: page created with the repository scaffold

Last updated: 2026-11-26
Verified against: b62791a
Covers:
- package.xml
- package_script.php
- package.sh
- Src/language/

## Purpose

`pkg_feedcurator` installs two extensions in one go: `plg_task_feedcurator` and `plg_system_feedcurator`. The shared tooling in `bin/` reads the version from `package.xml` and writes `<type>package</type>` entries to `updates.xml`.

## package.xml

- `<version>` is the single source of truth (calendar versioning `YY.WW.PATCH`); 26.48.0 is the first version.
- `<files>`: `plg_task_feedcurator.zip` (plugin, group task) and `plg_system_feedcurator.zip` (plugin, group system).
- `<blockChildUninstall>`: the extensions can only be uninstalled together, through the package.
- `<languages folder="language">`: `pkg_feedcurator.sys.ini` for en-GB and nl-NL.
- `<updateservers>`: `https://raw.githubusercontent.com/hans2103/pkg_feedcurator/main/updates.xml`; the zip downloads from the GitHub Release. No `<dlid>`.

## package_script.php

Class `Pkg_FeedcuratorInstallerScript` (Joomla finds it by the package element; a wrong class name means the script silently does not run).

- `preflight()`: refuses PHP below 8.3.0 and Joomla below 5.2.0 (`PKG_FEEDCURATOR_MIN_PHP`, `PKG_FEEDCURATOR_MIN_JOOMLA`). Keep these equal to `UPDATE_PHP_MINIMUM` and `UPDATE_TARGETPLATFORM` in `scripts.conf`.
- `postflight()`: on `install` and `discover_install` only, enables the plugins with element `feedcurator` in the folders `task` and `system`. An update keeps the site's choice.

## package.sh

- `bash package.sh` builds `packages/{version}/pkg_feedcurator-{version}.zip` with the version from `package.xml` and stamps nothing.
- `bash package.sh 26.48.1` checks the format (`JJ.WW.N`), stamps `<version>` and `<creationDate>` (Europe/Amsterdam) into `package.xml` and the two extension manifests and replaces `__DEPLOY_VERSION__` in `Src/` and `package_script.php`.
- Each extension folder is zipped with its contents in the zip root, without the patterns in `.deployignore` (also used by `bin/preflight.sh`). A missing folder stops the build.
- The package zip holds the manifest as `pkg_feedcurator.xml`, `package_script.php`, `language/{tag}/pkg_feedcurator.sys.ini` and the two extension zips.
- `tests/shell/test_package_sh.sh` builds in a copy of the repo and checks all of the above.
