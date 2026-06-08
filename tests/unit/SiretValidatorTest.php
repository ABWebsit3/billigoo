<?php
/**
 * Unit tests for the SIRET / SIREN Luhn validator.
 *
 * @package Billigoo
 */

namespace Billigoo\Tests\Unit;

use Billigoo\Core\SiretValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Billigoo\Core\SiretValidator
 */
final class SiretValidatorTest extends TestCase {

	/**
	 * Real, Luhn-valid SIRET should pass (spaces tolerated).
	 */
	public function test_valid_siret(): void {
		$this->assertTrue( SiretValidator::is_valid_siret( '44306184100013' ) );
		$this->assertTrue( SiretValidator::is_valid_siret( '443 061 841 00013' ) );
	}

	/**
	 * Wrong check digit / wrong length must fail.
	 */
	public function test_invalid_siret(): void {
		$this->assertFalse( SiretValidator::is_valid_siret( '44306184100014' ) ); // Bad checksum.
		$this->assertFalse( SiretValidator::is_valid_siret( '1234567890123' ) );  // 13 digits.
		$this->assertFalse( SiretValidator::is_valid_siret( '' ) );
		$this->assertFalse( SiretValidator::is_valid_siret( 'abcdefghijklmn' ) );
	}

	/**
	 * SIREN (9 digits) Luhn validation.
	 */
	public function test_siren(): void {
		$this->assertTrue( SiretValidator::is_valid_siren( '443061841' ) );
		$this->assertFalse( SiretValidator::is_valid_siren( '443061842' ) );
		$this->assertFalse( SiretValidator::is_valid_siren( '12345678' ) ); // 8 digits.
	}

	/**
	 * La Poste SIRETs are a documented exception (sum divisible by 5).
	 */
	public function test_la_poste_exception(): void {
		// SIREN 356000000 + establishment, with total digit sum divisible by 5 (here 15).
		$this->assertTrue( SiretValidator::is_valid_siret( '35600000000010' ) );
	}
}
