<?php
/**
 * Plugin Name: Billigoo
 * Plugin URI: https://billigoo.fr
 * Description: Facturation électronique Factur-X conforme 2026 pour WooCommerce.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 * Author: Billigoo
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: billigoo
 * Domain Path: /languages
 *
 * @package Billigoo
 */

defined( 'ABSPATH' ) || exit;

define( 'BILLIGOO_VERSION', '1.0.0' );
define( 'BILLIGOO_FILE', __FILE__ );
define( 'BILLIGOO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BILLIGOO_URL', plugin_dir_url( __FILE__ ) );
define( 'BILLIGOO_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoloader (mPDF, atgp/factur-x, Billigoo\ classes).
$billigoo_autoload = BILLIGOO_PATH . 'vendor/autoload.php';
if ( ! is_readable( $billigoo_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Billigoo : dépendances manquantes. Exécutez « composer install » dans le dossier du plugin.', 'billigoo' );
			echo '</p></div>';
		}
	);
	return;
}
require_once $billigoo_autoload;

// Declare HPOS (custom order tables) compatibility before WooCommerce initializes.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BILLIGOO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BILLIGOO_FILE, true );
		}
	}
);

register_activation_hook( __FILE__, array( \Billigoo\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Billigoo\Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin once all plugins (incl. WooCommerce) are loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'Billigoo requiert WooCommerce actif.', 'billigoo' );
					echo '</p></div>';
				}
			);
			return;
		}

		load_plugin_textdomain( 'billigoo', false, dirname( BILLIGOO_BASENAME ) . '/languages' );

		\Billigoo\Plugin::instance()->run();
	}
);
