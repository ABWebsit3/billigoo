<?php
/**
 * Unit tests for the FEC ledger builder.
 *
 * @package Billigoo
 */

namespace Billigoo\Tests\Unit;

use Billigoo\Export\FecExporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Billigoo\Export\FecExporter
 */
final class FecExporterTest extends TestCase {

	/**
	 * Build rows from the shared invoice fixture.
	 *
	 * @return array<int,array<int,string>>
	 */
	private function rows(): array {
		$data = require __DIR__ . '/../fixture-invoice-data.php';
		return FecExporter::rows_for_data( $data, array() );
	}

	/**
	 * Every invoice must balance: total debit == total credit.
	 */
	public function test_ledger_balances(): void {
		$debit  = 0.0;
		$credit = 0.0;
		foreach ( $this->rows() as $row ) {
			$debit  += (float) str_replace( ',', '.', $row[11] );
			$credit += (float) str_replace( ',', '.', $row[12] );
		}
		$this->assertEqualsWithDelta( $debit, $credit, 0.001 );
		$this->assertEqualsWithDelta( 1218.0, $debit, 0.001 ); // TTC from fixture.
	}

	/**
	 * The first row is the client debit for the TTC amount.
	 */
	public function test_client_debit_row(): void {
		$rows  = $this->rows();
		$first = $rows[0];
		$this->assertSame( '411000', $first[4] );      // CompteNum.
		$this->assertSame( '1218,00', $first[11] );     // Debit = TTC.
		$this->assertSame( '0,00', $first[12] );        // Credit.
	}

	/**
	 * Amounts use a comma decimal and no thousands separator.
	 */
	public function test_amount_format(): void {
		foreach ( $this->rows() as $row ) {
			$this->assertMatchesRegularExpression( '/^\d+,\d{2}$/', $row[11] );
			$this->assertMatchesRegularExpression( '/^\d+,\d{2}$/', $row[12] );
		}
	}
}
