<?php
/**
 * Legal sequential invoice numbering.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Billigoo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Allocates continuous, gap-free invoice numbers.
 *
 * French tax law (art. 242 nonies A CGI) requires an uninterrupted sequence
 * with no reuse. Numbers are NEVER derived from the WooCommerce order ID (those
 * have gaps). Allocation is serialised with a MySQL advisory lock (GET_LOCK) so
 * two simultaneous orders can never receive the same number.
 */
final class InvoiceNumber {

	private const LOCK_NAME    = 'billigoo_invoice_number';
	private const LOCK_TIMEOUT = 8;
	private const OPT_LAST     = 'billigoo_last_invoice_number';
	private const OPT_YEAR     = 'billigoo_invoice_number_year';

	/**
	 * Atomically reserve the next sequence value and return it formatted.
	 *
	 * @param \DateTimeInterface $date Invoice issue date (drives yearly reset).
	 * @return string Formatted invoice number, e.g. "FA-2026-0001".
	 *
	 * @throws \RuntimeException When the lock cannot be acquired.
	 */
	public static function allocate( \DateTimeInterface $date ): string {
		global $wpdb;

		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::LOCK_NAME, self::LOCK_TIMEOUT ) );
		if ( '1' !== (string) $got ) {
			throw new \RuntimeException( 'Billigoo: could not acquire invoice numbering lock.' );
		}

		try {
			$current_year = (int) $date->format( 'Y' );
			$stored_year  = (int) get_option( self::OPT_YEAR, $current_year );
			$last         = (int) get_option( self::OPT_LAST, 0 );

			if ( Settings::get( 'number_yearly_reset' ) && $current_year > $stored_year ) {
				$last = 0;
				update_option( self::OPT_YEAR, $current_year, false );
			}

			$next = $last + 1;
			update_option( self::OPT_LAST, $next, false );

			return self::format( $next, $current_year );
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK_NAME ) );
		}
	}

	/**
	 * Peek at the last allocated raw number (for dashboards/settings display).
	 */
	public static function last(): int {
		return (int) get_option( self::OPT_LAST, 0 );
	}

	/**
	 * Format a raw sequence number using the configured prefix and padding.
	 *
	 * The prefix supports a {year} token, substituted with the issue year.
	 *
	 * @param int $number Raw sequence value.
	 * @param int $year   Issue year.
	 */
	public static function format( int $number, int $year ): string {
		$prefix  = (string) Settings::get( 'number_prefix', 'FA-{year}-' );
		$prefix  = str_replace( '{year}', (string) $year, $prefix );
		$padding = (int) Settings::get( 'number_padding', 4 );

		return $prefix . str_pad( (string) $number, $padding, '0', STR_PAD_LEFT );
	}
}
