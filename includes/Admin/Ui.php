<?php
/**
 * Small presentational helpers shared by the Billigoo admin pages.
 *
 * @package Billigoo
 */

namespace Billigoo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless render helpers for the design-system chrome (header, icons, badges).
 */
final class Ui {

	/**
	 * Echo a Material Symbols icon.
	 *
	 * @param string $name   Icon ligature.
	 * @param bool   $filled Whether to use the filled variant.
	 */
	public static function icon( string $name, bool $filled = false ): void {
		printf(
			'<span class="material-symbols-outlined%s" aria-hidden="true">%s</span>',
			$filled ? ' is-filled' : '',
			esc_html( $name )
		);
	}

	/**
	 * Echo the standard page header (brand title, PRO chip, optional actions).
	 *
	 * @param string $title       Page title.
	 * @param string $actions_html Pre-escaped HTML for the right-hand actions.
	 */
	public static function header( string $title, string $actions_html = '' ): void {
		?>
		<div class="billigoo-header">
			<div class="billigoo-header__title">
				<?php self::icon( 'receipt_long' ); ?>
				<h1><?php echo esc_html( $title ); ?></h1>
				<span class="billigoo-pill-pro"><?php esc_html_e( 'Conforme 2026', 'billigoo' ); ?></span>
			</div>
			<?php if ( '' !== $actions_html ) : ?>
				<div class="billigoo-header__actions"><?php echo wp_kses_post( $actions_html ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}
}
