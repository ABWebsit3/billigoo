<?php
/**
 * Pre-generation validation.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Billigoo\Settings;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that enough data exists to emit a compliant invoice before any work is
 * done. Returns a list of human-readable error strings (empty = OK).
 */
final class InvoiceValidator {

	/**
	 * Validate an order for invoice generation.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<int,string> Error messages (empty when valid).
	 */
	public static function validate( \WC_Order $order ): array {
		$errors = array();

		if ( ! Settings::is_seller_configured() ) {
			$errors[] = __( "L'identité du vendeur (raison sociale, SIRET, adresse) doit être configurée dans les réglages Billigoo.", 'billigoo' );
		}

		$seller_siret = (string) Settings::get( 'seller_siret' );
		if ( '' !== $seller_siret && ! SiretValidator::is_valid_siret( $seller_siret ) ) {
			$errors[] = __( 'Le SIRET du vendeur est invalide.', 'billigoo' );
		}

		if ( OrderMeta::is_business( $order ) ) {
			$buyer_siret = OrderMeta::get( $order, OrderMeta::SIRET );
			if ( '' === $buyer_siret ) {
				$errors[] = __( 'Commande professionnelle : le SIRET de l’acheteur est manquant.', 'billigoo' );
			} elseif ( ! SiretValidator::is_valid_siret( $buyer_siret ) ) {
				$errors[] = __( 'Commande professionnelle : le SIRET de l’acheteur est invalide.', 'billigoo' );
			}
		}

		return $errors;
	}
}
