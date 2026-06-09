<?php
/**
 * Secure admin endpoints: download PDF/XML, generate on demand.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\Core\InvoiceStore;
use Billigoo\Pa\TransmissionManager;
use Billigoo\WooCommerce\Integration;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Capability- and nonce-gated handlers behind admin-post.php. The PDF lives in a
 * web-inaccessible directory, so these endpoints are the only way to fetch it.
 */
final class DownloadEndpoint {

	/**
	 * Register admin-post actions.
	 */
	public function register(): void {
		add_action( 'admin_post_billigoo_download_pdf', array( $this, 'download_pdf' ) );
		add_action( 'admin_post_billigoo_download_xml', array( $this, 'download_xml' ) );
		add_action( 'admin_post_billigoo_generate', array( $this, 'generate' ) );
		add_action( 'admin_post_billigoo_transmit', array( $this, 'transmit' ) );
	}

	/**
	 * Authorise the request and return the order, or die.
	 *
	 * @param string $nonce_prefix Nonce action prefix.
	 * @return \WC_Order
	 */
	private function authorise( string $nonce_prefix ): \WC_Order {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'billigoo' ), 403 );
		}
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( $nonce_prefix . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Commande introuvable.', 'billigoo' ), 404 );
		}
		return $order;
	}

	/**
	 * Stream the Factur-X PDF.
	 */
	public function download_pdf(): void {
		$order    = $this->authorise( 'billigoo_download_' );
		$relative = OrderMeta::get( $order, OrderMeta::INVOICE_PATH );
		$path     = '' !== $relative ? InvoiceStore::resolve( $relative ) : null;

		if ( ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Fichier de facture introuvable.', 'billigoo' ), 404 );
		}

		$number   = OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER, 'facture' );
		$filename = InvoiceStore::safe_filename( $number ) . '.pdf';

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Stream the embedded CII XML (stored on the order).
	 */
	public function download_xml(): void {
		$order = $this->authorise( 'billigoo_download_' );
		$xml   = OrderMeta::get( $order, OrderMeta::INVOICE_XML );

		if ( '' === $xml ) {
			wp_die( esc_html__( 'XML Factur-X indisponible.', 'billigoo' ), 404 );
		}

		$number = OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER, 'facture' );

		nocache_headers();
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . InvoiceStore::safe_filename( $number ) . '.xml"' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate an invoice on demand from the order meta box.
	 */
	public function generate(): void {
		$order  = $this->authorise( 'billigoo_generate_' );
		$result = ( new Integration() )->generate( $order );

		$redirect = $order->get_edit_order_url();
		$redirect = add_query_arg( 'billigoo_generated', $result ? '1' : '0', $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Re-transmit an invoice to the configured PA.
	 */
	public function transmit(): void {
		$order  = $this->authorise( 'billigoo_transmit_' );
		$result = ( new TransmissionManager() )->transmit( $order );

		$back = wp_get_referer() ?: $order->get_edit_order_url();
		$back = add_query_arg( 'billigoo_transmitted', rawurlencode( $result['status'] ), $back );
		wp_safe_redirect( $back );
		exit;
	}
}
