<?php
/**
 * Module with its own settings, used by tests.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Fixtures;

use ToolkitForPolylang\Modules\HasSettings;
use ToolkitForPolylang\Polylang\Adapter;

/**
 * Two settings: `color` (red, blue or green; default red) and `size` (1 to 10; default 1).
 */
final class FakeSettingsModule implements HasSettings {

	/**
	 * Constructor.
	 *
	 * @param string $id           Module id.
	 * @param bool   $overlaps_pro Whether the module overlaps Polylang Pro.
	 */
	public function __construct( private string $id, private bool $overlaps_pro = false ) {}

	/**
	 * Module id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Title.
	 */
	public function title(): string {
		return 'Fake ' . $this->id;
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'A fake module with settings.';
	}

	/**
	 * Overlap flag.
	 */
	public function overlaps_pro(): bool {
		return $this->overlaps_pro;
	}

	/**
	 * Adds no hooks.
	 *
	 * @param Adapter $polylang Adapter.
	 */
	public function register( Adapter $polylang ): void {}

	/**
	 * Defaults.
	 *
	 * @return array{color: string, size: int}
	 */
	public function default_settings(): array {
		return array(
			'color' => 'red',
			'size'  => 1,
		);
	}

	/**
	 * Keeps an allowed color and clamps the size; anything else becomes the default.
	 *
	 * @param mixed $input Raw input.
	 * @return array{color: string, size: int}
	 */
	public function sanitize_settings( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$color = $input['color'] ?? null;
		$size  = isset( $input['size'] ) && is_numeric( $input['size'] ) ? (int) $input['size'] : 1;

		return array(
			'color' => in_array( $color, array( 'red', 'blue', 'green' ), true ) ? $color : 'red',
			'size'  => max( 1, min( 10, $size ) ),
		);
	}

	/**
	 * One labelled text field per setting.
	 *
	 * @param array<string, mixed> $values      Current values.
	 * @param string               $name_prefix Field name prefix.
	 */
	public function render_settings( array $values, string $name_prefix ): void {
		foreach ( array( 'color', 'size' ) as $key ) {
			$field_id = 'tkpll-' . $this->id . '-' . $key;
			printf(
				'<label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" />',
				esc_attr( $field_id ),
				esc_html( ucfirst( $key ) ),
				esc_attr( $name_prefix . '[' . $key . ']' ),
				esc_attr( (string) ( $values[ $key ] ?? '' ) )
			);
		}
	}
}
