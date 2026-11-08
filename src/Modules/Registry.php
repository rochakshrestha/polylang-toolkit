<?php
/**
 * Holds the available modules and boots the enabled ones.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Modules;

use ToolkitForPolylang\Dependency;
use ToolkitForPolylang\Polylang\Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * Module registry.
 */
final class Registry {

	/**
	 * Modules keyed by id.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Ids of modules that were booted.
	 *
	 * @var string[]
	 */
	private array $booted = array();

	/**
	 * Ids of enabled modules held back because Polylang Pro is active.
	 *
	 * @var string[]
	 */
	private array $blocked_by_pro = array();

	/**
	 * Constructor.
	 *
	 * @param iterable<mixed> $modules Candidate modules. Anything that is not a ModuleInterface, or has an invalid or duplicate id, is ignored.
	 */
	public function __construct( iterable $modules ) {
		foreach ( $modules as $module ) {
			if ( ! $module instanceof ModuleInterface ) {
				continue;
			}
			$id = $module->id();
			if ( ! preg_match( '/^[a-z][a-z0-9_]{1,31}$/', $id ) || isset( $this->modules[ $id ] ) ) {
				continue;
			}
			$this->modules[ $id ] = $module;
		}
	}

	/**
	 * Builds the registry from the built-in modules plus the `tkpll_modules` filter.
	 */
	public static function from_filter(): self {
		/**
		 * Filters the list of toolkit modules.
		 *
		 * @param ModuleInterface[] $modules Built-in modules.
		 */
		$modules = apply_filters( 'tkpll_modules', self::builtin() );

		return new self( is_iterable( $modules ) ? $modules : array() );
	}

	/**
	 * Built-in modules. Empty in M0; each milestone adds its module here.
	 *
	 * @return ModuleInterface[]
	 */
	public static function builtin(): array {
		return array();
	}

	/**
	 * All registered modules keyed by id.
	 *
	 * @return array<string, ModuleInterface>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Registered module ids.
	 *
	 * @return string[]
	 */
	public function ids(): array {
		return array_keys( $this->modules );
	}

	/**
	 * Boots every enabled module that is allowed to run.
	 *
	 * @param array<string, bool> $enabled Module id => enabled flag.
	 * @param string              $status  A Dependency status.
	 * @param Adapter             $polylang Polylang adapter passed to modules.
	 * @return string[] Ids of the modules booted by this call.
	 */
	public function boot( array $enabled, string $status, Adapter $polylang ): array {
		if ( ! Dependency::can_boot( $status ) ) {
			return array();
		}

		$booted_now = array();
		foreach ( $this->modules as $id => $module ) {
			if ( empty( $enabled[ $id ] ) || in_array( $id, $this->booted, true ) ) {
				continue;
			}
			if ( Dependency::PRO_ACTIVE === $status && $module->overlaps_pro() ) {
				$this->blocked_by_pro[] = $id;
				continue;
			}
			$module->register( $polylang );
			$this->booted[] = $id;
			$booted_now[]   = $id;
		}

		return $booted_now;
	}

	/**
	 * Ids of modules booted so far.
	 *
	 * @return string[]
	 */
	public function booted(): array {
		return $this->booted;
	}

	/**
	 * Ids of enabled modules held back because Polylang Pro is active.
	 *
	 * @return string[]
	 */
	public function blocked_by_pro(): array {
		return array_values( array_unique( $this->blocked_by_pro ) );
	}
}
