<?php
/**
 * Outgoing webhooks for Billigoo domain events.
 *
 * @package Billigoo
 */

namespace Billigoo\Api;

use Billigoo\License;
use Billigoo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribes to the single `billigoo_event` hook and POSTs signed payloads to a
 * configured endpoint, keeping a bounded delivery log.
 */
final class Webhooks {

	private const LOG_OPTION = 'billigoo_webhook_log';
	private const LOG_MAX    = 30;

	/**
	 * Subscribe to domain events.
	 */
	public function register(): void {
		add_action( 'billigoo_event', array( $this, 'deliver' ), 10, 2 );
	}

	/**
	 * Deliver an event to the configured endpoint.
	 *
	 * @param string              $event Event name.
	 * @param array<string,mixed> $data  Payload data.
	 */
	public function deliver( string $event, array $data ): void {
		if ( ! License::has_feature( 'webhooks' ) ) {
			return;
		}
		$url = (string) Settings::get( 'webhook_url', '' );
		if ( '' === $url ) {
			return;
		}
		$events = (array) Settings::get( 'webhook_events', array() );
		if ( ! empty( $events ) && ! in_array( $event, $events, true ) ) {
			return;
		}

		$payload = wp_json_encode( array_merge( array( 'event' => $event ), $data ) );
		$secret  = Settings::secret( 'webhook_secret' );
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $secret ) {
			$headers['X-Billigoo-Signature'] = 'sha256=' . hash_hmac( 'sha256', (string) $payload, $secret );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 10,
				'blocking'    => false,
				'headers'     => $headers,
				'body'        => $payload,
				'data_format' => 'body',
			)
		);

		$this->log(
			array(
				'event'     => $event,
				'url'       => $url,
				'timestamp' => gmdate( 'c' ),
				'error'     => is_wp_error( $response ) ? $response->get_error_message() : '',
			)
		);
	}

	/**
	 * Append to the bounded delivery log.
	 *
	 * @param array<string,mixed> $entry Entry.
	 */
	private function log( array $entry ): void {
		$log = self::log_entries();
		array_unshift( $log, $entry );
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/**
	 * Read the delivery log (most recent first).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function log_entries(): array {
		$l = get_option( self::LOG_OPTION, array() );
		return is_array( $l ) ? $l : array();
	}

	/**
	 * Events that can be subscribed to (slug => label).
	 *
	 * @return array<string,string>
	 */
	public static function available_events(): array {
		return array(
			'invoice.generated' => __( 'Facture générée', 'billigoo' ),
			'invoice.sent'      => __( 'Facture transmise', 'billigoo' ),
			'invoice.accepted'  => __( 'Facture acceptée', 'billigoo' ),
			'invoice.rejected'  => __( 'Facture rejetée', 'billigoo' ),
			'ereporting.sent'   => __( 'E-reporting transmis', 'billigoo' ),
		);
	}
}
