<?php
/**
 * Branded Factur-X notice injected into the WooCommerce customer emails.
 *
 * @package Billigoo
 */

namespace Billigoo\WooCommerce;

use Billigoo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Votre facture est prête" block (Stitch email design) above the
 * order table of the customer-facing emails that carry the invoice.
 */
final class EmailNotice {

	/**
	 * Email IDs that should show the branded notice (mirrors EmailAttachment).
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
		add_action( 'woocommerce_email_before_order_table', array( $this, 'render' ), 5, 4 );
	}

	/**
	 * Output the notice for eligible HTML customer emails.
	 *
	 * @param mixed $order         Order object.
	 * @param bool  $sent_to_admin Whether the email targets the shop admin.
	 * @param bool  $plain_text    Whether the email is plain text.
	 * @param mixed $email         Email object.
	 */
	public function render( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		if ( $sent_to_admin || $plain_text || ! $order instanceof \WC_Order ) {
			return;
		}
		if ( ! Settings::get( 'attach_email' ) ) {
			return;
		}
		$email_id = $email instanceof \WC_Email ? $email->id : '';
		if ( ! in_array( $email_id, self::TARGET_EMAILS, true ) ) {
			return;
		}
		if ( ! OrderMeta::has_invoice( $order ) ) {
			return;
		}

		$number = OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER );
		$date   = $order->get_date_created();
		$disp   = $date ? $date->date_i18n( 'd/m/Y' ) : '';
		$total  = wp_strip_all_tags( $order->get_formatted_order_total() );

		// Inline styles only — required for email-client compatibility.
		?>
		<div style="margin:0 0 28px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-family:Inter,Arial,Helvetica,sans-serif;">
				<tr>
					<td style="background:#f5faff;border:1px solid #bec8d2;border-radius:12px;padding:28px 24px;text-align:center;">
						<div style="width:64px;height:64px;line-height:64px;border-radius:50%;background:#c9e6ff;display:inline-block;font-size:30px;">📄</div>
						<h1 style="margin:16px 0 6px;font-size:24px;font-weight:700;color:#161c20;"><?php esc_html_e( 'Votre facture est prête', 'billigoo' ); ?></h1>
						<p style="margin:0 auto;max-width:420px;font-size:14px;line-height:1.5;color:#3e4850;">
							<?php
							printf(
								/* translators: %s: "Factur-X" emphasised */
								esc_html__( 'Cette facture respecte le standard européen %s, garantissant sa conformité avec la réforme de la facturation électronique.', 'billigoo' ),
								'<strong style="color:#006591;">Factur-X</strong>'
							);
							?>
						</p>

						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:22px;background:#ffffff;border:1px solid #bec8d2;border-radius:12px;">
							<tr>
								<td style="padding:18px 20px;text-align:left;width:50%;">
									<div style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#3e4850;">N° <?php esc_html_e( 'Facture', 'billigoo' ); ?></div>
									<div style="font-size:16px;font-weight:600;color:#161c20;margin-top:2px;"><?php echo esc_html( $number ); ?></div>
								</td>
								<td style="padding:18px 20px;text-align:right;width:50%;">
									<div style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#3e4850;"><?php esc_html_e( 'Date d’émission', 'billigoo' ); ?></div>
									<div style="font-size:16px;color:#161c20;margin-top:2px;"><?php echo esc_html( $disp ); ?></div>
								</td>
							</tr>
							<tr>
								<td colspan="2" style="padding:16px 20px;border-top:1px solid #dde3e8;">
									<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
										<td style="text-align:left;vertical-align:bottom;">
											<div style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#3e4850;"><?php esc_html_e( 'Montant total TTC', 'billigoo' ); ?></div>
											<div style="font-size:28px;font-weight:700;color:#006591;letter-spacing:-.02em;margin-top:2px;"><?php echo esc_html( $total ); ?></div>
										</td>
										<td style="text-align:right;vertical-align:bottom;">
											<span style="display:inline-block;background:#dcfce7;color:#15803d;font-size:11px;font-weight:700;padding:5px 12px;border-radius:9999px;"><?php esc_html_e( 'CONFORME FACTUR-X', 'billigoo' ); ?></span>
										</td>
									</tr></table>
								</td>
							</tr>
						</table>

						<p style="margin:18px 0 0;font-size:12px;color:#6e7881;">📎 <?php esc_html_e( 'La facture PDF/A-3 (Factur-X) est jointe à cet email.', 'billigoo' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
