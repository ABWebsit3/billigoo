<?php
/**
 * Invoice generation orchestrator.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Drives the full pipeline: validate → allocate number → build XML → render PDF
 * → embed → store → persist on the order. Idempotent per order.
 */
final class InvoiceGenerator {

	/**
	 * Generate (or return the existing) invoice for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $force Regenerate even if one already exists (re-numbers).
	 * @return array<string,mixed> Result data.
	 *
	 * @throws \RuntimeException When validation fails or a stage errors.
	 */
	public function generate( \WC_Order $order, bool $force = false ): array {
		// 1. Idempotence.
		if ( ! $force && OrderMeta::has_invoice( $order ) ) {
			return array(
				'id'      => OrderMeta::get( $order, OrderMeta::INVOICE_ID ),
				'number'  => OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ),
				'path'    => OrderMeta::get( $order, OrderMeta::INVOICE_PATH ),
				'status'  => OrderMeta::get( $order, OrderMeta::INVOICE_STATUS ),
				'date'    => OrderMeta::get( $order, OrderMeta::INVOICE_DATE ),
				'existed' => true,
			);
		}

		// 2. Validate.
		$errors = InvoiceValidator::validate( $order );
		if ( ! empty( $errors ) ) {
			throw new \RuntimeException( 'Billigoo: ' . implode( ' ', $errors ) );
		}

		$date = $order->get_date_created() ? $order->get_date_created() : new \WC_DateTime();

		// 3. Allocate a legal sequential number.
		$number = InvoiceNumber::allocate( $date );

		// 4–6. Build data, XML, PDF, then assemble Factur-X.
		$data    = InvoiceData::from_order( $order, $number );
		$xml     = ( new FacturxBuilder() )->build( $data );
		$pdf     = ( new PdfBuilder() )->render( $data );
		$facturx = ( new PdfEmbedder() )->embed( $pdf, $xml );

		// 7. Store the file in the protected uploads dir.
		$abs_path = InvoiceStore::pdf_path( $number, $date );
		if ( ! InvoiceStore::write( $abs_path, $facturx ) ) {
			throw new \RuntimeException( 'Billigoo: could not write invoice file to ' . $abs_path );
		}

		// 8. Persist references on the order.
		$result = array(
			'id'     => $number,
			'number' => $number,
			'path'   => InvoiceStore::relative( $abs_path ),
			'status' => 'generated',
			'date'   => $date->format( DATE_ATOM ),
			'xml'    => $xml,
		);
		OrderMeta::save_invoice( $order, $result );

		$order->add_order_note(
			sprintf(
				/* translators: %s: invoice number */
				__( 'Facture Billigoo %s générée (Factur-X BASIC).', 'billigoo' ),
				$number
			)
		);

		/**
		 * Fires after a Factur-X invoice has been generated and stored.
		 *
		 * @param \WC_Order            $order  The order.
		 * @param array<string,mixed>  $result The generation result.
		 */
		do_action( 'billigoo_invoice_generated', $order, $result );

		return $result;
	}
}
