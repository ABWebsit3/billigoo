<?php
/**
 * Unit tests for the Crypto helper.
 *
 * @package Billigoo
 */

namespace Billigoo\Tests\Unit;

use Billigoo\Crypto;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Billigoo\Crypto
 */
final class CryptoTest extends TestCase {

	/**
	 * Encryption then decryption returns the original plaintext.
	 */
	public function test_round_trip(): void {
		if ( ! Crypto::available() ) {
			$this->markTestSkipped( 'openssl not available' );
		}
		$plain  = 'sk_live_8x2-secret-Ü€';
		$cipher = Crypto::encrypt( $plain );

		$this->assertNotSame( $plain, $cipher );
		$this->assertTrue( Crypto::is_encrypted( $cipher ) );
		$this->assertSame( $plain, Crypto::decrypt( $cipher ) );
	}

	/**
	 * Encryption is not applied twice (idempotent on already-encrypted input).
	 */
	public function test_no_double_encrypt(): void {
		if ( ! Crypto::available() ) {
			$this->markTestSkipped( 'openssl not available' );
		}
		$cipher = Crypto::encrypt( 'token' );
		$this->assertSame( $cipher, Crypto::encrypt( $cipher ) );
	}

	/**
	 * Plaintext / empty values pass through decrypt unchanged.
	 */
	public function test_plaintext_passthrough(): void {
		$this->assertSame( '', Crypto::encrypt( '' ) );
		$this->assertSame( 'plain', Crypto::decrypt( 'plain' ) );
	}
}
