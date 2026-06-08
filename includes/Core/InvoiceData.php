<?php
/**
 * Normalised invoice data extracted from a WooCommerce order.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Billigoo\Settings;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth shared by the XML builder and the PDF renderer, so the
 * visual invoice and the embedded Factur-X data can never disagree.
 */
final class InvoiceData {

	/**
	 * Build the normalised data array from an order.
	 *
	 * @param \WC_Order $order          Order.
	 * @param string    $invoice_number Allocated invoice number.
	 * @return array<string,mixed>
	 */
	public static function from_order( \WC_Order $order, string $invoice_number ): array {
		$seller = Settings::all();
		$date   = $order->get_date_created() ? $order->get_date_created() : new \WC_DateTime();

		$buyer_company = OrderMeta::get( $order, OrderMeta::COMPANY_NAME );
		$buyer_name    = '' !== $buyer_company
			? $buyer_company
			: trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$buyer_siret = OrderMeta::get( $order, OrderMeta::SIRET );

		$lines      = array();
		$tax_groups = array();
		$line_total = 0.0;

		foreach ( self::collect_items( $order ) as $row ) {
			$qty      = $row['qty'] > 0 ? $row['qty'] : 1.0;
			$net      = (float) $row['net'];
			$tax      = (float) $row['tax'];
			$rate     = 0.0 !== $net ? round( $tax / $net * 100, 2 ) : 0.0;
			$category = $rate > 0 ? 'S' : 'Z';
			$rate_key = $category . '|' . number_format( $rate, 2, '.', '' );

			$lines[] = array(
				'name'     => $row['name'],
				'qty'      => $qty,
				'unit_net' => $net / $qty,
				'net'      => $net,
				'tax'      => $tax,
				'rate'     => $rate,
				'category' => $category,
			);

			$line_total += $net;

			if ( ! isset( $tax_groups[ $rate_key ] ) ) {
				$tax_groups[ $rate_key ] = array(
					'category' => $category,
					'rate'     => $rate,
					'basis'    => 0.0,
					'tax'      => 0.0,
				);
			}
			$tax_groups[ $rate_key ]['basis'] += $net;
			$tax_groups[ $rate_key ]['tax']   += $tax;
		}

		return array(
			'invoice_number'  => $invoice_number,
			'issue_date'      => $date->format( 'Ymd' ),
			'issue_date_disp' => $date->format( 'd/m/Y' ),
			'currency'        => $order->get_currency(),
			'buyer_reference' => $order->get_order_number(),
			'order_id'        => $order->get_id(),
			'seller'          => array(
				'name'     => $seller['seller_name'],
				'siren'    => $seller['seller_siren'],
				'siret'    => $seller['seller_siret'],
				'vat'      => $seller['seller_vat'],
				'address'  => $seller['seller_address'],
				'postcode' => $seller['seller_postcode'],
				'city'     => $seller['seller_city'],
				'country'  => $seller['seller_country'] ?: 'FR',
			),
			'buyer'           => array(
				'name'     => $buyer_name,
				'siret'    => $buyer_siret,
				'siren'    => strlen( $buyer_siret ) >= 9 ? substr( $buyer_siret, 0, 9 ) : '',
				'vat'      => OrderMeta::get( $order, OrderMeta::VAT_NUMBER ),
				'address'  => trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
				'postcode' => $order->get_billing_postcode(),
				'city'     => $order->get_billing_city(),
				'country'  => $order->get_billing_country() ?: 'FR',
			),
			'lines'           => $lines,
			'tax_groups'      => array_values( $tax_groups ),
			'payment_terms'   => (string) $seller['payment_terms'],
			'profile'         => 'en16931' === ( $seller['profile'] ?? 'basic' ) ? 'EN 16931' : 'BASIC',
			'legal_mentions'  => (string) ( $seller['legal_mentions'] ?? '' ),
			'branding'        => array(
				'logo'    => (string) ( $seller['logo_url'] ?? '' ),
				'color'   => (string) ( $seller['primary_color'] ?? '#0ea5e9' ),
				'template'=> (string) ( $seller['template'] ?? 'default' ),
			),
			'totals'          => array(
				'line'  => $line_total,
				'basis' => $line_total,
				'tax'   => (float) $order->get_total_tax(),
				'grand' => (float) $order->get_total(),
				'due'   => (float) $order->get_total(),
			),
		);
	}

	/**
	 * Flatten products, shipping and fees into billable rows.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<int,array{name:string,qty:float,net:float,tax:float}>
	 */
	private static function collect_items( \WC_Order $order ): array {
		$rows = array();

		foreach ( $order->get_items() as $item ) {
			$rows[] = array(
				'name' => $item->get_name(),
				'qty'  => (float) $item->get_quantity(),
				'net'  => (float) $item->get_total(),
				'tax'  => (float) $item->get_total_tax(),
			);
		}
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( 0.0 === (float) $item->get_total() ) {
				continue;
			}
			$rows[] = array(
				'name' => $item->get_name() ?: __( 'Livraison', 'billigoo' ),
				'qty'  => 1.0,
				'net'  => (float) $item->get_total(),
				'tax'  => (float) $item->get_total_tax(),
			);
		}
		foreach ( $order->get_items( 'fee' ) as $item ) {
			$rows[] = array(
				'name' => $item->get_name(),
				'qty'  => 1.0,
				'net'  => (float) $item->get_total(),
				'tax'  => (float) $item->get_total_tax(),
			);
		}

		return $rows;
	}
}
