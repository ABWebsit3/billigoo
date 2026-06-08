<?php
/**
 * Billigoo dashboard.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\Settings;
use Billigoo\Core\InvoiceNumber;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * The landing screen: compliance overview, key figures and recent activity,
 * matching the Stitch dashboard design adapted to live Phase-1 data.
 */
final class DashboardPage {

	/**
	 * Render the dashboard.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$seller_ok = Settings::is_seller_configured();
		$pa_ok     = Settings::is_pa_configured();
		$summary   = Stats::summary();

		$actions  = '<a class="billigoo-btn billigoo-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=billigoo-settings' ) ) . '">';
		$actions .= '<span class="material-symbols-outlined">settings</span>' . esc_html__( 'Réglages', 'billigoo' ) . '</a>';
		$actions .= '<a class="billigoo-btn billigoo-btn--primary" href="' . esc_url( admin_url( 'admin.php?page=billigoo-invoices' ) ) . '">';
		$actions .= '<span class="material-symbols-outlined">description</span>' . esc_html__( 'Voir les factures', 'billigoo' ) . '</a>';
		?>
		<div class="wrap billigoo-app">
			<?php Ui::header( __( 'Tableau de bord', 'billigoo' ), $actions ); ?>

			<?php if ( isset( $_GET['billigoo_welcome'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="billigoo-alert billigoo-alert--success">
					<p class="billigoo-alert__msg">
						<?php Ui::icon( 'celebration', true ); ?>
						<?php esc_html_e( 'Configuration terminée — Billigoo est prêt à générer vos factures Factur-X.', 'billigoo' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $seller_ok ) : ?>
				<div class="billigoo-alert billigoo-alert--warn">
					<p class="billigoo-alert__msg">
						<?php Ui::icon( 'warning', true ); ?>
						<?php esc_html_e( 'L’identité du vendeur est incomplète. Aucune facture ne pourra être générée tant que les réglages ne sont pas remplis.', 'billigoo' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-setup' ) ); ?>"><?php esc_html_e( 'Configurer →', 'billigoo' ); ?></a>
				</div>
			<?php elseif ( ! $pa_ok ) : ?>
				<div class="billigoo-alert billigoo-alert--warn">
					<p class="billigoo-alert__msg">
						<?php Ui::icon( 'warning', true ); ?>
						<?php esc_html_e( 'Aucune Plateforme Agréée configurée. Configurez-la pour préparer la transmission automatique de la réforme 2026.', 'billigoo' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-settings#billigoo-pa' ) ); ?>"><?php esc_html_e( 'Configurer →', 'billigoo' ); ?></a>
				</div>
			<?php endif; ?>

			<!-- Metric cards -->
			<div class="billigoo-metrics">
				<div class="billigoo-metric">
					<span class="billigoo-metric__label"><?php esc_html_e( 'Factures ce mois', 'billigoo' ); ?></span>
					<div class="billigoo-metric__row">
						<span class="billigoo-metric__value billigoo-metric__value--primary"><?php echo esc_html( (string) $summary['this_month'] ); ?></span>
						<?php if ( null !== $summary['trend'] ) : ?>
							<span class="billigoo-metric__trend">
								<?php Ui::icon( $summary['trend'] >= 0 ? 'trending_up' : 'trending_down' ); ?>
								<?php echo esc_html( ( $summary['trend'] >= 0 ? '+' : '' ) . $summary['trend'] . '%' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<span class="billigoo-metric__foot"><?php esc_html_e( 'vs mois dernier', 'billigoo' ); ?></span>
				</div>

				<div class="billigoo-metric">
					<span class="billigoo-metric__label"><?php esc_html_e( 'CA facturé', 'billigoo' ); ?></span>
					<span class="billigoo-metric__value"><?php echo esc_html( Stats::money( $summary['turnover'] ) ); ?></span>
					<span class="billigoo-metric__foot"><?php esc_html_e( 'Depuis le 1er du mois', 'billigoo' ); ?></span>
				</div>

				<div class="billigoo-metric">
					<span class="billigoo-metric__label">
						<?php esc_html_e( 'Factures émises', 'billigoo' ); ?>
						<span class="billigoo-badge billigoo-badge--info"><?php esc_html_e( 'TOTAL', 'billigoo' ); ?></span>
					</span>
					<span class="billigoo-metric__value"><?php echo esc_html( (string) $summary['total'] ); ?></span>
					<span class="billigoo-metric__foot">
						<?php
						printf(
							/* translators: %s: last invoice number */
							esc_html__( 'Dernier n° : %s', 'billigoo' ),
							esc_html( (string) InvoiceNumber::last() )
						);
						?>
					</span>
				</div>

				<div class="billigoo-metric">
					<span class="billigoo-metric__label">
						<?php esc_html_e( 'Transmission PA', 'billigoo' ); ?>
						<?php if ( $pa_ok ) : ?>
							<span class="billigoo-badge billigoo-badge--success"><?php esc_html_e( 'ACTIVE', 'billigoo' ); ?></span>
						<?php else : ?>
							<span class="billigoo-badge billigoo-badge--warn"><?php esc_html_e( 'À CONFIGURER', 'billigoo' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="billigoo-metric__value<?php echo $pa_ok ? '' : ' billigoo-metric__value--error'; ?>"><?php echo $pa_ok ? esc_html( Settings::pa_provider_label() ) : '—'; ?></span>
					<span class="billigoo-metric__foot"><?php esc_html_e( 'Plateforme de Dématérialisation', 'billigoo' ); ?></span>
				</div>
			</div>

			<div class="billigoo-grid">
				<!-- Left column -->
				<div class="billigoo-col">
					<section class="billigoo-card">
						<div class="billigoo-card__head"><h2><?php esc_html_e( 'Statut de conformité réglementaire', 'billigoo' ); ?></h2></div>
						<div class="billigoo-card__body">
							<div class="billigoo-checklist">
								<div class="billigoo-checkitem billigoo-checkitem--ok">
									<?php Ui::icon( 'check_circle', true ); ?>
									<span><?php esc_html_e( 'Génération Factur-X (BASIC) active', 'billigoo' ); ?></span>
								</div>
								<div class="billigoo-checkitem <?php echo $seller_ok ? 'billigoo-checkitem--ok' : 'billigoo-checkitem--todo'; ?>">
									<?php Ui::icon( $seller_ok ? 'check_circle' : 'error', $seller_ok ); ?>
									<span><?php echo $seller_ok ? esc_html__( 'Identité vendeur complète', 'billigoo' ) : esc_html__( 'Identité vendeur à compléter', 'billigoo' ); ?></span>
								</div>
								<div class="billigoo-checkitem <?php echo $pa_ok ? 'billigoo-checkitem--ok' : 'billigoo-checkitem--info'; ?>">
									<?php Ui::icon( $pa_ok ? 'check_circle' : 'info', $pa_ok ); ?>
									<span><?php echo $pa_ok ? esc_html__( 'Plateforme Agréée connectée', 'billigoo' ) : esc_html__( 'E-reporting B2C : échéance 01/09/2026', 'billigoo' ); ?></span>
								</div>
								<div class="billigoo-checkitem billigoo-checkitem--ok">
									<?php Ui::icon( 'check_circle', true ); ?>
									<span><?php esc_html_e( 'Numérotation légale continue', 'billigoo' ); ?></span>
								</div>
							</div>
						</div>
					</section>

					<section class="billigoo-card">
						<div class="billigoo-card__head">
							<h2><?php esc_html_e( 'Activité récente', 'billigoo' ); ?></h2>
							<a class="billigoo-btn billigoo-btn--ghost billigoo-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-invoices' ) ); ?>"><?php esc_html_e( 'Tout voir', 'billigoo' ); ?></a>
						</div>
						<?php $this->recent_table(); ?>
					</section>
				</div>

				<!-- Right column -->
				<div class="billigoo-col">
					<section class="billigoo-card">
						<div class="billigoo-card__body">
							<h3 class="billigoo-eyebrow"><?php esc_html_e( 'Raccourcis', 'billigoo' ); ?></h3>
							<a class="billigoo-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-settings#billigoo-pa' ) ); ?>">
								<span class="billigoo-shortcut__icon"><?php Ui::icon( 'hub' ); ?></span>
								<span>
									<span class="billigoo-shortcut__title"><?php esc_html_e( 'Configurer la PA', 'billigoo' ); ?></span><br>
									<span class="billigoo-shortcut__sub"><?php esc_html_e( 'Lier votre plateforme agréée', 'billigoo' ); ?></span>
								</span>
							</a>
							<a class="billigoo-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-settings' ) ); ?>">
								<span class="billigoo-shortcut__icon"><?php Ui::icon( 'tune' ); ?></span>
								<span>
									<span class="billigoo-shortcut__title"><?php esc_html_e( 'Réglages de facturation', 'billigoo' ); ?></span><br>
									<span class="billigoo-shortcut__sub"><?php esc_html_e( 'Numérotation, déclencheur, emails', 'billigoo' ); ?></span>
								</span>
							</a>
							<a class="billigoo-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-setup' ) ); ?>">
								<span class="billigoo-shortcut__icon"><?php Ui::icon( 'rocket_launch' ); ?></span>
								<span>
									<span class="billigoo-shortcut__title"><?php esc_html_e( 'Relancer l’assistant', 'billigoo' ); ?></span><br>
									<span class="billigoo-shortcut__sub"><?php esc_html_e( 'Reconfigurer pas à pas', 'billigoo' ); ?></span>
								</span>
							</a>
						</div>
					</section>

					<section class="billigoo-card">
						<div class="billigoo-card__body">
							<h3 class="billigoo-eyebrow"><?php esc_html_e( 'Ressources', 'billigoo' ); ?></h3>
							<ul class="billigoo-reslist">
								<li><a href="https://billigoo.fr/conformite" target="_blank" rel="noopener"><?php Ui::icon( 'gavel' ); ?><?php esc_html_e( 'Guide conformité', 'billigoo' ); ?></a><?php Ui::icon( 'chevron_right' ); ?></li>
								<li><a href="https://billigoo.fr/support" target="_blank" rel="noopener"><?php Ui::icon( 'quiz' ); ?><?php esc_html_e( 'FAQ & support', 'billigoo' ); ?></a><?php Ui::icon( 'chevron_right' ); ?></li>
								<li><a href="https://billigoo.fr/calendrier" target="_blank" rel="noopener"><?php Ui::icon( 'event_note' ); ?><?php esc_html_e( 'Calendrier réforme', 'billigoo' ); ?></a><?php Ui::icon( 'chevron_right' ); ?></li>
							</ul>
						</div>
					</section>

					<div class="billigoo-helpcard">
						<?php Ui::icon( 'auto_awesome', true ); ?>
						<div>
							<div class="billigoo-helpcard__title"><?php esc_html_e( 'Besoin d’aide ?', 'billigoo' ); ?></div>
							<p><?php esc_html_e( 'Nos experts vous accompagnent pour la réforme 2026.', 'billigoo' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the "recent activity" table (latest 5 invoiced orders).
	 */
	private function recent_table(): void {
		$orders = wc_get_orders(
			array(
				'limit'      => 5,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_query' => array(
					array(
						'key'     => OrderMeta::INVOICE_NUMBER,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $orders ) ) {
			echo '<div class="billigoo-card__body"><p class="billigoo-field__help">' . esc_html__( 'Aucune facture générée pour le moment.', 'billigoo' ) . '</p></div>';
			return;
		}
		?>
		<table class="billigoo-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'N° Facture', 'billigoo' ); ?></th>
					<th><?php esc_html_e( 'Client', 'billigoo' ); ?></th>
					<th class="is-amount"><?php esc_html_e( 'Montant TTC', 'billigoo' ); ?></th>
					<th><?php esc_html_e( 'Date', 'billigoo' ); ?></th>
					<th><?php esc_html_e( 'Statut PA', 'billigoo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					if ( ! $order instanceof \WC_Order ) {
						continue;
					}
					$company = OrderMeta::get( $order, OrderMeta::COMPANY_NAME );
					$client  = '' !== $company ? $company : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
					$date    = $order->get_date_created();
					?>
					<tr onclick="window.location='<?php echo esc_url( $order->get_edit_order_url() ); ?>'">
						<td class="is-num"><?php echo esc_html( OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ) ); ?></td>
						<td><?php echo esc_html( $client ); ?></td>
						<td class="is-amount"><?php echo esc_html( Stats::money( (float) $order->get_total() ) ); ?></td>
						<td class="is-muted"><?php echo $date ? esc_html( $date->date_i18n( 'd/m/Y' ) ) : '—'; ?></td>
						<td><?php echo StatusBadge::html( OrderMeta::pa_status( $order ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
