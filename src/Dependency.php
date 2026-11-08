<?php
/**
 * Decides whether the toolkit can run next to the installed Polylang.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang;

defined( 'ABSPATH' ) || exit;

/**
 * Pure decision logic, kept free of WordPress calls so it can be unit tested.
 */
final class Dependency {

	public const OK         = 'ok';
	public const MISSING    = 'missing';
	public const TOO_OLD    = 'too_old';
	public const PRO_ACTIVE = 'pro_active';

	/**
	 * Evaluates the environment.
	 *
	 * Polylang Pro also defines POLYLANG_VERSION, so `PRO_ACTIVE` means the toolkit runs
	 * but modules that overlap Pro features stay off.
	 *
	 * @param string|null $polylang_version Value of POLYLANG_VERSION, null when Polylang is not loaded.
	 * @param bool        $polylang_active  Whether Polylang passed its own WP/PHP checks (POLYLANG_ACTIVE).
	 * @param bool        $pro              Whether POLYLANG_PRO is defined and truthy.
	 * @param string      $min_version      Minimum supported Polylang version.
	 * @return string One of the class constants.
	 */
	public static function evaluate( ?string $polylang_version, bool $polylang_active, bool $pro, string $min_version ): string {
		if ( null === $polylang_version || ! $polylang_active ) {
			return self::MISSING;
		}

		if ( version_compare( $polylang_version, $min_version, '<' ) ) {
			return self::TOO_OLD;
		}

		return $pro ? self::PRO_ACTIVE : self::OK;
	}

	/**
	 * Evaluates the current request's environment.
	 *
	 * @return string One of the class constants.
	 */
	public static function current(): string {
		return self::evaluate(
			defined( 'POLYLANG_VERSION' ) ? (string) constant( 'POLYLANG_VERSION' ) : null,
			defined( 'POLYLANG_ACTIVE' ) && constant( 'POLYLANG_ACTIVE' ),
			defined( 'POLYLANG_PRO' ) && constant( 'POLYLANG_PRO' ),
			TKPLL_MIN_POLYLANG
		);
	}

	/**
	 * Whether modules may boot at all.
	 *
	 * @param string $status A status from evaluate().
	 */
	public static function can_boot( string $status ): bool {
		return self::OK === $status || self::PRO_ACTIVE === $status;
	}
}
