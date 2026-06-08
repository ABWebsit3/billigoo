<?php
/**
 * Flat CSV export of invoices.
 *
 * @package Billigoo
 */

namespace Billigoo\Export;

use Billigoo\Admin\StatusBadge;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Produces a simple, spreadsheet-friendly CSV of invoiced orders for a period.
 */
final class CsvExporter {

	/**
	 * Build the CSV content (UTF-8 BOM, semicolon separated for FR Excel).
	 *
	 * @param string $after  Lower bound `Y-m-d`.
	 * @param string $before Upper bound `Y-m-d`.
	 * @return string
	 */
	public static function build( string $after, string $before ): string {
		$rows   = array();
		$rows[] = array(
			__( 'N° Facture', 'billigoo' ),
			__( 'Commande', 'billigoo' ),
			__( 'Date', 'billigoo' ),
			__( 'Client', 'billigoo' ),
			__( 'SIRET', 'billigoo' ),
			__( 'Total HT', 'billigoo' ),
			__( 'TVA', 'billigoo' ),
			__( 'Total TTC', 'billigoo' ),
			__( 'Statut PA', 'billigoo' ),
		);

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'orderby'      => 'date',
				'order'        => 'ASC',
				'date_created' => $after . '...' . $before,
				'meta_query'   => array(
					array(
						'key'     => OrderMeta::INVOICE_NUMBER,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$company = OrderMeta::get( $order, OrderMeta::COMPANY_NAME );
			$client  = '' !== $company ? $company : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$date    = $order->get_date_created();
			$ht      = (float) $order->get_total() - (float) $order->get_total_tax();

			$rows[] = array(
				OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ),
				(string) $order->get_order_number(),
				$date ? $date->date( 'Y-m-d' ) : '',
				$client,
				OrderMeta::get( $order, OrderMeta::SIRET ),
				number_format( $ht, 2, ',', '' ),
				number_format( (float) $order->get_total_tax(), 2, ',', '' ),
				number_format( (float) $order->get_total(), 2, ',', '' ),
				StatusBadge::label( OrderMeta::pa_status( $order ) ),
			);
		}

		$out = "\xEF\xBB\xBF";
		foreach ( $rows as $row ) {
			$out .= implode( ';', array_map( array( self::class, 'cell' ), $row ) ) . "\r\n";
		}
		return $out;
	}

	/**
	 * Quote a CSV cell.
	 *
	 * @param string $value Cell value.
	 */
	private static function cell( string $value ): string {
		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}
