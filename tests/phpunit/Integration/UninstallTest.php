<?php
/**
 * Tests uninstall.php removes only toolkit data.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Integration;

use WP_UnitTestCase;

/**
 * Uninstall test. Runs uninstall.php once (it declares a function), so keep this the only test that includes it.
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * @spec CORE-10
	 */
	public function test_uninstall_removes_only_tkpll_data(): void {
		global $wpdb;

		update_option( 'tkpll_settings', array( 'modules' => array() ) );
		update_option( 'tkpll_secrets', array( 'k' => 'v' ) );
		update_option( 'tkpll_module_cache', 'x' );
		set_transient( 'tkpll_t', 'x' );
		update_option( 'other_plugin_option', 'keep' );
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, '_tkpll_flag', '1' );
		add_post_meta( $post_id, 'other_meta', 'keep' );

		define( 'WP_UNINSTALL_PLUGIN', 'toolkit-for-polylang/toolkit-for-polylang.php' );
		require dirname( __DIR__, 3 ) . '/uninstall.php';
		wp_cache_flush();

		$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%tkpll\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 0, $left );
		$this->assertSame( '', get_post_meta( $post_id, '_tkpll_flag', true ) );
		$this->assertSame( 'keep', get_post_meta( $post_id, 'other_meta', true ) );
		$this->assertSame( 'keep', get_option( 'other_plugin_option' ) );
	}
}
