<?php
/**
 * E-reporting cron scheduling.
 *
 * @package Billigoo
 */

namespace Billigoo\Ereporting;

use Billigoo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers monthly/quarterly schedules and (re)schedules the e-reporting run to
 * match the current settings.
 */
final class Scheduler {

	public const HOOK = 'billigoo_ereporting_run';

	/**
	 * Wire schedules + handler, and keep the event in sync with settings.
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'reschedule' ) );
	}

	/**
	 * Add custom recurrences.
	 *
	 * @param array<string,array{interval:int,display:string}> $schedules Existing.
	 * @return array<string,array{interval:int,display:string}>
	 */
	public function schedules( array $schedules ): array {
		$schedules['billigoo_monthly']   = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => __( 'Une fois par mois (Billigoo)', 'billigoo' ) );
		$schedules['billigoo_quarterly'] = array( 'interval' => 91 * DAY_IN_SECONDS, 'display' => __( 'Une fois par trimestre (Billigoo)', 'billigoo' ) );
		return $schedules;
	}

	/**
	 * Ensure the event is scheduled (or cleared) per current settings.
	 */
	public function ensure_scheduled(): void {
		$enabled = (bool) Settings::get( 'ereporting_enabled' );
		$next    = wp_next_scheduled( self::HOOK );

		if ( ! $enabled ) {
			if ( $next ) {
				wp_unschedule_event( $next, self::HOOK );
			}
			return;
		}

		$recurrence = 'quarterly' === Settings::get( 'ereporting_period', 'monthly' ) ? 'billigoo_quarterly' : 'billigoo_monthly';

		// Reschedule cleanly so a periodicity change takes effect.
		if ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
		wp_schedule_event( strtotime( 'first day of next month midnight' ), $recurrence, self::HOOK );
	}

	/**
	 * Re-sync scheduling when settings are saved.
	 */
	public function reschedule(): void {
		$this->ensure_scheduled();
	}

	/**
	 * Run the report.
	 */
	public function run(): void {
		( new EreportingManager() )->run();
	}

	/**
	 * Clear the scheduled event (deactivation).
	 */
	public static function clear(): void {
		$next = wp_next_scheduled( self::HOOK );
		if ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}
}
