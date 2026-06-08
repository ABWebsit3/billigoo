<?php
/**
 * Standalone check: does FacturxBuilder emit XSD-valid BASIC CII XML?
 *
 * Run: php tests/facturx-xsd-check.php
 * No WordPress required — we stub the constant guard and i18n helpers.
 *
 * @package Billigoo
 */

define( 'ABSPATH', __DIR__ . '/../' );
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; } // phpcs:ignore
}

require __DIR__ . '/../vendor/autoload.php';

use Billigoo\Core\FacturxBuilder;

$data = require __DIR__ . '/fixture-invoice-data.php';

try {
	$xml = ( new FacturxBuilder() )->build( $data );
	echo "OK: XML built and validated against Factur-X 1.08 BASIC XSD.\n";
	echo 'Length: ' . strlen( $xml ) . " bytes\n";
	echo substr( $xml, 0, 600 ) . "\n...\n";
} catch ( \Throwable $e ) {
	echo "FAIL: " . $e->getMessage() . "\n";
	exit( 1 );
}
