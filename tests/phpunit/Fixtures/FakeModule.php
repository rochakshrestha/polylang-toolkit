<?php
/**
 * Configurable module used by tests.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Tests\Fixtures;

use ToolkitForPolylang\Modules\ModuleInterface;
use ToolkitForPolylang\Polylang\Adapter;

/**
 * Records how often register() ran.
 */
final class FakeModule implements ModuleInterface {

	/**
	 * Number of register() calls.
	 *
	 * @var int
	 */
	public int $registered = 0;

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
		return 'A fake module.';
	}

	/**
	 * Overlap flag.
	 */
	public function overlaps_pro(): bool {
		return $this->overlaps_pro;
	}

	/**
	 * Counts calls.
	 *
	 * @param Adapter $polylang Adapter.
	 */
	public function register( Adapter $polylang ): void {
		++$this->registered;
	}
}
