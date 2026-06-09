<?php
/**
 * Activation routine.
 *
 * @package Billigoo
 */

namespace Billigoo;

use Billigoo\Core\InvoiceStore;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once on plugin activation.
 */
final class Activator {

	/**
	 * Seed defaults, the invoice counter and the protected uploads directory.
	 */
	public static function activate(): void {
		// Persist default settings if none exist yet.
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}

		// Seed the sequential counter (non-autoloaded so updates stay cheap/atomic).
		if ( false === get_option( 'billigoo_last_invoice_number', false ) ) {
			add_option( 'billigoo_last_invoice_number', 0, '', 'no' );
		}
		if ( false === get_option( 'billigoo_invoice_number_year', false ) ) {
			add_option( 'billigoo_invoice_number_year', (int) gmdate( 'Y' ), '', 'no' );
		}

		// Create wp-content/uploads/billigoo with a deny-all .htaccess + index guard.
		InvoiceStore::ensure_protected_base_dir();

		// Schedule the hourly PA status poll and align e-reporting with settings.
		if ( ! wp_next_scheduled( \Billigoo\Pa\TransmissionManager::POLL_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', \Billigoo\Pa\TransmissionManager::POLL_HOOK );
		}
		( new \Billigoo\Ereporting\Scheduler() )->ensure_scheduled();

		// Send the admin to the onboarding wizard on the next page load, unless
		// setup has already been completed on a previous activation.
		if ( ! get_option( 'billigoo_setup_complete' ) ) {
			set_transient( 'billigoo_activation_redirect', 1, 60 );
		}

		flush_rewrite_rules();
	}
}
