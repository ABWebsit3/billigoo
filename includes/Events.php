<?php
/**
 * Central domain-event dispatcher.
 *
 * @package Billigoo
 */

namespace Billigoo;

use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Translates internal plugin actions into named domain events and re-broadcasts
 * them through a single `billigoo_event` action. Webhooks (and any future
 * consumer) subscribe to that one hook instead of many.
 */
final class Events {

	public const GENERATED      = 'invoice.generated';
	public const SENT           = 'invoice.sent';
	public const ACCEPTED       = 'invoice.accepted';
	public const REJECTED       = 'invoice.rejected';
	public const EREPORTING_SENT = 'ereporting.sent';

	/**
	 * Wire producers.
	 */
	public function register(): void {
		add_action( 'billigoo_invoice_generated', array( $this, 'on_generated' ), 10, 2 );
		add_action( 'billigoo_pa_status_changed', array( $this, 'on_pa_status' ), 10, 3 );
	}

	/**
	 * Emit a domain event.
	 *
	 * @param string              $event Event name.
	 * @param array<string,mixed> $data  Payload data.
	 */
	public static function dispatch( string $event, array $data ): void {
		/**
		 * A Billigoo domain event occurred.
		 *
		 * @param string              $event Event name (e.g. invoice.accepted).
		 * @param array<string,mixed> $data  Event payload.
		 */
		do_action( 'billigoo_event', $event, $data );
	}

	/**
	 * Map a freshly generated invoice to an event.
	 *
	 * @param \WC_Order           $order  Order.
	 * @param array<string,mixed> $result Generation result.
	 */
	public function on_generated( $order, $result ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		self::dispatch(
			self::GENERATED,
			array(
				'invoice_id' => (string) ( $result['number'] ?? '' ),
				'order_id'   => $order->get_id(),
				'timestamp'  => gmdate( 'c' ),
				'data'       => array( 'amount' => (float) $order->get_total() ),
			)
		);
	}

	/**
	 * Map a PA status change to sent/accepted/rejected events.
	 *
	 * @param \WC_Order $order      Order.
	 * @param string    $status     Normalised PA status.
	 * @param string    $remote_ref Remote reference.
	 */
	public function on_pa_status( $order, string $status, string $remote_ref = '' ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$map = array(
			'sent'     => self::SENT,
			'accepted' => self::ACCEPTED,
			'rejected' => self::REJECTED,
		);
		if ( ! isset( $map[ $status ] ) ) {
			return;
		}
		self::dispatch(
			$map[ $status ],
			array(
				'invoice_id' => OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ),
				'order_id'   => $order->get_id(),
				'timestamp'  => gmdate( 'c' ),
				'data'       => array(
					'pa_reference' => $remote_ref,
					'amount'       => (float) $order->get_total(),
				),
			)
		);
	}
}
