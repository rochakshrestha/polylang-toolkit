<?php
/**
 * Plugin entry point.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang;

use ToolkitForPolylang\Admin\Notices;
use ToolkitForPolylang\Admin\Settings;
use ToolkitForPolylang\Modules\Registry;
use ToolkitForPolylang\Polylang\Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the toolkit into WordPress.
 *
 * Boot sequence:
 * 1. File load: hook() adds callbacks only. No Polylang assumptions.
 * 2. `pll_init` (inside Polylang's `plugins_loaded` priority 1): boot() checks the dependency and registers enabled modules.
 * 3. `plugins_loaded` priority 20: admin UI and notices. Runs even when Polylang is missing, so the user sees why nothing works.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Module registry, built lazily.
	 *
	 * @var Registry|null
	 */
	private ?Registry $registry = null;

	/**
	 * Polylang adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $adapter;

	/**
	 * Constructor. Public so tests can build isolated instances.
	 *
	 * @param Adapter|null $adapter Polylang adapter.
	 */
	public function __construct( ?Adapter $adapter = null ) {
		$this->adapter = $adapter ?? new Adapter();
	}

	/**
	 * Shared instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Adds the top-level hooks. Safe to call when Polylang is absent.
	 */
	public function hook(): void {
		add_action( 'pll_init', array( $this, 'boot' ), 10, 0 );
		add_action( 'plugins_loaded', array( $this, 'admin' ), 20, 0 );
	}

	/**
	 * Boots enabled modules. Runs on `pll_init`.
	 */
	public function boot(): void {
		$status = $this->status();
		if ( ! Dependency::can_boot( $status ) ) {
			return;
		}

		$settings = Options::get();
		$this->registry()->boot( $settings['modules'], $status, $this->adapter );

		/**
		 * Fires after the toolkit has booted its enabled modules.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'tkpll_booted', $this );
	}

	/**
	 * Loads admin-only parts.
	 */
	public function admin(): void {
		if ( ! is_admin() ) {
			return;
		}

		( new Notices( $this ) )->hook();

		if ( Dependency::can_boot( $this->status() ) ) {
			( new Settings( $this ) )->hook();
		}
	}

	/**
	 * Current dependency status (see Dependency constants).
	 */
	public function status(): string {
		return Dependency::current();
	}

	/**
	 * Module registry.
	 */
	public function registry(): Registry {
		if ( null === $this->registry ) {
			$this->registry = Registry::from_filter();
		}
		return $this->registry;
	}

	/**
	 * Polylang adapter.
	 */
	public function adapter(): Adapter {
		return $this->adapter;
	}
}
