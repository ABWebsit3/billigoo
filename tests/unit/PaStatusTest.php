<?php
/**
 * Unit tests for PA status normalization and badge mapping.
 *
 * @package Billigoo
 */

namespace Billigoo\Tests\Unit;

use Billigoo\Pa\AbstractConnector;
use Billigoo\Admin\StatusBadge;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Billigoo\Pa\AbstractConnector
 * @covers \Billigoo\Admin\StatusBadge
 */
final class PaStatusTest extends TestCase {

	/**
	 * Provider statuses map onto the Billigoo vocabulary.
	 */
	public function test_normalize_status(): void {
		$this->assertSame( 'accepted', AbstractConnector::normalize_status( 'APPROVED' ) );
		$this->assertSame( 'rejected', AbstractConnector::normalize_status( 'refused' ) );
		$this->assertSame( 'sent', AbstractConnector::normalize_status( 'transmitted' ) );
		$this->assertSame( 'acknowledged', AbstractConnector::normalize_status( 'received' ) );
		$this->assertSame( 'paid', AbstractConnector::normalize_status( 'settled' ) );
		$this->assertSame( 'error', AbstractConnector::normalize_status( 'failed' ) );
		// Unknown values default to "sent" (transmitted but state unknown).
		$this->assertSame( 'sent', AbstractConnector::normalize_status( 'weird-thing' ) );
	}

	/**
	 * Each status resolves to a non-empty badge class and label.
	 */
	public function test_badge_mapping(): void {
		foreach ( array_keys( StatusBadge::filterable() ) as $status ) {
			$this->assertStringStartsWith( 'billigoo-badge--', StatusBadge::css_class( $status ) );
			$this->assertNotSame( '', StatusBadge::label( $status ) );
		}
		$this->assertSame( 'billigoo-badge--success', StatusBadge::css_class( 'accepted' ) );
		$this->assertSame( 'billigoo-badge--error', StatusBadge::css_class( 'rejected' ) );
	}
}
