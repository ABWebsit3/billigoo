<?php
/**
 * Manual / export-only PA connector.
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

defined( 'ABSPATH' ) || exit;

/**
 * Used for "Autre PDP" or when no automatic transmission is wired: the invoice
 * is generated and downloadable, but transmission is left to the operator.
 */
final class ManualConnector extends AbstractConnector {

	public function provider(): string {
		return 'other';
	}

	public function connect(): array {
		return array( 'ok' => true, 'message' => __( 'Mode manuel : aucune transmission automatique.', 'billigoo' ) );
	}

	public function send_invoice( string $pdf_path, string $xml, array $metadata ): array {
		return array(
			'status'    => 'not_transmitted',
			'remote_id' => '',
			'message'   => __( 'Transmission manuelle requise (PDP non automatisée).', 'billigoo' ),
		);
	}

	public function get_status( string $remote_id ): string {
		return 'not_transmitted';
	}
}
