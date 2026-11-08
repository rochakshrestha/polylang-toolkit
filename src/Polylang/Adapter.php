<?php
/**
 * The only place in the toolkit that touches Polylang internals.
 *
 * @package ToolkitForPolylang
 */

namespace ToolkitForPolylang\Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around free Polylang.
 *
 * Every method documents which Polylang API it relies on (see docs/polylang-api.md).
 * Modules call this class instead of PLL() or PLL_* classes, so a Polylang change
 * is fixed in one file. Prefer the public `pll_*` functions; reach into PLL() only
 * when no public function exists, and guard every such access.
 */
class Adapter {

	/**
	 * Polylang version, or null when Polylang is not loaded.
	 */
	public function version(): ?string {
		return defined( 'POLYLANG_VERSION' ) ? (string) constant( 'POLYLANG_VERSION' ) : null;
	}

	/**
	 * Whether Polylang Pro is loaded (it defines POLYLANG_PRO).
	 */
	public function is_pro(): bool {
		return defined( 'POLYLANG_PRO' ) && (bool) constant( 'POLYLANG_PRO' );
	}

	/**
	 * Whether the Polylang API (src/api.php) is loaded. It is loaded just before `pll_init`.
	 */
	public function api_loaded(): bool {
		return function_exists( 'pll_languages_list' );
	}

	/**
	 * Language slugs. Uses `pll_languages_list()`.
	 *
	 * @return string[]
	 */
	public function language_slugs(): array {
		if ( ! $this->api_loaded() ) {
			return array();
		}
		$slugs = pll_languages_list( array( 'fields' => 'slug' ) );
		return is_array( $slugs ) ? array_map( 'strval', $slugs ) : array();
	}

	/**
	 * Default language slug, or null. Uses `pll_default_language()`.
	 */
	public function default_language(): ?string {
		if ( ! function_exists( 'pll_default_language' ) ) {
			return null;
		}
		$slug = pll_default_language( 'slug' );
		return is_string( $slug ) && '' !== $slug ? $slug : null;
	}

	/**
	 * Reads one Polylang option, e.g. `media_support` or `sync`. Uses `PLL()->options` (`WP_Syntex\Polylang\Options\Options`, ArrayAccess).
	 *
	 * @param string $key Option key.
	 * @return mixed Null when unavailable.
	 */
	public function option( string $key ) {
		$pll = $this->pll();
		if ( null === $pll ) {
			return null;
		}
		$options = $pll->options;
		if ( ! $options instanceof \ArrayAccess || ! isset( $options[ $key ] ) ) {
			return null;
		}
		return $options[ $key ];
	}

	/**
	 * Keys enabled in Polylang's own synchronization settings (`options['sync']`),
	 * e.g. `taxonomies`, `post_meta`, `_thumbnail_id`. Modules must not re-copy these.
	 *
	 * @return string[]
	 */
	public function polylang_sync_keys(): array {
		$sync = $this->option( 'sync' );
		return is_array( $sync ) ? array_values( array_map( 'strval', $sync ) ) : array();
	}

	/**
	 * Source post and target language of a "+" new-translation request in post-new.php.
	 *
	 * Uses `PLL()->links->get_data_from_new_post_translation_request()` (Polylang 3.7+),
	 * which also verifies the `new-post-translation` nonce.
	 *
	 * @return array{from_post: \WP_Post, new_lang: string}|null
	 */
	public function new_post_translation_request(): ?array {
		$pll = $this->pll();
		if ( ! $pll instanceof \PLL_Admin_Base || ! $pll->links instanceof \PLL_Admin_Links ) {
			return null; // Only the admin context handles post-new.php.
		}

		$data = $pll->links->get_data_from_new_post_translation_request();
		if ( empty( $data ) ) {
			return null;
		}

		return array(
			'from_post' => $data['from_post'],
			'new_lang'  => (string) $data['new_lang']->slug,
		);
	}

	/**
	 * The Polylang context object (PLL_Admin, PLL_Settings, PLL_Frontend or PLL_REST_Request), or null.
	 */
	protected function pll(): ?\PLL_Base {
		if ( ! function_exists( 'PLL' ) ) {
			return null;
		}
		$pll = PLL();
		return $pll instanceof \PLL_Base ? $pll : null;
	}
}
