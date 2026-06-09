<?php
/**
 * Onboarding setup wizard.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A centered, multi-step setup assistant shown after activation, matching the
 * Stitch wizard design. Each step persists into the shared settings option.
 */
final class WizardPage {

	private const TOTAL_STEPS = 5;
	private const NONCE       = 'billigoo_wizard';

	/**
	 * Register the form handler.
	 */
	public function register(): void {
		add_action( 'admin_post_billigoo_wizard_save', array( $this, 'handle_save' ) );
	}

	/**
	 * Step titles, indexed 1..TOTAL_STEPS.
	 *
	 * @return array<int,string>
	 */
	private function titles(): array {
		return array(
			1 => __( 'Identité de l’entreprise', 'billigoo' ),
			2 => __( 'Facturation & numérotation', 'billigoo' ),
			3 => __( 'Plateforme Agréée', 'billigoo' ),
			4 => __( 'Template PDF', 'billigoo' ),
			5 => __( 'Récapitulatif', 'billigoo' ),
		);
	}

	/**
	 * Persist a step then advance, or finalize on the last step.
	 */
	public function handle_save(): void {
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'billigoo' ) );
		}

		$step   = isset( $_POST['step'] ) ? max( 1, min( self::TOTAL_STEPS, absint( wp_unslash( $_POST['step'] ) ) ) ) : 1;
		$posted = isset( $_POST[ Settings::OPTION ] ) && is_array( $_POST[ Settings::OPTION ] )
			? wp_unslash( $_POST[ Settings::OPTION ] ) // phpcs:ignore WordPress.Security.ValidationSanitization.InputNotSanitized -- sanitized via Settings::sanitize below.
			: array();

		if ( ! empty( $posted ) ) {
			$merged = array_merge( Settings::all(), $posted );
			update_option( Settings::OPTION, Settings::sanitize( $merged ) );
		}

		if ( $step >= self::TOTAL_STEPS ) {
			update_option( 'billigoo_setup_complete', 1 );
			wp_safe_redirect( admin_url( 'admin.php?page=billigoo&billigoo_welcome=1' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=billigoo-setup&step=' . ( $step + 1 ) ) );
		exit;
	}

	/**
	 * Render the wizard.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$step    = isset( $_GET['step'] ) ? max( 1, min( self::TOTAL_STEPS, absint( wp_unslash( $_GET['step'] ) ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$titles  = $this->titles();
		$percent = (int) round( ( $step / self::TOTAL_STEPS ) * 100 );
		$s       = Settings::all();
		?>
		<div class="wrap billigoo-app">
			<div class="billigoo-wizard">
				<div class="billigoo-wizard__brand">
					<?php Ui::icon( 'receipt_long' ); ?>
					<span>Billigoo</span>
				</div>

				<div class="billigoo-progress"><div class="billigoo-progress__bar" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></div></div>
				<p class="billigoo-progress__label">
					<?php
					printf(
						/* translators: 1: current step, 2: total steps */
						esc_html__( 'Étape %1$d sur %2$d', 'billigoo' ),
						(int) $step,
						(int) self::TOTAL_STEPS
					);
					?>
				</p>

				<section class="billigoo-card">
					<div class="billigoo-card__body">
						<h2><?php echo esc_html( $titles[ $step ] ); ?></h2>

						<?php if ( self::TOTAL_STEPS === $step ) : ?>
							<?php $this->step_recap( $s ); ?>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="billigoo_wizard_save" />
								<input type="hidden" name="step" value="<?php echo esc_attr( (string) $step ); ?>" />
								<?php wp_nonce_field( self::NONCE ); ?>

								<?php
								if ( 1 === $step ) {
									$this->step_identity( $s );
								} elseif ( 2 === $step ) {
									$this->step_numbering( $s );
								} elseif ( 3 === $step ) {
									$this->step_pa( $s );
								} elseif ( 4 === $step ) {
									$this->step_template( $s );
								}
								?>

								<div class="billigoo-wizard__nav">
									<?php if ( $step > 1 ) : ?>
										<a class="billigoo-btn billigoo-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-setup&step=' . ( $step - 1 ) ) ); ?>"><?php Ui::icon( 'arrow_back' ); ?><?php esc_html_e( 'Retour', 'billigoo' ); ?></a>
									<?php else : ?>
										<span></span>
									<?php endif; ?>
									<button type="submit" class="billigoo-btn billigoo-btn--primary"><?php esc_html_e( 'Continuer', 'billigoo' ); ?><?php Ui::icon( 'arrow_forward' ); ?></button>
								</div>
							</form>
						<?php endif; ?>

						<div class="billigoo-wizard__steps">
							<?php for ( $i = 1; $i <= self::TOTAL_STEPS; $i++ ) : ?>
								<span class="billigoo-wizard__dot <?php echo $i === $step ? 'is-active' : ( $i < $step ? 'is-done' : '' ); ?>"></span>
							<?php endfor; ?>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Step 1: seller identity fields.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function step_identity( array $s ): void {
		?>
		<p class="billigoo-wizard__lead"><?php esc_html_e( 'Ces informations figureront sur chaque facture Factur-X émise.', 'billigoo' ); ?></p>
		<div class="billigoo-fields">
			<?php
			$this->text( 'seller_name', __( 'Raison sociale', 'billigoo' ), $s['seller_name'], true );
			$this->text( 'seller_siret', __( 'SIRET (14 chiffres)', 'billigoo' ), $s['seller_siret'] );
			$this->text( 'seller_vat', __( 'N° TVA intracommunautaire', 'billigoo' ), $s['seller_vat'] );
			$this->text( 'seller_address', __( 'Adresse', 'billigoo' ), $s['seller_address'], true );
			$this->text( 'seller_postcode', __( 'Code postal', 'billigoo' ), $s['seller_postcode'] );
			$this->text( 'seller_city', __( 'Ville', 'billigoo' ), $s['seller_city'] );
			?>
		</div>
		<?php
	}

	/**
	 * Step 2: numbering.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function step_numbering( array $s ): void {
		?>
		<p class="billigoo-wizard__lead"><?php esc_html_e( 'La numérotation est séquentielle et sans rupture, conformément à la loi.', 'billigoo' ); ?></p>
		<div class="billigoo-fields">
			<?php
			$this->text( 'number_prefix', __( 'Préfixe', 'billigoo' ), $s['number_prefix'] );
			$this->text( 'number_padding', __( 'Longueur (zéros)', 'billigoo' ), (string) $s['number_padding'] );
			?>
			<div class="billigoo-field">
				<label for="wiz_trigger"><?php esc_html_e( 'Statut déclencheur', 'billigoo' ); ?></label>
				<select id="wiz_trigger" name="<?php echo esc_attr( Settings::OPTION ); ?>[trigger_status]">
					<?php
					foreach ( array(
						'processing' => __( 'En cours (processing)', 'billigoo' ),
						'completed'  => __( 'Terminée (completed)', 'billigoo' ),
						'on-hold'    => __( 'En attente (on-hold)', 'billigoo' ),
					) as $value => $label ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['trigger_status'], $value, false ), esc_html( $label ) );
					}
					?>
				</select>
			</div>
			<div class="billigoo-field">
				<label><?php esc_html_e( 'Réinitialisation annuelle', 'billigoo' ); ?></label>
				<?php $this->switch( 'number_yearly_reset', __( 'Repartir de 1 chaque 1er janvier', 'billigoo' ), (int) $s['number_yearly_reset'] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Step 3: Plateforme Agréée + email.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function step_pa( array $s ): void {
		?>
		<p class="billigoo-wizard__lead"><?php esc_html_e( 'Connectez votre plateforme (facultatif) pour préparer la transmission automatique.', 'billigoo' ); ?></p>
		<div class="billigoo-fields">
			<div class="billigoo-field">
				<label for="wiz_pa"><?php esc_html_e( 'Plateforme Agréée', 'billigoo' ); ?></label>
				<select id="wiz_pa" name="<?php echo esc_attr( Settings::OPTION ); ?>[pa_provider]">
					<option value=""><?php esc_html_e( '— Plus tard —', 'billigoo' ); ?></option>
					<?php
					foreach ( Settings::pa_providers() as $value => $label ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['pa_provider'], $value, false ), esc_html( $label ) );
					}
					?>
				</select>
			</div>
			<div class="billigoo-field">
				<label for="wiz_pa_env"><?php esc_html_e( 'Environnement', 'billigoo' ); ?></label>
				<select id="wiz_pa_env" name="<?php echo esc_attr( Settings::OPTION ); ?>[pa_environment]">
					<option value="sandbox" <?php selected( $s['pa_environment'], 'sandbox' ); ?>><?php esc_html_e( 'Test (sandbox)', 'billigoo' ); ?></option>
					<option value="production" <?php selected( $s['pa_environment'], 'production' ); ?>><?php esc_html_e( 'Production', 'billigoo' ); ?></option>
				</select>
			</div>
			<div class="billigoo-field billigoo-field--full">
				<label for="wiz_pa_key"><?php esc_html_e( 'Clé API / Jeton', 'billigoo' ); ?></label>
				<input type="password" id="wiz_pa_key" autocomplete="new-password" name="<?php echo esc_attr( Settings::OPTION ); ?>[pa_api_key]" value="" placeholder="<?php echo esc_attr( '' !== (string) $s['pa_api_key'] ? '•••••••• ' . __( '(enregistré)', 'billigoo' ) : '' ); ?>" />
			</div>
			<div class="billigoo-field billigoo-field--full">
				<label><?php esc_html_e( 'Transmission automatique', 'billigoo' ); ?></label>
				<?php $this->switch( 'pa_auto_transmit', __( 'Transmettre dès la génération (B2B)', 'billigoo' ), (int) $s['pa_auto_transmit'] ); ?>
			</div>
			<div class="billigoo-field billigoo-field--full">
				<label><?php esc_html_e( 'Email client', 'billigoo' ); ?></label>
				<?php $this->switch( 'attach_email', __( 'Joindre la facture aux emails de commande', 'billigoo' ), (int) $s['attach_email'] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Step 4: PDF template branding.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function step_template( array $s ): void {
		?>
		<p class="billigoo-wizard__lead"><?php esc_html_e( 'Personnalisez l’apparence de vos factures PDF.', 'billigoo' ); ?></p>
		<div class="billigoo-fields">
			<div class="billigoo-field">
				<label for="wiz_template"><?php esc_html_e( 'Modèle', 'billigoo' ); ?></label>
				<select id="wiz_template" name="<?php echo esc_attr( Settings::OPTION ); ?>[template]">
					<option value="default" <?php selected( $s['template'], 'default' ); ?>><?php esc_html_e( 'Standard', 'billigoo' ); ?></option>
					<option value="premium" <?php selected( $s['template'], 'premium' ); ?>><?php esc_html_e( 'Premium', 'billigoo' ); ?></option>
				</select>
			</div>
			<div class="billigoo-field">
				<label for="wiz_color"><?php esc_html_e( 'Couleur principale', 'billigoo' ); ?></label>
				<input type="color" id="wiz_color" name="<?php echo esc_attr( Settings::OPTION ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" style="height:40px;padding:4px;" />
			</div>
			<?php
			$this->text( 'logo_url', __( 'URL du logo', 'billigoo' ), (string) $s['logo_url'], true );
			?>
		</div>
		<?php
	}

	/**
	 * Step 4: recap and finalize.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function step_recap( array $s ): void {
		$ready = Settings::is_seller_configured();
		?>
		<div class="billigoo-wizard__done">
			<span class="billigoo-success-ring"><?php Ui::icon( $ready ? 'check_circle' : 'warning', true ); ?></span>
			<h2 style="border:0;"><?php echo $ready ? esc_html__( 'Tout est prêt !', 'billigoo' ) : esc_html__( 'Presque terminé', 'billigoo' ); ?></h2>
			<p class="billigoo-wizard__lead" style="margin-top:8px;">
				<?php
				echo $ready
					? esc_html__( 'Billigoo générera désormais une facture Factur-X conforme pour chaque commande éligible.', 'billigoo' )
					: esc_html__( 'L’identité du vendeur est incomplète : revenez à l’étape 1 pour la compléter avant d’émettre des factures.', 'billigoo' );
				?>
			</p>
		</div>

		<dl class="billigoo-detail-grid" style="margin:16px 0 8px;">
			<div><dt><?php esc_html_e( 'Raison sociale', 'billigoo' ); ?></dt><dd><?php echo esc_html( $s['seller_name'] ?: '—' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'SIRET', 'billigoo' ); ?></dt><dd><?php echo esc_html( $s['seller_siret'] ?: '—' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Préfixe n°', 'billigoo' ); ?></dt><dd><?php echo esc_html( $s['number_prefix'] ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Plateforme Agréée', 'billigoo' ); ?></dt><dd><?php echo esc_html( Settings::pa_provider_label() ?: __( 'Non configurée', 'billigoo' ) ); ?></dd></div>
		</dl>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="billigoo_wizard_save" />
			<input type="hidden" name="step" value="<?php echo esc_attr( (string) self::TOTAL_STEPS ); ?>" />
			<?php wp_nonce_field( self::NONCE ); ?>
			<div class="billigoo-wizard__nav">
				<a class="billigoo-btn billigoo-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-setup&step=1' ) ); ?>"><?php Ui::icon( 'edit' ); ?><?php esc_html_e( 'Modifier', 'billigoo' ); ?></a>
				<button type="submit" class="billigoo-btn billigoo-btn--primary"><?php Ui::icon( 'check' ); ?><?php esc_html_e( 'Terminer la configuration', 'billigoo' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Text field helper.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param bool   $full  Full width.
	 */
	private function text( string $key, string $label, string $value, bool $full = false ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		?>
		<div class="billigoo-field<?php echo $full ? ' billigoo-field--full' : ''; ?>">
			<label for="wiz_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" id="wiz_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		</div>
		<?php
	}

	/**
	 * Toggle switch helper.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Inline label.
	 * @param int    $checked Whether on.
	 */
	private function switch( string $key, string $label, int $checked ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
		<label class="billigoo-switch">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, $checked ); ?> />
			<span class="billigoo-switch__track"></span>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}
}
