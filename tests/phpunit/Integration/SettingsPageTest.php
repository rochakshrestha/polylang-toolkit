<?php
/**
 * Tests the settings page output and access control.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Integration;

use ToolkitForPolylang\Admin\Settings;
use ToolkitForPolylang\Plugin;
use WP_UnitTestCase;

/**
 * Settings page tests.
 */
class SettingsPageTest extends WP_UnitTestCase {

	/**
	 * Renders the page as the given user.
	 *
	 * @param string $role Role of the current user.
	 */
	private function render_as( string $role ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		ob_start();
		( new Settings( new Plugin() ) )->render();
		return (string) ob_get_clean();
	}

	/**
	 * @spec CORE-08
	 */
	public function test_admin_sees_disclaimer_and_official_links(): void {
		$html = $this->render_as( 'administrator' );

		$this->assertStringContainsString( 'not affiliated with or endorsed by WP SYNTEX', $html );
		$this->assertStringContainsString( 'https://polylang.pro/documentation/', $html );
		$this->assertStringContainsString( 'https://polylang.pro/', $html );
	}

	/**
	 * @spec CORE-08
	 * @group security
	 */
	public function test_users_without_manage_options_get_nothing(): void {
		$this->assertSame( '', $this->render_as( 'editor' ) );
		$this->assertSame( 'manage_options', Settings::CAPABILITY );
		$this->assertSame( 'tkpll', Settings::PAGE_SLUG );
	}
}
