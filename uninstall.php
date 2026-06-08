<?php
/**
 * Uninstall cleanup.
 *
 * Removes plugin options and transient state. Generated invoice files are
 * deliberately preserved: they are legally-required accounting documents and
 * must never be destroyed automatically.
 *
 * @package Billigoo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'billigoo_settings' );
delete_option( 'billigoo_last_invoice_number' );
delete_option( 'billigoo_invoice_number_year' );
delete_option( 'billigoo_setup_complete' );
delete_option( 'billigoo_license' );
delete_option( 'billigoo_license_endpoint' );
delete_option( 'billigoo_ereporting_history' );
delete_option( 'billigoo_webhook_log' );
delete_transient( 'billigoo_invoice_lock' );

wp_clear_scheduled_hook( 'billigoo_poll_pa_status' );
wp_clear_scheduled_hook( 'billigoo_ereporting_run' );
