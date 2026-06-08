<?php
/**
 * Chorus Pro connector (B2G) via the PISTE gateway.
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

defined( 'ABSPATH' ) || exit;

/**
 * Transmits invoices to public-sector buyers through Chorus Pro.
 *
 * Authenticates with OAuth2 client-credentials against PISTE, then deposits the
 * invoice. Used only when the buyer is a public entity (B2G).
 *
 * @see https://piste.gouv.fr
 */
final class ChorusProConnector extends AbstractConnector {

	private const TOKEN_URL_SANDBOX = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
	private const TOKEN_URL_PROD    = 'https://oauth.piste.gouv.fr/api/oauth/token';
	private const API_SANDBOX       = 'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/';
	private const API_PROD          = 'https://api.piste.gouv.fr/cpro/factures/v1/';

	private string $client_id;
	private string $client_secret;
	private bool $production;

	/**
	 * @param string $client_id     Decrypted client id.
	 * @param string $client_secret Decrypted client secret.
	 * @param bool   $production     Whether to use the production gateway.
	 */
	public function __construct( string $client_id, string $client_secret, bool $production = false ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->production    = $production;
	}

	public function provider(): string {
		return 'chorus_pro';
	}

	/**
	 * Obtain an OAuth2 access token, or '' on failure.
	 */
	private function token(): string {
		if ( '' === $this->client_id || '' === $this->client_secret ) {
			return '';
		}
		$url = $this->production ? self::TOKEN_URL_PROD : self::TOKEN_URL_SANDBOX;
		$res = $this->request(
			'POST',
			$url,
			array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			http_build_query(
				array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $this->client_id,
					'client_secret' => $this->client_secret,
					'scope'         => 'openid',
				)
			)
		);
		return $res['ok'] ? (string) ( $res['body']['access_token'] ?? '' ) : '';
	}

	public function connect(): array {
		$token = $this->token();
		return array(
			'ok'      => '' !== $token,
			'message' => '' !== $token ? __( 'Connexion Chorus Pro (PISTE) établie.', 'billigoo' ) : __( 'Échec d’authentification PISTE.', 'billigoo' ),
		);
	}

	public function send_invoice( string $pdf_path, string $xml, array $metadata ): array {
		$token = $this->token();
		if ( '' === $token ) {
			return array( 'status' => 'error', 'remote_id' => '', 'message' => __( 'Authentification Chorus Pro impossible.', 'billigoo' ) );
		}
		if ( ! is_readable( $pdf_path ) ) {
			return array( 'status' => 'error', 'remote_id' => '', 'message' => __( 'Fichier PDF introuvable.', 'billigoo' ) );
		}

		$base = $this->production ? self::API_PROD : self::API_SANDBOX;
		$res  = $this->post_json(
			$base . 'deposer/flux',
			array(
				'fichierFlux'  => base64_encode( (string) file_get_contents( $pdf_path ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
				'nomFichier'   => basename( $pdf_path ),
				'syntaxeFlux'  => 'IN_DP_E2_FACTURX_PDFA3',
				'avecSignature'=> false,
			),
			array( 'Authorization' => 'Bearer ' . $token )
		);

		if ( ! $res['ok'] ) {
			return array( 'status' => 'error', 'remote_id' => '', 'message' => $res['error'] ?: __( 'Dépôt Chorus Pro refusé.', 'billigoo' ) );
		}

		return array(
			'status'    => 'sent',
			'remote_id' => (string) ( $res['body']['numeroFluxDepot'] ?? '' ),
			'message'   => __( 'Déposée sur Chorus Pro.', 'billigoo' ),
		);
	}

	public function get_status( string $remote_id ): string {
		$token = $this->token();
		if ( '' === $token || '' === $remote_id ) {
			return 'sent';
		}
		$base = $this->production ? self::API_PROD : self::API_SANDBOX;
		$res  = $this->post_json(
			$base . 'consulter/compte-rendu',
			array( 'numeroFluxDepot' => $remote_id ),
			array( 'Authorization' => 'Bearer ' . $token )
		);
		if ( ! $res['ok'] ) {
			return 'sent';
		}
		return self::normalize_status( (string) ( $res['body']['etatCourantCodes'] ?? 'sent' ) );
	}
}
