<?php
/**
 * Default invoice PDF template (rendered by mPDF).
 *
 * Receives a single $data array (see Billigoo\Core\InvoiceData::from_order()).
 * Escaping note: this HTML is rendered to a PDF, never sent to a browser, but we
 * still escape all dynamic values defensively.
 *
 * @package Billigoo
 * @var array<string,mixed> $data
 */

defined( 'ABSPATH' ) || exit;

$bg_seller   = $data['seller'];
$bg_buyer    = $data['buyer'];
$bg_totals   = $data['totals'];
$bg_currency = $data['currency'];

/**
 * Local money formatter for the template.
 *
 * @param float  $amount   Amount.
 * @param string $currency Currency code.
 */
$bg_money = static function ( $amount, $currency ) {
	return number_format( (float) $amount, 2, ',', ' ' ) . ' ' . $currency;
};

$bg_style = file_get_contents( __DIR__ . '/style.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
?>
<style><?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>

<div class="header">
	<div class="seller">
		<div class="seller-name"><?php echo esc_html( $bg_seller['name'] ); ?></div>
		<div><?php echo esc_html( $bg_seller['address'] ); ?></div>
		<div><?php echo esc_html( trim( $bg_seller['postcode'] . ' ' . $bg_seller['city'] ) ); ?></div>
		<?php if ( '' !== $bg_seller['siret'] ) : ?>
			<div>SIRET : <?php echo esc_html( $bg_seller['siret'] ); ?></div>
		<?php endif; ?>
		<?php if ( '' !== $bg_seller['vat'] ) : ?>
			<div>TVA : <?php echo esc_html( $bg_seller['vat'] ); ?></div>
		<?php endif; ?>
	</div>
	<div class="doc-title">
		<h1>FACTURE</h1>
		<div class="invoice-number"><?php echo esc_html( $data['invoice_number'] ); ?></div>
		<div>Date : <?php echo esc_html( $data['issue_date_disp'] ); ?></div>
		<div>Commande : <?php echo esc_html( (string) $data['buyer_reference'] ); ?></div>
	</div>
</div>

<div class="buyer">
	<div class="buyer-label">Facturé à</div>
	<div class="buyer-name"><?php echo esc_html( $bg_buyer['name'] ); ?></div>
	<div><?php echo esc_html( $bg_buyer['address'] ); ?></div>
	<div><?php echo esc_html( trim( $bg_buyer['postcode'] . ' ' . $bg_buyer['city'] ) ); ?></div>
	<?php if ( '' !== $bg_buyer['siret'] ) : ?>
		<div>SIRET : <?php echo esc_html( $bg_buyer['siret'] ); ?></div>
	<?php endif; ?>
	<?php if ( '' !== $bg_buyer['vat'] ) : ?>
		<div>TVA : <?php echo esc_html( $bg_buyer['vat'] ); ?></div>
	<?php endif; ?>
</div>

<table class="lines">
	<thead>
		<tr>
			<th class="col-desc">Désignation</th>
			<th class="col-qty">Qté</th>
			<th class="col-pu">PU HT</th>
			<th class="col-vat">TVA</th>
			<th class="col-total">Montant HT</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $data['lines'] as $bg_line ) : ?>
			<tr>
				<td><?php echo esc_html( $bg_line['name'] ); ?></td>
				<td class="num"><?php echo esc_html( rtrim( rtrim( number_format( (float) $bg_line['qty'], 2, ',', ' ' ), '0' ), ',' ) ); ?></td>
				<td class="num"><?php echo esc_html( $bg_money( $bg_line['unit_net'], $bg_currency ) ); ?></td>
				<td class="num"><?php echo esc_html( number_format( (float) $bg_line['rate'], 2, ',', ' ' ) ); ?> %</td>
				<td class="num"><?php echo esc_html( $bg_money( $bg_line['net'], $bg_currency ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<table class="totals">
	<tr>
		<td class="label">Total HT</td>
		<td class="num"><?php echo esc_html( $bg_money( $bg_totals['line'], $bg_currency ) ); ?></td>
	</tr>
	<?php foreach ( $data['tax_groups'] as $bg_group ) : ?>
		<tr>
			<td class="label">TVA <?php echo esc_html( number_format( (float) $bg_group['rate'], 2, ',', ' ' ) ); ?> %</td>
			<td class="num"><?php echo esc_html( $bg_money( $bg_group['tax'], $bg_currency ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	<tr class="grand">
		<td class="label">Total TTC</td>
		<td class="num"><?php echo esc_html( $bg_money( $bg_totals['grand'], $bg_currency ) ); ?></td>
	</tr>
</table>

<?php if ( '' !== $data['payment_terms'] ) : ?>
	<div class="payment-terms">
		<strong>Conditions de paiement :</strong> <?php echo esc_html( $data['payment_terms'] ); ?>
	</div>
<?php endif; ?>

<div class="legal-mention">
	Facture électronique conforme Factur-X — Profil <?php echo esc_html( $data['profile'] ); ?>.
	<?php if ( '' !== (string) ( $data['legal_mentions'] ?? '' ) ) : ?>
		<br><?php echo esc_html( $data['legal_mentions'] ); ?>
	<?php endif; ?>
</div>
