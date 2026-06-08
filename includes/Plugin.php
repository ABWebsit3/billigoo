<?php
/**
 * Main plugin orchestrator.
 *
 * @package Billigoo
 */

namespace Billigoo;

use Billigoo\Admin\Admin;
use Billigoo\Api\RestApi;
use Billigoo\Api\Webhooks;
use Billigoo\Ereporting\Scheduler;
use Billigoo\Export\BatchGenerator;
use Billigoo\Pa\TransmissionManager;
use Billigoo\WooCommerce\CheckoutFields;
use Billigoo\WooCommerce\BlocksIntegration;
use Billigoo\WooCommerce\EmailAttachment;
use Billigoo\WooCommerce\EmailNotice;
use Billigoo\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every subsystem to WordPress / WooCommerce hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Register all hooks.
	 */
	public function run(): void {
		// Order data capture: classic (shortcode) checkout and block checkout.
		( new CheckoutFields() )->register();
		( new BlocksIntegration() )->register();

		// Automatic invoice generation on order status change.
		( new Integration() )->register();

		// Attach the PDF to the relevant WooCommerce email and add the branded notice.
		( new EmailAttachment() )->register();
		( new EmailNotice() )->register();

		// Domain events → webhooks.
		( new Events() )->register();
		( new Webhooks() )->register();

		// Phase 2/3: PA transmission, REST API, e-reporting cron, batch generation.
		( new TransmissionManager() )->register();
		( new RestApi() )->register();
		( new Scheduler() )->register();
		( new BatchGenerator() )->register();

		// Admin: dashboard, settings, invoice list, meta box, secure downloads, exports.
		if ( is_admin() ) {
			( new Admin() )->register();
		}
	}
}
