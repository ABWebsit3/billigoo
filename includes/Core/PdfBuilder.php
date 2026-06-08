<?php
/**
 * Renders the visual invoice PDF (PDF/A) via mPDF.
 *
 * @package Billigoo
 */

namespace Billigoo\Core;

use Mpdf\Mpdf;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the human-readable PDF that the Factur-X XML is later embedded into.
 * Rendered in mPDF's PDF/A mode (fonts embedded) so the assembled file can carry
 * the PDF/A-3 + Factur-X metadata required for archival.
 */
final class PdfBuilder {

	/**
	 * Render the invoice PDF and return the raw bytes.
	 *
	 * @param array<string,mixed> $data         Normalised invoice data.
	 * @param string|null         $template_dir Override template directory.
	 * @return string PDF content.
	 *
	 * @throws \Mpdf\MpdfException On rendering failure.
	 */
	public function render( array $data, ?string $template_dir = null ): string {
		if ( null === $template_dir ) {
			$slug = ( $data['branding']['template'] ?? 'default' );
			$dir  = BILLIGOO_PATH . 'templates/invoice-' . ( 'premium' === $slug ? 'premium' : 'default' );
			// Fall back to the default template if a premium dir is missing.
			$template_dir = is_dir( $dir ) ? $dir : BILLIGOO_PATH . 'templates/invoice-default';
		}
		$html = $this->capture( $template_dir . '/template.php', $data );

		$tmp = trailingslashit( get_temp_dir() ) . 'billigoo-mpdf';
		if ( ! is_dir( $tmp ) ) {
			wp_mkdir_p( $tmp );
		}

		$mpdf = new Mpdf(
			array(
				'mode'        => 'utf-8',
				'format'      => 'A4',
				'tempDir'     => $tmp,
				'PDFA'        => true,
				'PDFAauto'    => true,
				'margin_top'  => 18,
				'margin_left' => 15,
				'margin_right'=> 15,
			)
		);
		$mpdf->SetTitle( 'Facture ' . (string) $data['invoice_number'] );
		$mpdf->SetAuthor( (string) $data['seller']['name'] );
		$mpdf->WriteHTML( $html );

		return $mpdf->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
	}

	/**
	 * Render a PHP template to an HTML string with the given data in scope.
	 *
	 * @param string              $file Template absolute path.
	 * @param array<string,mixed> $data Data exposed as $data inside the template.
	 */
	private function capture( string $file, array $data ): string {
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}
}
