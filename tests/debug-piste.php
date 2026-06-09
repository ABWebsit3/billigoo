<?php
/**
 * Debug PISTE / Chorus Pro OAuth2 authentication.
 * Run BEFORE configuring Billigoo to diagnose the exact error.
 *
 * Usage:
 *   php tests/debug-piste.php <client_id> <client_secret> [sandbox|production]
 */

$client_id     = $argv[1] ?? '';
$client_secret = $argv[2] ?? '';
$env           = $argv[3] ?? 'sandbox';

if ( '' === $client_id || '' === $client_secret ) {
    fwrite( STDERR, "Usage: php debug-piste.php <client_id> <client_secret> [sandbox|production]\n" );
    exit( 1 );
}

$token_url = 'sandbox' === $env
    ? 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token'
    : 'https://oauth.piste.gouv.fr/api/oauth/token';

$api_base = 'sandbox' === $env
    ? 'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/'
    : 'https://api.piste.gouv.fr/cpro/factures/v1/';

echo "=== Debug PISTE ($env) ===\n";
echo "Token URL : $token_url\n";
echo "Client ID : $client_id\n\n";

// Test both auth methods × scopes — PISTE may require Basic Auth instead of body creds
$attempts = [
    [ 'method' => 'body',  'scope' => 'openid' ],
    [ 'method' => 'basic', 'scope' => 'openid' ],
    [ 'method' => 'basic', 'scope' => '' ],
    [ 'method' => 'basic', 'scope' => 'openid profile' ],
];

foreach ( $attempts as $attempt ) {
    $scope  = $attempt['scope'];
    $method = $attempt['method'];
    $label  = '' === $scope ? '(vide)' : $scope;
    echo "--- Tentative auth=$method scope=\"$label\" ---\n";

    $body_params = [ 'grant_type' => 'client_credentials' ];
    if ( '' !== $scope ) {
        $body_params['scope'] = $scope;
    }
    if ( 'body' === $method ) {
        $body_params['client_id']     = $client_id;
        $body_params['client_secret'] = $client_secret;
    }
    $payload = http_build_query( $body_params );

    $headers = [ 'Content-Type: application/x-www-form-urlencoded' ];
    if ( 'basic' === $method ) {
        $headers[] = 'Authorization: Basic ' . base64_encode( $client_id . ':' . $client_secret );
    }

    $ch = curl_init( $token_url );
    curl_setopt_array( $ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ] );

    $body = curl_exec( $ch );
    $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $err  = curl_error( $ch );
    curl_close( $ch );

    if ( $err ) {
        echo "CURL error : $err\n\n";
        continue;
    }

    echo "HTTP $code\n";
    $data = json_decode( $body, true );
    if ( json_last_error() === JSON_ERROR_NONE ) {
        echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
    } else {
        echo $body . "\n";
    }

    if ( $code === 200 && isset( $data['access_token'] ) ) {
        $token = $data['access_token'];
        echo "\n✅ Token obtenu avec scope=\"$label\"\n";
        echo "   Expiration : " . ( $data['expires_in'] ?? '?' ) . "s\n\n";

        // Test several API endpoint variants + with/without API key header
        $api_key = $argv[4] ?? ''; // optional: pass API key as 4th argument

        $endpoints = [
            $api_base . 'lister/factures-emises'                                          => 'deposer/flux (chemin actuel)',
            'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/deposer/flux'             => 'deposer/flux v1',
            'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/lister/factures-emises'  => 'lister/factures-emises v1',
            'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/'                         => 'base v1 (GET)',
        ];

        foreach ( $endpoints as $url => $label2 ) {
            foreach ( ( $api_key ? [false, true] : [false] ) as $with_key ) {
                $key_label = $with_key ? ' +ApiKey' : '';
                echo "--- Test : $label2$key_label ---\n";
                echo "    URL : $url\n";

                $headers = [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ];
                if ( $with_key ) {
                    $headers[] = 'cpro-api-key: ' . $api_key;
                    $headers[] = 'Ocp-Apim-Subscription-Key: ' . $api_key;
                }

                $is_get = str_ends_with( $url, '/' );
                $ch2 = curl_init( $url );
                curl_setopt_array( $ch2, [
                    CURLOPT_CUSTOMREQUEST  => $is_get ? 'GET' : 'POST',
                    CURLOPT_POSTFIELDS     => $is_get ? null : json_encode( [ 'numeroPage' => 1 ] ),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ] );
                $body2 = curl_exec( $ch2 );
                $code2 = curl_getinfo( $ch2, CURLINFO_HTTP_CODE );
                curl_close( $ch2 );

                $d2 = json_decode( $body2, true );
                $display = $d2 ?? ( substr( $body2, 0, 300 ) ?: '(empty)' );
                echo "    HTTP $code2 → " . json_encode( $display, JSON_UNESCAPED_UNICODE ) . "\n\n";

                if ( $code2 === 200 ) {
                    echo "✅ ACCESSIBLE : $url\n\n";
                }
            }
        }

        echo "\n=== Scope correct pour notre connecteur : \"$label\" ===\n";
        break;
    }
    echo "\n";
}
