<?php
/**
 * Invoice file storage (protected uploads directory).
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles writing/reading generated PDF files under a protected uploads folder
 * and resolving their on-disk paths.
 */
final class InvoiceStore {

	/**
	 * Absolute path to the plugin's base upload directory.
	 */
	public static function base_dir(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'billigoo';
	}

	/**
	 * Create the base upload dir and block direct web access to it.
	 */
	public static function ensure_protected_base_dir(): string {
		$base = self::base_dir();
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
		}

		$htaccess = $base . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$index = $base . '/index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $base;
	}

	/**
	 * Build the absolute path for an invoice PDF, creating the month folder.
	 *
	 * @param string             $invoice_number Formatted invoice number.
	 * @param \DateTimeInterface $date           Invoice issue date.
	 */
	public static function pdf_path( string $invoice_number, \DateTimeInterface $date ): string {
		$dir = self::base_dir() . '/' . $date->format( 'Y' ) . '/' . $date->format( 'm' );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir . '/' . self::safe_filename( $invoice_number ) . '.pdf';
	}

	/**
	 * Persist the generated PDF bytes to disk.
	 *
	 * @param string $path  Absolute target path.
	 * @param string $bytes PDF content.
	 * @return bool
	 */
	public static function write( string $path, string $bytes ): bool {
		return false !== file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Sanitize an invoice number for use as a filename.
	 *
	 * @param string $invoice_number Formatted number.
	 */
	public static function safe_filename( string $invoice_number ): string {
		return sanitize_file_name( str_replace( array( '/', '\\' ), '-', $invoice_number ) );
	}

	/**
	 * Convert an absolute path inside the base dir to a base-relative path.
	 *
	 * @param string $absolute Absolute path.
	 */
	public static function relative( string $absolute ): string {
		return ltrim( str_replace( self::base_dir(), '', $absolute ), '/\\' );
	}

	/**
	 * Resolve a base-relative path back to an absolute path, guarding against
	 * directory traversal outside the protected base directory.
	 *
	 * @param string $relative Base-relative path.
	 * @return string|null Absolute path, or null if it escapes the base dir.
	 */
	public static function resolve( string $relative ): ?string {
		$base = self::base_dir();
		$path = $base . '/' . ltrim( $relative, '/\\' );
		$real = realpath( $path );
		$base_real = realpath( $base );
		if ( false === $real || false === $base_real || ! str_starts_with( $real, $base_real ) ) {
			return null;
		}
		return $real;
	}
}
