<?php
/**
 * PA status → badge mapping for the admin UI.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the normalised PA transmission status to a design-system badge class and
 * a French label.
 */
final class StatusBadge {

	/**
	 * Badge CSS modifier for a status.
	 *
	 * @param string $status Normalised status.
	 */
	public static function css_class( string $status ): string {
		switch ( $status ) {
			case 'accepted':
			case 'paid':
				return 'billigoo-badge--success';
			case 'sent':
			case 'acknowledged':
				return 'billigoo-badge--info';
			case 'rejected':
			case 'error':
				return 'billigoo-badge--error';
			case 'pending':
				return 'billigoo-badge--warn';
			default:
				return 'billigoo-badge--neutral';
		}
	}

	/**
	 * Human (French) label for a status.
	 *
	 * @param string $status Normalised status.
	 */
	public static function label( string $status ): string {
		$labels = array(
			'accepted'        => __( 'Acceptée', 'billigoo' ),
			'paid'            => __( 'Payée', 'billigoo' ),
			'sent'            => __( 'Envoyée', 'billigoo' ),
			'acknowledged'    => __( 'Réceptionnée', 'billigoo' ),
			'rejected'        => __( 'Rejetée', 'billigoo' ),
			'error'           => __( 'Erreur', 'billigoo' ),
			'pending'         => __( 'En attente', 'billigoo' ),
			'not_transmitted' => __( 'Non transmise', 'billigoo' ),
		);
		return $labels[ $status ] ?? __( 'Non transmise', 'billigoo' );
	}

	/**
	 * Render a ready-to-use badge span.
	 *
	 * @param string $status Normalised status.
	 */
	public static function html( string $status ): string {
		return sprintf(
			'<span class="billigoo-badge %s">%s</span>',
			esc_attr( self::css_class( $status ) ),
			esc_html( self::label( $status ) )
		);
	}

	/**
	 * Statuses available as list filters.
	 *
	 * @return array<string,string>
	 */
	public static function filterable(): array {
		return array(
			'not_transmitted' => self::label( 'not_transmitted' ),
			'pending'         => self::label( 'pending' ),
			'sent'            => self::label( 'sent' ),
			'acknowledged'    => self::label( 'acknowledged' ),
			'accepted'        => self::label( 'accepted' ),
			'rejected'        => self::label( 'rejected' ),
			'paid'            => self::label( 'paid' ),
		);
	}
}
