<?php
/**
 * Standalone end-to-end check of the generation core:
 *   data → CII XML → mPDF (PDF/A) → atgp embed → read XML back.
 *
 * Run: php tests/facturx-pipeline-check.php
 * Writes the assembled Factur-X PDF to tests/output/ for veraPDF inspection.
 *
 * No WordPress required: a few WP helpers are stubbed below.
 *
 * @package Billigoo
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'BILLIGOO_PATH', __DIR__ . '/../' );
define( 'BILLIGOO_URL', '' );
define( 'BILLIGOO_VERSION', 'test' );

// --- Minimal WordPress shims used by PdfBuilder + template ---
function __( $t, $d = 'default' ) { return $t; }                       // phpcs:ignore
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); } // phpcs:ignore
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); } // phpcs:ignore
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); } // phpcs:ignore
function trailingslashit( $p ) { return rtrim( (string) $p, '/\\' ) . '/'; } // phpcs:ignore
function get_temp_dir() { return sys_get_temp_dir(); }                  // phpcs:ignore
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); } // phpcs:ignore

require __DIR__ . '/../vendor/autoload.php';

use Billigoo\Core\FacturxBuilder;
use Billigoo\Core\PdfBuilder;
use Billigoo\Core\PdfEmbedder;
use Atgp\FacturX\Reader;

$data = require __DIR__ . '/fixture-invoice-data.php';

try {
	$xml     = ( new FacturxBuilder() )->build( $data );
	$pdf     = ( new PdfBuilder() )->render( $data );
	$facturx = ( new PdfEmbedder() )->embed( $pdf, $xml );

	$out_dir = __DIR__ . '/output';
	wp_mkdir_p( $out_dir );
	$out = $out_dir . '/' . $data['invoice_number'] . '.pdf';
	file_put_contents( $out, $facturx );

	// Round-trip: pull the embedded XML back out and confirm it matches.
	$extracted = ( new Reader() )->extractXML( $facturx, false );
	$ok        = str_contains( $extracted, $data['invoice_number'] );

	echo "Visual PDF bytes : " . strlen( $pdf ) . "\n";
	echo "Factur-X bytes   : " . strlen( $facturx ) . "\n";
	echo "Embedded XML read : " . ( $ok ? 'OK (invoice number present)' : 'MISMATCH' ) . "\n";
	echo "Written to        : $out\n";
	echo $ok ? "PIPELINE OK\n" : "PIPELINE FAIL\n";
	exit( $ok ? 0 : 1 );
} catch ( \Throwable $e ) {
	echo 'FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
	exit( 1 );
}
