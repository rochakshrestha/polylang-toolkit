<?php
/**
 * Tests the settings page output and access control.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Integration;

use ToolkitForPolylang\Admin\Settings;
use ToolkitForPolylang\Options;
use ToolkitForPolylang\Plugin;
use ToolkitForPolylang\Tests\Fixtures\FakeModule;
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
	 */
	public function test_registered_modules_are_listed_with_labelled_checkboxes(): void {
		$module = new FakeModule( 'fake' );
		add_filter(
			'tkpll_modules',
			static function ( array $modules ) use ( $module ): array {
				$modules[] = $module;
				return $modules;
			}
		);
		update_option( Options::OPTION_NAME, array( 'modules' => array( 'fake' => true ) ) );

		$html = $this->render_as( 'administrator' );

		$this->assertStringContainsString( '<label for="tkpll-module-fake">Fake fake</label>', $html );
		$this->assertStringContainsString( 'name="tkpll_settings[modules][fake]"', $html );
		$this->assertStringContainsString( 'aria-describedby="tkpll-module-fake-desc"', $html );
		$this->assertStringContainsString( "checked='checked'", $html );
		$this->assertStringNotContainsString( 'No modules are available yet.', $html );
	}

	/**
	 * @spec CORE-08
	 */
	public function test_empty_registry_says_so(): void {
		$this->assertStringContainsString( 'No modules are available yet.', $this->render_as( 'administrator' ) );
	}

	/**
	 * @spec CORE-08
	 */
	public function test_page_is_a_languages_submenu(): void {
		global $submenu;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		( new Settings( new Plugin() ) )->add_page();

		$slugs = wp_list_pluck( $submenu[ Settings::PARENT_SLUG ] ?? array(), 2 );
		$this->assertContains( Settings::PAGE_SLUG, $slugs );
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
