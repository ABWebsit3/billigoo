<?php
/**
 * Drives PA transmission of generated invoices.
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

use Billigoo\Settings;
use Billigoo\License;
use Billigoo\Core\InvoiceStore;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-transmits B2B invoices after generation, supports manual re-transmission,
 * and refreshes statuses via a polling cron.
 */
final class TransmissionManager {

	public const POLL_HOOK = 'billigoo_poll_pa_status';

	/**
	 * Statuses that are final and need no further polling.
	 *
	 * @var array<int,string>
	 */
	private const FINAL = array( 'accepted', 'rejected', 'paid' );

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'billigoo_invoice_generated', array( $this, 'on_generated' ), 20, 2 );
		add_action( self::POLL_HOOK, array( $this, 'poll_statuses' ) );
	}

	/**
	 * Auto-transmit when enabled and eligible.
	 *
	 * @param \WC_Order            $order  Order.
	 * @param array<string,mixed>  $result Generation result.
	 */
	public function on_generated( $order, $result ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		if ( ! Settings::get( 'pa_auto_transmit' ) ) {
			return;
		}
		$this->transmit( $order );
	}

	/**
	 * Transmit (or re-transmit) one order's invoice to its PA.
	 *
	 * @param \WC_Order $order Order.
	 * @return array{status:string,remote_id:string,message:string}
	 */
	public function transmit( \WC_Order $order ): array {
		if ( ! License::has_feature( 'pa_transmission' ) ) {
			return array( 'status' => OrderMeta::pa_status( $order ), 'remote_id' => '', 'message' => __( 'Transmission PA réservée au plan Pro.', 'billigoo' ) );
		}
		if ( ! OrderMeta::has_invoice( $order ) ) {
			return array( 'status' => 'not_transmitted', 'remote_id' => '', 'message' => __( 'Aucune facture à transmettre.', 'billigoo' ) );
		}
		if ( ! Settings::is_pa_configured() ) {
			return array( 'status' => 'not_transmitted', 'remote_id' => '', 'message' => __( 'Plateforme Agréée non configurée.', 'billigoo' ) );
		}
		// B2C transactions are handled by e-reporting, not B2B transmission.
		if ( ! OrderMeta::is_business( $order ) ) {
			return array( 'status' => 'not_transmitted', 'remote_id' => '', 'message' => __( 'Commande B2C : relève de l’e-reporting.', 'billigoo' ) );
		}

		$relative = OrderMeta::get( $order, OrderMeta::INVOICE_PATH );
		$pdf      = '' !== $relative ? InvoiceStore::resolve( $relative ) : null;
		if ( ! $pdf ) {
			return array( 'status' => 'error', 'remote_id' => '', 'message' => __( 'Fichier de facture introuvable.', 'billigoo' ) );
		}

		$connector = PaRouter::for_order( $order );
		$xml       = OrderMeta::get( $order, OrderMeta::INVOICE_XML );
		$meta      = array(
			'invoice_number'  => OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ),
			'currency'        => $order->get_currency(),
			'issue_date_disp' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : gmdate( 'Y-m-d' ),
			'buyer_siret'     => OrderMeta::get( $order, OrderMeta::SIRET ),
			'total'           => (float) $order->get_total(),
		);

		$retries = (int) Settings::get( 'pa_retry', 1 );
		$result  = array( 'status' => 'error', 'remote_id' => '', 'message' => '' );
		for ( $attempt = 0; $attempt <= $retries; $attempt++ ) {
			$result = $connector->send_invoice( $pdf, $xml, $meta );
			if ( 'error' !== $result['status'] ) {
				break;
			}
		}

		OrderMeta::save_pa_status(
			$order,
			array(
				'status'    => $result['status'],
				'provider'  => $connector->provider(),
				'remote_id' => $result['remote_id'],
				'message'   => $result['message'],
			)
		);

		$order->add_order_note(
			sprintf(
				/* translators: 1: provider, 2: status, 3: message */
				__( 'Billigoo — Transmission PA (%1$s) : %2$s. %3$s', 'billigoo' ),
				$connector->provider(),
				$result['status'],
				$result['message']
			)
		);

		/**
		 * Fires when an order's PA status changes.
		 *
		 * @param \WC_Order $order     Order.
		 * @param string    $status    Normalised status.
		 * @param string    $remote_id Remote reference.
		 */
		do_action( 'billigoo_pa_status_changed', $order, $result['status'], $result['remote_id'] );

		return $result;
	}

	/**
	 * Cron: refresh non-final PA statuses.
	 */
	public function poll_statuses(): void {
		if ( ! License::has_feature( 'pa_transmission' ) || ! Settings::is_pa_configured() ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 50,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => OrderMeta::PA_REMOTE_ID,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => OrderMeta::PA_STATUS,
						'value'   => self::FINAL,
						'compare' => 'NOT IN',
					),
				),
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$remote = OrderMeta::get( $order, OrderMeta::PA_REMOTE_ID );
			if ( '' === $remote ) {
				continue;
			}
			$status = PaRouter::for_order( $order )->get_status( $remote );
			if ( $status !== OrderMeta::pa_status( $order ) ) {
				OrderMeta::save_pa_status(
					$order,
					array(
						'status'    => $status,
						'remote_id' => $remote,
						'message'   => __( 'Statut mis à jour automatiquement.', 'billigoo' ),
					)
				);
				do_action( 'billigoo_pa_status_changed', $order, $status, $remote );
			}
		}
	}
}
