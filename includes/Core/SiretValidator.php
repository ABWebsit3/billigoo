<?php
/**
 * SIREN / SIRET validation.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Validates French SIRET (14 digits) and SIREN (9 digits) identifiers using
 * the Luhn checksum. Reused by checkout AJAX validation and the pre-generation
 * invoice validator.
 */
final class SiretValidator {

	/**
	 * Validate a 14-digit SIRET.
	 *
	 * @param string $siret Raw value (spaces/non-digits tolerated).
	 */
	public static function is_valid_siret( string $siret ): bool {
		$digits = preg_replace( '/\D/', '', $siret );
		if ( 14 !== strlen( $digits ) ) {
			return false;
		}
		// La Poste SIRET (SIREN 356000000) is a documented exception to Luhn.
		if ( str_starts_with( $digits, '356000000' ) ) {
			return self::sum_of_digits( $digits ) % 5 === 0;
		}
		return self::luhn_valid( $digits );
	}

	/**
	 * Validate a 9-digit SIREN.
	 *
	 * @param string $siren Raw value (spaces/non-digits tolerated).
	 */
	public static function is_valid_siren( string $siren ): bool {
		$digits = preg_replace( '/\D/', '', $siren );
		if ( 9 !== strlen( $digits ) ) {
			return false;
		}
		return self::luhn_valid( $digits );
	}

	/**
	 * Standard Luhn checksum validation.
	 *
	 * @param string $digits Numeric string.
	 */
	private static function luhn_valid( string $digits ): bool {
		$sum    = 0;
		$length = strlen( $digits );
		// Rightmost digit has position 1; double every second digit from the right.
		for ( $i = 0; $i < $length; $i++ ) {
			$d = (int) $digits[ $length - 1 - $i ];
			if ( 1 === $i % 2 ) {
				$d *= 2;
				if ( $d > 9 ) {
					$d -= 9;
				}
			}
			$sum += $d;
		}
		return 0 === $sum % 10;
	}

	/**
	 * Plain digit sum (for the La Poste exception).
	 *
	 * @param string $digits Numeric string.
	 */
	private static function sum_of_digits( string $digits ): int {
		$sum = 0;
		foreach ( str_split( $digits ) as $d ) {
			$sum += (int) $d;
		}
		return $sum;
	}
}
