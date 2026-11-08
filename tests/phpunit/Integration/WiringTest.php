<?php
/**
 * Tests that hooks are wired and the Polylang adapter works against real free Polylang.
 *
 * These cover plumbing rather than a spec criterion, so they carry no @spec tag.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Integration;

use ToolkitForPolylang\Admin\Notices;
use ToolkitForPolylang\Admin\Settings;
use ToolkitForPolylang\Options;
use ToolkitForPolylang\Plugin;
use ToolkitForPolylang\Polylang\Adapter;
use WP_UnitTestCase;

/**
 * Wiring tests.
 */
class WiringTest extends WP_UnitTestCase {

	/**
	 * The main file hooked the shared instance when the plugin loaded.
	 */
	public function test_plugin_hooks_boot_and_admin(): void {
		$plugin = new Plugin();
		$plugin->hook();

		$this->assertNotFalse( has_action( 'pll_init', array( $plugin, 'boot' ) ) );
		$this->assertNotFalse( has_action( 'plugins_loaded', array( $plugin, 'admin' ) ) );
		$this->assertSame( Plugin::instance(), Plugin::instance() );
		$this->assertInstanceOf( Adapter::class, $plugin->adapter() );
	}

	/**
	 * Admin parts load only in the admin and only with a usable Polylang.
	 */
	public function test_admin_hooks_settings_and_notices_in_admin(): void {
		set_current_screen( 'dashboard' );
		$plugin = new Plugin();
		$plugin->admin();

		$this->assertNotFalse( has_action( 'admin_notices' ) );
		$this->assertNotFalse( has_action( 'admin_menu' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Settings registers the option with a sanitizer and adds a "Settings" link.
	 */
	public function test_settings_registers_option_and_action_link(): void {
		$settings = new Settings( new Plugin() );
		$settings->hook();
		$settings->register_setting();

		$clean = sanitize_option( Options::OPTION_NAME, array( 'modules' => array( 'evil' => '1' ) ) );
		$this->assertSame( array( 'modules' => array() ), $clean );

		$links = $settings->action_links( array( 'deactivate' => 'x' ) );
		$this->assertStringContainsString( 'page=tkpll', $links[0] );
		$this->assertSame( 'x', $links['deactivate'] );
	}

	/**
	 * Nothing is printed when there is nothing to say, or the user cannot act on it.
	 */
	public function test_notices_print_nothing_when_polylang_is_fine(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		( new Notices( new Plugin() ) )->render();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		ob_start();
		( new Notices( new Plugin() ) )->render();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Adapter reads real free Polylang.
	 */
	public function test_adapter_reads_polylang(): void {
		$adapter = new Adapter();

		$this->assertSame( POLYLANG_VERSION, $adapter->version() );
		$this->assertFalse( $adapter->is_pro() );
		$this->assertTrue( $adapter->api_loaded() );
		$this->assertIsArray( $adapter->language_slugs() );
		$this->assertTrue( null === $adapter->default_language() || is_string( $adapter->default_language() ) );
		$this->assertIsArray( $adapter->polylang_sync_keys() );
		$this->assertNull( $adapter->new_post_translation_request() );
		$adapter->option( 'media_support' );
	}

	/**
	 * Adapter degrades to empty values when the Polylang context is unavailable.
	 */
	public function test_adapter_without_polylang_context_returns_empty_values(): void {
		$adapter = new class() extends Adapter {
			/**
			 * No Polylang context.
			 */
			protected function pll(): ?\PLL_Base {
				return null;
			}
		};

		$this->assertNull( $adapter->option( 'sync' ) );
		$this->assertSame( array(), $adapter->polylang_sync_keys() );
		$this->assertNull( $adapter->new_post_translation_request() );
	}
}
