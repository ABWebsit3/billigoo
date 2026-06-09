<?php
/**
 * Shared connector behaviour (HTTP + status normalisation).
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

defined( 'ABSPATH' ) || exit;

/**
 * Base class with HTTP helpers and a single status vocabulary.
 *
 * Normalised statuses: pending, sent, acknowledged, accepted, rejected, paid,
 * not_transmitted, error.
 */
abstract class AbstractConnector implements PaConnector {

	/**
	 * Map a provider-specific status onto the Billigoo vocabulary.
	 *
	 * @param string $raw Provider status.
	 */
	public static function normalize_status( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		$map = array(
			'pending'      => 'pending',
			'queued'       => 'pending',
			'draft'        => 'pending',
			'sent'         => 'sent',
			'submitted'    => 'sent',
			'transmitted'  => 'sent',
			'delivered'    => 'acknowledged',
			'received'     => 'acknowledged',
			'acknowledged' => 'acknowledged',
			'acknowledgement' => 'acknowledged',
			'accepted'     => 'accepted',
			'approved'     => 'accepted',
			'validated'    => 'accepted',
			'rejected'     => 'rejected',
			'refused'      => 'rejected',
			'declined'     => 'rejected',
			'error'        => 'error',
			'failed'       => 'error',
			'paid'         => 'paid',
			'settled'      => 'paid',
		);
		return $map[ $raw ] ?? 'sent';
	}

	/**
	 * Perform a JSON POST and decode the body.
	 *
	 * @param string               $url     Endpoint.
	 * @param array<string,mixed>  $body    Body payload (encoded as JSON).
	 * @param array<string,string> $headers Extra headers.
	 * @return array{ok:bool,code:int,body:array<string,mixed>,error:string}
	 */
	protected function post_json( string $url, array $body, array $headers = array() ): array {
		return $this->request( 'POST', $url, $headers + array( 'Content-Type' => 'application/json' ), wp_json_encode( $body ) );
	}

	/**
	 * Perform a GET and decode the JSON body.
	 *
	 * @param string               $url     Endpoint.
	 * @param array<string,string> $headers Extra headers.
	 * @return array{ok:bool,code:int,body:array<string,mixed>,error:string}
	 */
	protected function get_json( string $url, array $headers = array() ): array {
		return $this->request( 'GET', $url, $headers, null );
	}

	/**
	 * Perform a multipart/form-data POST (for file uploads).
	 *
	 * @param string                            $url     Endpoint.
	 * @param array<string,scalar>              $fields  Plain form fields.
	 * @param array<string,array{name:string,type:string,content:string}> $files Files.
	 * @param array<string,string>              $headers Extra headers.
	 * @return array{ok:bool,code:int,body:array<string,mixed>,error:string}
	 */
	protected function post_multipart( string $url, array $fields, array $files, array $headers = array() ): array {
		$boundary = 'billigoo' . wp_generate_password( 16, false );
		$eol      = "\r\n";
		$payload  = '';

		foreach ( $fields as $name => $value ) {
			$payload .= '--' . $boundary . $eol;
			$payload .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
			$payload .= $value . $eol;
		}
		foreach ( $files as $name => $file ) {
			$payload .= '--' . $boundary . $eol;
			$payload .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file['name'] . '"' . $eol;
			$payload .= 'Content-Type: ' . $file['type'] . $eol . $eol;
			$payload .= $file['content'] . $eol;
		}
		$payload .= '--' . $boundary . '--' . $eol;

		$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
		return $this->request( 'POST', $url, $headers, $payload );
	}

	/**
	 * Low-level HTTP request wrapper around wp_remote_request.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     Endpoint.
	 * @param array<string,string> $headers Headers.
	 * @param string|array|null    $body    Body.
	 * @return array{ok:bool,code:int,body:array<string,mixed>,error:string}
	 */
	protected function request( string $method, string $url, array $headers, $body ) {
		// piste.gouv.fr uses IGC/A (French government CA) not included in most
		// default CA bundles. Disable SSL peer verification for that domain only.
		$sslverify = ! (bool) preg_match( '#https://[^/]*\.gouv\.fr#i', $url );

		$response = wp_remote_request(
			$url,
			array(
				'method'     => $method,
				'timeout'    => 30,
				'headers'    => $headers,
				'body'       => $body,
				'sslverify'  => $sslverify,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'code' => 0, 'body' => array(), 'error' => $response->get_error_message() );
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$parsed = json_decode( $raw, true );
		$parsed = is_array( $parsed ) ? $parsed : array();

		return array(
			'ok'    => $code >= 200 && $code < 300,
			'code'  => $code,
			'body'  => $parsed,
			'error' => $code >= 400 ? ( $parsed['message'] ?? ( 'HTTP ' . $code ) ) : '',
		);
	}
}
