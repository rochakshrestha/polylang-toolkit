<?php
/**
 * Removes everything the toolkit stored. Never touches Polylang data.
 *
 * @package ToolkitForPolylang
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes toolkit data for the current site.
 */
function tkpll_uninstall_site(): void {
	global $wpdb;

	delete_option( 'tkpll_settings' );
	delete_option( 'tkpll_secrets' );

	// Options and transients added by modules, all prefixed `tkpll_`.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'tkpll_' ) . '%',
			$wpdb->esc_like( '_transient_tkpll_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_tkpll_' ) . '%'
		)
	);

	// Post and term meta added by modules, all prefixed `_tkpll_`.
	foreach ( array( $wpdb->postmeta, $wpdb->termmeta ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE meta_key LIKE %s", $wpdb->esc_like( '_tkpll_' ) . '%' ) );
	}

	wp_cache_flush();
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $tkpll_site_id ) {
		switch_to_blog( (int) $tkpll_site_id );
		tkpll_uninstall_site();
		restore_current_blog();
	}
} else {
	tkpll_uninstall_site();
}
