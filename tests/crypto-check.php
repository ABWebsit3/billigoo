<?php
/**
 * Standalone check: Crypto encrypt/decrypt round-trip.
 *
 * Run: php tests/crypto-check.php
 * No WordPress required — we stub the constant guard and wp_salt().
 *
 * @package Billigoo
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) { return 'billigoo-test-salt-' . $scheme; } // phpcs:ignore
}

require __DIR__ . '/../vendor/autoload.php';

use Billigoo\Crypto;

$plain  = 'sk_live_secret_Ü€_42';
$cipher = Crypto::encrypt( $plain );
$back   = Crypto::decrypt( $cipher );

if ( $back !== $plain ) {
	echo "FAIL: round-trip mismatch\n";
	exit( 1 );
}
if ( $cipher === Crypto::encrypt( $cipher ) && Crypto::is_encrypted( $cipher ) ) {
	echo "OK: encrypt/decrypt round-trip + idempotent re-encrypt.\n";
	echo 'Cipher sample: ' . substr( $cipher, 0, 32 ) . "...\n";
	exit( 0 );
}
echo "FAIL: idempotency check\n";
exit( 1 );
