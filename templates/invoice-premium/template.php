<?php
/**
 * Premium invoice PDF template (rendered by mPDF).
 *
 * Receives the same $data array as the default template
 * (see Billigoo\Core\InvoiceData::from_order()) plus $data['branding'].
 *
 * @package Billigoo
 * @var array<string,mixed> $data
 */

defined( 'ABSPATH' ) || exit;

$bg_seller   = $data['seller'];
$bg_buyer    = $data['buyer'];
$bg_totals   = $data['totals'];
$bg_currency = $data['currency'];
$bg_brand    = $data['branding'] ?? array( 'color' => '#0ea5e9', 'logo' => '' );
$bg_accent   = preg_match( '/^#[0-9a-fA-F]{3,6}$/', (string) $bg_brand['color'] ) ? $bg_brand['color'] : '#0ea5e9';

$bg_money = static function ( $amount, $currency ) {
	return number_format( (float) $amount, 2, ',', ' ' ) . ' ' . $currency;
};

$bg_style = file_get_contents( __DIR__ . '/style.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
$bg_style = str_replace( 'var(--accent, #0ea5e9)', esc_attr( $bg_accent ), $bg_style );
?>
<style><?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>

<?php if ( '' !== (string) $bg_brand['logo'] ) : ?>
	<img class="logo" src="<?php echo esc_url( $bg_brand['logo'] ); ?>" alt="" />
<?php endif; ?>

<div class="band">
	<h1>FACTURE</h1>
	<div class="meta">
		<?php echo esc_html( $data['invoice_number'] ); ?> &nbsp;·&nbsp;
		<?php echo esc_html( $data['issue_date_disp'] ); ?> &nbsp;·&nbsp;
		<?php esc_html_e( 'Commande', 'billigoo' ); ?> <?php echo esc_html( (string) $data['buyer_reference'] ); ?>
	</div>
</div>

<table class="parties">
	<tr>
		<td>
			<div class="party-label"><?php esc_html_e( 'Émetteur', 'billigoo' ); ?></div>
			<div class="party-name"><?php echo esc_html( $bg_seller['name'] ); ?></div>
			<div><?php echo esc_html( $bg_seller['address'] ); ?></div>
			<div><?php echo esc_html( trim( $bg_seller['postcode'] . ' ' . $bg_seller['city'] ) ); ?></div>
			<?php if ( '' !== $bg_seller['siret'] ) : ?><div>SIRET : <?php echo esc_html( $bg_seller['siret'] ); ?></div><?php endif; ?>
			<?php if ( '' !== $bg_seller['vat'] ) : ?><div>TVA : <?php echo esc_html( $bg_seller['vat'] ); ?></div><?php endif; ?>
		</td>
		<td>
			<div class="party-label"><?php esc_html_e( 'Facturé à', 'billigoo' ); ?></div>
			<div class="party-name"><?php echo esc_html( $bg_buyer['name'] ); ?></div>
			<div><?php echo esc_html( $bg_buyer['address'] ); ?></div>
			<div><?php echo esc_html( trim( $bg_buyer['postcode'] . ' ' . $bg_buyer['city'] ) ); ?></div>
			<?php if ( '' !== $bg_buyer['siret'] ) : ?><div>SIRET : <?php echo esc_html( $bg_buyer['siret'] ); ?></div><?php endif; ?>
			<?php if ( '' !== $bg_buyer['vat'] ) : ?><div>TVA : <?php echo esc_html( $bg_buyer['vat'] ); ?></div><?php endif; ?>
		</td>
	</tr>
</table>

<table class="lines">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Désignation', 'billigoo' ); ?></th>
			<th class="num"><?php esc_html_e( 'Qté', 'billigoo' ); ?></th>
			<th class="num"><?php esc_html_e( 'PU HT', 'billigoo' ); ?></th>
			<th class="num"><?php esc_html_e( 'TVA', 'billigoo' ); ?></th>
			<th class="num"><?php esc_html_e( 'Montant HT', 'billigoo' ); ?></th>
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
		<td><?php esc_html_e( 'Total HT', 'billigoo' ); ?></td>
		<td class="num"><?php echo esc_html( $bg_money( $bg_totals['line'], $bg_currency ) ); ?></td>
	</tr>
	<?php foreach ( $data['tax_groups'] as $bg_group ) : ?>
		<tr>
			<td>TVA <?php echo esc_html( number_format( (float) $bg_group['rate'], 2, ',', ' ' ) ); ?> %</td>
			<td class="num"><?php echo esc_html( $bg_money( $bg_group['tax'], $bg_currency ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	<tr class="grand">
		<td><?php esc_html_e( 'Total TTC', 'billigoo' ); ?></td>
		<td class="num"><?php echo esc_html( $bg_money( $bg_totals['grand'], $bg_currency ) ); ?></td>
	</tr>
</table>

<?php if ( '' !== $data['payment_terms'] ) : ?>
	<div class="payment-terms"><strong><?php esc_html_e( 'Conditions de paiement :', 'billigoo' ); ?></strong> <?php echo esc_html( $data['payment_terms'] ); ?></div>
<?php endif; ?>

<div class="signature"><?php esc_html_e( 'Signature / cachet', 'billigoo' ); ?></div>

<div class="legal-mention">
	<?php
	printf(
		/* translators: %s: Factur-X profile */
		esc_html__( 'Facture électronique conforme Factur-X — Profil %s.', 'billigoo' ),
		esc_html( $data['profile'] )
	);
	if ( '' !== (string) ( $data['legal_mentions'] ?? '' ) ) {
		echo '<br>' . esc_html( $data['legal_mentions'] );
	}
	?>
</div>
