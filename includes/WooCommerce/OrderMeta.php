<?php
/**
 * Typed access to Billigoo order meta (HPOS-safe).
 *
 * @package Billigoo
 */

namespace Billigoo\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Reads/writes Billigoo metadata on a WC_Order. Always operate through
 * wc_get_order() so this works identically with HPOS on or off.
 */
final class OrderMeta {

	// B2B data captured at checkout.
	public const IS_BUSINESS  = '_billigoo_is_business';
	public const COMPANY_NAME = '_billigoo_company_name';
	public const SIRET        = '_billigoo_siret';
	public const VAT_NUMBER   = '_billigoo_vat_number';

	// Generated invoice references.
	public const INVOICE_ID     = '_billigoo_invoice_id';
	public const INVOICE_NUMBER = '_billigoo_invoice_number';
	public const INVOICE_PATH   = '_billigoo_invoice_path';
	public const INVOICE_STATUS = '_billigoo_invoice_status';
	public const INVOICE_DATE   = '_billigoo_invoice_date';
	public const INVOICE_XML    = '_billigoo_invoice_xml';

	// Plateforme Agréée transmission state.
	public const PA_STATUS    = '_billigoo_pa_status';
	public const PA_PROVIDER  = '_billigoo_pa_provider';
	public const PA_REMOTE_ID = '_billigoo_pa_remote_id';
	public const PA_MESSAGE   = '_billigoo_pa_message';
	public const PA_SENT_AT   = '_billigoo_pa_sent_at';

	/**
	 * Whether the buyer flagged themselves as a professional.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function is_business( \WC_Order $order ): bool {
		return 'yes' === $order->get_meta( self::IS_BUSINESS );
	}

	/**
	 * Convenience getter for a meta value with default.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $key   Meta key constant.
	 * @param string    $default Fallback.
	 */
	public static function get( \WC_Order $order, string $key, string $default = '' ): string {
		$value = $order->get_meta( $key );
		return '' !== $value && null !== $value ? (string) $value : $default;
	}

	/**
	 * Whether an invoice has already been generated for this order.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function has_invoice( \WC_Order $order ): bool {
		return '' !== $order->get_meta( self::INVOICE_ID );
	}

	/**
	 * Store the B2B fields collected at checkout.
	 *
	 * @param \WC_Order            $order Order.
	 * @param array<string,string> $data  Keyed by company_name|siret|vat_number, plus is_business bool.
	 */
	public static function save_business_fields( \WC_Order $order, array $data ): void {
		$order->update_meta_data( self::IS_BUSINESS, ! empty( $data['is_business'] ) ? 'yes' : 'no' );
		$order->update_meta_data( self::COMPANY_NAME, sanitize_text_field( $data['company_name'] ?? '' ) );
		$order->update_meta_data( self::SIRET, preg_replace( '/\D/', '', $data['siret'] ?? '' ) );
		$order->update_meta_data( self::VAT_NUMBER, strtoupper( sanitize_text_field( $data['vat_number'] ?? '' ) ) );
		$order->save();
	}

	/**
	 * Persist the result of a successful generation onto the order.
	 *
	 * @param \WC_Order $order  Order.
	 * @param array     $result Result with keys: id, number, path, status, date, xml.
	 */
	public static function save_invoice( \WC_Order $order, array $result ): void {
		$order->update_meta_data( self::INVOICE_ID, (string) $result['id'] );
		$order->update_meta_data( self::INVOICE_NUMBER, (string) $result['number'] );
		$order->update_meta_data( self::INVOICE_PATH, (string) $result['path'] );
		$order->update_meta_data( self::INVOICE_STATUS, (string) $result['status'] );
		$order->update_meta_data( self::INVOICE_DATE, (string) $result['date'] );
		if ( isset( $result['xml'] ) ) {
			$order->update_meta_data( self::INVOICE_XML, (string) $result['xml'] );
		}
		$order->save();
	}

	/**
	 * Current normalised PA transmission status (default: not transmitted).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function pa_status( \WC_Order $order ): string {
		return self::get( $order, self::PA_STATUS, 'not_transmitted' );
	}

	/**
	 * Persist a PA transmission result on the order.
	 *
	 * @param \WC_Order            $order  Order.
	 * @param array<string,string> $result Keys: status, provider, remote_id, message.
	 */
	public static function save_pa_status( \WC_Order $order, array $result ): void {
		$order->update_meta_data( self::PA_STATUS, (string) ( $result['status'] ?? 'not_transmitted' ) );
		if ( isset( $result['provider'] ) ) {
			$order->update_meta_data( self::PA_PROVIDER, (string) $result['provider'] );
		}
		if ( isset( $result['remote_id'] ) ) {
			$order->update_meta_data( self::PA_REMOTE_ID, (string) $result['remote_id'] );
		}
		$order->update_meta_data( self::PA_MESSAGE, (string) ( $result['message'] ?? '' ) );
		$order->update_meta_data( self::PA_SENT_AT, gmdate( 'c' ) );
		$order->save();
	}
}
