<?php
/**
 * B2B fields for the classic checkout + My Account prefill.
 *
 * @package Billigoo
 */

namespace Billigoo\WooCommerce;

use Billigoo\Core\SiretValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "I'm ordering as a professional" toggle and SIRET / VAT / company
 * fields to the classic (shortcode) checkout, validates them, and persists them
 * to the order and the customer profile.
 */
final class CheckoutFields {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_fields' ), 10, 2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_billigoo_validate_siret', array( $this, 'ajax_validate_siret' ) );
		add_action( 'wp_ajax_nopriv_billigoo_validate_siret', array( $this, 'ajax_validate_siret' ) );
	}

	/**
	 * Render the B2B fields below the billing form.
	 *
	 * @param \WC_Checkout|null $checkout Checkout instance.
	 */
	public function render_fields( $checkout = null ): void {
		$customer    = WC()->customer;
		$is_business = $customer ? $customer->get_meta( OrderMeta::IS_BUSINESS ) : '';
		$company     = $customer ? $customer->get_meta( OrderMeta::COMPANY_NAME ) : '';
		$siret       = $customer ? $customer->get_meta( OrderMeta::SIRET ) : '';
		$vat         = $customer ? $customer->get_meta( OrderMeta::VAT_NUMBER ) : '';

		echo '<div id="billigoo-b2b-fields" class="billigoo-b2b">';
		echo '<div class="billigoo-b2b__head">';
		echo '<span class="billigoo-b2b__icon" aria-hidden="true">🏢</span>';
		echo '<span><span class="billigoo-b2b__title">' . esc_html__( 'Facturation professionnelle (B2B)', 'billigoo' ) . '</span>';
		echo '<span class="billigoo-b2b__sub">' . esc_html__( 'Recevez une facture Factur-X conforme avec votre SIRET et votre TVA.', 'billigoo' ) . '</span></span>';
		echo '</div>';
		echo '<div class="billigoo-b2b__body">';
		woocommerce_form_field(
			'billigoo_is_business',
			array(
				'type'  => 'checkbox',
				'label' => __( 'Je commande en tant que professionnel', 'billigoo' ),
				'class' => array( 'form-row-wide', 'billigoo-toggle-row' ),
			),
			'yes' === $is_business ? 1 : 0
		);

		echo '<div class="billigoo-b2b-conditional" style="' . ( 'yes' === $is_business ? '' : 'display:none;' ) . '">';
		woocommerce_form_field(
			'billigoo_company_name',
			array(
				'type'  => 'text',
				'label' => __( 'Raison sociale', 'billigoo' ),
				'class' => array( 'form-row-wide' ),
			),
			$company
		);
		woocommerce_form_field(
			'billigoo_siret',
			array(
				'type'        => 'text',
				'label'       => __( 'SIRET', 'billigoo' ),
				'placeholder' => '14 chiffres',
				'class'       => array( 'form-row-wide' ),
			),
			$siret
		);
		echo '<p id="billigoo-siret-feedback" class="billigoo-feedback" aria-live="polite"></p>';
		woocommerce_form_field(
			'billigoo_vat_number',
			array(
				'type'        => 'text',
				'label'       => __( 'Numéro de TVA intracommunautaire', 'billigoo' ),
				'placeholder' => 'FR00000000000',
				'class'       => array( 'form-row-wide' ),
			),
			$vat
		);
		echo '</div>'; // .billigoo-b2b-conditional
		echo '</div>'; // .billigoo-b2b__body
		echo '</div>'; // #billigoo-b2b-fields
	}

	/**
	 * Server-side validation during checkout.
	 */
	public function validate_fields(): void {
		// Checkout nonce is verified by WooCommerce before this hook fires.
		if ( empty( $_POST['billigoo_is_business'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$company = isset( $_POST['billigoo_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billigoo_company_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$siret   = isset( $_POST['billigoo_siret'] ) ? sanitize_text_field( wp_unslash( $_POST['billigoo_siret'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $company ) {
			wc_add_notice( __( 'Veuillez indiquer votre raison sociale.', 'billigoo' ), 'error' );
		}
		if ( '' === $siret ) {
			wc_add_notice( __( 'Veuillez indiquer votre numéro SIRET.', 'billigoo' ), 'error' );
		} elseif ( ! SiretValidator::is_valid_siret( $siret ) ) {
			wc_add_notice( __( 'Le numéro SIRET saisi est invalide (14 chiffres, clé de contrôle erronée).', 'billigoo' ), 'error' );
		}
	}

	/**
	 * Persist the fields onto the order and mirror to the customer profile.
	 *
	 * @param \WC_Order $order Order being created.
	 * @param array     $data  Posted checkout data.
	 */
	public function save_fields( \WC_Order $order, array $data ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$is_business = ! empty( $_POST['billigoo_is_business'] );
		$fields      = array(
			'is_business'  => $is_business,
			'company_name' => isset( $_POST['billigoo_company_name'] ) ? wp_unslash( $_POST['billigoo_company_name'] ) : '',
			'siret'        => isset( $_POST['billigoo_siret'] ) ? wp_unslash( $_POST['billigoo_siret'] ) : '',
			'vat_number'   => isset( $_POST['billigoo_vat_number'] ) ? wp_unslash( $_POST['billigoo_vat_number'] ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$order->update_meta_data( OrderMeta::IS_BUSINESS, $is_business ? 'yes' : 'no' );
		$order->update_meta_data( OrderMeta::COMPANY_NAME, sanitize_text_field( $fields['company_name'] ) );
		$order->update_meta_data( OrderMeta::SIRET, preg_replace( '/\D/', '', $fields['siret'] ) );
		$order->update_meta_data( OrderMeta::VAT_NUMBER, strtoupper( sanitize_text_field( $fields['vat_number'] ) ) );

		// Mirror to customer for future prefill.
		$customer_id = $order->get_customer_id();
		if ( $customer_id ) {
			$customer = new \WC_Customer( $customer_id );
			$customer->update_meta_data( OrderMeta::IS_BUSINESS, $is_business ? 'yes' : 'no' );
			$customer->update_meta_data( OrderMeta::COMPANY_NAME, sanitize_text_field( $fields['company_name'] ) );
			$customer->update_meta_data( OrderMeta::SIRET, preg_replace( '/\D/', '', $fields['siret'] ) );
			$customer->update_meta_data( OrderMeta::VAT_NUMBER, strtoupper( sanitize_text_field( $fields['vat_number'] ) ) );
			$customer->save();
		}
	}

	/**
	 * Enqueue the checkout JS (toggle + inline SIRET validation).
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		wp_enqueue_style(
			'billigoo-checkout',
			BILLIGOO_URL . 'public/assets/css/checkout.css',
			array(),
			BILLIGOO_VERSION
		);
		wp_enqueue_script(
			'billigoo-checkout',
			BILLIGOO_URL . 'public/assets/js/checkout.js',
			array( 'jquery' ),
			BILLIGOO_VERSION,
			true
		);
		wp_localize_script(
			'billigoo-checkout',
			'billigooCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'billigoo_validate_siret' ),
				'i18n'    => array(
					'valid'   => __( 'SIRET valide', 'billigoo' ),
					'invalid' => __( 'SIRET invalide (14 chiffres, clé erronée)', 'billigoo' ),
				),
			)
		);
	}

	/**
	 * AJAX: validate a SIRET (format + Luhn) for inline feedback.
	 */
	public function ajax_validate_siret(): void {
		check_ajax_referer( 'billigoo_validate_siret', 'nonce' );
		$siret = isset( $_POST['siret'] ) ? sanitize_text_field( wp_unslash( $_POST['siret'] ) ) : '';
		wp_send_json_success( array( 'valid' => SiretValidator::is_valid_siret( $siret ) ) );
	}
}
