#!/usr/bin/env bash
# Benchmark gate 13 (i18n), used by bin/benchmark and CI job wp-checks.
# Runs make-pot in the wp-env cli container. Fails when make-pot fails (exit code, not just its log),
# when it prints a warning (e.g. a placeholder without a translators: comment), or when no .pot is written.
# Protected file: changes need the spec-change label.
set -uo pipefail

cd "$(dirname "$0")/.."
mkdir -p build
rm -f build/toolkit-for-polylang.pot

# Dev and test output can hold large JS bundles (Playwright reports), which make-pot would parse.
EXCLUDE=vendor,node_modules,tests,build,dist,docs,bin,playwright-report,test-results

npx wp-env run cli --env-cwd=wp-content/plugins/toolkit-for-polylang \
	php -d memory_limit=1G /usr/local/bin/wp i18n make-pot . build/toolkit-for-polylang.pot --exclude="$EXCLUDE" \
	2>&1 | tee build/make-pot.log
status=${PIPESTATUS[0]}

if [[ $status -ne 0 ]]; then
	echo "✗ make-pot failed (exit $status), see build/make-pot.log"
	exit 1
fi
if grep -qi 'warning' build/make-pot.log; then
	echo "✗ make-pot reported warnings, see build/make-pot.log"
	exit 1
fi
if [[ ! -s build/toolkit-for-polylang.pot ]]; then
	echo "✗ make-pot wrote no build/toolkit-for-polylang.pot"
	exit 1
fi
echo "make-pot: no warnings."
