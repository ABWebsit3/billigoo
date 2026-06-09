<?php
/**
 * Extracts the embedded Factur-X XML from a PDF and writes it to a .xml file.
 *
 * Usage: php tests/extract-xml.php path/to/FA-2026-0002.pdf
 */

require __DIR__ . '/../vendor/autoload.php';

$pdf_path = $argv[1] ?? null;
if ( ! $pdf_path || ! is_file( $pdf_path ) ) {
    fwrite( STDERR, "Usage: php extract-xml.php <path/to/invoice.pdf>\n" );
    exit( 1 );
}

try {
    $reader = new \Atgp\FacturX\Reader();
    // extractXML returns the raw XML string; pass false to skip XSD re-validation here.
    $xml = $reader->extractXML( file_get_contents( $pdf_path ), false );

    if ( '' === trim( $xml ) ) {
        fwrite( STDERR, "Aucun XML Factur-X trouvé dans ce PDF.\n" );
        exit( 1 );
    }

    $out = preg_replace( '/\.pdf$/i', '.xml', $pdf_path );
    file_put_contents( $out, $xml );

    echo "XML extrait → {$out}\n";
    echo "Taille XML : " . strlen( $xml ) . " octets\n";
    echo "\n--- Début du XML ---\n";
    echo substr( $xml, 0, 500 ) . "\n";

} catch ( \Throwable $e ) {
    fwrite( STDERR, 'Erreur : ' . $e->getMessage() . "\n" );
    exit( 1 );
}
