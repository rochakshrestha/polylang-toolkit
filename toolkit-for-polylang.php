<?php
/**
 * Toolkit for Polylang
 *
 * @package           ToolkitForPolylang
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Toolkit for Polylang
 * Plugin URI:        https://github.com/rochakshrestha/polylang-toolkit
 * Description:       Essential extras for free Polylang: translated slugs, content duplication, field sync and better media handling. Not affiliated with WP SYNTEX / Polylang.
 * Version:           0.1.0-dev
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            Rochak Shrestha
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       toolkit-for-polylang
 */

// This file must stay parseable by old PHP versions so the version guard below can show a notice instead of a fatal error.

defined( 'ABSPATH' ) || exit;

define( 'TKPLL_VERSION', '0.1.0-dev' );
define( 'TKPLL_FILE', __FILE__ );
define( 'TKPLL_DIR', __DIR__ );
define( 'TKPLL_MIN_PHP', '8.1' );
define( 'TKPLL_MIN_POLYLANG', '3.9' );

if ( version_compare( PHP_VERSION, TKPLL_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Toolkit for Polylang needs PHP %1$s or newer. This site runs PHP %2$s, so the plugin is not active.', 'toolkit-for-polylang' ),
						TKPLL_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

require_once TKPLL_DIR . '/src/autoload.php';

\ToolkitForPolylang\Plugin::instance()->hook();
