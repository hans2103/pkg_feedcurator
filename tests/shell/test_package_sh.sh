#!/bin/bash
# Tests voor package.sh van pkg_feedcurator: bouwt in een kopie van de repo (de werkmap blijft
# ongemoeid) en controleert de zip, het stempelen en __DEPLOY_VERSION__.
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"

copy="$tmp/repo"
mkdir -p "$copy"
( cd "$repo" && git ls-files -z --cached --others --exclude-standard | xargs -0 -I{} rsync -R {} "$copy/" )
cd "$copy" || exit 1

version=$(grep -m1 '<version>' package.xml | sed -E 's#.*<version>([^<]+)</version>.*#\1#')

expect "build zonder versie" 0 bash package.sh
zip="packages/${version}/pkg_feedcurator-${version}.zip"
[ -f "$zip" ] && pass "pakket-zip bestaat" || bad "pakket-zip ontbreekt: $zip"
list=$(unzip -Z1 "$zip")
for entry in pkg_feedcurator.xml package_script.php plg_task_feedcurator.zip plg_system_feedcurator.zip language/en-GB/pkg_feedcurator.sys.ini language/nl-NL/pkg_feedcurator.sys.ini; do
  echo "$list" | grep -qx "$entry" && pass "  in het pakket: $entry" || bad "  ontbreekt in het pakket: $entry"
done
unzip -p "$zip" plg_task_feedcurator.zip > "$tmp/plg.zip"
plg=$(unzip -Z1 "$tmp/plg.zip")
for entry in feedcurator.xml services/provider.php src/Extension/Feedcurator.php forms/import.xml sql/mysql/install.sql sql/mysql/updates/26.48.0.sql language/nl-NL/plg_task_feedcurator.ini; do
  echo "$plg" | grep -qx "$entry" && pass "  in de taakplugin: $entry" || bad "  ontbreekt in de taakplugin: $entry"
done
unzip -p "$zip" plg_system_feedcurator.zip > "$tmp/sys.zip"
sys=$(unzip -Z1 "$tmp/sys.zip")
for entry in feedcurator.xml services/provider.php src/Extension/Feedcurator.php language/nl-NL/plg_system_feedcurator.sys.ini; do
  echo "$sys" | grep -qx "$entry" && pass "  in de systeemplugin: $entry" || bad "  ontbreekt in de systeemplugin: $entry"
done
echo "$plg" | grep -q 'CLAUDE.md' && bad "  CLAUDE.md in de plugin-zip" || pass "  geen CLAUDE.md in de plugin-zip"
same "zonder versie niets gestempeld" "" "$(git -C "$repo" status --porcelain -- package.xml)"

expect "build met versie" 0 bash package.sh 99.1.0
same "package.xml gestempeld" "1" "$(grep -c '<version>99.1.0</version>' package.xml)"
for manifest in Src/plugins/task/feedcurator/feedcurator.xml Src/plugins/system/feedcurator/feedcurator.xml; do
  same "gestempeld: $manifest" "1" "$(grep -c '<version>99.1.0</version>' "$manifest")"
done
grep -rq '__DEPLOY_VERSION__' Src package_script.php && bad "__DEPLOY_VERSION__ niet vervangen" || pass "__DEPLOY_VERSION__ vervangen"
[ -f packages/99.1.0/pkg_feedcurator-99.1.0.zip ] && pass "zip met de nieuwe versie" || bad "zip met de nieuwe versie ontbreekt"

expect "ongeldige versie geweigerd" 1 bash package.sh 1.2
rm -rf Src/plugins/system/feedcurator
expect "zonder pluginmap geweigerd" 1 bash package.sh

exit "$failed"
