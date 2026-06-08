<?php
/**
 * Admin bootstrap: menus, pages, meta box, download endpoints, assets.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Billigoo back-office: a dedicated top-level menu holding the
 * dashboard, invoice list and settings, plus the order meta box, the secure
 * download endpoints and the onboarding wizard.
 */
final class Admin {

	private DashboardPage $dashboard_page;
	private SettingsPage $settings_page;
	private InvoicesPage $invoices_page;
	private WizardPage $wizard_page;
	private OrderMetaBox $order_meta_box;
	private DownloadEndpoint $downloads;
	private ExportEndpoint $exports;

	/**
	 * Page hook suffixes that should receive the Billigoo admin assets.
	 *
	 * @var array<int,string>
	 */
	private array $page_hooks = array();

	public function __construct() {
		$this->dashboard_page = new DashboardPage();
		$this->settings_page  = new SettingsPage();
		$this->invoices_page  = new InvoicesPage();
		$this->wizard_page    = new WizardPage();
		$this->order_meta_box = new OrderMetaBox();
		$this->downloads      = new DownloadEndpoint();
		$this->exports        = new ExportEndpoint();
	}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );

		$this->settings_page->register();
		$this->wizard_page->register();
		$this->order_meta_box->register();
		$this->downloads->register();
		$this->exports->register();
	}

	/**
	 * Build the dedicated Billigoo menu and its sub-pages.
	 */
	public function menus(): void {
		$this->page_hooks[] = add_menu_page(
			__( 'Billigoo', 'billigoo' ),
			__( 'Billigoo', 'billigoo' ),
			'manage_woocommerce',
			'billigoo',
			array( $this->dashboard_page, 'render' ),
			'dashicons-media-spreadsheet',
			56
		);

		$this->page_hooks[] = add_submenu_page(
			'billigoo',
			__( 'Tableau de bord', 'billigoo' ),
			__( 'Tableau de bord', 'billigoo' ),
			'manage_woocommerce',
			'billigoo',
			array( $this->dashboard_page, 'render' )
		);

		$this->page_hooks[] = add_submenu_page(
			'billigoo',
			__( 'Factures Billigoo', 'billigoo' ),
			__( 'Factures', 'billigoo' ),
			'manage_woocommerce',
			'billigoo-invoices',
			array( $this->invoices_page, 'render' )
		);

		$this->page_hooks[] = add_submenu_page(
			'billigoo',
			__( 'Réglages Billigoo', 'billigoo' ),
			__( 'Réglages', 'billigoo' ),
			'manage_woocommerce',
			'billigoo-settings',
			array( $this->settings_page, 'render' )
		);

		// Onboarding wizard: reachable by URL but kept out of the menu.
		$this->page_hooks[] = add_submenu_page(
			'billigoo',
			__( 'Assistant de configuration', 'billigoo' ),
			__( 'Assistant', 'billigoo' ),
			'manage_woocommerce',
			'billigoo-setup',
			array( $this->wizard_page, 'render' )
		);
		remove_submenu_page( 'billigoo', 'billigoo-setup' );
	}

	/**
	 * Load the design-system stylesheet (and slide-over script) on our pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'billigoo-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0..1,0&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts are versionless.
		);
		wp_enqueue_style(
			'billigoo-admin',
			BILLIGOO_URL . 'public/assets/css/admin.css',
			array(),
			BILLIGOO_VERSION
		);
		wp_enqueue_script(
			'billigoo-admin',
			BILLIGOO_URL . 'public/assets/js/admin.js',
			array(),
			BILLIGOO_VERSION,
			true
		);
	}

	/**
	 * Send freshly-activated, not-yet-configured stores to the setup wizard.
	 */
	public function maybe_redirect_to_wizard(): void {
		if ( ! get_transient( 'billigoo_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'billigoo_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( get_option( 'billigoo_setup_complete' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=billigoo-setup' ) );
		exit;
	}
}
