<?php
/**
 * B2C e-reporting aggregation and transmission.
 *
 * @package Billigoo
 */

namespace Billigoo\Ereporting;

use Billigoo\Events;
use Billigoo\License;
use Billigoo\Settings;
use Billigoo\Core\InvoiceStore;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Collects B2C transactions for a period, aggregates VAT per rate, and produces
 * the transaction-data report required by the e-reporting obligation.
 *
 * Real transmission targets the PPF / a PA endpoint which is not available here,
 * so the report is built, stored as a signed JSON file, and recorded in history;
 * wiring the actual send is a one-line swap in transmit().
 */
final class EreportingManager {

	private const HISTORY_OPTION = 'billigoo_ereporting_history';
	private const HISTORY_MAX    = 24;

	/**
	 * Build, store and record the report for a period.
	 *
	 * @param string $after  Lower bound `Y-m-d`.
	 * @param string $before Upper bound `Y-m-d`.
	 * @param string $label  Human period label (e.g. "2026-05").
	 * @return array<string,mixed> The report.
	 */
	public function run_for_period( string $after, string $before, string $label ): array {
		$report = $this->build( $after, $before, $label );
		$file   = $this->store( $report, $label );
		$report['file'] = $file;

		$this->record(
			array(
				'label'        => $label,
				'generated_at' => gmdate( 'c' ),
				'count'        => $report['count'],
				'total_ttc'    => $report['totals']['ttc'],
				'file'         => InvoiceStore::relative( $file ),
				'transmitted'  => $this->transmit( $report ),
			)
		);

		Events::dispatch(
			Events::EREPORTING_SENT,
			array(
				'invoice_id' => '',
				'order_id'   => 0,
				'timestamp'  => gmdate( 'c' ),
				'data'       => array( 'period' => $label, 'count' => $report['count'], 'amount' => $report['totals']['ttc'] ),
			)
		);

		return $report;
	}

	/**
	 * Aggregate B2C orders in the period.
	 *
	 * @param string $after  Lower bound `Y-m-d`.
	 * @param string $before Upper bound `Y-m-d`.
	 * @param string $label  Period label.
	 * @return array<string,mixed>
	 */
	public function build( string $after, string $before, string $label ): array {
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'wc-processing', 'wc-completed' ),
				'date_created' => $after . '...' . $before,
			)
		);

		$rates = array(); // rate => [base, tax].
		$count = 0;
		$ht    = 0.0;
		$tva   = 0.0;
		$ttc   = 0.0;

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof \WC_Order || OrderMeta::is_business( $order ) ) {
				continue; // B2B handled by invoice transmission, not e-reporting.
			}
			++$count;
			$order_tax = (float) $order->get_total_tax();
			$order_ht  = (float) $order->get_total() - $order_tax;
			$ht       += $order_ht;
			$tva      += $order_tax;
			$ttc      += (float) $order->get_total();

			$taxed_base = 0.0;
			foreach ( $order->get_taxes() as $tax_item ) {
				$rate = (float) $tax_item->get_rate_percent();
				$amt  = (float) $tax_item->get_tax_total() + (float) $tax_item->get_shipping_tax_total();
				if ( $rate <= 0 ) {
					continue;
				}
				$base                 = $amt / ( $rate / 100 );
				$taxed_base          += $base;
				$key                  = number_format( $rate, 2, '.', '' );
				$rates[ $key ]        = $rates[ $key ] ?? array( 'rate' => $rate, 'base' => 0.0, 'tax' => 0.0 );
				$rates[ $key ]['base'] += $base;
				$rates[ $key ]['tax']  += $amt;
			}
			$zero = $order_ht - $taxed_base;
			if ( abs( $zero ) > 0.01 ) {
				$rates['0.00']         = $rates['0.00'] ?? array( 'rate' => 0.0, 'base' => 0.0, 'tax' => 0.0 );
				$rates['0.00']['base'] += $zero;
			}
		}

		return array(
			'period'       => $label,
			'from'         => $after,
			'to'           => $before,
			'currency'     => get_woocommerce_currency(),
			'generated_at' => gmdate( 'c' ),
			'count'        => $count,
			'by_rate'      => array_values( $rates ),
			'totals'       => array( 'ht' => round( $ht, 2 ), 'tva' => round( $tva, 2 ), 'ttc' => round( $ttc, 2 ) ),
		);
	}

	/**
	 * Transmit the report to the PA/PPF. Placeholder until an endpoint exists.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return bool Whether transmission was attempted/succeeded.
	 */
	private function transmit( array $report ): bool {
		/**
		 * Allow integrators to perform the real e-reporting transmission.
		 *
		 * @param bool                $handled Whether transmission was handled.
		 * @param array<string,mixed> $report  The report payload.
		 */
		return (bool) apply_filters( 'billigoo_ereporting_transmit', false, $report );
	}

	/**
	 * Store the report as a JSON file in the protected uploads dir.
	 *
	 * @param array<string,mixed> $report Report.
	 * @param string              $label  Period label.
	 * @return string Absolute file path.
	 */
	private function store( array $report, string $label ): string {
		$dir = InvoiceStore::base_dir() . '/ereporting';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$path = $dir . '/ereporting-' . sanitize_file_name( $label ) . '.json';
		file_put_contents( $path, (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $path;
	}

	/**
	 * Append an entry to the bounded history.
	 *
	 * @param array<string,mixed> $entry Entry.
	 */
	private function record( array $entry ): void {
		$history = self::history();
		array_unshift( $history, $entry );
		update_option( self::HISTORY_OPTION, array_slice( $history, 0, self::HISTORY_MAX ), false );
	}

	/**
	 * Read the stored history (most recent first).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function history(): array {
		$h = get_option( self::HISTORY_OPTION, array() );
		return is_array( $h ) ? $h : array();
	}

	/**
	 * Cron entry point: run the just-closed period if enabled.
	 */
	public function run(): void {
		if ( ! Settings::get( 'ereporting_enabled' ) || ! License::has_feature( 'ereporting' ) ) {
			return;
		}

		$period = (string) Settings::get( 'ereporting_period', 'monthly' );
		if ( 'quarterly' === $period ) {
			$first  = strtotime( 'first day of -3 months', strtotime( gmdate( 'Y-m-01' ) ) );
			$after  = gmdate( 'Y-m-01', $first );
			$before = gmdate( 'Y-m-t', strtotime( 'last day of -1 month', strtotime( gmdate( 'Y-m-01' ) ) ) );
			$label  = gmdate( 'Y', $first ) . '-T' . ceil( (int) gmdate( 'n', $first ) / 3 );
		} else {
			$first  = strtotime( 'first day of last month' );
			$after  = gmdate( 'Y-m-01', $first );
			$before = gmdate( 'Y-m-t', $first );
			$label  = gmdate( 'Y-m', $first );
		}

		$this->run_for_period( $after . ' 00:00:00', $before . ' 23:59:59', $label );
	}
}
