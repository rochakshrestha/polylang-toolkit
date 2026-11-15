<?php
/**
 * Settings page under Polylang's "Languages" menu.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Admin;

use ToolkitForPolylang\Dependency;
use ToolkitForPolylang\Modules\HasSettings;
use ToolkitForPolylang\Options;
use ToolkitForPolylang\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Languages → Toolkit.
 */
final class Settings {

	public const PAGE_SLUG   = 'tkpll';
	public const PARENT_SLUG = 'mlang'; // Polylang's menu slug, see docs/polylang-api.md.
	public const CAPABILITY  = 'manage_options';
	public const GROUP       = 'tkpll';
	public const MODULES_TAB = 'modules';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds hooks.
	 */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 ); // After Polylang adds its menu.
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TKPLL_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Registers the submenu page.
	 */
	public function add_page(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Toolkit for Polylang', 'toolkit-for-polylang' ),
			__( 'Toolkit', 'toolkit-for-polylang' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the option with the Settings API (handles nonce and capability on save).
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Options::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Options::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize callback. Saves only the tab named in `_tab` and keeps every other key.
	 *
	 * Without `_tab` the input is a whole option: WordPress passes the first result back in when it
	 * sanitizes twice (first save on a fresh site), so the switches and every module's settings are
	 * sanitized again from it, which gives the same result.
	 *
	 * @param mixed $input Raw input.
	 * @return array<mixed>
	 */
	public function sanitize( $input ): array {
		$registry = $this->plugin->registry();
		$input    = is_array( $input ) ? $input : array();
		$tab      = isset( $input['_tab'] ) && is_string( $input['_tab'] ) ? $input['_tab'] : null;
		$out      = Options::raw();
		unset( $out['_tab'] );
		$out['modules'] = Options::get()['modules'];

		if ( null === $tab || self::MODULES_TAB === $tab ) {
			$out['modules'] = Options::sanitize( $input, $registry->ids() )['modules'];
		}
		foreach ( $registry->with_settings() as $id => $module ) {
			if ( ( null === $tab && array_key_exists( $id, $input ) ) || $id === $tab ) {
				$out[ $id ] = $module->sanitize_settings( $input[ $id ] ?? array() );
			}
		}
		return $out;
	}

	/**
	 * The tab being viewed: a module id with settings, or the Modules tab.
	 *
	 * Read-only view selection, so no nonce. The value must equal a module id exactly.
	 */
	private function current_tab(): string {
		$requested = isset( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared to known ids only, never output.
		if ( ! is_string( $requested ) ) {
			return self::MODULES_TAB;
		}
		return isset( $this->plugin->registry()->with_settings()[ $requested ] ) ? $requested : self::MODULES_TAB;
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'toolkit-for-polylang' ) )
		);
		return $links;
	}

	/**
	 * Renders the page: the disclaimer, the tab links, then the current tab.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap tkpll-settings">
			<h1><?php esc_html_e( 'Toolkit for Polylang', 'toolkit-for-polylang' ); ?></h1>

			<div class="notice notice-info inline tkpll-disclaimer">
				<p>
					<?php esc_html_e( 'Toolkit for Polylang is an independent, free companion plugin for free Polylang. It is not affiliated with or endorsed by WP SYNTEX, the makers of Polylang.', 'toolkit-for-polylang' ); ?>
				</p>
				<p>
					<a href="https://polylang.pro/documentation/"><?php esc_html_e( 'Official Polylang documentation', 'toolkit-for-polylang' ); ?></a>
					&middot;
					<a href="https://polylang.pro/"><?php esc_html_e( 'For official premium support and advanced features, see Polylang Pro', 'toolkit-for-polylang' ); ?></a>
				</p>
			</div>

			<?php
			$this->render_tab_links( $tab );
			$with_settings = $this->plugin->registry()->with_settings();
			if ( isset( $with_settings[ $tab ] ) ) {
				$this->render_module_tab( $with_settings[ $tab ] );
			} else {
				$this->render_modules_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Tab links: Modules first, then one per module with settings, in registry order.
	 *
	 * @param string $current Current tab.
	 */
	private function render_tab_links( string $current ): void {
		$base = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$tabs = array( self::MODULES_TAB => array( __( 'Modules', 'toolkit-for-polylang' ), $base ) );
		foreach ( $this->plugin->registry()->with_settings() as $id => $module ) {
			$tabs[ $id ] = array( $module->title(), add_query_arg( 'tab', $id, $base ) );
		}
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Toolkit settings', 'toolkit-for-polylang' ); ?>">
			<?php
			foreach ( $tabs as $id => list( $label, $url ) ) {
				if ( $id === $current ) {
					printf( '<a href="%s" class="nav-tab nav-tab-active" aria-current="page">%s</a>', esc_url( $url ), esc_html( $label ) );
				} else {
					printf( '<a href="%s" class="nav-tab">%s</a>', esc_url( $url ), esc_html( $label ) );
				}
			}
			?>
		</nav>
		<?php
	}

	/**
	 * The Modules tab: one on/off switch per registered module.
	 */
	private function render_modules_tab(): void {
		$settings = Options::get();
		$modules  = $this->plugin->registry()->all();
		$pro      = Dependency::PRO_ACTIVE === $this->plugin->status();
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( self::GROUP );
			$this->render_tab_field( self::MODULES_TAB );
			?>
			<h2><?php esc_html_e( 'Modules', 'toolkit-for-polylang' ); ?></h2>

			<?php if ( empty( $modules ) ) : ?>
				<p class="tkpll-no-modules"><?php esc_html_e( 'No modules are available yet.', 'toolkit-for-polylang' ); ?></p>
			<?php else : ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $modules as $id => $module ) : ?>
						<?php
						$field_id = 'tkpll-module-' . $id;
						$blocked  = $pro && $module->overlaps_pro();
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $module->title() ); ?></label>
							</th>
							<td>
								<input
									type="checkbox"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( Options::OPTION_NAME . '[modules][' . $id . ']' ); ?>"
									value="1"
									aria-describedby="<?php echo esc_attr( $field_id . '-desc' ); ?>"
									<?php checked( ! empty( $settings['modules'][ $id ] ) ); ?>
									<?php disabled( $blocked ); ?>
								/>
								<p class="description" id="<?php echo esc_attr( $field_id . '-desc' ); ?>">
									<?php echo esc_html( $module->description() ); ?>
									<?php if ( $blocked ) : ?>
										<strong><?php esc_html_e( 'Off while Polylang Pro is active.', 'toolkit-for-polylang' ); ?></strong>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * A module's own tab: a note when the module is not running, then its fields.
	 *
	 * @param HasSettings $module Module.
	 */
	private function render_module_tab( HasSettings $module ): void {
		$id = $module->id();
		if ( in_array( $id, $this->plugin->registry()->blocked_by_pro(), true ) ) {
			$note = __( 'This module is off while Polylang Pro is active, because Polylang Pro has a similar feature. Its settings are kept.', 'toolkit-for-polylang' );
		} elseif ( ! Options::is_module_enabled( $id ) ) {
			$note = __( 'This module is switched off. Its settings are kept and apply once you switch it on in the Modules tab.', 'toolkit-for-polylang' );
		} else {
			$note = '';
		}
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( self::GROUP );
			$this->render_tab_field( $id );
			?>
			<h2><?php echo esc_html( $module->title() ); ?></h2>
			<?php if ( '' !== $note ) : ?>
				<div class="notice notice-warning inline tkpll-module-off"><p><?php echo esc_html( $note ); ?></p></div>
			<?php endif; ?>
			<?php $module->render_settings( Options::module_settings( $module ), Options::OPTION_NAME . '[' . $id . ']' ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * The hidden field naming the tab a form saves (see sanitize()).
	 *
	 * @param string $tab Tab id.
	 */
	private function render_tab_field( string $tab ): void {
		printf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( Options::OPTION_NAME . '[_tab]' ),
			esc_attr( $tab )
		);
	}
}
