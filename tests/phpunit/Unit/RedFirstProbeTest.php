<?php
/**
 * TEMPORARY probe for the red-first CI job. Removed before merge.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Unit;

use WP_UnitTestCase;

/**
 * One pending test that fails (accepted) and one that passes (must be rejected).
 */
class RedFirstProbeTest extends WP_UnitTestCase {

	/**
	 * @group pending
	 */
	public function test_probe_fails_without_feature(): void {
		$this->assertTrue( class_exists( 'ToolkitForPolylang\Modules\Dup\Module' ) );
	}

	/**
	 * @group pending
	 */
	public function test_probe_passes_without_feature(): void {
		$this->assertSame( 2, 1 + 1 );
	}
}
