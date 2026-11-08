<?php
/**
 * Settings page under Polylang's "Languages" menu.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Admin;

use ToolkitForPolylang\Dependency;
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
	 * Sanitize callback.
	 *
	 * @param mixed $input Raw input.
	 * @return array{modules: array<string, bool>}
	 */
	public function sanitize( $input ): array {
		return Options::sanitize( $input, $this->plugin->registry()->ids() );
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
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = Options::get();
		$modules  = $this->plugin->registry()->all();
		$pro      = Dependency::PRO_ACTIVE === $this->plugin->status();
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

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
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
		</div>
		<?php
	}
}
