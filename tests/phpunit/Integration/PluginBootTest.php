<?php
/**
 * Tests the boot sequence with real free Polylang loaded.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Integration;

use ToolkitForPolylang\Options;
use ToolkitForPolylang\Plugin;
use ToolkitForPolylang\Tests\Fixtures\FakeModule;
use WP_UnitTestCase;

/**
 * Plugin boot tests.
 */
class PluginBootTest extends WP_UnitTestCase {

	/**
	 * Module added through the filter.
	 *
	 * @var FakeModule
	 */
	private FakeModule $module;

	/**
	 * Adds a fake module through the public filter.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->module = new FakeModule( 'fake' );
		add_filter(
			'tkpll_modules',
			function ( array $modules ): array {
				$modules[] = $this->module;
				return $modules;
			}
		);
	}

	/**
	 * @spec CORE-07
	 */
	public function test_filter_adds_module_and_booted_action_fires(): void {
		update_option( Options::OPTION_NAME, array( 'modules' => array( 'fake' => true ) ) );
		$fired = 0;
		add_action(
			'tkpll_booted',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		( new Plugin() )->boot();

		$this->assertSame( 1, $this->module->registered );
		$this->assertSame( 1, $fired );
	}

	/**
	 * @spec CORE-04
	 * @group coexistence
	 */
	public function test_all_modules_off_registers_nothing(): void {
		delete_option( Options::OPTION_NAME );

		$plugin = new Plugin();
		$plugin->boot();

		$this->assertSame( 0, $this->module->registered );
		$this->assertSame( array(), $plugin->registry()->booted() );
	}

	/**
	 * Polylang is active in the test environment, so the real status must allow booting.
	 *
	 * @spec CORE-01
	 * @group coexistence
	 */
	public function test_environment_has_a_usable_polylang(): void {
		$this->assertSame( 'ok', ( new Plugin() )->status() );
	}

	/**
	 * @spec CORE-04
	 * @group performance
	 */
	public function test_boot_with_modules_off_is_cheap_and_stores_little(): void {
		update_option( Options::OPTION_NAME, array( 'modules' => array( 'fake' => false ) ) );
		wp_cache_flush();

		$before = get_num_queries();
		( new Plugin() )->boot();
		$this->assertLessThanOrEqual( 2, get_num_queries() - $before );

		$autoloaded = wp_load_alloptions();
		foreach ( $autoloaded as $name => $value ) {
			if ( str_starts_with( $name, 'tkpll_' ) ) {
				$this->assertLessThan( 10240, strlen( (string) $value ), "{$name} is over 10 KB while autoloaded" );
			}
		}
	}
}
