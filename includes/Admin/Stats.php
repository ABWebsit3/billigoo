<?php
/**
 * Lightweight dashboard statistics derived from invoiced orders.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates Phase-1 invoice figures for the dashboard metric cards.
 *
 * All figures are derived from WooCommerce orders carrying a Billigoo invoice
 * number; there is no separate ledger to keep in sync.
 */
final class Stats {

	/**
	 * Number of invoices generated within a date range.
	 *
	 * @param string $after  Inclusive lower bound, `Y-m-d H:i:s`.
	 * @param string $before Inclusive upper bound, `Y-m-d H:i:s`.
	 */
	public static function count_between( string $after, string $before ): int {
		$ids = wc_get_orders(
			array(
				'limit'        => -1,
				'return'       => 'ids',
				'date_created' => $after . '...' . $before,
				'meta_query'   => array(
					array(
						'key'     => OrderMeta::INVOICE_NUMBER,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Total turnover (TTC) invoiced within a date range.
	 *
	 * @param string $after  Inclusive lower bound, `Y-m-d H:i:s`.
	 * @param string $before Inclusive upper bound, `Y-m-d H:i:s`.
	 */
	public static function turnover_between( string $after, string $before ): float {
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'date_created' => $after . '...' . $before,
				'meta_query'   => array(
					array(
						'key'     => OrderMeta::INVOICE_NUMBER,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$total = 0.0;
		foreach ( (array) $orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$total += (float) $order->get_total();
			}
		}
		return $total;
	}

	/**
	 * All-time count of generated invoices.
	 */
	public static function count_total(): int {
		$ids = wc_get_orders(
			array(
				'limit'      => -1,
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => OrderMeta::INVOICE_NUMBER,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Bundle of figures used by the dashboard, computed once per request.
	 *
	 * @return array{this_month:int,last_month:int,trend:?int,turnover:float,total:int}
	 */
	public static function summary(): array {
		$start_this = gmdate( 'Y-m-01 00:00:00' );
		$end_this   = gmdate( 'Y-m-t 23:59:59' );
		$start_last = gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
		$end_last   = gmdate( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );

		$this_month = self::count_between( $start_this, $end_this );
		$last_month = self::count_between( $start_last, $end_last );

		$trend = null;
		if ( $last_month > 0 ) {
			$trend = (int) round( ( ( $this_month - $last_month ) / $last_month ) * 100 );
		} elseif ( $this_month > 0 ) {
			$trend = 100;
		}

		return array(
			'this_month' => $this_month,
			'last_month' => $last_month,
			'trend'      => $trend,
			'turnover'   => self::turnover_between( $start_this, $end_this ),
			'total'      => self::count_total(),
		);
	}

	/**
	 * Format a monetary amount the French way: `1 440,00 €`.
	 *
	 * @param float $amount Amount.
	 */
	public static function money( float $amount ): string {
		// Non-breaking spaces for thousands and before the symbol.
		return number_format( $amount, 2, ',', "\xc2\xa0" ) . "\xc2\xa0€";
	}
}
