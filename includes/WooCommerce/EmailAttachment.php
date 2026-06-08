<?php
/**
 * Attaches the generated Factur-X PDF to WooCommerce emails.
 *
 * @package Billigoo
 */

namespace Billigoo\WooCommerce;

use Billigoo\Settings;
use Billigoo\Core\InvoiceStore;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the invoice PDF as an attachment to the customer-facing order emails.
 */
final class EmailAttachment {

	/**
	 * Email IDs that should carry the invoice.
	 *
	 * @var array<int,string>
	 */
	private const TARGET_EMAILS = array(
		'customer_processing_order',
		'customer_completed_order',
		'customer_invoice',
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach' ), 10, 4 );
	}

	/**
	 * Append the invoice PDF path to the email attachments.
	 *
	 * @param array     $attachments Existing attachment paths.
	 * @param string    $email_id    Email identifier.
	 * @param mixed     $order       Order object (or other resource).
	 * @param mixed     $email       Email object.
	 * @return array
	 */
	public function attach( $attachments, $email_id, $order, $email = null ): array {
		$attachments = is_array( $attachments ) ? $attachments : array();

		if ( ! Settings::get( 'attach_email' ) ) {
			return $attachments;
		}
		if ( ! in_array( $email_id, self::TARGET_EMAILS, true ) ) {
			return $attachments;
		}
		if ( ! $order instanceof \WC_Order ) {
			return $attachments;
		}

		$relative = OrderMeta::get( $order, OrderMeta::INVOICE_PATH );
		if ( '' === $relative ) {
			return $attachments;
		}

		$path = InvoiceStore::resolve( $relative );
		if ( $path && is_readable( $path ) ) {
			$attachments[] = $path;
		}

		return $attachments;
	}
}
