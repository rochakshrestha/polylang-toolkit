<?php
/**
 * Tests for the dependency decision.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Unit;

use ToolkitForPolylang\Dependency;
use WP_UnitTestCase;

/**
 * Dependency tests.
 */
class DependencyTest extends WP_UnitTestCase {

	/**
	 * @spec CORE-01
	 * @group coexistence
	 */
	public function test_missing_when_polylang_not_loaded_or_inactive(): void {
		$this->assertSame( Dependency::MISSING, Dependency::evaluate( null, false, false, '3.9' ) );
		$this->assertSame( Dependency::MISSING, Dependency::evaluate( '3.9.1', false, false, '3.9' ) );
		$this->assertFalse( Dependency::can_boot( Dependency::MISSING ) );
	}

	/**
	 * @spec CORE-02
	 */
	public function test_too_old_below_minimum(): void {
		$this->assertSame( Dependency::TOO_OLD, Dependency::evaluate( '3.8.9', true, false, '3.9' ) );
		$this->assertSame( Dependency::OK, Dependency::evaluate( '3.9.0', true, false, '3.9' ) );
		$this->assertFalse( Dependency::can_boot( Dependency::TOO_OLD ) );
	}

	/**
	 * @spec CORE-03
	 * @group coexistence
	 */
	public function test_pro_active_still_boots(): void {
		$this->assertSame( Dependency::PRO_ACTIVE, Dependency::evaluate( '3.9.1', true, true, '3.9' ) );
		$this->assertTrue( Dependency::can_boot( Dependency::PRO_ACTIVE ) );
	}
}
