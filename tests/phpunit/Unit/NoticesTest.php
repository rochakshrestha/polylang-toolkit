<?php
/**
 * Tests for admin notice messages.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Unit;

use ToolkitForPolylang\Admin\Notices;
use ToolkitForPolylang\Dependency;
use WP_UnitTestCase;

/**
 * Notices tests.
 */
class NoticesTest extends WP_UnitTestCase {

	/**
	 * @spec CORE-01
	 */
	public function test_missing_polylang_shows_an_error(): void {
		$notice = Notices::message( Dependency::MISSING, array() );

		$this->assertNotNull( $notice );
		$this->assertSame( 'error', $notice['type'] );
	}

	/**
	 * @spec CORE-02
	 */
	public function test_too_old_names_the_required_version(): void {
		$notice = Notices::message( Dependency::TOO_OLD, array() );

		$this->assertNotNull( $notice );
		$this->assertStringContainsString( TKPLL_MIN_POLYLANG, $notice['message'] );
	}

	/**
	 * @spec CORE-03
	 */
	public function test_pro_notice_lists_held_back_modules_only_when_there_are_some(): void {
		$this->assertNull( Notices::message( Dependency::PRO_ACTIVE, array() ) );
		$this->assertNull( Notices::message( Dependency::OK, array() ) );

		$notice = Notices::message( Dependency::PRO_ACTIVE, array( 'Duplication', 'Slugs' ) );
		$this->assertNotNull( $notice );
		$this->assertSame( 'info', $notice['type'] );
		$this->assertStringContainsString( 'Duplication, Slugs', $notice['message'] );
	}
}
