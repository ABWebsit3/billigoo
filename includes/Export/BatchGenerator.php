<?php
/**
 * Retroactive batch generation from the WooCommerce orders list.
 *
 * @package Billigoo
 */

namespace Billigoo\Export;

use Billigoo\License;
use Billigoo\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Générer Billigoo Factur-X" bulk action to the orders list. Uses
 * Action Scheduler (bundled with WooCommerce) to process large selections in the
 * background, falling back to a capped synchronous loop when it is unavailable.
 */
final class BatchGenerator {

	private const BULK_KEY  = 'billigoo_generate_batch';
	private const ONE_HOOK  = 'billigoo_generate_one';
	private const MAX_SYNC  = 500;

	/**
	 * Register bulk action handlers for both HPOS and legacy order screens.
	 */
	public function register(): void {
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_action' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle' ), 10, 3 );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle' ), 10, 3 );

		add_action( self::ONE_HOOK, array( $this, 'generate_one' ) );
	}

	/**
	 * Append our bulk action.
	 *
	 * @param array<string,string> $actions Existing actions.
	 * @return array<string,string>
	 */
	public function add_action( array $actions ): array {
		if ( License::has_feature( 'batch' ) ) {
			$actions[ self::BULK_KEY ] = __( 'Générer Billigoo Factur-X', 'billigoo' );
		}
		return $actions;
	}

	/**
	 * Process the bulk action.
	 *
	 * @param string         $redirect Redirect URL.
	 * @param string         $action   Action key.
	 * @param array<int,int> $ids      Selected order IDs.
	 * @return string
	 */
	public function handle( string $redirect, string $action, array $ids ): string {
		if ( self::BULK_KEY !== $action ) {
			return $redirect;
		}
		if ( ! current_user_can( 'manage_options' ) || ! License::has_feature( 'batch' ) ) {
			return $redirect;
		}

		$ids   = array_slice( array_map( 'absint', $ids ), 0, self::MAX_SYNC );
		$async = function_exists( 'as_enqueue_async_action' );

		foreach ( $ids as $id ) {
			if ( $async ) {
				as_enqueue_async_action( self::ONE_HOOK, array( $id ), 'billigoo' );
			} else {
				$this->generate_one( $id );
			}
		}

		return add_query_arg(
			array(
				'billigoo_batch' => $async ? 'queued' : 'done',
				'billigoo_count' => count( $ids ),
			),
			$redirect
		);
	}

	/**
	 * Generate the invoice for a single order (Action Scheduler callback).
	 *
	 * @param int $order_id Order ID.
	 */
	public function generate_one( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			( new Integration() )->generate( $order );
		}
	}
}
