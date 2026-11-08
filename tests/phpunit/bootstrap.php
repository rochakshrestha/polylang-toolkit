<?php
/**
 * PHPUnit bootstrap: loads the WordPress test library, free Polylang, then the toolkit.
 *
 * Runs inside wp-env's tests-cli container (WP_TESTS_DIR is set there).
 *
 * @package ToolkitForPolylang
 */

// Composer autoloader: test fixtures and PHPUnit polyfills.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$tkpll_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tkpll_tests_dir ) {
	$tkpll_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $tkpll_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$tkpll_tests_dir}. Run inside wp-env: npm run test:php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

require_once $tkpll_tests_dir . '/includes/functions.php';

/*
 * A PHPUnit run is neither admin, cron nor WP-CLI, and the test database has no languages,
 * so free Polylang would build no context: no PLL(), no pll_* API, no `pll_init`.
 * Forcing the admin context makes Polylang load fully (see docs/polylang-api.md, "Load order").
 * Tests that need the front-end context build it themselves.
 */
if ( ! defined( 'PLL_ADMIN' ) ) {
	define( 'PLL_ADMIN', true );
}

tests_add_filter(
	'muplugins_loaded',
	static function () {
		// Same path strings WordPress uses for active plugins, so require_once never loads a file twice.
		require_once WP_PLUGIN_DIR . '/polylang/polylang.php';
		require_once WP_PLUGIN_DIR . '/toolkit-for-polylang/toolkit-for-polylang.php';
	}
);

require $tkpll_tests_dir . '/includes/bootstrap.php';
