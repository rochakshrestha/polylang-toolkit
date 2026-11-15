<?php
/**
 * Stores the toolkit settings in a single option.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang;

use ToolkitForPolylang\Modules\HasSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings shape: `[ 'modules' => [ '<module id>' => bool ], '<module id>' => [ ... ] ]`.
 *
 * A module that implements HasSettings stores its own settings under its id (module_settings()).
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
	 * A module's settings: its defaults, with the stored value put over each default key.
	 * Stored keys the defaults do not have are ignored; a corrupted option gives the defaults.
	 *
	 * @param HasSettings $module Module.
	 * @return array<string, mixed>
	 */
	public static function module_settings( HasSettings $module ): array {
		$settings = $module->default_settings();
		$stored   = self::raw();
		$mine     = isset( $stored[ $module->id() ] ) && is_array( $stored[ $module->id() ] ) ? $stored[ $module->id() ] : array();

		foreach ( array_keys( $settings ) as $key ) {
			if ( array_key_exists( $key, $mine ) ) {
				$settings[ $key ] = $mine[ $key ];
			}
		}
		return $settings;
	}

	/**
	 * The stored option as an array (empty when missing or corrupted), every key kept.
	 *
	 * @return array<mixed>
	 */
	public static function raw(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		return is_array( $stored ) ? $stored : array();
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
