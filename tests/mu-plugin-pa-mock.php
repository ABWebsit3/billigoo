<?php
/**
 * PA Mock — mu-plugin de test pour simuler les réponses des Plateformes Agréées.
 *
 * INSTALLATION : copier ce fichier dans wp-content/mu-plugins/billigoo-pa-mock.php
 * DÉSINSTALLER après les tests — NE PAS laisser en production.
 *
 * Simule trois scénarios selon la constante BILLIGOO_PA_MOCK_SCENARIO :
 *   'success'  — dépôt accepté, statut final = accepted  (défaut)
 *   'pending'  — dépôt accepté, statut en attente
 *   'error'    — dépôt refusé par la PA
 */

defined( 'ABSPATH' ) || exit;

// Changer ce flag pour tester les différents scénarios.
if ( ! defined( 'BILLIGOO_PA_MOCK_SCENARIO' ) ) {
    define( 'BILLIGOO_PA_MOCK_SCENARIO', 'success' );
}

add_filter( 'pre_http_request', 'billigoo_mock_pa_http', 10, 3 );

function billigoo_mock_pa_http( $preempt, array $args, string $url ) {

    // ── Chorus Pro / PISTE ────────────────────────────────────────────────────
    if ( str_contains( $url, 'piste.gouv.fr' ) ) {

        // Token endpoint
        if ( str_contains( $url, 'oauth/token' ) ) {
            return billigoo_mock_response( 200, [
                'access_token' => 'mock_token_chorus_' . uniqid(),
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ] );
        }

        // Dépôt facture
        if ( str_contains( $url, 'deposer/flux' ) ) {
            if ( 'error' === BILLIGOO_PA_MOCK_SCENARIO ) {
                return billigoo_mock_response( 400, [ 'message' => 'Syntaxe de flux non reconnue (mock)' ] );
            }
            return billigoo_mock_response( 200, [
                'numeroFluxDepot' => 'CHORUS-MOCK-' . strtoupper( uniqid() ),
                'dateDepot'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
            ] );
        }

        // Consultation compte-rendu (status polling)
        if ( str_contains( $url, 'consulter/compte-rendu' ) ) {
            $status = match ( BILLIGOO_PA_MOCK_SCENARIO ) {
                'pending' => 'EN_COURS_TRAITEMENT',
                'error'   => 'REJETEE',
                default   => 'COMPTABILISEE_PAYEE',
            };
            return billigoo_mock_response( 200, [ 'etatCourantCodes' => $status ] );
        }
    }

    // ── Pennylane ─────────────────────────────────────────────────────────────
    if ( str_contains( $url, 'pennylane.com' ) ) {

        // Test de connexion (GET customer_invoices)
        if ( str_contains( $url, 'customer_invoices' ) && 'GET' === ( $args['method'] ?? 'GET' ) ) {
            return billigoo_mock_response( 200, [ 'data' => [], 'total' => 0 ] );
        }

        // Import facture
        if ( str_contains( $url, 'customer_invoices/import' ) ) {
            if ( 'error' === BILLIGOO_PA_MOCK_SCENARIO ) {
                return billigoo_mock_response( 422, [ 'message' => 'Fichier invalide (mock)' ] );
            }
            return billigoo_mock_response( 201, [
                'id'     => 'PL-MOCK-' . strtoupper( uniqid() ),
                'status' => 'pending',
            ] );
        }

        // Statut
        if ( str_contains( $url, 'customer_invoices/' ) ) {
            $status = match ( BILLIGOO_PA_MOCK_SCENARIO ) {
                'pending' => 'pending',
                'error'   => 'rejected',
                default   => 'accepted',
            };
            return billigoo_mock_response( 200, [ 'status' => $status ] );
        }
    }

    // Laisser passer toutes les autres requêtes normalement.
    return $preempt;
}

/**
 * Forge une réponse WP_HTTP compatible.
 *
 * @param int   $code HTTP status code.
 * @param array $body Body as array (will be JSON-encoded).
 */
function billigoo_mock_response( int $code, array $body ): array {
    $json = wp_json_encode( $body );
    return [
        'headers'       => new \Requests_Utility_CaseInsensitiveDictionary( [ 'content-type' => 'application/json' ] ),
        'body'          => $json,
        'response'      => [ 'code' => $code, 'message' => '' ],
        'cookies'       => [],
        'http_response' => null,
    ];
}
