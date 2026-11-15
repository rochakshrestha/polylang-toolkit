<?php
/**
 * Tests the module settings contract and the per-module settings tabs.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Integration;

use ToolkitForPolylang\Admin\Settings;
use ToolkitForPolylang\Dependency;
use ToolkitForPolylang\Modules\ModuleInterface;
use ToolkitForPolylang\Options;
use ToolkitForPolylang\Plugin;
use ToolkitForPolylang\Tests\Fixtures\FakeModule;
use ToolkitForPolylang\Tests\Fixtures\FakeSettingsModule;
use WP_UnitTestCase;

/**
 * The settings form posts `tkpll_settings[_tab]` (the tab being saved: `modules` or a module id)
 * plus that tab's fields: `tkpll_settings[modules][<id>]` on the Modules tab, and
 * `tkpll_settings[<id>][<key>]` on a module's tab.
 */
class ModuleSettingsTest extends WP_UnitTestCase {

	/**
	 * Clears request state and the registered setting between tests.
	 */
	public function tear_down(): void {
		unset( $_GET['tab'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simulates the request.
		unregister_setting( Settings::GROUP, Options::OPTION_NAME );
		parent::tear_down();
	}

	/**
	 * Registers modules through the public filter.
	 *
	 * @param ModuleInterface ...$modules Modules to add.
	 */
	private function add_modules( ModuleInterface ...$modules ): void {
		add_filter(
			'tkpll_modules',
			static function ( array $existing ) use ( $modules ): array {
				return array_merge( $existing, $modules );
			}
		);
	}

	/**
	 * The three modules most tests use: one without settings, two with.
	 */
	private function add_standard_modules(): void {
		$this->add_modules( new FakeModule( 'plain' ), new FakeSettingsModule( 'alpha' ), new FakeSettingsModule( 'beta' ) );
	}

	/**
	 * Renders the settings page.
	 *
	 * @param string      $role   Role of the current user.
	 * @param mixed       $tab    Value of the `tab` query argument; null for none.
	 * @param Plugin|null $plugin Plugin instance; a fresh one by default.
	 */
	private function render( string $role, mixed $tab = null, ?Plugin $plugin = null ): string {
		if ( null === $tab ) {
			unset( $_GET['tab'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simulates the request.
		} else {
			$_GET['tab'] = $tab; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simulates the request.
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		ob_start();
		( new Settings( $plugin ?? new Plugin() ) )->render();
		return (string) ob_get_clean();
	}

	/**
	 * Saves the option the way options.php does: update_option() runs the registered sanitize callback.
	 *
	 * @param array<string, mixed> $posted Posted `tkpll_settings` value.
	 * @return array<string, mixed> The stored option.
	 */
	private function save( array $posted ): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new Settings( new Plugin() ) )->register_setting();
		update_option( Options::OPTION_NAME, $posted );
		$stored = get_option( Options::OPTION_NAME );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * The opening `<a>` tag of a tab link: `modules` is the plain page URL, anything else `&tab=<id>`.
	 *
	 * @param string $html Page HTML.
	 * @param string $tab  Tab id.
	 */
	private function tab_link( string $html, string $tab ): string {
		$href = 'modules' === $tab
			? '/href="[^"]*page=tkpll"/'
			: '/href="[^"]*page=tkpll(?:&#038;|&amp;|&)tab=' . preg_quote( $tab, '/' ) . '"/';
		preg_match_all( '/<a\b[^>]*>/', $html, $tags );
		foreach ( $tags[0] as $tag ) {
			if ( preg_match( $href, $tag ) ) {
				return $tag;
			}
		}
		return '';
	}

	/**
	 * @spec CORE-11
	 * @group pending
	 */
	public function test_modules_tab_comes_first_then_one_tab_per_settings_module(): void {
		$this->add_standard_modules();

		$html = $this->render( 'administrator' );

		$modules = $this->tab_link( $html, 'modules' );
		$alpha   = $this->tab_link( $html, 'alpha' );
		$beta    = $this->tab_link( $html, 'beta' );
		$this->assertNotSame( '', $modules, 'Modules tab link missing' );
		$this->assertNotSame( '', $alpha, 'alpha tab link missing' );
		$this->assertNotSame( '', $beta, 'beta tab link missing' );
		$this->assertSame( '', $this->tab_link( $html, 'plain' ), 'a module without HasSettings must get no tab' );
		$this->assertLessThan( strpos( $html, $alpha ), strpos( $html, $modules ) );
		$this->assertLessThan( strpos( $html, $beta ), strpos( $html, $alpha ) );
		$this->assertStringContainsString( 'aria-current="page"', $modules );
		$this->assertStringNotContainsString( 'aria-current', $alpha );
		$this->assertStringContainsString( 'name="tkpll_settings[modules][plain]"', $html );
	}

	/**
	 * @spec CORE-11
	 * @group pending
	 */
	public function test_a_module_tab_shows_its_fields_with_stored_values(): void {
		$this->add_standard_modules();
		update_option(
			Options::OPTION_NAME,
			array(
				'modules' => array( 'alpha' => true ),
				'alpha'   => array(
					'color' => 'blue',
					'size'  => 4,
				),
			)
		);

		$html = $this->render( 'administrator', 'alpha' );

		$this->assertStringContainsString( 'aria-current="page"', $this->tab_link( $html, 'alpha' ) );
		$this->assertStringNotContainsString( 'aria-current', $this->tab_link( $html, 'modules' ) );
		$this->assertStringContainsString( '<label for="tkpll-alpha-color">Color</label>', $html );
		$this->assertStringContainsString( 'name="tkpll_settings[alpha][color]" value="blue"', $html );
		$this->assertStringContainsString( 'name="tkpll_settings[alpha][size]" value="4"', $html );
		$this->assertStringContainsString( 'name="tkpll_settings[_tab]" value="alpha"', $html );
		$this->assertStringNotContainsString( 'tkpll-module-off', $html, 'alpha is on, so no "module is off" note' );
	}

	/**
	 * @spec CORE-11
	 * @group pending
	 */
	public function test_tab_of_a_switched_off_module_opens_and_says_so(): void {
		$this->add_standard_modules();
		update_option( Options::OPTION_NAME, array( 'modules' => array( 'alpha' => false ) ) );

		$html = $this->render( 'administrator', 'alpha' );

		$this->assertStringContainsString( 'name="tkpll_settings[alpha][color]" value="red"', $html );
		$this->assertStringContainsString( 'tkpll-module-off', $html );
		$this->assertLessThan( strpos( $html, 'tkpll_settings[alpha][color]' ), strpos( $html, 'tkpll-module-off' ) );
	}

	/**
	 * @spec CORE-11
	 * @group pending
	 */
	public function test_tab_of_a_module_held_back_by_pro_opens_and_says_so(): void {
		$this->add_modules( new FakeSettingsModule( 'alpha', true ), new FakeSettingsModule( 'beta' ) );
		$switches = array(
			'alpha' => true,
			'beta'  => true,
		);
		update_option( Options::OPTION_NAME, array( 'modules' => $switches ) );
		$plugin = new Plugin();
		$plugin->registry()->boot( $switches, Dependency::PRO_ACTIVE, $plugin->adapter() );
		$this->assertSame( array( 'alpha' ), $plugin->registry()->blocked_by_pro() );

		$html = $this->render( 'administrator', 'alpha', $plugin );

		$this->assertStringContainsString( 'name="tkpll_settings[alpha][color]"', $html );
		$this->assertStringContainsString( 'tkpll-module-off', $html );
		$note = strpos( $html, 'tkpll-module-off' );
		$this->assertStringContainsString( 'Polylang Pro', substr( $html, (int) $note, strpos( $html, 'tkpll_settings[alpha][color]' ) - (int) $note ) );

		// Control: beta is neither off nor held back, so its tab has no note.
		$this->assertStringNotContainsString( 'tkpll-module-off', $this->render( 'administrator', 'beta', $plugin ) );
	}

	/**
	 * Tab values that must fall back to the Modules tab.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_tabs(): array {
		return array(
			'empty'                   => array( '' ),
			'unknown id'              => array( 'nope' ),
			'module without settings' => array( 'plain' ),
			'modules itself'          => array( 'modules' ),
			'path traversal'          => array( '../alpha' ),
			'markup'                  => array( '"><script>alert(1)</script>' ),
			'upper case'              => array( 'ALPHA' ),
			'array'                   => array( array( 'alpha' ) ),
		);
	}

	/**
	 * @spec CORE-11
	 * @group pending
	 * @group security
	 * @dataProvider invalid_tabs
	 *
	 * @param mixed $tab Value of the `tab` query argument.
	 */
	public function test_an_invalid_tab_shows_the_modules_tab( mixed $tab ): void {
		$this->add_standard_modules();

		$html = $this->render( 'administrator', $tab );

		// Control: the tabs exist at all, so this cannot pass on a page without tabs.
		$this->assertNotSame( '', $this->tab_link( $html, 'alpha' ) );
		$this->assertStringContainsString( 'aria-current="page"', $this->tab_link( $html, 'modules' ) );
		$this->assertStringContainsString( 'name="tkpll_settings[modules][alpha]"', $html );
		$this->assertStringNotContainsString( 'tkpll_settings[alpha][color]', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	/**
	 * @spec CORE-11 CORE-12
	 * @group pending
	 * @group security
	 */
	public function test_tabs_post_to_the_settings_api_and_stay_admin_only(): void {
		$this->add_standard_modules();

		$html = $this->render( 'administrator', 'alpha' );

		// Control: the alpha tab rendered its form.
		$this->assertStringContainsString( 'name="tkpll_settings[alpha][color]"', $html );
		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( "name='option_page' value='tkpll'", $html );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="[0-9a-f]+"/', $html );
		$this->assertSame( 1, wp_verify_nonce( $this->nonce( $html ), 'tkpll-options' ) );

		// What options.php relies on: the option is allowed for the tkpll page, and saving needs manage_options.
		( new Settings( new Plugin() ) )->register_setting();
		$this->assertContains( Options::OPTION_NAME, $GLOBALS['new_allowed_options'][ Settings::GROUP ] ?? array() );
		$this->assertSame( 'manage_options', apply_filters( 'option_page_capability_' . Settings::GROUP, 'manage_options' ) );

		$this->assertSame( '', $this->render( 'editor', 'alpha' ) );
	}

	/**
	 * The nonce value in a rendered form.
	 *
	 * @param string $html Page HTML.
	 */
	private function nonce( string $html ): string {
		return preg_match( '/name="_wpnonce" value="([0-9a-f]+)"/', $html, $m ) ? $m[1] : '';
	}

	/**
	 * @spec CORE-12
	 * @group pending
	 * @group security
	 */
	public function test_saving_a_module_tab_stores_its_sanitized_settings_and_keeps_the_rest(): void {
		$alpha = new FakeSettingsModule( 'alpha' );
		$this->add_modules( new FakeModule( 'plain' ), $alpha, new FakeSettingsModule( 'beta' ) );
		$switches = array(
			'plain' => true,
			'alpha' => true,
			'beta'  => false,
		);
		$beta     = array(
			'color' => 'green',
			'size'  => 7,
		);
		update_option(
			Options::OPTION_NAME,
			array(
				'modules' => $switches,
				'beta'    => $beta,
			)
		);
		$posted = array(
			'color' => 'blue',
			'size'  => '99',
			'junk'  => 'x',
		);

		$stored = $this->save(
			array(
				'_tab'  => 'alpha',
				'alpha' => $posted,
				'beta'  => array( 'color' => 'red' ),
				'ghost' => array( 'x' => 1 ),
			)
		);

		$this->assertSame( $alpha->sanitize_settings( $posted ), $stored['alpha'] ?? null );
		$this->assertSame(
			array(
				'color' => 'blue',
				'size'  => 10,
			),
			$stored['alpha'] ?? null
		);
		$this->assertSame( $switches, $stored['modules'] ?? null, 'saving a module tab must not touch the switches' );
		$this->assertSame( $beta, $stored['beta'] ?? null, 'saving alpha must not touch beta, even if beta keys were posted' );
		$this->assertArrayNotHasKey( 'ghost', $stored, 'keys for an unregistered module are dropped' );
		$this->assertArrayNotHasKey( '_tab', $stored );
	}

	/**
	 * On a fresh site the option does not exist yet, and WordPress runs the sanitize callback twice
	 * (update_option(), then add_option() with the first result). The save must survive that.
	 *
	 * @spec CORE-12
	 * @group pending
	 * @group security
	 */
	public function test_first_save_on_a_fresh_site_keeps_the_module_settings(): void {
		$alpha = new FakeSettingsModule( 'alpha' );
		$this->add_modules( $alpha );
		delete_option( Options::OPTION_NAME );
		$posted = array(
			'color' => 'green',
			'size'  => '5',
		);

		$stored = $this->save(
			array(
				'_tab'  => 'alpha',
				'alpha' => $posted,
			)
		);

		$this->assertSame( $alpha->sanitize_settings( $posted ), $stored['alpha'] ?? null );
		$this->assertSame( array( 'alpha' => false ), $stored['modules'] ?? null );
	}

	/**
	 * @spec CORE-12
	 * @group pending
	 * @group security
	 */
	public function test_saving_the_modules_tab_keeps_every_module_setting(): void {
		$this->add_standard_modules();
		$alpha   = array(
			'color' => 'blue',
			'size'  => 3,
		);
		$removed = array( 'level' => 2 );
		update_option(
			Options::OPTION_NAME,
			array(
				'modules' => array( 'alpha' => true ),
				'alpha'   => $alpha,
				'removed' => $removed,
			)
		);

		$stored = $this->save(
			array(
				'_tab'    => 'modules',
				'modules' => array( 'beta' => '1' ),
				'alpha'   => array( 'color' => 'green' ),
			)
		);

		$this->assertSame(
			array(
				'plain' => false,
				'alpha' => false,
				'beta'  => true,
			),
			$stored['modules'] ?? null
		);
		$this->assertSame( $alpha, $stored['alpha'] ?? null, 'saving the switches must keep alpha settings' );
		$this->assertSame( $removed, $stored['removed'] ?? null, 'settings of a module that is gone stay until uninstall' );
	}

	/**
	 * @spec CORE-12
	 * @group pending
	 */
	public function test_module_settings_puts_stored_values_over_the_defaults(): void {
		$alpha = new FakeSettingsModule( 'alpha' );
		update_option(
			Options::OPTION_NAME,
			array(
				'modules' => array(),
				'alpha'   => array(
					'color' => 'green',
					'extra' => 'ignored',
				),
			)
		);

		$this->assertSame(
			array(
				'color' => 'green',
				'size'  => 1,
			),
			Options::module_settings( $alpha )
		);
	}

	/**
	 * @spec CORE-12
	 * @group pending
	 */
	public function test_module_settings_falls_back_to_defaults_when_the_option_is_corrupted(): void {
		$alpha = new FakeSettingsModule( 'alpha' );

		foreach ( array( 'garbage', array( 'alpha' => 'garbage' ), array() ) as $option ) {
			update_option( Options::OPTION_NAME, $option );
			$this->assertSame( $alpha->default_settings(), Options::module_settings( $alpha ) );
		}
	}

	/**
	 * Edge case: a module cannot take the id that holds the switches.
	 *
	 * @spec CORE-12
	 * @group pending
	 */
	public function test_a_module_with_the_id_modules_is_rejected(): void {
		$this->add_modules( new FakeSettingsModule( 'modules' ), new FakeSettingsModule( 'alpha' ) );

		$ids = ( new Plugin() )->registry()->ids();

		$this->assertContains( 'alpha', $ids );
		$this->assertNotContains( 'modules', $ids );
	}
}
