<?php
/**
 * REST API (namespace billigoo/v1).
 *
 * @package Billigoo
 */

namespace Billigoo\Api;

use Billigoo\License;
use Billigoo\Admin\Stats;
use Billigoo\Admin\StatusBadge;
use Billigoo\Core\InvoiceStore;
use Billigoo\Pa\TransmissionManager;
use Billigoo\WooCommerce\Integration;
use Billigoo\WooCommerce\OrderMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write access to invoices for the Agence plan, authenticated with
 * Application Passwords and protected by a per-IP rate limit.
 */
final class RestApi {

	private const NS         = 'billigoo/v1';
	private const RATE_LIMIT = 100; // requests per minute per IP.

	/**
	 * Register routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Define the endpoints.
	 */
	public function routes(): void {
		$num = array( 'id' => array( 'validate_callback' => static fn( $v ) => is_numeric( $v ) ) );

		register_rest_route( self::NS, '/invoices', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_invoices' ),
			'permission_callback' => array( $this, 'permission' ),
		) );
		register_rest_route( self::NS, '/invoices/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_invoice' ),
			'permission_callback' => array( $this, 'permission' ),
			'args'                => $num,
		) );
		register_rest_route( self::NS, '/invoices/(?P<id>\d+)/pdf', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_pdf' ),
			'permission_callback' => array( $this, 'permission' ),
			'args'                => $num,
		) );
		register_rest_route( self::NS, '/invoices/(?P<id>\d+)/xml', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_xml' ),
			'permission_callback' => array( $this, 'permission' ),
			'args'                => $num,
		) );
		register_rest_route( self::NS, '/invoices/(?P<id>\d+)/transmit', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'transmit' ),
			'permission_callback' => array( $this, 'permission' ),
			'args'                => $num,
		) );
		register_rest_route( self::NS, '/invoices/generate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate' ),
			'permission_callback' => array( $this, 'permission' ),
		) );
		register_rest_route( self::NS, '/stats', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'stats' ),
			'permission_callback' => array( $this, 'permission' ),
		) );
	}

	/**
	 * Capability + plan + rate-limit gate.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'billigoo_forbidden', __( 'Permission refusée.', 'billigoo' ), array( 'status' => 403 ) );
		}
		if ( ! License::has_feature( 'rest_api' ) ) {
			return new \WP_Error( 'billigoo_plan', __( 'API REST réservée au plan Agence.', 'billigoo' ), array( 'status' => 403 ) );
		}
		if ( ! $this->rate_ok() ) {
			return new \WP_Error( 'billigoo_rate_limited', __( 'Trop de requêtes.', 'billigoo' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * Sliding per-IP, per-minute rate limit via transient.
	 */
	private function rate_ok(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'billigoo_rl_' . md5( $ip . gmdate( 'YmdHi' ) );
		$n   = (int) get_transient( $key );
		if ( $n >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Map an order to an invoice payload.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string,mixed>
	 */
	private function to_payload( \WC_Order $order ): array {
		return array(
			'invoice_number' => OrderMeta::get( $order, OrderMeta::INVOICE_NUMBER ),
			'order_id'       => $order->get_id(),
			'date'           => OrderMeta::get( $order, OrderMeta::INVOICE_DATE ),
			'client'         => OrderMeta::get( $order, OrderMeta::COMPANY_NAME ) ?: $order->get_formatted_billing_full_name(),
			'siret'          => OrderMeta::get( $order, OrderMeta::SIRET ),
			'total'          => (float) $order->get_total(),
			'currency'       => $order->get_currency(),
			'pa_status'      => OrderMeta::pa_status( $order ),
			'pa_status_label'=> StatusBadge::label( OrderMeta::pa_status( $order ) ),
		);
	}

	/**
	 * Resolve and validate an order that carries an invoice.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WC_Order|\WP_Error
	 */
	private function resolve( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order instanceof \WC_Order || ! OrderMeta::has_invoice( $order ) ) {
			return new \WP_Error( 'billigoo_not_found', __( 'Facture introuvable.', 'billigoo' ), array( 'status' => 404 ) );
		}
		return $order;
	}

	/**
	 * GET /invoices
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_invoices( \WP_REST_Request $request ): \WP_REST_Response {
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$query = wc_get_orders( array(
			'limit'      => 25,
			'paged'      => $page,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'paginate'   => true,
			'meta_query' => array( array( 'key' => OrderMeta::INVOICE_NUMBER, 'compare' => 'EXISTS' ) ),
		) );

		$items = array();
		foreach ( $query->orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$items[] = $this->to_payload( $order );
			}
		}
		return new \WP_REST_Response(
			array( 'page' => $page, 'total' => (int) $query->total, 'total_pages' => (int) $query->max_num_pages, 'invoices' => $items )
		);
	}

	/**
	 * GET /invoices/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_invoice( \WP_REST_Request $request ) {
		$order = $this->resolve( $request );
		return $order instanceof \WP_Error ? $order : new \WP_REST_Response( $this->to_payload( $order ) );
	}

	/**
	 * GET /invoices/{id}/pdf
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_pdf( \WP_REST_Request $request ) {
		$order = $this->resolve( $request );
		if ( $order instanceof \WP_Error ) {
			return $order;
		}
		$path = InvoiceStore::resolve( OrderMeta::get( $order, OrderMeta::INVOICE_PATH ) );
		if ( ! $path || ! is_readable( $path ) ) {
			return new \WP_Error( 'billigoo_no_file', __( 'Fichier introuvable.', 'billigoo' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( array(
			'filename' => basename( $path ),
			'mime'     => 'application/pdf',
			'base64'   => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		) );
	}

	/**
	 * GET /invoices/{id}/xml
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_xml( \WP_REST_Request $request ) {
		$order = $this->resolve( $request );
		if ( $order instanceof \WP_Error ) {
			return $order;
		}
		return new \WP_REST_Response( array( 'xml' => OrderMeta::get( $order, OrderMeta::INVOICE_XML ) ) );
	}

	/**
	 * POST /invoices/{id}/transmit
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function transmit( \WP_REST_Request $request ) {
		$order = $this->resolve( $request );
		if ( $order instanceof \WP_Error ) {
			return $order;
		}
		$result = ( new TransmissionManager() )->transmit( $order );
		return new \WP_REST_Response( $result );
	}

	/**
	 * POST /invoices/generate { order_id }
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request->get_param( 'order_id' ) );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'billigoo_not_found', __( 'Commande introuvable.', 'billigoo' ), array( 'status' => 404 ) );
		}
		$result = ( new Integration() )->generate( $order, (bool) $request->get_param( 'force' ) );
		if ( null === $result ) {
			return new \WP_Error( 'billigoo_generate_failed', __( 'Échec de génération.', 'billigoo' ), array( 'status' => 500 ) );
		}
		return new \WP_REST_Response( $this->to_payload( $order ), 201 );
	}

	/**
	 * GET /stats
	 */
	public function stats(): \WP_REST_Response {
		return new \WP_REST_Response( Stats::summary() );
	}
}
