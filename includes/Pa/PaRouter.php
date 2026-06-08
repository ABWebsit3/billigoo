<?php
/**
 * Routes invoices to the configured PA connector.
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

use Billigoo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the right connector from settings and decides B2B vs B2G routing.
 */
final class PaRouter {

	/**
	 * Build a connector for a provider slug (decrypting credentials).
	 *
	 * @param string $provider Provider slug.
	 */
	public static function build( string $provider ): PaConnector {
		switch ( $provider ) {
			case 'pennylane':
				return new PennylaneConnector( Settings::secret( 'pa_api_key' ) );
			case 'chorus_pro':
				return new ChorusProConnector(
					(string) Settings::get( 'pa_chorus_client_id', '' ),
					Settings::secret( 'pa_chorus_client_secret' ),
					'production' === Settings::get( 'pa_environment', 'sandbox' )
				);
			case 'qonto':
			case 'sage':
				// Connectors planned post-launch — fall back to manual for now.
			default:
				return new ManualConnector();
		}
	}

	/**
	 * Connector for the store's configured provider (used for the test button).
	 */
	public static function configured(): PaConnector {
		return self::build( (string) Settings::get( 'pa_provider', '' ) );
	}

	/**
	 * Connector to use for a specific order (B2G → Chorus Pro when applicable).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function for_order( \WC_Order $order ): PaConnector {
		if ( self::is_public_buyer( $order ) ) {
			return self::build( 'chorus_pro' );
		}
		return self::configured();
	}

	/**
	 * Whether the buyer is a public entity (B2G), routed to Chorus Pro.
	 *
	 * Reliable detection requires the SIRENE directory; we expose a filter and
	 * honour the global B2G toggle so integrators can decide per order.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function is_public_buyer( \WC_Order $order ): bool {
		$is_public = (bool) Settings::get( 'pa_b2g', 0 ) && 'chorus_pro' === Settings::get( 'pa_provider', '' );

		/**
		 * Filter whether an order's buyer is a public entity (B2G).
		 *
		 * @param bool      $is_public Default decision.
		 * @param \WC_Order $order     Order.
		 */
		return (bool) apply_filters( 'billigoo_is_public_buyer', $is_public, $order );
	}
}
