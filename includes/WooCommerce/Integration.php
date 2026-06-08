<?php
/**
 * WooCommerce order lifecycle integration (automatic generation).
 *
 * @package Billigoo
 */

namespace Billigoo\WooCommerce;

use Billigoo\Settings;
use Billigoo\Core\InvoiceGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * Triggers invoice generation when an order reaches the configured status.
 */
final class Integration {

	/**
	 * Hook into order status transitions.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_generate' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_generate' ), 20, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'maybe_generate' ), 20, 1 );
	}

	/**
	 * Generate the invoice if this status is the configured trigger.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_generate( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$trigger = (string) Settings::get( 'trigger_status', 'processing' );
		if ( $order->get_status() !== $trigger ) {
			return;
		}

		$this->generate( $order );
	}

	/**
	 * Run generation, logging failures rather than breaking the order flow.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $force Force regeneration.
	 * @return array<string,mixed>|null
	 */
	public function generate( \WC_Order $order, bool $force = false ): ?array {
		try {
			return ( new InvoiceGenerator() )->generate( $order, $force );
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				$e->getMessage(),
				array( 'source' => 'billigoo', 'order_id' => $order->get_id() )
			);
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Billigoo : échec de génération de la facture — %s', 'billigoo' ),
					$e->getMessage()
				)
			);
			return null;
		}
	}
}
