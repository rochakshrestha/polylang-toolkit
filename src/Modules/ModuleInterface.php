<?php
/**
 * Contract every toolkit module implements.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Modules;

use ToolkitForPolylang\Polylang\Adapter;

defined( 'ABSPATH' ) || exit;

/**
 * A module is one switchable feature (DUP, SLUG, SYNC, MEDIA, COMPAT …).
 *
 * Rules:
 * - The constructor must not add hooks or touch the database.
 * - All hooks are added in register(), which runs only when the module is enabled
 *   and the dependency check passed.
 * - id() and overlaps_pro() run on `plugins_loaded`, before `init`: they must not call
 *   translation functions. Only title() and description() may (they run in admin screens).
 */
interface ModuleInterface {

	/**
	 * Stable lowercase id, also the settings key and the spec prefix in lower case (e.g. "slug").
	 */
	public function id(): string;

	/**
	 * Translated, human-readable name.
	 */
	public function title(): string;

	/**
	 * Translated one-sentence description shown in settings.
	 */
	public function description(): string;

	/**
	 * Whether Polylang Pro ships an equivalent feature. Such modules stay off when POLYLANG_PRO is defined.
	 */
	public function overlaps_pro(): bool;

	/**
	 * Adds the module's hooks.
	 *
	 * @param Adapter $polylang Access point to Polylang.
	 */
	public function register( Adapter $polylang ): void;
}
