<?php
/**
 * Pennylane PA connector (REST, bearer token).
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

defined( 'ABSPATH' ) || exit;

/**
 * Transmits customer invoices to Pennylane.
 *
 * @see https://pennylane.com/fr/api-documentation
 */
final class PennylaneConnector extends AbstractConnector {

	private const API_BASE = 'https://app.pennylane.com/api/v2/';

	private string $token;

	/**
	 * @param string $token Decrypted API token.
	 */
	public function __construct( string $token ) {
		$this->token = $token;
	}

	public function provider(): string {
		return 'pennylane';
	}

	/**
	 * Auth headers.
	 *
	 * @return array<string,string>
	 */
	private function auth(): array {
		return array( 'Authorization' => 'Bearer ' . $this->token );
	}

	public function connect(): array {
		if ( '' === $this->token ) {
			return array( 'ok' => false, 'message' => __( 'Jeton API Pennylane manquant.', 'billigoo' ) );
		}
		$res = $this->get_json( self::API_BASE . 'customer_invoices?per_page=1', $this->auth() );
		return array(
			'ok'      => $res['ok'],
			'message' => $res['ok'] ? __( 'Connexion Pennylane établie.', 'billigoo' ) : ( $res['error'] ?: __( 'Échec de connexion.', 'billigoo' ) ),
		);
	}

	public function send_invoice( string $pdf_path, string $xml, array $metadata ): array {
		if ( ! is_readable( $pdf_path ) ) {
			return array( 'status' => 'error', 'remote_id' => '', 'message' => __( 'Fichier PDF introuvable.', 'billigoo' ) );
		}

		$content = (string) file_get_contents( $pdf_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$res     = $this->post_multipart(
			self::API_BASE . 'customer_invoices/import',
			array(
				'invoice_number' => (string) ( $metadata['invoice_number'] ?? '' ),
				'currency'       => (string) ( $metadata['currency'] ?? 'EUR' ),
				'date'           => (string) ( $metadata['issue_date_disp'] ?? '' ),
			),
			array(
				'file' => array(
					'name'    => basename( $pdf_path ),
					'type'    => 'application/pdf',
					'content' => $content,
				),
			),
			$this->auth()
		);

		if ( ! $res['ok'] ) {
			return array( 'status' => 'error', 'remote_id' => '', 'message' => $res['error'] ?: __( 'Transmission refusée.', 'billigoo' ) );
		}

		return array(
			'status'    => self::normalize_status( (string) ( $res['body']['status'] ?? 'sent' ) ),
			'remote_id' => (string) ( $res['body']['id'] ?? '' ),
			'message'   => __( 'Transmise à Pennylane.', 'billigoo' ),
		);
	}

	public function get_status( string $remote_id ): string {
		if ( '' === $remote_id ) {
			return 'not_transmitted';
		}
		$res = $this->get_json( self::API_BASE . 'customer_invoices/' . rawurlencode( $remote_id ), $this->auth() );
		if ( ! $res['ok'] ) {
			return 'sent';
		}
		return self::normalize_status( (string) ( $res['body']['status'] ?? 'sent' ) );
	}
}
