<?php
/**
 * Stores the toolkit settings in a single option.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang;

defined( 'ABSPATH' ) || exit;

/**
 * Settings shape: `[ 'modules' => [ '<module id>' => bool ] ]`.
 *
 * Modules add their own keys under `[ '<module id>' => [ ... ] ]` in later milestones.
 * Secrets (API keys) never go in this option; they belong in `tkpll_secrets` (not autoloaded).
 *
 * @phpstan-type SettingsShape array{modules: array<string, bool>}
 */
final class Options {

	public const OPTION_NAME  = 'tkpll_settings';
	public const SECRETS_NAME = 'tkpll_secrets';

	/**
	 * Returns default settings: every module off.
	 *
	 * @return array{modules: array<string, bool>}
	 */
	public static function defaults(): array {
		return array( 'modules' => array() );
	}

	/**
	 * Returns the stored settings merged over the defaults.
	 *
	 * @return array{modules: array<string, bool>}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$modules = array();
		if ( isset( $stored['modules'] ) && is_array( $stored['modules'] ) ) {
			foreach ( $stored['modules'] as $id => $enabled ) {
				$modules[ (string) $id ] = (bool) $enabled;
			}
		}

		return array( 'modules' => $modules );
	}

	/**
	 * Whether a module is switched on.
	 *
	 * @param string $module_id Module id.
	 */
	public static function is_module_enabled( string $module_id ): bool {
		$settings = self::get();
		return ! empty( $settings['modules'][ $module_id ] );
	}

	/**
	 * Sanitizes raw settings input (from the settings form or the REST settings endpoint).
	 *
	 * Unknown module ids are dropped, values become booleans.
	 *
	 * @param mixed    $input     Raw input.
	 * @param string[] $known_ids Ids of registered modules.
	 * @return array{modules: array<string, bool>}
	 */
	public static function sanitize( $input, array $known_ids ): array {
		$clean = self::defaults();

		if ( ! is_array( $input ) || ! isset( $input['modules'] ) || ! is_array( $input['modules'] ) ) {
			foreach ( $known_ids as $id ) {
				$clean['modules'][ $id ] = false;
			}
			return $clean;
		}

		foreach ( $known_ids as $id ) {
			$clean['modules'][ $id ] = ! empty( $input['modules'][ $id ] );
		}

		return $clean;
	}
}
