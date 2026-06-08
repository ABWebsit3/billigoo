<?php
/**
 * B2B fields for the WooCommerce block checkout.
 *
 * @package Billigoo
 */

namespace Billigoo\WooCommerce;

use Billigoo\Core\SiretValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the professional fields in the block-based checkout using the
 * Additional Checkout Fields API (WooCommerce 8.9+), and syncs their values
 * into the canonical Billigoo order meta keys so the generator reads one source.
 */
final class BlocksIntegration {

	private const F_BUSINESS = 'billigoo/is_business';
	private const F_COMPANY  = 'billigoo/company';
	private const F_SIRET    = 'billigoo/siret';
	private const F_VAT      = 'billigoo/vat';

	/**
	 * Register hooks (no-op when the API is unavailable).
	 */
	public function register(): void {
		add_action( 'woocommerce_init', array( $this, 'register_fields' ) );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'sync_value' ), 10, 4 );
		add_action( 'woocommerce_blocks_validate_location_contact_fields', array( $this, 'validate_contact' ), 10, 3 );
	}

	/**
	 * Register the additional checkout fields.
	 */
	public function register_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::F_BUSINESS,
				'label'    => __( 'Je commande en tant que professionnel', 'billigoo' ),
				'location' => 'contact',
				'type'     => 'checkbox',
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::F_COMPANY,
				'label'    => __( 'Raison sociale', 'billigoo' ),
				'location' => 'contact',
				'type'     => 'text',
				'required' => false,
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::F_SIRET,
				'label'             => __( 'SIRET', 'billigoo' ),
				'location'          => 'contact',
				'type'              => 'text',
				'required'          => false,
				'sanitize_callback' => static function ( $value ) {
					return preg_replace( '/\D/', '', (string) $value );
				},
				'validate_callback' => static function ( $value ) {
					if ( '' !== $value && ! SiretValidator::is_valid_siret( (string) $value ) ) {
						return new \WP_Error( 'billigoo_siret_invalid', __( 'Le numéro SIRET saisi est invalide.', 'billigoo' ) );
					}
					return true;
				},
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::F_VAT,
				'label'             => __( 'Numéro de TVA intracommunautaire', 'billigoo' ),
				'location'          => 'contact',
				'type'              => 'text',
				'required'          => false,
				'sanitize_callback' => static function ( $value ) {
					return strtoupper( sanitize_text_field( (string) $value ) );
				},
			)
		);
	}

	/**
	 * Cross-field validation: require company + SIRET when "professional" is set.
	 *
	 * @param \WP_Error            $errors Error accumulator.
	 * @param array<string,mixed>  $fields Submitted contact fields.
	 * @param string               $group  Field group.
	 */
	public function validate_contact( \WP_Error $errors, array $fields, string $group ): void {
		if ( empty( $fields[ self::F_BUSINESS ] ) ) {
			return;
		}
		if ( empty( $fields[ self::F_COMPANY ] ) ) {
			$errors->add( 'billigoo_company_required', __( 'Veuillez indiquer votre raison sociale.', 'billigoo' ) );
		}
		if ( empty( $fields[ self::F_SIRET ] ) ) {
			$errors->add( 'billigoo_siret_required', __( 'Veuillez indiquer votre numéro SIRET.', 'billigoo' ) );
		}
	}

	/**
	 * Mirror an additional field value into the canonical Billigoo order meta.
	 *
	 * @param string $key       Field id.
	 * @param mixed  $value     Submitted value.
	 * @param string $group     Field group.
	 * @param object $wc_object Target object (order or customer).
	 */
	public function sync_value( string $key, $value, string $group, $wc_object ): void {
		if ( ! $wc_object instanceof \WC_Order && ! $wc_object instanceof \WC_Customer ) {
			return;
		}

		switch ( $key ) {
			case self::F_BUSINESS:
				$wc_object->update_meta_data( OrderMeta::IS_BUSINESS, $value ? 'yes' : 'no' );
				break;
			case self::F_COMPANY:
				$wc_object->update_meta_data( OrderMeta::COMPANY_NAME, sanitize_text_field( (string) $value ) );
				break;
			case self::F_SIRET:
				$wc_object->update_meta_data( OrderMeta::SIRET, preg_replace( '/\D/', '', (string) $value ) );
				break;
			case self::F_VAT:
				$wc_object->update_meta_data( OrderMeta::VAT_NUMBER, strtoupper( sanitize_text_field( (string) $value ) ) );
				break;
		}
	}
}
