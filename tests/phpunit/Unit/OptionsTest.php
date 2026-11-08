<?php
/**
 * Tests for settings handling.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Unit;

use ToolkitForPolylang\Options;
use WP_UnitTestCase;

/**
 * Options tests.
 */
class OptionsTest extends WP_UnitTestCase {

	/**
	 * @spec CORE-04
	 */
	public function test_defaults_have_every_module_off(): void {
		delete_option( Options::OPTION_NAME );

		$this->assertSame( array( 'modules' => array() ), Options::get() );
		$this->assertFalse( Options::is_module_enabled( 'dup' ) );
	}

	/**
	 * @spec CORE-06
	 * @group security
	 */
	public function test_sanitize_keeps_only_known_ids_as_booleans(): void {
		$clean = Options::sanitize(
			array(
				'modules' => array(
					'dup'     => '1',
					'slug'    => '',
					'unknown' => '1',
				),
			),
			array( 'dup', 'slug', 'sync' )
		);

		$this->assertSame(
			array(
				'dup'  => true,
				'slug' => false,
				'sync' => false,
			),
			$clean['modules']
		);
	}

	/**
	 * @spec CORE-06
	 * @group security
	 */
	public function test_sanitize_survives_garbage_input(): void {
		foreach ( array( null, 'x', 5, array( 'modules' => 'x' ) ) as $input ) {
			$this->assertSame( array( 'dup' => false ), Options::sanitize( $input, array( 'dup' ) )['modules'] );
		}
	}
}
