<?php
/**
 * FEC (Fichier des Écritures Comptables) export.
 *
 * @package Billigoo
 */

namespace Billigoo\Export;

use Billigoo\Settings;
use Billigoo\Core\InvoiceData;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Builds an A47 A-1 compliant FEC file from invoiced orders in a period.
 *
 * Per invoice we write a balanced set of entries: a client debit for the TTC
 * amount, sales credits per HT tax-group, and VAT credits per group, so that
 * total debit == total credit for every écriture.
 */
final class FecExporter {

	/**
	 * FEC mandatory columns (order matters).
	 *
	 * @var array<int,string>
	 */
	private const COLUMNS = array(
		'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib',
		'CompAuxNum', 'CompAuxLib', 'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit',
		'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise',
	);

	/**
	 * Build the FEC content for a date range (UTF-8 with BOM, pipe-separated).
	 *
	 * @param string $after  Lower bound `Y-m-d`.
	 * @param string $before Upper bound `Y-m-d`.
	 * @return string
	 */
	public static function build( string $after, string $before ): string {
		$journal_code = (string) Settings::get( 'fec_journal_code', 'VT' );
		$journal_lib  = (string) Settings::get( 'fec_journal_lib', 'Ventes' );
		$acc_client   = (string) Settings::get( 'fec_account_client', '411000' );
		$acc_sales    = (string) Settings::get( 'fec_account_sales', '707000' );
		$acc_vat      = (string) Settings::get( 'fec_account_vat', '445710' );

		$lines   = array();
		$lines[] = implode( "\t", self::COLUMNS );

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

		$opts = array(
			'journal_code' => $journal_code,
			'journal_lib'  => $journal_lib,
			'acc_client'   => $acc_client,
			'acc_sales'    => $acc_sales,
			'acc_vat'      => $acc_vat,
		);

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$number = OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER );
			$data   = InvoiceData::from_order( $order, $number );
			foreach ( self::rows_for_data( $data, $opts ) as $row ) {
				$lines[] = implode( "\t", $row );
			}
		}

		// UTF-8 BOM + CRLF line endings (FEC convention).
		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Build the balanced écriture rows for one invoice's normalised data.
	 *
	 * Pure function (no WooCommerce dependency) so the balancing logic can be
	 * unit-tested directly. For every invoice total debit == total credit.
	 *
	 * @param array<string,mixed> $data Normalised invoice data (see InvoiceData).
	 * @param array<string,string> $opts Keys: journal_code, journal_lib, acc_client, acc_sales, acc_vat.
	 * @return array<int,array<int,string>>
	 */
	public static function rows_for_data( array $data, array $opts ): array {
		$journal_code = $opts['journal_code'] ?? 'VT';
		$journal_lib  = $opts['journal_lib'] ?? 'Ventes';
		$acc_client   = $opts['acc_client'] ?? '411000';
		$acc_sales    = $opts['acc_sales'] ?? '707000';
		$acc_vat      = $opts['acc_vat'] ?? '445710';

		$number    = (string) $data['invoice_number'];
		$ymd       = (string) ( $data['issue_date'] ?? gmdate( 'Ymd' ) );
		$buyer     = $data['buyer'];
		$buyer_ref = '' !== (string) $buyer['siret'] ? (string) $buyer['siret'] : (string) ( $data['order_id'] ?? '' );
		$lib       = sprintf( 'Facture %s', $number );

		$rows = array();

		// Client debit for the full TTC amount.
		$rows[] = self::row(
			$journal_code, $journal_lib, $number, $ymd,
			$acc_client, 'Clients', $buyer_ref, $buyer['name'],
			$number, $ymd, $lib,
			(float) $data['totals']['grand'], 0.0
		);

		// Sales credit per tax-group (HT basis).
		foreach ( $data['tax_groups'] as $group ) {
			if ( (float) $group['basis'] <= 0 ) {
				continue;
			}
			$rows[] = self::row(
				$journal_code, $journal_lib, $number, $ymd,
				$acc_sales, 'Ventes', '', '',
				$number, $ymd, $lib,
				0.0, (float) $group['basis']
			);
		}

		// VAT credit per tax-group.
		foreach ( $data['tax_groups'] as $group ) {
			if ( (float) $group['tax'] <= 0 ) {
				continue;
			}
			$rows[] = self::row(
				$journal_code, $journal_lib, $number, $ymd,
				$acc_vat, sprintf( 'TVA collectée %s%%', rtrim( rtrim( number_format( (float) $group['rate'], 2, '.', '' ), '0' ), '.' ) ), '', '',
				$number, $ymd, $lib,
				0.0, (float) $group['tax']
			);
		}

		return $rows;
	}

	/**
	 * Assemble one FEC row in column order.
	 *
	 * @param string $journal_code Journal code.
	 * @param string $journal_lib  Journal label.
	 * @param string $num          Écriture number.
	 * @param string $date         Écriture date (Ymd).
	 * @param string $compte       Account number.
	 * @param string $compte_lib   Account label.
	 * @param string $aux_num      Aux account number.
	 * @param string $aux_lib      Aux account label.
	 * @param string $piece_ref    Piece reference.
	 * @param string $piece_date   Piece date (Ymd).
	 * @param string $lib          Entry label.
	 * @param float  $debit        Debit amount.
	 * @param float  $credit       Credit amount.
	 * @return array<int,string>
	 */
	private static function row( string $journal_code, string $journal_lib, string $num, string $date, string $compte, string $compte_lib, string $aux_num, string $aux_lib, string $piece_ref, string $piece_date, string $lib, float $debit, float $credit ): array {
		return array(
			self::clean( $journal_code ),
			self::clean( $journal_lib ),
			self::clean( $num ),
			$date,
			self::clean( $compte ),
			self::clean( $compte_lib ),
			self::clean( $aux_num ),
			self::clean( $aux_lib ),
			self::clean( $piece_ref ),
			$piece_date,
			self::clean( $lib ),
			self::amount( $debit ),
			self::amount( $credit ),
			'',          // EcritureLet
			'',          // DateLet
			$date,       // ValidDate
			'',          // Montantdevise
			'',          // Idevise
		);
	}

	/**
	 * Format an amount FEC-style: 2 decimals, comma separator, no thousands.
	 *
	 * @param float $amount Amount.
	 */
	private static function amount( float $amount ): string {
		return number_format( $amount, 2, ',', '' );
	}

	/**
	 * Strip tab/newline characters that would corrupt the columnar layout.
	 *
	 * @param string $value Value.
	 */
	private static function clean( string $value ): string {
		return trim( str_replace( array( "\t", "\r", "\n" ), ' ', $value ) );
	}
}
