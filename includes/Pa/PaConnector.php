<?php
/**
 * Plateforme Agréée connector contract.
 *
 * @package Billigoo
 */

namespace Billigoo\Pa;

defined( 'ABSPATH' ) || exit;

/**
 * Common interface every PA (Plateforme de Dématérialisation Partenaire)
 * connector implements, so the router can treat them uniformly.
 */
interface PaConnector {

	/**
	 * Verify credentials / reachability.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function connect(): array;

	/**
	 * Transmit an invoice.
	 *
	 * @param string               $pdf_path Absolute path to the Factur-X PDF.
	 * @param string               $xml      Embedded CII XML string.
	 * @param array<string,mixed>  $metadata Invoice metadata (number, buyer, totals…).
	 * @return array{status:string,remote_id:string,message:string}
	 */
	public function send_invoice( string $pdf_path, string $xml, array $metadata ): array;

	/**
	 * Fetch the current status of a previously transmitted invoice.
	 *
	 * @param string $remote_id Remote reference.
	 * @return string Normalised status.
	 */
	public function get_status( string $remote_id ): string;

	/**
	 * Provider slug.
	 */
	public function provider(): string;
}
