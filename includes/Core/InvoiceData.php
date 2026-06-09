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

		// Build rate_id → rate_percent from order tax items so we use the exact
		// stored percentage (e.g. 20.00) instead of back-calculating from rounded
		// amounts (which yields e.g. 11.67/58.33*100 = 20.01).
		$order_rates = array();
		foreach ( $order->get_taxes() as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( $rate_id ) {
				$order_rates[ (int) $rate_id ] = (float) $tax_item->get_rate_percent();
			}
		}

		$lines      = array();
		$tax_groups = array();
		$line_total = 0.0;

		foreach ( self::collect_items( $order ) as $row ) {
			$qty  = $row['qty'] > 0 ? $row['qty'] : 1.0;
			$net  = (float) $row['net'];
			$tax  = (float) $row['tax'];
			$rate = self::resolve_rate( $row, $order_rates, $net, $tax );

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
	 * Includes the primary WC rate_id so the caller can resolve the exact rate%.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<int,array{name:string,qty:float,net:float,tax:float,rate_id:int}>
	 */
	private static function collect_items( \WC_Order $order ): array {
		$rows = array();

		foreach ( $order->get_items() as $item ) {
			$rows[] = array(
				'name'    => $item->get_name(),
				'qty'     => (float) $item->get_quantity(),
				'net'     => (float) $item->get_total(),
				'tax'     => (float) $item->get_total_tax(),
				'rate_id' => self::primary_rate_id( $item ),
			);
		}
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( 0.0 === (float) $item->get_total() ) {
				continue;
			}
			$rows[] = array(
				'name'    => $item->get_name() ?: __( 'Livraison', 'billigoo' ),
				'qty'     => 1.0,
				'net'     => (float) $item->get_total(),
				'tax'     => (float) $item->get_total_tax(),
				'rate_id' => self::primary_rate_id( $item ),
			);
		}
		foreach ( $order->get_items( 'fee' ) as $item ) {
			$rows[] = array(
				'name'    => $item->get_name(),
				'qty'     => 1.0,
				'net'     => (float) $item->get_total(),
				'tax'     => (float) $item->get_total_tax(),
				'rate_id' => self::primary_rate_id( $item ),
			);
		}

		return $rows;
	}

	/**
	 * Return the first WC tax rate_id applied to an item (0 when none).
	 *
	 * @param \WC_Order_Item $item Order item.
	 */
	private static function primary_rate_id( \WC_Order_Item $item ): int {
		$taxes   = $item->get_taxes();
		$rate_ids = array_keys( $taxes['total'] ?? array() );
		return (int) ( $rate_ids[0] ?? 0 );
	}

	/**
	 * Resolve the tax rate percentage for a row.
	 * Uses the stored WC rate (exact) when available; falls back to back-calculation
	 * only for manual/legacy orders without a rate_id.
	 *
	 * @param array<string,mixed> $row         Row from collect_items.
	 * @param array<int,float>    $order_rates Map rate_id → rate_percent.
	 * @param float               $net         Item net total.
	 * @param float               $tax         Item tax total.
	 */
	private static function resolve_rate( array $row, array $order_rates, float $net, float $tax ): float {
		$rate_id = (int) ( $row['rate_id'] ?? 0 );
		if ( $rate_id > 0 && isset( $order_rates[ $rate_id ] ) ) {
			return (float) $order_rates[ $rate_id ];
		}
		return 0.0 !== $net ? round( $tax / $net * 100, 2 ) : 0.0;
	}
}
