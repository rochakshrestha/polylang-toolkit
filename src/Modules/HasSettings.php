<?php
/**
 * Contract for a module with its own settings.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * A module with settings gets its own tab on Languages → Toolkit, and its settings are stored
 * under `tkpll_settings[<id>]`. See docs/specs/module-settings.md (CORE-11, CORE-12).
 *
 * These methods run on admin screens only, so they may call translation functions.
 */
interface HasSettings extends ModuleInterface {

	/**
	 * The module's settings with their defaults. Its keys are the only keys ever stored for the module.
	 *
	 * @return array<string, mixed>
	 */
	public function default_settings(): array;

	/**
	 * Clean values for the keys of default_settings(), from raw form input. Must not trust the
	 * input's shape, and must return the same result when given its own output.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( mixed $input ): array;

	/**
	 * Echoes the tab's form fields, named `<name_prefix>[<key>]`, escaped, each with a label.
	 *
	 * @param array<string, mixed> $values      Current values (defaults merged with stored values).
	 * @param string               $name_prefix Field name prefix, e.g. `tkpll_settings[slug]`.
	 */
	public function render_settings( array $values, string $name_prefix ): void;
}
