#!/usr/bin/env bash
# Runs after `wp-env start` (see .wp-env.json lifecycleScripts).
# Activates Polylang + the toolkit in both environments, sets pretty permalinks and
# creates English/French/Nepali so `pll_init` fires and Polylang's setup wizard stays out of the way.
set -euo pipefail

for env in cli tests-cli; do
	npx wp-env run "$env" wp plugin activate polylang toolkit-for-polylang
	npx wp-env run "$env" wp rewrite structure '/%postname%/' --hard
	# PLL()->model->add_language() maps to Model\Languages::add() in Polylang 3.9.1 (see docs/polylang-api.md). Idempotent.
	npx wp-env run "$env" wp transient delete pll_activation_redirect || true
	npx wp-env run "$env" wp eval '
		if ( ! function_exists( "PLL" ) || empty( PLL()->model ) || ! empty( pll_languages_list() ) ) { return; }
		foreach ( array( array( "English", "en", "en_US", "gb" ), array( "Français", "fr", "fr_FR", "fr" ), array( "नेपाली", "ne", "ne_NP", "np" ) ) as $i => $l ) {
			PLL()->model->add_language( array( "name" => $l[0], "slug" => $l[1], "locale" => $l[2], "rtl" => 0, "term_group" => $i, "flag" => $l[3] ) );
		}
	' || echo "warning: could not create default languages; create them in Languages > Settings"
done
