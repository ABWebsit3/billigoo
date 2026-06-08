<?php
/**
 * PHPUnit bootstrap for WordPress-independent unit tests.
 *
 * Provides just enough of the WordPress runtime (the ABSPATH guard and a couple
 * of i18n shims) for the pure-logic classes under test to load.
 *
 * @package Billigoo
 */

define( 'ABSPATH', __DIR__ . '/../' );

if ( ! function_exists( '__' ) ) {
	/**
	 * Minimal i18n shim.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Deterministic salt shim for crypto tests.
	 *
	 * @param string $scheme Salt scheme.
	 */
	function wp_salt( $scheme = 'auth' ) {
		return 'billigoo-test-salt-' . $scheme;
	}
}

require __DIR__ . '/../vendor/autoload.php';
