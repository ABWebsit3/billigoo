<?php
/**
 * Settings access helper.
 *
 * @package Billigoo
 */

namespace Billigoo;

defined( 'ABSPATH' ) || exit;

/**
 * Centralised, typed access to the plugin settings option.
 *
 * All settings live in a single option array `billigoo_settings`, except the
 * invoice counter which is stored separately for atomic updates.
 */
final class Settings {

	public const OPTION = 'billigoo_settings';

	/**
	 * Default values, also used to whitelist keys on save.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'seller_name'         => '',
			'seller_siren'        => '',
			'seller_siret'        => '',
			'seller_vat'          => '',
			'seller_address'      => '',
			'seller_postcode'     => '',
			'seller_city'         => '',
			'seller_country'      => 'FR',
			'number_prefix'       => 'FA-{year}-',
			'number_padding'      => 4,
			'number_yearly_reset' => 1,
			'trigger_status'      => 'processing',
			'profile'             => 'basic',
			'payment_terms'       => '',
			'attach_email'        => 1,
			// Facturation / template (Phase 4).
			'currency'            => 'EUR',
			'legal_mentions'      => '',
			'template'            => 'default',
			'logo_url'            => '',
			'primary_color'       => '#0ea5e9',
			// Plateforme Agréée (PDP) — Phase 2.
			'pa_provider'             => '',
			'pa_api_key'              => '',
			'pa_environment'          => 'sandbox',
			'pa_auto_transmit'        => 0,
			'pa_retry'                => 1,
			'pa_b2g'                  => 0,
			'pa_chorus_client_id'     => '',
			'pa_chorus_client_secret' => '',
			// E-reporting (Phase 3).
			'ereporting_enabled'  => 0,
			'ereporting_period'   => 'monthly',
			// Export FEC accounting (Phase 3).
			'fec_journal_code'    => 'VT',
			'fec_journal_lib'     => 'Ventes',
			'fec_account_client'  => '411000',
			'fec_account_sales'   => '707000',
			'fec_account_vat'     => '445710',
			// API & webhooks (Phase 3).
			'api_enabled'         => 1,
			'webhook_url'         => '',
			'webhook_secret'      => '',
			'webhook_events'      => array(),
		);
	}

	/**
	 * Keys whose stored value is encrypted at rest.
	 *
	 * @return array<int,string>
	 */
	public static function secret_keys(): array {
		return array( 'pa_api_key', 'pa_chorus_client_secret', 'webhook_secret' );
	}

	/**
	 * Read and decrypt a secret setting.
	 *
	 * @param string $key Setting key.
	 */
	public static function secret( string $key ): string {
		return Crypto::decrypt( (string) self::get( $key, '' ) );
	}

	/**
	 * Setting keys belonging to each settings tab (for partial saves).
	 *
	 * @param string $tab Tab slug.
	 * @return array<int,string>|null Keys for the tab, or null for "all".
	 */
	private static function tab_keys( string $tab ): ?array {
		$map = array(
			'general'    => array( 'seller_name', 'seller_siren', 'seller_siret', 'seller_vat', 'seller_address', 'seller_postcode', 'seller_city', 'seller_country', 'number_prefix', 'number_padding', 'number_yearly_reset', 'trigger_status' ),
			'billing'    => array( 'profile', 'currency', 'payment_terms', 'legal_mentions', 'attach_email', 'template', 'logo_url', 'primary_color' ),
			'pa'         => array( 'pa_provider', 'pa_api_key', 'pa_environment', 'pa_auto_transmit', 'pa_retry', 'pa_b2g', 'pa_chorus_client_id', 'pa_chorus_client_secret' ),
			'ereporting' => array( 'ereporting_enabled', 'ereporting_period' ),
			'export'     => array( 'fec_journal_code', 'fec_journal_lib', 'fec_account_client', 'fec_account_sales', 'fec_account_vat' ),
			'api'        => array( 'api_enabled', 'webhook_url', 'webhook_secret', 'webhook_events' ),
		);
		return $map[ $tab ] ?? null;
	}

	/**
	 * Supported Plateformes Agréées (Plateformes de Dématérialisation Partenaires).
	 *
	 * @return array<string,string> provider slug => display label.
	 */
	public static function pa_providers(): array {
		return array(
			'pennylane'  => 'Pennylane',
			'chorus_pro' => 'Chorus Pro',
			'qonto'      => 'Qonto',
			'sage'       => 'Sage',
			'other'      => __( 'Autre PDP', 'billigoo' ),
		);
	}

	/**
	 * Human label of the currently selected PA provider (or empty string).
	 */
	public static function pa_provider_label(): string {
		$providers = self::pa_providers();
		$slug      = (string) self::get( 'pa_provider', '' );
		return $providers[ $slug ] ?? '';
	}

	/**
	 * Whether a Plateforme Agréée has been selected and keyed in.
	 */
	public static function is_pa_configured(): bool {
		$all = self::all();
		if ( '' === $all['pa_provider'] ) {
			return false;
		}
		if ( 'chorus_pro' === $all['pa_provider'] ) {
			return '' !== $all['pa_chorus_client_id'] && '' !== $all['pa_chorus_client_secret'];
		}
		return '' !== $all['pa_api_key'];
	}

