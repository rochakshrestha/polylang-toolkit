#!/usr/bin/env bash
# Builds dist/toolkit-for-polylang.zip from the files not listed in .distignore,
# then checks the zip has what a release needs and nothing it shouldn't.
set -euo pipefail

cd "$(dirname "$0")/.."
SLUG=toolkit-for-polylang
STAGE=build/zip/$SLUG

rm -rf build/zip dist
mkdir -p "$STAGE" dist

rsync -a --exclude-from=.distignore ./ "$STAGE/"
(cd build/zip && zip -qr "../../dist/$SLUG.zip" "$SLUG")

listing=$(unzip -Z1 "dist/$SLUG.zip")
fail=0
for required in "$SLUG/toolkit-for-polylang.php" "$SLUG/readme.txt" "$SLUG/uninstall.php" "$SLUG/src/Plugin.php" "$SLUG/LICENSE"; do
	if ! grep -qx "$required" <<<"$listing"; then
		echo "✗ missing from zip: $required"; fail=1
	fi
done
for forbidden in "$SLUG/vendor/" "$SLUG/node_modules/" "$SLUG/tests/" "$SLUG/.git/" "$SLUG/AGENTS.md" "$SLUG/composer.json"; do
	if grep -q "^$forbidden" <<<"$listing"; then
		echo "✗ must not ship: $forbidden"; fail=1
	fi
done

version_header=$(sed -n 's/^ \* Version:[[:space:]]*//p' toolkit-for-polylang.php)
version_const=$(sed -n "s/^define( 'TKPLL_VERSION', '\(.*\)' );/\1/p" toolkit-for-polylang.php)
if [[ "$version_header" != "$version_const" ]]; then
	echo "✗ Version header ($version_header) and TKPLL_VERSION ($version_const) differ"; fail=1
fi

[[ $fail -eq 0 ]] && echo "Built dist/$SLUG.zip ($(wc -c <"dist/$SLUG.zip") bytes, version $version_header)"
exit $fail
