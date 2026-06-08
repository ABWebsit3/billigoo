<?php
/**
 * Symmetric encryption for stored secrets (PA / webhook credentials).
 *
 * @package Billigoo
 */

namespace Billigoo;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-CBC helper keyed off the site's WordPress salts.
 *
 * Encrypted values are tagged with an `enc:` prefix so the same field can be
 * stored, re-saved and read back idempotently (we never double-encrypt).
 */
final class Crypto {

	private const PREFIX = 'enc:';
	private const CIPHER = 'aes-256-cbc';

	/**
	 * Whether encryption is available on this host.
	 */
	public static function available(): bool {
		return function_exists( 'openssl_encrypt' );
	}

	/**
	 * Whether a stored value is already encrypted by us.
	 *
	 * @param string $value Stored value.
	 */
	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::PREFIX );
	}

	/**
	 * Encrypt a plaintext secret. Returns the value unchanged if encryption is
	 * unavailable, empty, or the value is already encrypted.
	 *
	 * @param string $plain Plaintext.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain || self::is_encrypted( $plain ) || ! self::available() ) {
			return $plain;
		}
		$iv     = openssl_random_pseudo_bytes( (int) openssl_cipher_iv_length( self::CIPHER ) );
		$cipher = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $plain;
		}
		return self::PREFIX . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a value previously produced by encrypt(). Plaintext (un-prefixed)
	 * input is returned as-is so legacy/plain values keep working.
	 *
	 * @param string $stored Stored value.
	 */
	public static function decrypt( string $stored ): string {
		if ( ! self::is_encrypted( $stored ) || ! self::available() ) {
			return $stored;
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw ) {
			return '';
		}
		$iv_len = (int) openssl_cipher_iv_length( self::CIPHER );
		if ( strlen( $raw ) <= $iv_len ) {
			return '';
		}
		$iv    = substr( $raw, 0, $iv_len );
		$data  = substr( $raw, $iv_len );
		$plain = openssl_decrypt( $data, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Derive a 256-bit key from the site's secret auth salt.
	 */
	private static function key(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'secure_auth' ) : 'billigoo-fallback-salt';
		return hash( 'sha256', 'billigoo|' . $salt, true );
	}
}