	/**
	 * Full settings array merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when missing.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Whether the seller identity is sufficiently configured to emit invoices.
	 */
	public static function is_seller_configured(): bool {
		$all = self::all();
		return '' !== $all['seller_name']
			&& '' !== $all['seller_siret']
			&& '' !== $all['seller_address'];
	}

	/**
	 * Sanitize the settings array before persisting.
	 *
	 * Tab-aware: when a `_billigoo_tab` marker is present only that tab's keys are
	 * processed and the rest are preserved from the stored option, so saving one
	 * settings tab never wipes the others. With no marker (e.g. the wizard, which
	 * merges the full array) every key is processed.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$out  = self::all();
		$tab  = isset( $input['_billigoo_tab'] ) ? sanitize_key( (string) $input['_billigoo_tab'] ) : '';
		$keys = self::tab_keys( $tab ) ?? array_keys( self::defaults() );

		foreach ( $keys as $key ) {
			$out[ $key ] = self::sanitize_field( $key, $input, $out );
		}

		// Derive SIREN from SIRET when omitted (SIREN = first 9 digits of SIRET).
		if ( '' === $out['seller_siren'] && strlen( (string) $out['seller_siret'] ) >= 9 ) {
			$out['seller_siren'] = substr( (string) $out['seller_siret'], 0, 9 );
		}

		return $out;
	}

	/**
	 * Sanitize a single setting from raw input, falling back to the current value.
	 *
	 * @param string              $key     Setting key.
	 * @param array<string,mixed> $input   Raw input.
	 * @param array<string,mixed> $current Current (stored) values.
	 * @return mixed
	 */
	private static function sanitize_field( string $key, array $input, array $current ) {
		// Secrets: encrypt new values, keep existing when the field is left blank.
		if ( in_array( $key, self::secret_keys(), true ) ) {
			$raw = trim( (string) ( $input[ $key ] ?? '' ) );
			if ( '' === $raw ) {
				return $current[ $key ] ?? '';
			}
			return Crypto::encrypt( $raw );
		}

		switch ( $key ) {
			case 'seller_siren':
			case 'seller_siret':
				return preg_replace( '/\D/', '', (string) ( $input[ $key ] ?? '' ) );
			case 'seller_vat':
				return strtoupper( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ) );
			case 'seller_country':
				return strtoupper( substr( sanitize_text_field( (string) ( $input[ $key ] ?? 'FR' ) ), 0, 2 ) );
			case 'number_padding':
				return max( 1, min( 10, absint( $input[ $key ] ?? 4 ) ) );
			case 'number_yearly_reset':
			case 'attach_email':
			case 'pa_auto_transmit':
			case 'pa_b2g':
			case 'ereporting_enabled':
			case 'api_enabled':
				return empty( $input[ $key ] ) ? 0 : 1;
			case 'pa_retry':
				return max( 0, min( 3, absint( $input[ $key ] ?? 1 ) ) );
			case 'trigger_status':
				$v = sanitize_text_field( (string) ( $input[ $key ] ?? 'processing' ) );
				return in_array( $v, array( 'processing', 'completed', 'on-hold' ), true ) ? $v : 'processing';
			case 'profile':
				$v = sanitize_key( (string) ( $input[ $key ] ?? 'basic' ) );
				return in_array( $v, array( 'basic', 'en16931' ), true ) ? $v : 'basic';
			case 'template':
				$v = sanitize_key( (string) ( $input[ $key ] ?? 'default' ) );
				return in_array( $v, array( 'default', 'premium' ), true ) ? $v : 'default';
			case 'currency':
				return strtoupper( substr( sanitize_text_field( (string) ( $input[ $key ] ?? 'EUR' ) ), 0, 3 ) ) ?: 'EUR';
			case 'primary_color':
				$v = sanitize_hex_color( (string) ( $input[ $key ] ?? '' ) );
				return $v ?: '#0ea5e9';
			case 'logo_url':
			case 'webhook_url':
				return esc_url_raw( (string) ( $input[ $key ] ?? '' ) );
			case 'payment_terms':
			case 'legal_mentions':
				return sanitize_textarea_field( (string) ( $input[ $key ] ?? '' ) );
			case 'pa_provider':
				$v = sanitize_key( (string) ( $input[ $key ] ?? '' ) );
				return array_key_exists( $v, self::pa_providers() ) ? $v : '';
			case 'pa_environment':
				$v = sanitize_key( (string) ( $input[ $key ] ?? 'sandbox' ) );
				return in_array( $v, array( 'sandbox', 'production' ), true ) ? $v : 'sandbox';
			case 'ereporting_period':
				$v = sanitize_key( (string) ( $input[ $key ] ?? 'monthly' ) );
				return in_array( $v, array( 'monthly', 'quarterly' ), true ) ? $v : 'monthly';
			case 'webhook_events':
				$raw = (array) ( $input[ $key ] ?? array() );
				return array_values( array_map( 'sanitize_text_field', $raw ) );
			default:
				return sanitize_text_field( (string) ( $input[ $key ] ?? ( $current[ $key ] ?? '' ) ) );
		}
	}
}
