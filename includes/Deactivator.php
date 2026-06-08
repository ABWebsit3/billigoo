<?php
/**
 * Deactivation routine.
 *
 * @package Billigoo
 */

namespace Billigoo;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once on plugin deactivation. Intentionally conservative: data and
 * generated invoices are preserved (cleanup happens in uninstall.php only).
 */
final class Deactivator {

	/**
	 * Release transient state on deactivation.
	 */
	public static function deactivate(): void {
		delete_transient( 'billigoo_invoice_lock' );

		wp_clear_scheduled_hook( \Billigoo\Pa\TransmissionManager::POLL_HOOK );
		\Billigoo\Ereporting\Scheduler::clear();

		flush_rewrite_rules();
	}
}
