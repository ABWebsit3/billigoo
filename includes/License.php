<?php
/**
 * Licence plan + feature gating (client side).
 *
 * @package Billigoo
 */

namespace Billigoo;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the active plan and which features it unlocks.
 *
 * The licence *server* (billigoo.fr) is a separate project; here we keep the
 * client: an optional remote activation call plus local feature gating. By
 * default the plan resolves to `agence` (everything unlocked) so the plugin is
 * fully usable without a server — override via the `billigoo_license_plan`
 * filter or by activating a key.
 */
final class License {

	public const OPTION = 'billigoo_license';

	public const PLAN_STARTER = 'starter';
	public const PLAN_PRO     = 'pro';
	public const PLAN_AGENCE  = 'agence';

	/**
	 * Feature → minimum plan required.
	 *
	 * @var array<string,string>
	 */
	private const FEATURES = array(
		'pa_transmission' => self::PLAN_PRO,
		'ereporting'      => self::PLAN_PRO,
		'profile_en16931' => self::PLAN_PRO,
		'export_fec'      => self::PLAN_PRO,
		'template_premium'=> self::PLAN_PRO,
		'rest_api'        => self::PLAN_AGENCE,
		'webhooks'        => self::PLAN_AGENCE,
		'batch'           => self::PLAN_AGENCE,
		'accounts_mapping'=> self::PLAN_AGENCE,
	);

	/**
	 * Plan rank for comparison.
	 *
	 * @var array<string,int>
	 */
	private const RANK = array(
		self::PLAN_STARTER => 0,
		self::PLAN_PRO     => 1,
		self::PLAN_AGENCE  => 2,
	);

	/**
	 * Current active plan slug.
	 */
	public static function plan(): string {
		$stored = self::data();
		$plan   = ( 'active' === ( $stored['status'] ?? '' ) && isset( $stored['plan'] ) )
			? (string) $stored['plan']
			: self::PLAN_AGENCE; // Dev default: everything unlocked.

		/**
		 * Filter the resolved licence plan (used for testing / overrides).
		 *
		 * @param string $plan One of starter|pro|agence.
		 */
		$plan = (string) apply_filters( 'billigoo_license_plan', $plan );
		return isset( self::RANK[ $plan ] ) ? $plan : self::PLAN_AGENCE;
	}

	/**
	 * Whether the active plan unlocks a feature.
	 *
	 * @param string $feature Feature key.
	 */
	public static function has_feature( string $feature ): bool {
		$required = self::FEATURES[ $feature ] ?? self::PLAN_STARTER;
		return self::RANK[ self::plan() ] >= self::RANK[ $required ];
	}

	/**
	 * Stored licence data (key, plan, status, expires_at).
	 *
	 * @return array<string,mixed>
	 */
	public static function data(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Human label for a plan.
	 *
	 * @param string|null $plan Plan slug (defaults to active).
	 */
	public static function label( ?string $plan = null ): string {
		$plan   = $plan ?? self::plan();
		$labels = array(
			self::PLAN_STARTER => 'Starter',
			self::PLAN_PRO     => 'Pro',
			self::PLAN_AGENCE  => 'Agence',
		);
		return $labels[ $plan ] ?? 'Starter';
	}

	/**
	 * Activate a licence key against the configured remote endpoint.
	 *
	 * Degrades gracefully: with no endpoint configured the key is simply stored
	 * as pending so local development is never blocked.
	 *
	 * @param string $key Licence key.
	 * @return array{ok:bool,message:string}
	 */
	public static function activate( string $key ): array {
		$key = trim( $key );
		if ( '' === $key ) {
			return array( 'ok' => false, 'message' => __( 'Clé de licence vide.', 'billigoo' ) );
		}

		$endpoint = (string) apply_filters( 'billigoo_license_endpoint', get_option( 'billigoo_license_endpoint', '' ) );
		if ( '' === $endpoint ) {
			self::store( array( 'key' => $key, 'plan' => self::PLAN_PRO, 'status' => 'pending', 'expires_at' => '' ) );
			return array( 'ok' => true, 'message' => __( 'Clé enregistrée (validation distante non configurée).', 'billigoo' ) );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'key'    => $key,
						'domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['valid'] ) ) {
			return array( 'ok' => false, 'message' => __( 'Licence refusée par le serveur.', 'billigoo' ) );
		}

		self::store(
			array(
				'key'        => $key,
				'plan'       => isset( $body['plan'] ) ? (string) $body['plan'] : self::PLAN_PRO,
				'status'     => 'active',
				'expires_at' => isset( $body['expires_at'] ) ? (string) $body['expires_at'] : '',
			)
		);
		return array( 'ok' => true, 'message' => __( 'Licence activée.', 'billigoo' ) );
	}

	/**
	 * Remove the stored licence.
	 */
	public static function deactivate(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Persist licence data.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	private static function store( array $data ): void {
		update_option( self::OPTION, $data, false );
	}
}
