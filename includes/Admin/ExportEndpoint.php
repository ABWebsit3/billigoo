<?php
/**
 * Secure FEC / CSV export download endpoints.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\License;
use Billigoo\Export\CsvExporter;
use Billigoo\Export\FecExporter;

defined( 'ABSPATH' ) || exit;

/**
 * Streams export files generated on demand from the Export settings tab.
 */
final class ExportEndpoint {

	public const NONCE = 'billigoo_export';

	/**
	 * Register admin-post handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_billigoo_export_fec', array( $this, 'export_fec' ) );
		add_action( 'admin_post_billigoo_export_csv', array( $this, 'export_csv' ) );
	}

	/**
	 * Read and validate the requested period (defaults to the current year).
	 *
	 * @return array{after:string,before:string}
	 */
	private function period(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce checked by caller.
		$after  = isset( $_GET['after'] ) ? sanitize_text_field( wp_unslash( $_GET['after'] ) ) : '';
		$before = isset( $_GET['before'] ) ? sanitize_text_field( wp_unslash( $_GET['before'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$after  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $after ) ? $after : gmdate( 'Y-01-01' );
		$before = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before ) ? $before : gmdate( 'Y-12-31' );
		return array( 'after' => $after . ' 00:00:00', 'before' => $before . ' 23:59:59' );
	}

	/**
	 * Authorise an export request.
	 */
	private function authorise(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'billigoo' ), 403 );
		}
		check_admin_referer( self::NONCE );
	}

	/**
	 * Stream the FEC file.
	 */
	public function export_fec(): void {
		$this->authorise();
		if ( ! License::has_feature( 'export_fec' ) ) {
			wp_die( esc_html__( 'L’export FEC est réservé au plan Pro.', 'billigoo' ), 403 );
		}
		$p       = $this->period();
		$content = FecExporter::build( $p['after'], $p['before'] );
		$name    = 'FEC-' . gmdate( 'Ymd', strtotime( $p['after'] ) ) . '-' . gmdate( 'Ymd', strtotime( $p['before'] ) ) . '.txt';
		$this->stream( $content, $name, 'text/plain; charset=utf-8' );
	}

	/**
	 * Stream the CSV file.
	 */
	public function export_csv(): void {
		$this->authorise();
		$p       = $this->period();
		$content = CsvExporter::build( $p['after'], $p['before'] );
		$name    = 'factures-' . gmdate( 'Ymd', strtotime( $p['after'] ) ) . '-' . gmdate( 'Ymd', strtotime( $p['before'] ) ) . '.csv';
		$this->stream( $content, $name, 'text/csv; charset=utf-8' );
	}

	/**
	 * Send a download response.
	 *
	 * @param string $content  File body.
	 * @param string $filename Download name.
	 * @param string $mime     Content type.
	 */
	private function stream( string $content, string $filename, string $mime ): void {
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
