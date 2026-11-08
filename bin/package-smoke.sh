#!/usr/bin/env bash
# Benchmark gate 15 (full form, used in CI): installs the built zip on a fresh WordPress
# next to free Polylang, activates it, uninstalls it and checks nothing `tkpll_` is left.
# Needs Docker. Run bin/build-zip.sh first.
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT=$(pwd)
ENV_DIR=build/package-env
POLYLANG_ZIP=${POLYLANG_ZIP:-https://downloads.wordpress.org/plugin/polylang.3.9.1.zip}

rm -rf "$ENV_DIR"
mkdir -p "$ENV_DIR"
cat >"$ENV_DIR/.wp-env.json" <<JSON
{
	"core": ${WP_CORE_JSON:-null},
	"phpVersion": "${WP_ENV_PHP_VERSION:-8.1}",
	"port": 8890,
	"testsPort": 8891,
	"plugins": [ "$POLYLANG_ZIP", "$ROOT/build/zip/toolkit-for-polylang" ]
}
JSON

cd "$ENV_DIR"
cleanup() { npx --prefix "$ROOT" wp-env destroy --force >/dev/null 2>&1 || true; }
trap cleanup EXIT

npx --prefix "$ROOT" wp-env start
run() { npx --prefix "$ROOT" wp-env run cli "$@"; }

run wp plugin activate polylang toolkit-for-polylang
run wp plugin is-active toolkit-for-polylang

# Gate 14: Plugin Check on exactly what ships (dev files are not in the zip).
run wp plugin install plugin-check --activate
mkdir -p "$ROOT/build"
run wp plugin check toolkit-for-polylang --format=csv | tee "$ROOT/build/plugin-check.csv"
if grep -Eq ',(ERROR|WARNING),' "$ROOT/build/plugin-check.csv"; then
	echo "✗ Plugin Check reported errors or warnings (build/plugin-check.csv)"
	exit 1
fi
run wp option update tkpll_settings '{"modules":{}}' --format=json
run wp plugin deactivate toolkit-for-polylang
run wp plugin uninstall toolkit-for-polylang

left=$(run wp db query "SELECT COUNT(*) FROM wp_options WHERE option_name LIKE 'tkpll\\_%'" --skip-column-names | tr -d '[:space:]')
if [[ "$left" != "0" ]]; then
	echo "✗ $left tkpll_ option(s) left after uninstall"
	exit 1
fi
run wp plugin is-active polylang
echo "Package smoke test passed."
