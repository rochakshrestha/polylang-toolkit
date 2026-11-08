<?php
/**
 * Tests for the module registry.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Unit;

use ToolkitForPolylang\Dependency;
use ToolkitForPolylang\Modules\Registry;
use ToolkitForPolylang\Polylang\Adapter;
use ToolkitForPolylang\Tests\Fixtures\FakeModule;
use WP_UnitTestCase;

/**
 * Registry tests.
 */
class RegistryTest extends WP_UnitTestCase {

	/**
	 * @spec CORE-01 CORE-02
	 * @group coexistence
	 */
	public function test_nothing_boots_without_a_usable_polylang(): void {
		$module   = new FakeModule( 'alpha' );
		$registry = new Registry( array( $module ) );

		$this->assertSame( array(), $registry->boot( array( 'alpha' => true ), Dependency::MISSING, new Adapter() ) );
		$this->assertSame( array(), $registry->boot( array( 'alpha' => true ), Dependency::TOO_OLD, new Adapter() ) );
		$this->assertSame( 0, $module->registered );
	}

	/**
	 * @spec CORE-03
	 * @group coexistence
	 */
	public function test_pro_blocks_only_overlapping_modules(): void {
		$overlap  = new FakeModule( 'overlap', true );
		$other    = new FakeModule( 'other', false );
		$registry = new Registry( array( $overlap, $other ) );

		$booted = $registry->boot(
			array(
				'overlap' => true,
				'other'   => true,
			),
			Dependency::PRO_ACTIVE,
			new Adapter()
		);

		$this->assertSame( array( 'other' ), $booted );
		$this->assertSame( array( 'overlap' ), $registry->blocked_by_pro() );
		$this->assertSame( 0, $overlap->registered );
		$this->assertSame( 1, $other->registered );
	}

	/**
	 * @spec CORE-04
	 * @group coexistence
	 */
	public function test_disabled_modules_never_register(): void {
		$module   = new FakeModule( 'alpha' );
		$registry = new Registry( array( $module ) );

		$registry->boot( array(), Dependency::OK, new Adapter() );
		$registry->boot( array( 'alpha' => false ), Dependency::OK, new Adapter() );

		$this->assertSame( 0, $module->registered );
		$this->assertSame( array(), $registry->booted() );
	}

	/**
	 * @spec CORE-04
	 */
	public function test_module_boots_once_even_if_boot_runs_twice(): void {
		$module   = new FakeModule( 'alpha' );
		$registry = new Registry( array( $module ) );

		$registry->boot( array( 'alpha' => true ), Dependency::OK, new Adapter() );
		$registry->boot( array( 'alpha' => true ), Dependency::OK, new Adapter() );

		$this->assertSame( 1, $module->registered );
	}

	/**
	 * @spec CORE-05
	 */
	public function test_invalid_and_duplicate_modules_are_ignored(): void {
		$registry = new Registry(
			array(
				new FakeModule( 'good' ),
				new FakeModule( 'good' ),
				new FakeModule( 'Bad-Id' ),
				new FakeModule( 'x' ),
				'not a module',
				new \stdClass(),
			)
		);

		$this->assertSame( array( 'good' ), $registry->ids() );
	}
}
