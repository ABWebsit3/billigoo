<?php
define( 'ABSPATH', '.' );
spl_autoload_register( function ( $class ) {
    $file = __DIR__ . '/../includes/' . str_replace( [ 'Billigoo\\', '\\' ], [ '', '/' ], $class ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

use Billigoo\Core\SiretValidator;

$list = [
    '35600000000048' => 'La Poste (exemple erroné)',
    '73282932000074' => 'Amazon France SAS',
    '78467169500103' => 'SIRET vendeur configuré',
    '30007664800019' => 'INSEE',
    '35600000000038' => 'La Poste variante',
    '12345678900018' => 'Fictif test',
];

foreach ( $list as $siret => $label ) {
    $ok = SiretValidator::is_valid_siret( $siret );
    echo ( $ok ? 'OK' : 'KO' ) . '  ' . $siret . '  ' . $label . PHP_EOL;
}
