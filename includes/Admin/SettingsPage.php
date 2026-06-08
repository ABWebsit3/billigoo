<?php
/**
 * Tabbed settings page (WordPress Settings API + design system).
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

use Billigoo\License;
use Billigoo\Settings;
use Billigoo\Core\InvoiceNumber;
use Billigoo\Pa\PaRouter;
use Billigoo\Api\Webhooks;
use Billigoo\Ereporting\EreportingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Seven-tab settings screen: Général, Facturation, Plateforme Agréée,
 * E-reporting, Export, API & Webhooks, Licence.
 */
final class SettingsPage {

	private const GROUP = 'billigoo_settings_group';

	/**
	 * Tab slug → label.
	 *
	 * @return array<string,string>
	 */
	private function tabs(): array {
		return array(
			'general'    => __( 'Général', 'billigoo' ),
			'billing'    => __( 'Facturation', 'billigoo' ),
			'pa'         => __( 'Plateforme Agréée', 'billigoo' ),
			'ereporting' => __( 'E-reporting', 'billigoo' ),
			'export'     => __( 'Export', 'billigoo' ),
			'api'        => __( 'API & Webhooks', 'billigoo' ),
			'license'    => __( 'Licence', 'billigoo' ),
		);
	}

	/**
	 * Register settings, AJAX and licence handlers.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_' . self::GROUP, static fn() => 'manage_woocommerce' );
		add_action( 'wp_ajax_billigoo_test_pa', array( $this, 'ajax_test_pa' ) );
		add_action( 'admin_post_billigoo_license', array( $this, 'handle_license' ) );
	}

	/**
	 * Register the option with its sanitizer.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
			)
		);
	}

	/**
	 * AJAX: test the configured PA connection.
	 */
	public function ajax_test_pa(): void {
		check_ajax_referer( 'billigoo_test_pa', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'billigoo' ) ), 403 );
		}
		$result = PaRouter::configured()->connect();
		wp_send_json_success( $result );
	}

	/**
	 * Activate or deactivate a licence key.
	 */
	public function handle_license(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'billigoo' ), 403 );
		}
		check_admin_referer( 'billigoo_license' );

		$notice = 'saved';
		if ( isset( $_POST['billigoo_license_deactivate'] ) ) {
			License::deactivate();
			$notice = 'deactivated';
		} else {
			$key    = isset( $_POST['billigoo_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['billigoo_license_key'] ) ) : '';
			$result = License::activate( $key );
			$notice = $result['ok'] ? 'activated' : 'error';
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'billigoo-settings', 'tab' => 'license', 'billigoo_license' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tabs = $this->tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		$s = Settings::all();
		?>
		<div class="wrap billigoo-app">
			<?php Ui::header( __( 'Réglages', 'billigoo' ) ); ?>

			<nav class="billigoo-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a class="billigoo-tab <?php echo $slug === $tab ? 'is-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=billigoo-settings&tab=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'export':
					$this->tab_export();
					break;
				case 'license':
					$this->tab_license();
					break;
				default:
					$this->settings_form( $tab, $s );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render an option-backed settings form for a tab.
	 *
	 * @param string              $tab Tab slug.
	 * @param array<string,mixed> $s   Settings.
	 */
	private function settings_form( string $tab, array $s ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( self::GROUP ); ?>
			<input type="hidden" name="<?php echo esc_attr( Settings::OPTION ); ?>[_billigoo_tab]" value="<?php echo esc_attr( $tab ); ?>" />
			<div class="billigoo-col">
				<?php
				switch ( $tab ) {
					case 'general':
						$this->tab_general( $s );
						break;
					case 'billing':
						$this->tab_billing( $s );
						break;
					case 'pa':
						$this->tab_pa( $s );
						break;
					case 'ereporting':
						$this->tab_ereporting( $s );
						break;
					case 'api':
						$this->tab_api( $s );
						break;
				}
				?>
			</div>
			<div class="billigoo-form-actions">
				<button type="submit" class="billigoo-btn billigoo-btn--primary"><?php Ui::icon( 'save' ); ?><?php esc_html_e( 'Enregistrer', 'billigoo' ); ?></button>
			</div>
		</form>
		<?php
	}

	/* ----------------------------------------------------------------- Tabs */

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function tab_general( array $s ): void {
		$this->card_open( __( 'Identité de l’entreprise', 'billigoo' ) );
		echo '<div class="billigoo-fields">';
		$this->text( 'seller_name', __( 'Raison sociale', 'billigoo' ), $s['seller_name'], '', true );
		$this->text( 'seller_vat', __( 'N° TVA intracommunautaire', 'billigoo' ), $s['seller_vat'] );
		$this->text( 'seller_siren', __( 'SIREN', 'billigoo' ), $s['seller_siren'] );
		$this->text( 'seller_siret', __( 'SIRET (siège)', 'billigoo' ), $s['seller_siret'] );
		$this->text( 'seller_address', __( 'Adresse', 'billigoo' ), $s['seller_address'], '', true );
		$this->text( 'seller_postcode', __( 'Code postal', 'billigoo' ), $s['seller_postcode'] );
		$this->text( 'seller_city', __( 'Ville', 'billigoo' ), $s['seller_city'] );
		$this->text( 'seller_country', __( 'Pays (ISO)', 'billigoo' ), $s['seller_country'] );
		echo '</div>';
		$this->card_close();

		$this->card_open( __( 'Numérotation', 'billigoo' ) );
		echo '<div class="billigoo-fields">';
		$this->text( 'number_prefix', __( 'Préfixe', 'billigoo' ), $s['number_prefix'], __( 'Jeton {year} accepté, ex. FA-{year}-', 'billigoo' ) );
		$this->text( 'number_padding', __( 'Longueur (zéros)', 'billigoo' ), (string) $s['number_padding'] );
		$this->switch_field( 'number_yearly_reset', __( 'Réinitialisation annuelle', 'billigoo' ), __( 'Repartir de 1 chaque 1er janvier', 'billigoo' ), (int) $s['number_yearly_reset'] );
		$this->select( 'trigger_status', __( 'Statut déclencheur', 'billigoo' ), array(
			'processing' => __( 'En cours (processing)', 'billigoo' ),
			'completed'  => __( 'Terminée (completed)', 'billigoo' ),
			'on-hold'    => __( 'En attente (on-hold)', 'billigoo' ),
		), (string) $s['trigger_status'] );
		echo '<div class="billigoo-field"><label>' . esc_html__( 'Dernier numéro émis', 'billigoo' ) . '</label><div class="billigoo-kv"><code>' . esc_html( (string) InvoiceNumber::last() ) . '</code></div></div>';
		echo '</div>';
		$this->card_close();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function tab_billing( array $s ): void {
		$en16931 = License::has_feature( 'profile_en16931' );
		$premium = License::has_feature( 'template_premium' );

		$this->card_open( __( 'Format & facturation', 'billigoo' ) );
		echo '<div class="billigoo-fields">';
		$this->select( 'profile', __( 'Profil Factur-X', 'billigoo' ), array(
			'basic'    => 'BASIC',
			'en16931'  => 'EN 16931' . ( $en16931 ? '' : ' (Pro)' ),
		), (string) $s['profile'] );
		$this->text( 'currency', __( 'Devise', 'billigoo' ), (string) $s['currency'] );
		$this->switch_field( 'attach_email', __( 'Email client', 'billigoo' ), __( 'Joindre la facture Factur-X aux emails client', 'billigoo' ), (int) $s['attach_email'] );
		$this->textarea( 'payment_terms', __( 'Conditions de paiement', 'billigoo' ), (string) $s['payment_terms'] );
		$this->textarea( 'legal_mentions', __( 'Mentions légales', 'billigoo' ), (string) $s['legal_mentions'] );
		echo '</div>';
		$this->card_close();

		$this->card_open( __( 'Template PDF', 'billigoo' ) );
		echo '<div class="billigoo-fields">';
		$this->select( 'template', __( 'Modèle', 'billigoo' ), array(
			'default' => __( 'Standard', 'billigoo' ),
			'premium' => __( 'Premium', 'billigoo' ) . ( $premium ? '' : ' (Pro)' ),
		), (string) $s['template'] );
		$this->color( 'primary_color', __( 'Couleur principale', 'billigoo' ), (string) $s['primary_color'] );
		$this->text( 'logo_url', __( 'URL du logo', 'billigoo' ), (string) $s['logo_url'], __( 'Lien direct vers une image (PNG/JPG).', 'billigoo' ), true );
		echo '</div>';
		$this->card_close();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function tab_pa( array $s ): void {
		$this->card_open( __( 'Plateforme Agréée (PA)', 'billigoo' ), Settings::is_pa_configured() ? array( 'success', __( 'CONFIGURÉE', 'billigoo' ) ) : array( 'warn', __( 'NON CONFIGURÉE', 'billigoo' ) ) );
		if ( ! License::has_feature( 'pa_transmission' ) ) {
			$this->info( __( 'La transmission automatique est une fonctionnalité du plan Pro. Vous pouvez enregistrer la configuration dès maintenant.', 'billigoo' ) );
		}
		echo '<div class="billigoo-fields">';
		$providers = array( '' => __( '— Sélectionner —', 'billigoo' ) ) + Settings::pa_providers();
		$this->select( 'pa_provider', __( 'Plateforme', 'billigoo' ), $providers, (string) $s['pa_provider'] );
		$this->select( 'pa_environment', __( 'Environnement', 'billigoo' ), array(
			'sandbox'    => __( 'Test (sandbox)', 'billigoo' ),
			'production' => __( 'Production', 'billigoo' ),
		), (string) $s['pa_environment'] );
		$this->secret( 'pa_api_key', __( 'Clé API / Jeton', 'billigoo' ), (string) $s['pa_api_key'], true );
		$this->text( 'pa_chorus_client_id', __( 'Chorus Pro — Client ID', 'billigoo' ), (string) $s['pa_chorus_client_id'] );
		$this->secret( 'pa_chorus_client_secret', __( 'Chorus Pro — Client Secret', 'billigoo' ), (string) $s['pa_chorus_client_secret'] );
		$this->select( 'pa_retry', __( 'Tentatives en cas d’échec', 'billigoo' ), array( '0' => '0', '1' => '1', '3' => '3' ), (string) $s['pa_retry'] );
		$this->switch_field( 'pa_auto_transmit', __( 'Transmission automatique', 'billigoo' ), __( 'Transmettre dès la génération (B2B)', 'billigoo' ), (int) $s['pa_auto_transmit'] );
		$this->switch_field( 'pa_b2g', __( 'Mode B2G (Chorus Pro)', 'billigoo' ), __( 'Router les acheteurs publics vers Chorus Pro', 'billigoo' ), (int) $s['pa_b2g'] );
		echo '</div>';
		echo '<p style="margin-top:16px;"><button type="button" class="billigoo-btn billigoo-btn--ghost" id="billigoo-test-pa" data-nonce="' . esc_attr( wp_create_nonce( 'billigoo_test_pa' ) ) . '"><span class="material-symbols-outlined">wifi_tethering</span>' . esc_html__( 'Tester la connexion', 'billigoo' ) . '</button> <span id="billigoo-test-pa-result" class="billigoo-field__help"></span></p>';
		$this->card_close();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function tab_ereporting( array $s ): void {
		$this->card_open( __( 'E-reporting B2C', 'billigoo' ) );
		if ( ! License::has_feature( 'ereporting' ) ) {
			$this->info( __( 'L’e-reporting est inclus dans le plan Pro.', 'billigoo' ) );
		}
		echo '<div class="billigoo-fields">';
		$this->switch_field( 'ereporting_enabled', __( 'Activation', 'billigoo' ), __( 'Transmettre les données de transactions B2C', 'billigoo' ), (int) $s['ereporting_enabled'] );
		$this->select( 'ereporting_period', __( 'Périodicité', 'billigoo' ), array(
			'monthly'   => __( 'Mensuelle', 'billigoo' ),
			'quarterly' => __( 'Trimestrielle', 'billigoo' ),
		), (string) $s['ereporting_period'] );
		echo '</div>';
		$this->card_close();

		$history = EreportingManager::history();
		$this->card_open( __( 'Historique des envois', 'billigoo' ) );
		if ( empty( $history ) ) {
			echo '<p class="billigoo-field__help">' . esc_html__( 'Aucun rapport généré pour le moment.', 'billigoo' ) . '</p>';
		} else {
			echo '<table class="billigoo-table"><thead><tr><th>' . esc_html__( 'Période', 'billigoo' ) . '</th><th>' . esc_html__( 'Transactions', 'billigoo' ) . '</th><th class="is-amount">' . esc_html__( 'Total TTC', 'billigoo' ) . '</th><th>' . esc_html__( 'Généré le', 'billigoo' ) . '</th></tr></thead><tbody>';
			foreach ( $history as $h ) {
				echo '<tr><td>' . esc_html( (string) ( $h['label'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $h['count'] ?? 0 ) ) . '</td><td class="is-amount">' . esc_html( Stats::money( (float) ( $h['total_ttc'] ?? 0 ) ) ) . '</td><td class="is-muted">' . esc_html( (string) ( $h['generated_at'] ?? '' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		$this->card_close();
	}

	/**
	 * @param array<string,mixed> $s Settings.
	 */
	private function tab_api( array $s ): void {
		$this->card_open( __( 'API REST', 'billigoo' ) );
		if ( ! License::has_feature( 'rest_api' ) ) {
			$this->info( __( 'L’API REST et les webhooks sont inclus dans le plan Agence.', 'billigoo' ) );
		}
		echo '<div class="billigoo-fields">';
		$this->switch_field( 'api_enabled', __( 'Activer l’API', 'billigoo' ), __( 'Exposer les endpoints billigoo/v1 (Application Passwords)', 'billigoo' ), (int) $s['api_enabled'] );
		echo '<div class="billigoo-field billigoo-field--full"><label>' . esc_html__( 'Base des endpoints', 'billigoo' ) . '</label><div class="billigoo-kv"><code>' . esc_html( esc_url_raw( rest_url( 'billigoo/v1/' ) ) ) . '</code></div></div>';
		echo '</div>';
		$this->card_close();

		$this->card_open( __( 'Webhooks', 'billigoo' ) );
		echo '<div class="billigoo-fields">';
		$this->text( 'webhook_url', __( 'URL de destination', 'billigoo' ), (string) $s['webhook_url'], '', true );
		$this->secret( 'webhook_secret', __( 'Secret de signature (HMAC)', 'billigoo' ), (string) $s['webhook_secret'], true );
		echo '<div class="billigoo-field billigoo-field--full"><label>' . esc_html__( 'Événements', 'billigoo' ) . '</label>';
		$selected = (array) $s['webhook_events'];
		foreach ( Webhooks::available_events() as $value => $label ) {
			printf(
				'<label class="billigoo-switch" style="margin:4px 0;"><input type="checkbox" name="%1$s[webhook_events][]" value="%2$s" %3$s /><span class="billigoo-switch__track"></span><span>%4$s</span></label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $value ),
				checked( in_array( $value, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</div></div>';
		$this->card_close();

		$log = Webhooks::log_entries();
		if ( ! empty( $log ) ) {
			$this->card_open( __( 'Journal des livraisons', 'billigoo' ) );
			echo '<table class="billigoo-table"><thead><tr><th>' . esc_html__( 'Événement', 'billigoo' ) . '</th><th>' . esc_html__( 'Date', 'billigoo' ) . '</th><th>' . esc_html__( 'Erreur', 'billigoo' ) . '</th></tr></thead><tbody>';
			foreach ( $log as $entry ) {
				echo '<tr><td>' . esc_html( (string) ( $entry['event'] ?? '' ) ) . '</td><td class="is-muted">' . esc_html( (string) ( $entry['timestamp'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $entry['error'] ?? '' ) ?: '—' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			$this->card_close();
		}
	}

	/**
	 * Export tab (download forms + batch info).
	 */
	private function tab_export(): void {
		$after  = gmdate( 'Y-01-01' );
		$before = gmdate( 'Y-12-31' );
		$this->card_open( __( 'Exports comptables', 'billigoo' ) );
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( ExportEndpoint::NONCE ); ?>
			<div class="billigoo-fields">
				<div class="billigoo-field"><label for="bg_after"><?php esc_html_e( 'Du', 'billigoo' ); ?></label><input type="date" id="bg_after" name="after" value="<?php echo esc_attr( $after ); ?>" /></div>
				<div class="billigoo-field"><label for="bg_before"><?php esc_html_e( 'Au', 'billigoo' ); ?></label><input type="date" id="bg_before" name="before" value="<?php echo esc_attr( $before ); ?>" /></div>
			</div>
			<div class="billigoo-form-actions">
				<button type="submit" name="action" value="billigoo_export_csv" class="billigoo-btn billigoo-btn--ghost"><?php Ui::icon( 'table' ); ?><?php esc_html_e( 'Export CSV', 'billigoo' ); ?></button>
				<button type="submit" name="action" value="billigoo_export_fec" class="billigoo-btn billigoo-btn--primary" <?php disabled( ! License::has_feature( 'export_fec' ) ); ?>><?php Ui::icon( 'account_balance' ); ?><?php esc_html_e( 'Export FEC', 'billigoo' ); ?></button>
			</div>
		</form>
		<?php
		$this->card_close();

		$this->card_open( __( 'Génération rétroactive en lot', 'billigoo' ) );
		echo '<p class="billigoo-field__help">';
		esc_html_e( 'Sélectionnez des commandes dans WooCommerce → Commandes, puis l’action groupée « Générer Billigoo Factur-X » (jusqu’à 500 par lot, traitées en arrière-plan).', 'billigoo' );
		echo '</p>';
		echo '<a class="billigoo-btn billigoo-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=wc-orders' ) ) . '"><span class="material-symbols-outlined">list_alt</span>' . esc_html__( 'Ouvrir les commandes', 'billigoo' ) . '</a>';
		$this->card_close();
	}

	/**
	 * Licence tab.
	 */
	private function tab_license(): void {
		$data = License::data();
		$plan = License::plan();

		$this->card_open( __( 'Licence Billigoo', 'billigoo' ), array( 'success', strtoupper( License::label( $plan ) ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['billigoo_license'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['billigoo_license'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$map    = array(
				'activated'   => array( 'success', __( 'Licence activée.', 'billigoo' ) ),
				'deactivated' => array( 'info', __( 'Licence désactivée.', 'billigoo' ) ),
				'saved'       => array( 'success', __( 'Clé enregistrée.', 'billigoo' ) ),
				'error'       => array( 'warn', __( 'Activation impossible — vérifiez la clé.', 'billigoo' ) ),
			);
			if ( isset( $map[ $notice ] ) ) {
				$this->info( $map[ $notice ][1], $map[ $notice ][0] );
			}
		}
		?>
		<p class="billigoo-field__help"><?php esc_html_e( 'Le plan actif déverrouille les fonctionnalités. Sans serveur de licence, tout est déverrouillé par défaut pour le développement.', 'billigoo' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="billigoo_license" />
			<?php wp_nonce_field( 'billigoo_license' ); ?>
			<div class="billigoo-fields">
				<div class="billigoo-field billigoo-field--full">
					<label for="bg_license_key"><?php esc_html_e( 'Clé de licence', 'billigoo' ); ?></label>
					<input type="text" id="bg_license_key" name="billigoo_license_key" value="<?php echo esc_attr( (string) ( $data['key'] ?? '' ) ); ?>" placeholder="XXXX-XXXX-XXXX-XXXX" />
				</div>
				<div class="billigoo-field"><label><?php esc_html_e( 'Statut', 'billigoo' ); ?></label><div class="billigoo-kv"><code><?php echo esc_html( (string) ( $data['status'] ?? 'dev' ) ); ?></code></div></div>
				<div class="billigoo-field"><label><?php esc_html_e( 'Expiration', 'billigoo' ); ?></label><div class="billigoo-kv"><code><?php echo esc_html( (string) ( $data['expires_at'] ?? '—' ) ?: '—' ); ?></code></div></div>
			</div>
			<div class="billigoo-form-actions">
				<button type="submit" class="billigoo-btn billigoo-btn--primary"><?php Ui::icon( 'key' ); ?><?php esc_html_e( 'Activer', 'billigoo' ); ?></button>
				<?php if ( ! empty( $data ) ) : ?>
					<button type="submit" name="billigoo_license_deactivate" value="1" class="billigoo-btn billigoo-btn--ghost"><?php esc_html_e( 'Désactiver', 'billigoo' ); ?></button>
				<?php endif; ?>
			</div>
		</form>
		<?php
		$this->card_close();
	}

	/* ------------------------------------------------------------- Helpers */

	/**
	 * Open a card section, optionally with a status badge.
	 *
	 * @param string                    $title Title.
	 * @param array{0:string,1:string}|null $badge [modifier, label].
	 */
	private function card_open( string $title, ?array $badge = null ): void {
		echo '<section class="billigoo-card"><div class="billigoo-card__head"><h2>' . esc_html( $title ) . '</h2>';
		if ( $badge ) {
			echo '<span class="billigoo-badge billigoo-badge--' . esc_attr( $badge[0] ) . '">' . esc_html( $badge[1] ) . '</span>';
		}
		echo '</div><div class="billigoo-card__body">';
	}

	/**
	 * Close a card section.
	 */
	private function card_close(): void {
		echo '</div></section>';
	}

	/**
	 * Inline info note.
	 *
	 * @param string $message Message.
	 * @param string $type    Alert modifier (info|warn|success).
	 */
	private function info( string $message, string $type = 'info' ): void {
		echo '<div class="billigoo-alert billigoo-alert--' . esc_attr( $type ) . '" style="margin-bottom:18px;"><p class="billigoo-alert__msg"><span class="material-symbols-outlined">info</span>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Text field.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $help  Help.
	 * @param bool   $full  Full width.
	 */
	private function text( string $key, string $label, string $value, string $help = '', bool $full = false ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		echo '<div class="billigoo-field' . ( $full ? ' billigoo-field--full' : '' ) . '">';
		echo '<label for="bg_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="text" id="bg_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		if ( '' !== $help ) {
			echo '<p class="billigoo-field__help">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Secret (password) field — value never echoed; shows whether one is stored.
	 *
	 * @param string $key    Key.
	 * @param string $label  Label.
	 * @param string $stored Stored (encrypted) value.
	 * @param bool   $full   Full width.
	 */
	private function secret( string $key, string $label, string $stored, bool $full = false ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		$set  = '' !== $stored;
		echo '<div class="billigoo-field' . ( $full ? ' billigoo-field--full' : '' ) . '">';
		echo '<label for="bg_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="password" autocomplete="new-password" id="bg_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( $set ? '•••••••• ' . __( '(enregistré)', 'billigoo' ) : __( 'Non défini', 'billigoo' ) ) . '" />';
		echo '<p class="billigoo-field__help">' . esc_html__( 'Laissez vide pour conserver la valeur actuelle. Stockée chiffrée.', 'billigoo' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Textarea field (full width).
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function textarea( string $key, string $label, string $value ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		echo '<div class="billigoo-field billigoo-field--full">';
		echo '<label for="bg_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<textarea id="bg_' . esc_attr( $key ) . '" rows="2" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		echo '</div>';
	}

	/**
	 * Select field.
	 *
	 * @param string                $key     Key.
	 * @param string                $label   Label.
	 * @param array<string,string>  $options Options.
	 * @param string                $value   Current value.
	 */
	private function select( string $key, string $label, array $options, string $value ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		echo '<div class="billigoo-field">';
		echo '<label for="bg_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="bg_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $val => $text ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $val ), selected( $value, (string) $val, false ), esc_html( $text ) );
		}
		echo '</select></div>';
	}

	/**
	 * Colour field.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function color( string $key, string $label, string $value ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		echo '<div class="billigoo-field">';
		echo '<label for="bg_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="color" id="bg_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" style="height:40px;padding:4px;" />';
		echo '</div>';
	}

	/**
	 * Toggle switch field wrapped in a grid cell.
	 *
	 * @param string $key     Key.
	 * @param string $label   Field label.
	 * @param string $inline  Inline switch label.
	 * @param int    $checked Whether on.
	 */
	private function switch_field( string $key, string $label, string $inline, int $checked ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		echo '<div class="billigoo-field">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
		echo '<label class="billigoo-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( 1, $checked, false ) . ' /><span class="billigoo-switch__track"></span><span>' . esc_html( $inline ) . '</span></label>';
		echo '</div>';
	}
}
