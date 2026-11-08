<?php
/**
 * Admin notices about the Polylang dependency and Polylang Pro.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Admin;

use ToolkitForPolylang\Dependency;
use ToolkitForPolylang\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Shows why the toolkit is inactive, or which modules Polylang Pro replaces.
 */
final class Notices {

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
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Returns the notice message for the current state, or null when nothing should be shown.
	 *
	 * @param string   $status         Dependency status.
	 * @param string[] $blocked_by_pro Enabled module titles held back by Polylang Pro.
	 * @return array{type: string, message: string}|null
	 */
	public static function message( string $status, array $blocked_by_pro ): ?array {
		switch ( $status ) {
			case Dependency::MISSING:
				return array(
					'type'    => 'error',
					'message' => __( 'Toolkit for Polylang needs the free Polylang plugin. Install and activate Polylang to use the toolkit.', 'toolkit-for-polylang' ),
				);
			case Dependency::TOO_OLD:
				return array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s: minimum Polylang version. */
						__( 'Toolkit for Polylang needs Polylang %s or newer. Please update Polylang; the toolkit is paused until then.', 'toolkit-for-polylang' ),
						TKPLL_MIN_POLYLANG
					),
				);
			case Dependency::PRO_ACTIVE:
				if ( empty( $blocked_by_pro ) ) {
					return null;
				}
				return array(
					'type'    => 'info',
					'message' => sprintf(
						/* translators: %s: comma-separated list of module names. */
						__( 'Polylang Pro is active, so these Toolkit for Polylang modules are switched off to avoid conflicts. Use the matching Polylang Pro features instead: %s', 'toolkit-for-polylang' ),
						implode( ', ', $blocked_by_pro )
					),
				);
		}

		return null;
	}

	/**
	 * Prints the notice.
	 */
	public function render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$status  = $this->plugin->status();
		$titles  = array();
		$modules = $this->plugin->registry()->all();
		foreach ( $this->plugin->registry()->blocked_by_pro() as $id ) {
			if ( isset( $modules[ $id ] ) ) {
				$titles[] = $modules[ $id ]->title();
			}
		}

		$notice = self::message( $status, $titles );
		if ( null === $notice ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
	}
}
