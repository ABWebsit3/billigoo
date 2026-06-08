<?php
/**
 * Invoices list admin page.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\Settings;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the list of invoiced orders as a design-system card with a detail
 * slide-over, plus a short compliance summary.
 */
final class InvoicesPage {

	private const PER_PAGE = 25;

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['pa_status'] ) ? sanitize_key( wp_unslash( $_GET['pa_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$meta_query = array(
			array(
				'key'     => OrderMeta::INVOICE_NUMBER,
				'compare' => 'EXISTS',
			),
		);
		if ( '' !== $status && array_key_exists( $status, StatusBadge::filterable() ) ) {
			if ( 'not_transmitted' === $status ) {
				// Untransmitted = no PA status meta yet, or explicitly not transmitted.
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => OrderMeta::PA_STATUS, 'compare' => 'NOT EXISTS' ),
					array( 'key' => OrderMeta::PA_STATUS, 'value' => 'not_transmitted' ),
				);
			} else {
				$meta_query[] = array( 'key' => OrderMeta::PA_STATUS, 'value' => $status );
			}
		}

		$args = array(
			'limit'      => self::PER_PAGE,
			'paged'      => $paged,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'paginate'   => true,
			'meta_query' => $meta_query,
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = wc_get_orders( $args );
		$orders = $query->orders;

		$actions  = '<a class="billigoo-btn billigoo-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=billigoo' ) ) . '">';
		$actions .= '<span class="material-symbols-outlined">dashboard</span>' . esc_html__( 'Tableau de bord', 'billigoo' ) . '</a>';
		?>
		<div class="wrap billigoo-app">
			<?php Ui::header( __( 'Factures', 'billigoo' ), $actions ); ?>

			<?php if ( ! Settings::is_seller_configured() ) : ?>
				<div class="billigoo-alert billigoo-alert--warn">
					<p class="billigoo-alert__msg">
						<?php Ui::icon( 'warning', true ); ?>
						<?php esc_html_e( 'L’identité du vendeur n’est pas complète : aucune nouvelle facture ne sera générée.', 'billigoo' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-setup' ) ); ?>"><?php esc_html_e( 'Configurer →', 'billigoo' ); ?></a>
				</div>
			<?php endif; ?>

			<section class="billigoo-card">
				<div class="billigoo-card__head">
					<h2>
						<?php
						printf(
							/* translators: %d: number of invoices */
							esc_html( _n( '%d facture', '%d factures', (int) $query->total, 'billigoo' ) ),
							(int) $query->total
						);
						?>
					</h2>
					<form method="get" style="display:flex;gap:8px;align-items:center;">
						<input type="hidden" name="page" value="billigoo-invoices" />
						<select name="pa_status" style="padding:7px 10px;border:1px solid var(--bg-outline-variant);border-radius:6px;font-size:13px;">
							<option value=""><?php esc_html_e( 'Tous les statuts PA', 'billigoo' ); ?></option>
							<?php foreach ( StatusBadge::filterable() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher…', 'billigoo' ); ?>"
							style="padding:7px 12px;border:1px solid var(--bg-outline-variant);border-radius:6px;font-size:13px;" />
						<button type="submit" class="billigoo-btn billigoo-btn--ghost billigoo-btn--sm"><?php Ui::icon( 'search' ); ?></button>
					</form>
				</div>

				<?php if ( empty( $orders ) ) : ?>
					<div class="billigoo-card__body">
						<p class="billigoo-field__help"><?php esc_html_e( 'Aucune facture générée pour le moment.', 'billigoo' ); ?></p>
					</div>
				<?php else : ?>
					<table class="billigoo-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'N° Facture', 'billigoo' ); ?></th>
								<th><?php esc_html_e( 'Commande', 'billigoo' ); ?></th>
								<th><?php esc_html_e( 'Client', 'billigoo' ); ?></th>
								<th class="is-amount"><?php esc_html_e( 'Montant TTC', 'billigoo' ); ?></th>
								<th><?php esc_html_e( 'Date', 'billigoo' ); ?></th>
								<th><?php esc_html_e( 'Statut PA', 'billigoo' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $orders as $order ) : ?>
								<?php
								if ( ! $order instanceof \WC_Order ) {
									continue;
								}
								$this->row( $order );
								?>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php $this->pagination( $paged, (int) $query->max_num_pages, $search ); ?>
				<?php endif; ?>
			</section>
		</div>

		<?php $this->slideover(); ?>
		<?php
	}

	/**
	 * Render one invoice row carrying the data the slide-over reads.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function row( \WC_Order $order ): void {
		$company = OrderMeta::get( $order, OrderMeta::COMPANY_NAME );
		$client  = '' !== $company ? $company : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$siret   = OrderMeta::get( $order, OrderMeta::SIRET );
		$date    = $order->get_date_created();
		$disp    = $date ? $date->date_i18n( 'd/m/Y' ) : '—';
		$total   = Stats::money( (float) $order->get_total() );

		$pdf = wp_nonce_url(
			admin_url( 'admin-post.php?action=billigoo_download_pdf&order_id=' . $order->get_id() ),
			'billigoo_download_' . $order->get_id()
		);
		$xml = wp_nonce_url(
			admin_url( 'admin-post.php?action=billigoo_download_xml&order_id=' . $order->get_id() ),
			'billigoo_download_' . $order->get_id()
		);
		$transmit = wp_nonce_url(
			admin_url( 'admin-post.php?action=billigoo_transmit&order_id=' . $order->get_id() ),
			'billigoo_transmit_' . $order->get_id()
		);
		$pa_status = OrderMeta::pa_status( $order );
		?>
		<tr class="billigoo-row"
			data-number="<?php echo esc_attr( OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ) ); ?>"
			data-order="<?php echo esc_attr( $order->get_order_number() ); ?>"
			data-client="<?php echo esc_attr( $client ); ?>"
			data-siret="<?php echo esc_attr( $siret ); ?>"
			data-date="<?php echo esc_attr( $disp ); ?>"
			data-total="<?php echo esc_attr( $total ); ?>"
			data-status-label="<?php echo esc_attr( StatusBadge::label( $pa_status ) ); ?>"
			data-status-class="<?php echo esc_attr( StatusBadge::css_class( $pa_status ) ); ?>"
			data-pdf="<?php echo esc_url( $pdf ); ?>"
			data-xml="<?php echo esc_url( $xml ); ?>"
			data-transmit="<?php echo esc_url( $transmit ); ?>"
			data-edit="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
			<td class="is-num"><?php echo esc_html( OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ) ); ?></td>
			<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
			<td>
				<?php echo esc_html( $client ); ?>
				<?php if ( '' !== $siret ) : ?><br><span class="is-muted"><?php echo esc_html( $siret ); ?></span><?php endif; ?>
			</td>
			<td class="is-amount"><?php echo esc_html( $total ); ?></td>
			<td class="is-muted"><?php echo esc_html( $disp ); ?></td>
			<td><?php echo StatusBadge::html( $pa_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<td><span class="billigoo-btn billigoo-btn--ghost billigoo-btn--sm"><?php Ui::icon( 'visibility' ); ?></span></td>
		</tr>
		<?php
	}

	/**
	 * Simple prev/next pagination footer.
	 *
	 * @param int    $paged  Current page.
	 * @param int    $pages  Total pages.
	 * @param string $search Active search term.
	 */
	private function pagination( int $paged, int $pages, string $search ): void {
		if ( $pages < 2 ) {
			return;
		}
		$base = admin_url( 'admin.php?page=billigoo-invoices' );
		if ( '' !== $search ) {
			$base = add_query_arg( 's', rawurlencode( $search ), $base );
		}
		?>
		<div class="billigoo-card__foot" style="display:flex;justify-content:space-between;align-items:center;">
			<span class="billigoo-field__help">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Page %1$d sur %2$d', 'billigoo' ),
					(int) $paged,
					(int) $pages
				);
				?>
			</span>
			<span style="display:flex;gap:8px;">
				<?php if ( $paged > 1 ) : ?>
					<a class="billigoo-btn billigoo-btn--ghost billigoo-btn--sm" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base ) ); ?>"><?php Ui::icon( 'chevron_left' ); ?><?php esc_html_e( 'Précédent', 'billigoo' ); ?></a>
				<?php endif; ?>
				<?php if ( $paged < $pages ) : ?>
					<a class="billigoo-btn billigoo-btn--ghost billigoo-btn--sm" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base ) ); ?>"><?php esc_html_e( 'Suivant', 'billigoo' ); ?><?php Ui::icon( 'chevron_right' ); ?></a>
				<?php endif; ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render the (initially hidden) invoice detail slide-over shell.
	 */
	private function slideover(): void {
		?>
		<div id="billigoo-slideover" class="billigoo-slideover-overlay">
			<aside class="billigoo-slideover" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Détail de la facture', 'billigoo' ); ?>">
				<div class="billigoo-slideover__head">
					<h2 data-field="number">—</h2>
					<button type="button" class="billigoo-slideover__close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'billigoo' ); ?>"><?php Ui::icon( 'close' ); ?></button>
				</div>
				<div class="billigoo-slideover__body">
					<div>
						<div class="billigoo-field__help" style="text-transform:uppercase;font-weight:600;"><?php esc_html_e( 'Montant total TTC', 'billigoo' ); ?></div>
						<div class="billigoo-detail-amount" data-field="total">—</div>
						<div style="margin-top:8px;"><span class="billigoo-badge billigoo-badge--neutral" data-field="status">—</span></div>
					</div>
					<dl class="billigoo-detail-grid">
						<div><dt><?php esc_html_e( 'Commande', 'billigoo' ); ?></dt><dd data-field="order">—</dd></div>
						<div><dt><?php esc_html_e( 'Date d’émission', 'billigoo' ); ?></dt><dd data-field="date">—</dd></div>
						<div><dt><?php esc_html_e( 'Client', 'billigoo' ); ?></dt><dd data-field="client">—</dd></div>
						<div><dt><?php esc_html_e( 'SIRET', 'billigoo' ); ?></dt><dd data-field="siret">—</dd></div>
						<div><dt><?php esc_html_e( 'Format', 'billigoo' ); ?></dt><dd><span class="billigoo-badge billigoo-badge--success"><?php esc_html_e( 'FACTUR-X BASIC', 'billigoo' ); ?></span></dd></div>
					</dl>
					<a class="billigoo-btn billigoo-btn--ghost billigoo-btn--sm" data-action="edit" href="#"><?php Ui::icon( 'open_in_new' ); ?><?php esc_html_e( 'Ouvrir la commande', 'billigoo' ); ?></a>
				</div>
				<div class="billigoo-slideover__foot">
					<a class="billigoo-btn billigoo-btn--primary" data-action="pdf" href="#"><?php Ui::icon( 'download' ); ?><?php esc_html_e( 'PDF', 'billigoo' ); ?></a>
					<a class="billigoo-btn billigoo-btn--ghost" data-action="xml" href="#"><?php Ui::icon( 'code' ); ?><?php esc_html_e( 'XML', 'billigoo' ); ?></a>
					<a class="billigoo-btn billigoo-btn--ghost" data-action="transmit" href="#"><?php Ui::icon( 'send' ); ?><?php esc_html_e( 'Transmettre', 'billigoo' ); ?></a>
				</div>
			</aside>
		</div>
		<?php
	}
}
