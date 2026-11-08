<?php
/**
 * PSR-4 autoloader for the ToolkitForPolylang namespace.
 *
 * The release zip ships without Composer, so the plugin loads its own classes.
 *
 * @package ToolkitForPolylang
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'ToolkitForPolylang\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
