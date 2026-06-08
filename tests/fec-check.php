<?php
/**
 * Standalone check: FEC ledger rows balance (debit == credit).
 *
 * Run: php tests/fec-check.php
 * No WordPress required — uses the shared invoice-data fixture.
 *
 * @package Billigoo
 */

define( 'ABSPATH', __DIR__ . '/../' );

require __DIR__ . '/../vendor/autoload.php';

use Billigoo\Export\FecExporter;

$data = require __DIR__ . '/fixture-invoice-data.php';
$rows = FecExporter::rows_for_data( $data, array() );

$debit  = 0.0;
$credit = 0.0;
foreach ( $rows as $row ) {
	$debit  += (float) str_replace( ',', '.', $row[11] );
	$credit += (float) str_replace( ',', '.', $row[12] );
}

printf( "Rows: %d | Débit: %.2f | Crédit: %.2f\n", count( $rows ), $debit, $credit );

if ( abs( $debit - $credit ) > 0.001 ) {
	echo "FAIL: ledger not balanced\n";
	exit( 1 );
}
if ( abs( $debit - (float) $data['totals']['grand'] ) > 0.001 ) {
	echo "FAIL: debit total != invoice TTC\n";
	exit( 1 );
}
echo "OK: FEC ledger balanced and equals invoice TTC.\n";
exit( 0 );
