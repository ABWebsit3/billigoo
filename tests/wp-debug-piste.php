<?php
/**
 * Diagnostic PISTE dans le contexte WordPress (wp_remote_request).
 *
 * Uploader dans wp-content/plugins/billigoo/tests/ puis accéder via :
 *   https://billigoo.agence-abweb.fr/wp-content/plugins/billigoo/tests/wp-debug-piste.php
 *
 * SUPPRIMER après le debug.
 */

// Bootstrap WordPress
$wp_root = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ); // remonte à la racine WP
if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
    // Fallback : chercher wp-load.php en remontant
    $dir = __DIR__;
    for ( $i = 0; $i < 8; $i++ ) {
        $dir = dirname( $dir );
        if ( file_exists( $dir . '/wp-load.php' ) ) {
            $wp_root = $dir;
            break;
        }
    }
}
require_once $wp_root . '/wp-load.php';

header( 'Content-Type: text/plain; charset=utf-8' );

// Récupérer les credentials depuis les settings Billigoo
$settings      = get_option( 'billigoo_settings', [] );
$client_id     = $settings['pa_chorus_client_id'] ?? '';
$client_secret_raw = $settings['pa_chorus_client_secret'] ?? '';

echo "=== Diagnostic PISTE (wp_remote_request) ===\n\n";
echo "pa_provider      : " . ( $settings['pa_provider'] ?? '(vide)' ) . "\n";
echo "pa_environment   : " . ( $settings['pa_environment'] ?? '(vide)' ) . "\n";
echo "pa_chorus_client_id (brut) : " . ( '' !== $client_id ? $client_id : '(VIDE)' ) . "\n";
echo "pa_chorus_client_secret (stocké) : " . ( '' !== $client_secret_raw ? substr( $client_secret_raw, 0, 10 ) . '...' : '(VIDE)' ) . "\n";

// Décrypter le secret via Billigoo\Crypto
if ( class_exists( '\Billigoo\Crypto' ) ) {
    $client_secret = \Billigoo\Crypto::decrypt( $client_secret_raw );
    echo "pa_chorus_client_secret (déchiffré) : " . ( '' !== $client_secret ? substr( $client_secret, 0, 8 ) . '...' : '(VIDE — déchiffrement échoué)' ) . "\n";
} else {
    echo "Classe Billigoo\\Crypto non trouvée — plugin non chargé correctement\n";
    $client_secret = '';
}

echo "\n--- Test wp_remote_request → PISTE token ---\n";

$token_url = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
$response  = wp_remote_post( $token_url, [
    'timeout'   => 20,
    'sslverify' => false,
    'headers'   => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
    'body'      => http_build_query( [
        'grant_type'    => 'client_credentials',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'scope'         => 'openid',
    ] ),
] );

if ( is_wp_error( $response ) ) {
    echo "WP_Error : " . $response->get_error_message() . "\n";
    echo "Code     : " . $response->get_error_code() . "\n";
} else {
    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    echo "HTTP     : $code\n";
    echo "Body     : $body\n";
    $data = json_decode( $body, true );
    if ( isset( $data['access_token'] ) ) {
        echo "\n✅ Token obtenu ! Credentials OK, wp_remote_request fonctionne.\n";
    } else {
        echo "\n❌ Pas de token. Voir le body ci-dessus.\n";
    }
}

echo "\n--- Info PHP/WP ---\n";
echo "PHP      : " . PHP_VERSION . "\n";
echo "WP       : " . get_bloginfo( 'version' ) . "\n";
echo "SSL ext  : " . ( extension_loaded( 'openssl' ) ? 'OK' : 'MANQUANT' ) . "\n";
echo "cURL ext : " . ( extension_loaded( 'curl' ) ? 'OK' : 'MANQUANT' ) . "\n";
