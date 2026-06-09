<?php
/**
 * MU-PLUGIN de diagnostic PISTE — affiche un admin notice avec les résultats.
 *
 * INSTALLATION : copier dans wp-content/mu-plugins/mu-debug-piste.php
 * SUPPRIMER immédiatement après lecture des résultats.
 */

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( ! isset( $_GET['billigoo_piste_debug'] ) ) {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Billigoo PISTE Debug</strong> — ';
        echo '<a href="' . esc_url( add_query_arg( 'billigoo_piste_debug', '1' ) ) . '">Lancer le diagnostic</a>';
        echo '</p></div>';
        return;
    }

    echo '<div class="notice notice-warning" style="font-family:monospace;white-space:pre-wrap;font-size:13px;">';
    echo '<strong>=== Diagnostic PISTE ===</strong>' . "\n\n";

    // Settings
    $settings      = get_option( 'billigoo_settings', [] );
    $client_id     = $settings['pa_chorus_client_id'] ?? '';
    $secret_stored = $settings['pa_chorus_client_secret'] ?? '';

    echo 'pa_provider    : ' . esc_html( $settings['pa_provider'] ?? '(vide)' ) . "\n";
    echo 'pa_environment : ' . esc_html( $settings['pa_environment'] ?? '(vide)' ) . "\n";
    echo 'client_id      : ' . esc_html( '' !== $client_id ? $client_id : '(VIDE)' ) . "\n";
    echo 'secret stocké  : ' . esc_html( '' !== $secret_stored ? substr( $secret_stored, 0, 12 ) . '...' : '(VIDE)' ) . "\n";

    // Déchiffrement
    $client_secret = '';
    if ( class_exists( '\Billigoo\Crypto' ) ) {
        $client_secret = \Billigoo\Crypto::decrypt( $secret_stored );
        echo 'secret déchiffré : ' . esc_html( '' !== $client_secret ? substr( $client_secret, 0, 8 ) . '...' : '(VIDE — echec déchiffrement)' ) . "\n";
    } else {
        echo "Billigoo\\Crypto non trouvé\n";
    }

    echo "\n--- wp_remote_post → token PISTE ---\n";

    $url      = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
    $response = wp_remote_post( $url, [
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
        echo 'WP_Error : ' . esc_html( $response->get_error_message() ) . "\n";
        echo 'Code     : ' . esc_html( $response->get_error_code() ) . "\n";
    } else {
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        echo 'HTTP     : ' . esc_html( (string) $code ) . "\n";
        echo 'Body     : ' . esc_html( $body ) . "\n";
        $data = json_decode( $body, true );
        if ( isset( $data['access_token'] ) ) {
            echo "\n✅ TOKEN OBTENU — wp_remote_request fonctionne.\n";
        }
    }

    echo "\nPHP : " . PHP_VERSION . " | WP : " . get_bloginfo( 'version' ) . "\n";
    echo 'SSL ext  : ' . ( extension_loaded( 'openssl' ) ? 'OK' : 'MANQUANT' ) . "\n";
    echo 'cURL ext : ' . ( extension_loaded( 'curl' ) ? 'OK' : 'MANQUANT' ) . "\n";
    echo '</div>';
} );
