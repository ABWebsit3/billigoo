<?php
/**
 * "Billigoo" meta box on the order edit screen.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Billigoo\Settings;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the invoice status and actions inside the WooCommerce order editor
 * (works with both HPOS and the legacy post-based screen).
 */
final class OrderMetaBox {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
	}

	/**
	 * Register the meta box on the relevant screen.
	 */
	public function add(): void {
		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'billigoo_order_metabox',
			__( 'Billigoo', 'billigoo' ),
			array( $this, 'render' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Screen subject.
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( OrderMeta::has_invoice( $order ) ) {
			$this->render_existing( $order );
			return;
		}

		if ( ! Settings::is_seller_configured() ) {
			echo '<p>' . esc_html__( 'Configurez l’identité du vendeur pour générer une facture.', 'billigoo' ) . '</p>';
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=billigoo_generate&order_id=' . $order->get_id() ),
			'billigoo_generate_' . $order->get_id()
		);
		echo '<p>' . esc_html__( 'Aucune facture générée pour cette commande.', 'billigoo' ) . '</p>';
		echo '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Générer maintenant', 'billigoo' ) . '</a>';
	}

	/**
	 * Render details + actions when an invoice already exists.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function render_existing( \WC_Order $order ): void {
		$number = OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER );
		$date   = OrderMeta::get( $order, OrderMeta::INVOICE_DATE );
		$disp   = '' !== $date ? wp_date( 'd/m/Y H:i', strtotime( $date ) ) : '';

		$pdf = wp_nonce_url(
			admin_url( 'admin-post.php?action=billigoo_download_pdf&order_id=' . $order->get_id() ),
			'billigoo_download_' . $order->get_id()
		);
		$xml = wp_nonce_url(
			admin_url( 'admin-post.php?action=billigoo_download_xml&order_id=' . $order->get_id() ),
			'billigoo_download_' . $order->get_id()
		);

		$pa_status = OrderMeta::pa_status( $order );

		echo '<p><strong>' . esc_html__( 'Facture', 'billigoo' ) . ' :</strong> ' . esc_html( $number ) . '</p>';
		if ( '' !== $disp ) {
			echo '<p><strong>' . esc_html__( 'Générée le', 'billigoo' ) . ' :</strong> ' . esc_html( $disp ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Statut PA', 'billigoo' ) . ' :</strong> ' . esc_html( StatusBadge::label( $pa_status ) );
		$provider = OrderMeta::get( $order, OrderMeta::PA_PROVIDER );
		if ( '' !== $provider ) {
			echo ' <span style="color:#646970;">(' . esc_html( $provider );
			$remote = OrderMeta::get( $order, OrderMeta::PA_REMOTE_ID );
			if ( '' !== $remote ) {
				echo ' · ' . esc_html( $remote );
			}
			echo ')</span>';
		}
		echo '</p>';

		echo '<p>';
		echo '<a class="button" href="' . esc_url( $pdf ) . '">' . esc_html__( 'PDF', 'billigoo' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $xml ) . '">' . esc_html__( 'XML', 'billigoo' ) . '</a>';
		echo '</p>';

		if ( \Billigoo\Settings::is_pa_configured() && OrderMeta::is_business( $order ) ) {
			$transmit = wp_nonce_url(
				admin_url( 'admin-post.php?action=billigoo_transmit&order_id=' . $order->get_id() ),
				'billigoo_transmit_' . $order->get_id()
			);
			$label = in_array( $pa_status, array( 'not_transmitted', 'error', 'rejected' ), true )
				? __( 'Transmettre à la PA', 'billigoo' )
				: __( 'Actualiser / retransmettre', 'billigoo' );
			echo '<p><a class="button button-primary" href="' . esc_url( $transmit ) . '">' . esc_html( $label ) . '</a></p>';
		}
	}
}
